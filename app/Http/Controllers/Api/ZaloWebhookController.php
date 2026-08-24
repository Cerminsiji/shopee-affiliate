<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShopeeLinkResolverService;
use App\Services\ZaloOaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZaloWebhookController extends Controller
{
    private const DEFAULT_AFFILIATE_ID = 'an_17332410386';

    private const DISCLAIMER = "Giá dự kiến tính theo voucher đang áp dụng.\nMức giảm thực tế xác nhận tại checkout Shopee.\nWebsite có sử dụng link tiếp thị liên kết.";

    public function __construct(
        private ShopeeLinkResolverService $resolver,
        private ZaloOaService $zalo,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if ($request->input('event_name') !== 'user_send_text') {
            return response()->json(['ok' => true]);
        }

        $text = (string) $request->input('message.text', '');
        $senderId = (string) $request->input('sender.id', '');

        if (! $senderId || ! str_contains($text, 'shopee.vn')) {
            return response()->json(['ok' => true]);
        }

        if (! preg_match('#https?://\S*shopee\.vn\S*#i', $text, $m)) {
            return response()->json(['ok' => true]);
        }

        $canonicalUrl = $this->resolver->resolveCanonicalUrl($m[0]);
        $voucherUrl = $this->resolver->buildVoucherUrl($canonicalUrl, self::DEFAULT_AFFILIATE_ID);

        $this->zalo->sendText($senderId, "Link voucher của bạn:\n{$voucherUrl}\n\n".self::DISCLAIMER);

        return response()->json(['ok' => true]);
    }
}
