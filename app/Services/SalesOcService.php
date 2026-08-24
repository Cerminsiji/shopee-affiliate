<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Đọc thông tin sản phẩm + link voucher (Facebook/Instagram/Zalo) từ salesoc.vn.
 *
 * facebookAffiliateUrl/instagramAffiliateUrl/zaloAffiliateUrl trong response là
 * link rút gọn mờ (s.afp.ad/... hoặc shp.ee/...) — nơi mã giảm giá thực sự được
 * áp dụng. Link này thuộc tài khoản affiliate của salesoc.vn, không có cách nào
 * gắn mmp_pid của mình vào (server salesoc/mạng ad kiểm soát redirect cuối), nên
 * hoa hồng đơn hàng đi qua link này sẽ về salesoc.vn — đây là đánh đổi được chấp
 * nhận để đổi lấy mã giảm giá thật cho người dùng, thay vì link tự tạo không áp
 * được mã nào.
 */
class SalesOcService
{
    private const ENDPOINT = 'https://salesoc.vn/api/convert-with-shelf';

    // Giả lập request từ mobile — salesoc.vn trả về dữ liệu ổn định hơn khi gọi từ UA mobile.
    private const MOBILE_USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    public function fetchProductAndVoucherLabels(string $shopeeUrl): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => self::MOBILE_USER_AGENT,
            ])->timeout(10)->post(self::ENDPOINT, [
                'url' => $shopeeUrl,
            ]);

            if (! $response->successful() || ! $response->json('success')) {
                return null;
            }

            $data = $response->json();
            $price = $this->parsePrice($data['price'] ?? null);

            return [
                // Cùng shape với ShopeeLinkResolverService::fetchProductInfo() để
                // dùng thay thế được cho nhau ở phía controller/frontend.
                'product_name' => $data['productName'] ?? null,
                'product_image' => $data['imageUrl'] ?? null,
                'original_price' => $price,
                'discounted_price' => $price,
                'discount_percent' => 0,
                'sold_count' => 0,
                'rating' => 0,
                'voucher_labels' => $this->extractLabels($data),
                // Link áp mã giảm giá thật (CTA chính) — thuộc affiliate account của salesoc.vn.
                // API không trả trạng thái còn/hết lượt của từng mã, nên trả TẤT CẢ lựa chọn
                // (không chỉ mã % cao nhất) để người dùng thử link khác nếu link đầu đã hết lượt.
                'voucher_links' => [
                    'facebook' => $this->extractOptions($data, 'facebookAffiliateUrls', $data['facebookAffiliateUrl'] ?? null),
                    'instagram' => $this->extractOptions($data, 'instagramAffiliateUrls', $data['instagramAffiliateUrl'] ?? null),
                    'zalo' => $this->extractOptions($data, 'zaloAffiliateUrls', $data['zaloAffiliateUrl'] ?? null),
                ],
            ];
        } catch (\Exception $e) {
            Log::warning('SalesOcService fetch failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Lấy toàn bộ {label, url} của 1 nền tảng. Nếu không có entry nào có label
     * (không có mã giảm giá độc quyền), fallback về link affiliate chung của
     * nền tảng đó (nếu có) để vẫn còn ít nhất 1 lựa chọn cho người dùng.
     */
    private function extractOptions(array $data, string $key, ?string $fallbackUrl): array
    {
        $options = [];

        foreach ($data[$key] ?? [] as $entry) {
            $label = trim($entry['label'] ?? '');
            $url = $entry['url'] ?? null;
            if ($label !== '' && $url) {
                $options[] = ['label' => $label, 'url' => $url];
            }
        }

        if (empty($options) && $fallbackUrl) {
            $options[] = ['label' => null, 'url' => $fallbackUrl];
        }

        return $options;
    }

    private function extractLabels(array $data): array
    {
        $labels = [];

        foreach (['facebookAffiliateUrls', 'instagramAffiliateUrls', 'zaloAffiliateUrls'] as $key) {
            foreach ($data[$key] ?? [] as $entry) {
                $label = trim($entry['label'] ?? '');
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }

        return array_values(array_unique($labels));
    }

    private function parsePrice(?string $raw): ?float
    {
        if (! $raw) {
            return null;
        }

        $digits = preg_replace('/[^\d]/', '', $raw);

        return $digits === '' ? null : (float) $digits;
    }
}
