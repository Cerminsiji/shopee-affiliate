<?php

namespace App\Services;

use App\Models\ShortLink;
use Illuminate\Support\Str;

class ShortLinkService
{
    public function create(string $targetUrl, ?string $source = null, ?string $productName = null): ShortLink
    {
        do {
            $code = Str::random(7);
        } while (ShortLink::where('code', $code)->exists());

        return ShortLink::create([
            'code' => $code,
            'target_url' => $targetUrl,
            'source' => $source,
            'product_name' => $productName,
        ]);
    }

    public function resolveAndTrack(string $code): ?string
    {
        $link = ShortLink::where('code', $code)->first();

        if (! $link) {
            return null;
        }

        $link->increment('clicks');

        return $link->target_url;
    }
}
