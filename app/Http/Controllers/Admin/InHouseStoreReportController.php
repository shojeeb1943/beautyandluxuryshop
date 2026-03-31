<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Http\Controllers\BaseController;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Utils\Helpers;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InHouseStoreReportController extends BaseController
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepo,
        private readonly ProductRepositoryInterface $productRepo,
    ) {
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse|JsonResponse
    {
        $dateType = $request?->date_type ?? 'this_year';
        $from = $request?->from;
        $to = $request?->to;
        $categoryId = $request?->category_id;
        $search = $request?->search;

        $categories = $this->categoryRepo->getListWhere(filters: ['parent_id' => 0], dataLimit: 'all');

        // Base query for in-house products
        $productsQuery = Product::where('added_by', 'admin')
            ->when($categoryId && $categoryId != 'all', fn($q) => $q->where('category_id', $categoryId))
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"));

        // Count products with and without buying price (product-level or any variation-level)
        $totalInhouseProducts = (clone $productsQuery)->count();
        $allProductsForCount = (clone $productsQuery)->select('id', 'buying_price', 'variation')->get();
        $productsWithBuyingPrice = $allProductsForCount->filter(function ($p) {
            if ($p->buying_price > 0) {
                return true;
            }
            $variationData = $p->variation ? (is_array($p->variation) ? $p->variation : json_decode($p->variation, true)) : null;
            if (is_array($variationData)) {
                foreach ($variationData as $var) {
                    if (($var['buying_price'] ?? 0) > 0) {
                        return true;
                    }
                }
            }
            return false;
        })->count();
        $productsMissingBuyingPrice = $totalInhouseProducts - $productsWithBuyingPrice;

        // Calculate total asset value using variation-level buying_price when available,
        // falling back to product-level buying_price × current_stock.
        $productsForAsset = (clone $productsQuery)->select('id', 'buying_price', 'current_stock', 'variation')->get();
        $totalAssetValue = 0;
        foreach ($productsForAsset as $p) {
            $variationData = $p->variation ? (is_array($p->variation) ? $p->variation : json_decode($p->variation, true)) : null;
            if (is_array($variationData) && count($variationData) > 0) {
                foreach ($variationData as $var) {
                    $varBuyingPrice = $var['buying_price'] ?? null;
                    if ($varBuyingPrice > 0) {
                        $totalAssetValue += ($var['qty'] ?? 0) * $varBuyingPrice;
                    } elseif ($p->buying_price > 0) {
                        $totalAssetValue += ($var['qty'] ?? 0) * $p->buying_price;
                    }
                }
            } elseif ($p->buying_price > 0) {
                $totalAssetValue += $p->current_stock * $p->buying_price;
            }
        }

        // Get order details for in-house products with date filtering
        $orderDetailsQuery = OrderDetail::whereHas('product', function ($query) use ($categoryId) {
            $query->where('added_by', 'admin')
                ->when($categoryId && $categoryId != 'all', function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);
                });
        })
            ->where('delivery_status', 'delivered');

        // Apply date filters
        $orderDetailsQuery = $this->applyDateFilter($orderDetailsQuery, $dateType, $from, $to);

        // Get total sales revenue (price * qty for all delivered orders)
        $totalSalesRevenue = (clone $orderDetailsQuery)->sum(DB::raw('price * qty'));

        // Calculate profit using variation-level buying_price when available,
        // falling back to product-level buying_price.
        $orderDetailsForProfit = (clone $orderDetailsQuery)
            ->with('product:id,buying_price,variation')
            ->select('order_details.product_id', 'order_details.price', 'order_details.qty', 'order_details.variant')
            ->get();

        $revenueWithCost = 0;
        $totalCost = 0;
        $profitableItemsQty = 0;

        foreach ($orderDetailsForProfit as $detail) {
            $product = $detail->product;
            if (!$product) {
                continue;
            }

            // Resolve effective buying price: check variation JSON first
            $effectiveBuyingPrice = null;

            if ($detail->variant && $product->variation) {
                $variationData = is_array($product->variation)
                    ? $product->variation
                    : json_decode($product->variation, true);

                if (is_array($variationData)) {
                    foreach ($variationData as $var) {
                        if (isset($var['type']) && $var['type'] === $detail->variant && isset($var['buying_price']) && $var['buying_price'] > 0) {
                            $effectiveBuyingPrice = $var['buying_price'];
                            break;
                        }
                    }
                }
            }

            // Fall back to product-level buying_price
            if ($effectiveBuyingPrice === null && $product->buying_price > 0) {
                $effectiveBuyingPrice = $product->buying_price;
            }

            if ($effectiveBuyingPrice !== null) {
                $revenueWithCost += $detail->price * $detail->qty;
                $totalCost += $effectiveBuyingPrice * $detail->qty;
                $profitableItemsQty += $detail->qty;
            }
        }

        $profitAmount = $revenueWithCost - $totalCost;

        // Count sold items excluded from profit calculation (due to missing buying_price)
        $totalSoldItemsQty = (clone $orderDetailsQuery)->sum('qty');
        $excludedFromProfitQty = $totalSoldItemsQty - $profitableItemsQty;

        // Calculate profit margin percentage
        $profitMarginPercent = $revenueWithCost > 0 ? ($profitAmount / $revenueWithCost) * 100 : 0;

        // Get top performing products (by sales quantity)
        $topProducts = (clone $orderDetailsQuery)
            ->with('product:id,name,thumbnail,thumbnail_storage_type,unit_price,buying_price,current_stock')
            ->selectRaw('product_id, SUM(qty) as total_qty, SUM(price * qty) as total_revenue')
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // Get products list with their sales data for the table
        $productsWithSales = (clone $productsQuery)
            ->withSum(['orderDelivered as total_sold_qty' => function ($query) use ($dateType, $from, $to) {
                $this->applyDateFilterOnRelation($query, $dateType, $from, $to);
            }], 'qty')
            ->withSum(['orderDelivered as total_revenue' => function ($query) use ($dateType, $from, $to) {
                $this->applyDateFilterOnRelation($query, $dateType, $from, $to);
            }], DB::raw('price * qty'))
            ->paginate(Helpers::pagination_limit())
            ->appends($request?->all() ?? []);

        // Compute per-product COGS (cost of goods actually sold) for Total Profit = Revenue - COGS
        // Load all delivered order_details for the current page of products (single query, no N+1)
        $pageProductIds = $productsWithSales->pluck('id');

        $soldDetails = OrderDetail::whereIn('product_id', $pageProductIds)
            ->where('delivery_status', 'delivered')
            ->select('product_id', 'price', 'qty', 'variant');
        $soldDetails = $this->applyDateFilter($soldDetails, $dateType, $from, $to);
        $soldDetails = $soldDetails->get()->groupBy('product_id');

        // Build variation buying_price lookup keyed by [product_id][variant_type]
        $varBuyingMap = [];
        foreach ($productsWithSales as $p) {
            $vars = $p->variation
                ? (is_array($p->variation) ? $p->variation : json_decode($p->variation, true))
                : null;
            if (is_array($vars)) {
                foreach ($vars as $var) {
                    if (!empty($var['type'])) {
                        $varBuyingMap[$p->id][$var['type']] = (float)($var['buying_price'] ?? 0);
                    }
                }
            }
        }

        // Per-product COGS: sum(buying_price × qty_sold) matched by variant
        $productCogs = [];
        foreach ($soldDetails as $productId => $details) {
            $product = $productsWithSales->firstWhere('id', $productId);
            $cogs = 0;
            $hasCost = false;

            foreach ($details as $detail) {
                $bp = null;
                $varType = $detail->variant;

                if ($varType && isset($varBuyingMap[$productId][$varType]) && $varBuyingMap[$productId][$varType] > 0) {
                    $bp = $varBuyingMap[$productId][$varType];
                } elseif (!$varType && $product && $product->buying_price > 0) {
                    $bp = $product->buying_price;
                }

                if ($bp !== null) {
                    $cogs += $bp * $detail->qty;
                    $hasCost = true;
                }
            }

            $productCogs[$productId] = $hasCost ? $cogs : null;
        }

        return view('admin-views.report.inhouse-store-report', compact(
            'categories',
            'totalInhouseProducts',
            'productsWithBuyingPrice',
            'productsMissingBuyingPrice',
            'totalAssetValue',
            'totalSalesRevenue',
            'totalCost',
            'profitAmount',
            'profitMarginPercent',
            'excludedFromProfitQty',
            'totalSoldItemsQty',
            'topProducts',
            'productsWithSales',
            'dateType',
            'from',
            'to',
            'categoryId',
            'search',
            'productCogs'
        ));
    }

    private function applyDateFilter($query, $dateType, $from, $to)
    {
        return $query->when($dateType == 'this_year', function ($q) {
            return $q->whereYear('order_details.created_at', date('Y'));
        })
            ->when($dateType == 'this_month', function ($q) {
                return $q->whereMonth('order_details.created_at', date('m'))
                    ->whereYear('order_details.created_at', date('Y'));
            })
            ->when($dateType == 'this_week', function ($q) {
                return $q->whereBetween('order_details.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })
            ->when($dateType == 'today', function ($q) {
                return $q->whereDate('order_details.created_at', Carbon::today());
            })
            ->when($dateType == 'custom_date' && $from && $to, function ($q) use ($from, $to) {
                return $q->whereDate('order_details.created_at', '>=', $from)
                    ->whereDate('order_details.created_at', '<=', $to);
            });
    }

    private function applyDateFilterOnRelation($query, $dateType, $from, $to)
    {
        $query->when($dateType == 'this_year', function ($q) {
            return $q->whereYear('order_details.created_at', date('Y'));
        })
            ->when($dateType == 'this_month', function ($q) {
                return $q->whereMonth('order_details.created_at', date('m'))
                    ->whereYear('order_details.created_at', date('Y'));
            })
            ->when($dateType == 'this_week', function ($q) {
                return $q->whereBetween('order_details.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })
            ->when($dateType == 'today', function ($q) {
                return $q->whereDate('order_details.created_at', Carbon::today());
            })
            ->when($dateType == 'custom_date' && $from && $to, function ($q) use ($from, $to) {
                return $q->whereDate('order_details.created_at', '>=', $from)
                    ->whereDate('order_details.created_at', '<=', $to);
            });
    }
}
