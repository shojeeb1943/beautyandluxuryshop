<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Utils\Helpers;

class GoogleFeedController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepo,
    )
    {
    }

    public function feed()
    {
        $currencyCode = $this->getCurrencyCode();
        $items = collect();

        $this->productRepo->getWebListWithScope(
            scope: 'active',
            dataLimit: 'all',
            relations: ['brand', 'category'],
        )->each(function ($p) use (&$items, $currencyCode) {
            $variations = json_decode($p->variation ?? '[]', true);
            $common = [
                'name'        => $p->name,
                'description' => mb_substr(strip_tags($p->details ?? ''), 0, 5000),
                'link'        => route('product', ['slug' => $p->slug]),
                'image_link'  => $p->thumbnail_full_url['path'] ?? '',
                'brand'       => $p->brand->name ?? '',
                'category'    => $p->category->name ?? '',
            ];

            if (!empty($variations)) {
                foreach ($variations as $v) {
                    $type  = $v['type'] ?? '';
                    $price = $v['price'] ?? $p->unit_price;
                    $qty   = $v['qty'] ?? 0;
                    $sku   = $v['sku'] ?? ($p->id . '-' . $type);
                    $items->push(array_merge($common, [
                        'id'            => $p->id . '-' . str_replace(' ', '-', $type),
                        'item_group_id' => $p->id,
                        'price'         => number_format((float)$price, 2) . ' ' . $currencyCode,
                        'availability'  => (int)$qty > 0 ? 'in stock' : 'out of stock',
                        'sku'           => $sku,
                    ]));
                }
            } else {
                $items->push(array_merge($common, [
                    'id'            => $p->id,
                    'item_group_id' => null,
                    'price'         => number_format((float)$p->unit_price, 2) . ' ' . $currencyCode,
                    'availability'  => $p->current_stock > 0 ? 'in stock' : 'out of stock',
                    'sku'           => $p->code ?? '',
                ]));
            }
        });

        $products = $items;
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
