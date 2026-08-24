<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopeeLinkResolverService
{
    public function __construct(
        private UrlValidationService $urlValidator,
        private ShopeeProductLookupService $productLookup,
    ) {}

    // Short-link chính thức của Shopee cần theo dõi redirect để lấy URL sản phẩm đầy đủ.
    private const SHORT_LINK_DOMAINS = ['s.shopee.vn', 'shp.ee'];

    public function resolveCanonicalUrl(string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $host = preg_replace('/^www\./', '', $host);

        $isShortLink = false;
        foreach (self::SHORT_LINK_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                $isShortLink = true;
                break;
            }
        }

        if (! $isShortLink) {
            return $url;
        }

        try {
            $response = Http::withOptions(['allow_redirects' => false])
                ->timeout(10)
                ->get($url);

            $location = $response->header('Location');

            return $location ?: $url;
        } catch (\Exception $e) {
            Log::warning('Shopee short link resolve error: '.$e->getMessage());

            return $url;
        }
    }

    public function extractIds(string $url): ?array
    {
        $ids = $this->urlValidator->extractShopeeIds($url);

        return $ids ?: null;
    }

    public function fetchProductInfo(string $itemId, string $shopId): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                'Referer' => 'https://shopee.vn',
            ])->timeout(10)->get('https://shopee.vn/api/v4/item/get', [
                'itemid' => $itemId,
                'shopid' => $shopId,
            ]);

            $item = $response->successful() ? $response->json('data') : null;

            if ($item) {
                $image = $item['image'] ?? null;

                return [
                    'product_name' => $item['name'] ?? null,
                    'product_image' => $image ? 'https://cf.shopee.vn/file/'.$image : null,
                    'original_price' => ($item['price_before_discount'] ?? $item['price'] ?? 0) / 100000,
                    'discounted_price' => ($item['price'] ?? 0) / 100000,
                    'discount_percent' => $item['raw_discount'] ?? 0,
                    'sold_count' => $item['sold'] ?? 0,
                    'rating' => $item['item_rating']['rating_star'] ?? 0,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Shopee item API error: '.$e->getMessage());
        }

        // Gọi thẳng Shopee từ server gần như luôn bị anti-bot chặn — fallback qua proxy bên thứ 3.
        return $this->productLookup->getByItemId($itemId);
    }

    public function buildVoucherUrl(string $canonicalUrl, string $affiliateId, ?string $smtt = null): string
    {
        $parts = parse_url($canonicalUrl);
        parse_str($parts['query'] ?? '', $query);

        $query['mmp_pid'] = $affiliateId;
        if ($smtt) {
            $query['smtt'] = $smtt;
        }

        $base = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').($parts['path'] ?? '');

        return $base.'?'.http_build_query($query);
    }
}
