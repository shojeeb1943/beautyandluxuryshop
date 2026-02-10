<?php

use App\Models\FlashDeal;
use App\Models\FlashDealProduct;
use App\Models\Product;
use App\Models\Review;
use App\Utils\ProductManager;
use Illuminate\Support\Facades\Cache;

if (!function_exists('getOverallRating')) {
    function getOverallRating(object|array $reviews): array
    {
        $totalRating = count($reviews);
        $rating = 0;
        foreach ($reviews as $key => $review) {
            $rating += $review->rating;
        }
        if ($totalRating == 0) {
            $overallRating = 0;
        } else {
            $overallRating = number_format($rating / $totalRating, 2);
        }

        return [$overallRating, $totalRating];
    }
}

if (!function_exists('getRating')) {
    function getRating(object|array $reviews): array
    {
        $rating5 = 0;
        $rating4 = 0;
        $rating3 = 0;
        $rating2 = 0;
        $rating1 = 0;
        foreach ($reviews as $key => $review) {
            if ($review->rating == 5) {
                $rating5 += 1;
            }
            if ($review->rating == 4) {
                $rating4 += 1;
            }
            if ($review->rating == 3) {
                $rating3 += 1;
            }
            if ($review->rating == 2) {
                $rating2 += 1;
            }
            if ($review->rating == 1) {
                $rating1 += 1;
            }
        }
        return [$rating5, $rating4, $rating3, $rating2, $rating1];
    }
}

if (!function_exists('getProductDiscount')) {
    /**
     * @param object|array $product
     * @param string|float|int $price
     * @return float
     */
    function getProductDiscount(object|array $product, string|float|int $price): float
    {
        $discount = 0;
        if ($product['discount_type'] == 'percent') {
            $discount = ($price * $product['discount']) / 100;
        } elseif ($product['discount_type'] == 'flat') {
            $discount = $product['discount'];
        }

        return floatval($discount);
    }
}

if (!function_exists('getVariationDiscount')) {
    /**
     * Calculate variation-specific discount
     * Priority: Clearance Sale > Variation Discount > Product Discount
     *
     * @param object|array $product
     * @param object|array|null $variation
     * @param string|float|int $variationPrice
     * @return array ['discount' => float, 'discount_type' => string, 'discount_value' => float]
     */
    function getVariationDiscount(object|array $product, object|array|null $variation, string|float|int $variationPrice): array
    {
        // Priority 1: Check for clearance sale (highest priority)
        if ((isset($product['clearanceSale']) && $product['clearanceSale']) ||
            (isset($product['clearance_sale']) && $product['clearance_sale'])) {
            $discount = getProductPriceByType(product: $product, type: 'discounted_amount', result: 'value', price: $variationPrice);
            $discountType = getProductPriceByType(product: $product, type: 'discount_type', result: 'string');
            $discountValue = getProductPriceByType(product: $product, type: 'discount', result: 'value');

            return [
                'discount' => floatval($discount),
                'discount_type' => $discountType,
                'discount_value' => floatval($discountValue),
                'source' => 'clearance_sale'
            ];
        }

        // Priority 2: Check for variation-specific discount
        if ($variation !== null) {
            $variationObj = is_object($variation) ? $variation : (object) $variation;
            $variationDiscount = $variationObj->discount ?? 0;
            $variationDiscountType = $variationObj->discount_type ?? 'flat';

            if ($variationDiscount > 0) {
                if ($variationDiscountType == 'percent') {
                    $discount = ($variationPrice * $variationDiscount) / 100;
                } else {
                    $discount = $variationDiscount;
                }

                return [
                    'discount' => floatval($discount),
                    'discount_type' => $variationDiscountType,
                    'discount_value' => floatval($variationDiscount),
                    'source' => 'variation'
                ];
            }
        }

        // Priority 3: Fall back to product-level discount
        $discount = getProductDiscount(product: $product, price: $variationPrice);
        $discountType = $product['discount_type'] ?? 'flat';
        $discountValue = $product['discount'] ?? 0;

        return [
            'discount' => floatval($discount),
            'discount_type' => $discountType,
            'discount_value' => floatval($discountValue),
            'source' => 'product'
        ];
    }
}

if (!function_exists('getVariationFromProduct')) {
    /**
     * Get variation object from product by variant string
     *
     * @param object|array $product
     * @param string $variantString
     * @return object|null
     */
    function getVariationFromProduct(object|array $product, string $variantString): ?object
    {
        if (empty($variantString)) {
            return null;
        }

        $variations = is_string($product['variation']) ? json_decode($product['variation']) : $product['variation'];

        if (!$variations || !is_array($variations)) {
            return null;
        }

        foreach ($variations as $variation) {
            $variationObj = is_object($variation) ? $variation : (object) $variation;
            if (isset($variationObj->type) && $variationObj->type == $variantString) {
                return $variationObj;
            }
        }

        return null;
    }
}

if (!function_exists('getPriceRangeWithDiscount')) {
    function getPriceRangeWithDiscount(array|object $product, string|null $type = 'web'): float|string
    {
        $productUnitPrice = $product->unit_price;
        foreach (json_decode($product->variation) as $key => $variation) {
            if ($key == 0) {
                $productUnitPrice = $variation->price;
            }
        }

        if ($product->digitalVariation && count($product->digitalVariation) > 0) {
            $digitalVariations = $product->digitalVariation->toArray();
            $productUnitPrice = $digitalVariations[0]['price'];
        }

        if ($type == 'panel') {
            if (isset($product['clearanceSale']) && $product['clearanceSale']) {
                $discountAmount = getProductPriceByType(product: $product, type: 'discounted_amount', result: 'value', price: $productUnitPrice, from: 'panel');
                $productDiscountedPrice = setCurrencySymbol(amount: usdToDefaultCurrency(amount: $productUnitPrice - $discountAmount), currencyCode: getCurrencyCode());
                return '<span class="discounted-unit-price fs-24 font-bold">' . $productDiscountedPrice . '</span>' . '<del class="product-total-unit-price align-middle text-muted fs-18 font-semibold">' . setCurrencySymbol(amount: usdToDefaultCurrency(amount: $productUnitPrice), currencyCode: getCurrencyCode()) . '</del>';
            } elseif ($product->discount > 0) {
                $amount = $productUnitPrice - getProductDiscount(product: $product, price: $productUnitPrice);
                $productDiscountedPrice = setCurrencySymbol(amount: usdToDefaultCurrency(amount: $amount), currencyCode: getCurrencyCode());
                return '<span class="discounted-unit-price fs-24 font-bold">' . $productDiscountedPrice . '</span>' . '<del class="product-total-unit-price align-middle text-muted fs-18 font-semibold">' . setCurrencySymbol(amount: usdToDefaultCurrency(amount: $productUnitPrice), currencyCode: getCurrencyCode()) . '</del>';
            } else {
                return '<span class="discounted-unit-price fs-24 font-bold">' . setCurrencySymbol(amount: usdToDefaultCurrency(amount: $productUnitPrice), currencyCode: getCurrencyCode()) . '</span>';
            }
        } else {
            if (isset($product['clearanceSale']) && $product['clearanceSale']) {
                $discountAmount = getProductPriceByType(product: $product, type: 'discounted_amount', result: 'value', price: $productUnitPrice);
                $productDiscountedPrice = webCurrencyConverter(amount: $productUnitPrice - $discountAmount);
                return '<span class="discounted-unit-price fs-24 font-bold">' . $productDiscountedPrice . '</span>' . '<del class="product-total-unit-price align-middle text-muted fs-18 font-semibold">' . webCurrencyConverter(amount: $productUnitPrice) . '</del>';

            } elseif ($product->discount > 0) {
                $productDiscountedPrice = webCurrencyConverter(amount: $productUnitPrice - getProductDiscount(product: $product, price: $productUnitPrice));
                return '<span class="discounted-unit-price fs-24 font-bold">' . $productDiscountedPrice . '</span>' . '<del class="product-total-unit-price align-middle text-muted fs-18 font-semibold">' . webCurrencyConverter(amount: $productUnitPrice) . '</del>';
            } else {
                return '<span class="discounted-unit-price fs-24 font-bold">' . webCurrencyConverter(amount: $productUnitPrice) . '</span>';
            }
        }
    }
}

if (!function_exists('getRatingCount')) {
    function getRatingCount($product_id, $rating)
    {
        return Review::where(['product_id' => $product_id, 'rating' => $rating])->whereNull('delivery_man_id')->count();
    }
}

if (!function_exists('units')) {
    function units(): array
    {
        return ['kg', 'pc', 'gms', 'ltrs','pair','oz','lb'];
    }
}

if (!function_exists('getVendorProductsCount')) {
    function getVendorProductsCount(string $type):int
    {
        $products = \Illuminate\Support\Facades\DB::table('products')->where(['added_by'=>'seller'])->get();
        return match ($type) {
            'new-product' => $products->where('request_status', 0)->count(),
            'product-updated-request' => $products->whereNotNull('is_shipping_cost_updated')->where('is_shipping_cost_updated', 0)->count(),
            'approved' => $products->where('request_status', 1)->count(),
            'denied' => $products->where('request_status', 2)->where('status' , 0)->count(),
        };
    }
}
if (!function_exists('getAdminProductsCount')) {
    function getAdminProductsCount(string $type):int
    {
        $products = \Illuminate\Support\Facades\DB::table('products')->where(['added_by'=>'admin'])->get();
        return match ($type) {
            'all' => $products->count(),
            'new-product' => $products->where('request_status', 0)->count(),
            'product-updated-request' => $products->whereNotNull('is_shipping_cost_updated')->where('is_shipping_cost_updated', 0)->count(),
            'approved' => $products->where('request_status', 1)->count(),
            'denied' => $products->where('request_status', 2)->where('status' , 0)->count(),
        };
    }
}


if (!function_exists('getRestockProductFCMTopic')) {
    function getRestockProductFCMTopic(array|object $restockRequest): string
    {
        return 'restock_'.$restockRequest['id'].'_product_restock_'.$restockRequest->product_id.'_topic';
    }
}


if (!function_exists('isProductInWishList')) {
    function isProductInWishList(string|int $productId): bool
    {
        if (session('wish_list') && in_array($productId, session('wish_list'))) {
            return true;
        }
        return false;
    }
}

if (!function_exists('isProductInCompareList')) {
    function isProductInCompareList(string|int $productId): bool
    {
        if (session('compare_list') && in_array($productId, session('compare_list'))) {
            return true;
        }
        return false;
    }
}


if (!function_exists('getProductMaxUnitPriceRange')) {
    function getProductMaxUnitPriceRange($type = null): int
    {
        $maxUnitPrice = Cache::remember(CACHE_FOR_PRODUCTS_MAX_UNIT_PRICE, CACHE_FOR_3_HOURS, function () {
            return Product::all()->max('unit_price');
        });

        if ($type == 'web') {
            $maxUnitPrice = webCurrencyConverterOnlyDigit(amount: $maxUnitPrice);
        }

        $ranges = [
            1000 => 500,
            10000 => 1000,
            100000 => 5000,
            1000000 => 10000,
            PHP_INT_MAX => 50000
        ];

        foreach ($ranges as $max => $increment) {
            if ($maxUnitPrice <= $max) {
                return ceil($maxUnitPrice / $increment) * $increment;
            }
        }
        return 0;
    }
}

if (!function_exists('getProductMinUnitPriceRange')) {
    function getProductMinUnitPriceRange($type = null): int
    {
        $minUnitPrice = Cache::remember(CACHE_FOR_PRODUCTS_MIN_UNIT_PRICE, CACHE_FOR_3_HOURS, function () {
            return Product::all()->min('unit_price');
        });

        if ($type == 'web') {
            $minUnitPrice = webCurrencyConverterOnlyDigit(amount: $minUnitPrice);
        }

        if ($minUnitPrice < 10) {
            return 0;
        }

        $ranges = [
            100 => 10,
            1000 => 50,
            10000 => 100,
            100000 => 1000,
            PHP_INT_MAX => 5000
        ];

        foreach ($ranges as $max => $increment) {
            if ($minUnitPrice <= $max) {
                return floor($minUnitPrice / $increment) * $increment;
            }
        }

        return 0;
    }
}

if (!function_exists('getFeaturedDealsProductList')) {
    function getFeaturedDealsProductList()
    {
        $cacheKey = 'cache_for_Featured_deals_products_list_'.getDefaultLanguage();
        $cacheKeys = Cache::get(CACHE_FOR_FEATURED_DEAL_PRODUCTS_LIST, []);
        if (!in_array($cacheKey, $cacheKeys)) {
            $cacheKeys[] = $cacheKey;
            Cache::put(CACHE_FOR_FEATURED_DEAL_PRODUCTS_LIST, $cacheKeys, CACHE_FOR_3_HOURS);
        }

        return Cache::remember($cacheKey, CACHE_FOR_3_HOURS, function () {
            $featuredDealID = FlashDeal::where(['deal_type' => 'feature_deal', 'status' => 1])
                ->whereDate('start_date', '<=', date('Y-m-d'))
                ->whereDate('end_date', '>=', date('Y-m-d'))->pluck('id')->first();
            $featuredDealProductIDs = $featuredDealID ? FlashDealProduct::where('flash_deal_id', $featuredDealID)->pluck('product_id')->toArray() : [];
            return ProductManager::getPriorityWiseFeatureDealQuery(
                query: Product::active()->with(['category', 'clearanceSale' => function($query) {
                    return $query->active();
                }])->whereIn('id', $featuredDealProductIDs),
                dataLimit: 'all'
            );
        });
    }
}
