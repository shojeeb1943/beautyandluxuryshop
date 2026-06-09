<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Utils\Helpers;

class FacebookFeedController extends Controller
{
    public function feed()
    {
        $currencyCode = $this->getCurrencyCode();

        $products = Product::active()
            ->with(['brand', 'category'])
            ->get()
            ->map(function ($p) use ($currencyCode) {
                $image = $p->thumbnail_full_url;
                return [
                    'id'           => $p->id,
                    'name'         => $p->name,
                    'description'  => mb_substr(strip_tags($p->details ?? ''), 0, 5000),
                    'link'         => url('/product/' . $p->slug),
                    'image_link'   => $image['path'] ?? '',
                    'availability' => $p->current_stock > 0 ? 'in stock' : 'out of stock',
                    'price'        => number_format($p->unit_price, 2) . ' ' . $currencyCode,
                    'brand'        => $p->brand->name ?? '',
                    'category'     => $p->category->name ?? '',
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . view('facebook-feed', compact('products'))->render();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function getCurrencyCode(): string
    {
        try {
            return strtoupper(Helpers::currency_code());
        } catch (\Throwable $e) {
            return 'BDT';
        }
    }
}
