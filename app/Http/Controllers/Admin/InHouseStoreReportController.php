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

        $categories = $this->categoryRepo->getListWhere(filters: ['parent_id' => 0], dataLimit: 'all');

        // Get in-house products with their order details
        $productsQuery = Product::where('added_by', 'admin')
            ->when($categoryId && $categoryId != 'all', function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            });

        // Count products with and without buying price
        $totalInhouseProducts = (clone $productsQuery)->count();
        $productsWithBuyingPrice = (clone $productsQuery)->whereNotNull('buying_price')->where('buying_price', '>', 0)->count();
        $productsMissingBuyingPrice = $totalInhouseProducts - $productsWithBuyingPrice;

        // Calculate total asset value (only for products with buying_price)
        $totalAssetValue = (clone $productsQuery)
            ->whereNotNull('buying_price')
            ->where('buying_price', '>', 0)
            ->selectRaw('SUM(current_stock * buying_price) as total_asset')
            ->value('total_asset') ?? 0;

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

        // Calculate profit: We need to join with products to get buying_price
        // Profit = (selling_price * qty) - (buying_price * qty) for products with buying_price
        $profitData = (clone $orderDetailsQuery)
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->whereNotNull('products.buying_price')
            ->where('products.buying_price', '>', 0)
            ->selectRaw('
                SUM(order_details.price * order_details.qty) as revenue,
                SUM(products.buying_price * order_details.qty) as cost,
                SUM(order_details.qty) as total_qty
            ')
            ->first();

        $revenueWithCost = $profitData->revenue ?? 0;
        $totalCost = $profitData->cost ?? 0;
        $profitAmount = $revenueWithCost - $totalCost;
        $profitableItemsQty = $profitData->total_qty ?? 0;

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
        $productsWithSales = Product::where('added_by', 'admin')
            ->when($categoryId && $categoryId != 'all', function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->withSum(['orderDelivered as total_sold_qty' => function ($query) use ($dateType, $from, $to) {
                $this->applyDateFilterOnRelation($query, $dateType, $from, $to);
            }], 'qty')
            ->withSum(['orderDelivered as total_revenue' => function ($query) use ($dateType, $from, $to) {
                $this->applyDateFilterOnRelation($query, $dateType, $from, $to);
            }], DB::raw('price * qty'))
            ->paginate(Helpers::pagination_limit())
            ->appends($request?->all() ?? []);

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
            'categoryId'
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
