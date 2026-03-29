@extends('layouts.admin.app')
@section('title', translate('in_house_store_report'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .badge-not-set {
            background-color: #ffc107;
            color: #212529;
            font-size: 11px;
            padding: 4px 8px;
        }
        .summary-stats {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        {{-- Page Header --}}
        <div class="mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/back-end/img/inhouse_sale.png') }}" alt="">
                {{ translate('in_house_store_report') }}
            </h2>
        </div>

        {{-- Warning Banner for Missing Buying Prices --}}
        @if($productsMissingBuyingPrice > 0)
            <div class="alert alert-soft-warning mb-3" role="alert">
                <div class="d-flex gap-3">
                    <div class="flex-shrink-0">
                        <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/icons/warning.svg') }}" width="20" alt="">
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading mb-1">{{ translate('incomplete_data_notice') }}</h6>
                        <p class="mb-0 fs-12">
                            {{ translate('some_products_do_not_have_buying_price_set') }}.
                            {{ translate('profit_calculations_may_be_partial') }}.
                            <strong>{{ $productsMissingBuyingPrice }}/{{ $totalInhouseProducts }}</strong>
                            {{ translate('products_need_buying_price') }}.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Filter Card --}}
        <div class="card mb-3">
            <div class="card-body">
                <form action="{{ route('admin.report.inhouse-store-report') }}" method="GET">
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-6 col-md-3">
                            <label class="form-label">{{ translate('category') }}</label>
                            <select class="form-control" name="category_id">
                                <option value="all">{{ translate('all_categories') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category['id'] }}" {{ ($categoryId ?? '') == $category['id'] ? 'selected' : '' }}>
                                        {{ $category['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label class="form-label">{{ translate('date_range') }}</label>
                            <select class="form-control" name="date_type" id="date_type">
                                <option value="this_year" {{ $dateType == 'this_year' ? 'selected' : '' }}>{{ translate('this_year') }}</option>
                                <option value="this_month" {{ $dateType == 'this_month' ? 'selected' : '' }}>{{ translate('this_month') }}</option>
                                <option value="this_week" {{ $dateType == 'this_week' ? 'selected' : '' }}>{{ translate('this_week') }}</option>
                                <option value="today" {{ $dateType == 'today' ? 'selected' : '' }}>{{ translate('today') }}</option>
                                <option value="custom_date" {{ $dateType == 'custom_date' ? 'selected' : '' }}>{{ translate('custom_date') }}</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-2 custom-date-fields" style="{{ $dateType != 'custom_date' ? 'display:none' : '' }}">
                            <label class="form-label">{{ translate('from') }}</label>
                            <input type="date" name="from" value="{{ $from }}" class="form-control">
                        </div>
                        <div class="col-sm-6 col-md-2 custom-date-fields" style="{{ $dateType != 'custom_date' ? 'display:none' : '' }}">
                            <label class="form-label">{{ translate('to') }}</label>
                            <input type="date" name="to" value="{{ $to }}" class="form-control">
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="tio-filter-list"></i> {{ translate('filter') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Main Statistics Cards --}}
        <div class="row g-3 mb-3">
            {{-- Total Asset Value --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1">{{ translate('total_asset_value') }}</p>
                                <h3 class="mb-0">
                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $totalAssetValue), currencyCode: getCurrencyCode()) }}
                                </h3>
                                <small class="text-muted">{{ translate('stock') }} x {{ translate('buying_price') }}</small>
                            </div>
                            <div class="icon-circle bg-soft-primary">
                                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/products.svg') }}" width="24" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Total Sales Revenue --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1">{{ translate('total_sales') }}</p>
                                <h3 class="mb-0 text-success">
                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $totalSalesRevenue), currencyCode: getCurrencyCode()) }}
                                </h3>
                                <small class="text-muted">{{ translate('delivered_orders') }}</small>
                            </div>
                            <div class="icon-circle bg-soft-success">
                                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/cart.svg') }}" width="24" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Total Cost --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1">{{ translate('total_cost') }}</p>
                                <h3 class="mb-0 text-danger">
                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $totalCost), currencyCode: getCurrencyCode()) }}
                                </h3>
                                <small class="text-muted">{{ translate('cost_of_goods_sold') }}</small>
                            </div>
                            <div class="icon-circle bg-soft-danger">
                                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/costs.png') }}" width="24" alt=""
                                     onerror="this.src='{{ dynamicAsset(path: 'public/assets/back-end/img/products.svg') }}'">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Profit --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1">{{ translate('net_profit') }}</p>
                                <h3 class="mb-0 {{ $profitAmount >= 0 ? 'text-info' : 'text-danger' }}">
                                    {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $profitAmount), currencyCode: getCurrencyCode()) }}
                                </h3>
                                @if($profitMarginPercent != 0)
                                    <span class="badge badge-soft-{{ $profitAmount >= 0 ? 'success' : 'danger' }}">
                                        {{ $profitAmount >= 0 ? '+' : '' }}{{ number_format($profitMarginPercent, 1) }}% {{ translate('margin') }}
                                    </span>
                                @else
                                    <small class="text-muted">{{ translate('sales') }} - {{ translate('cost') }}</small>
                                @endif
                            </div>
                            <div class="icon-circle bg-soft-info">
                                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/earning.png') }}" width="24" alt=""
                                     onerror="this.src='{{ dynamicAsset(path: 'public/assets/back-end/img/products.svg') }}'">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary Stats Bar --}}
        <div class="card mb-3 summary-stats">
            <div class="card-body py-3">
                <div class="row text-center">
                    <div class="col-6 col-md-3 border-end">
                        <h4 class="mb-0 text-dark">{{ $totalInhouseProducts }}</h4>
                        <small class="text-muted">{{ translate('total_products') }}</small>
                    </div>
                    <div class="col-6 col-md-3 border-end">
                        <h4 class="mb-0 text-success">{{ $productsWithBuyingPrice }}</h4>
                        <small class="text-muted">{{ translate('with_cost_data') }}</small>
                    </div>
                    <div class="col-6 col-md-3 border-end">
                        <h4 class="mb-0 text-warning">{{ $productsMissingBuyingPrice }}</h4>
                        <small class="text-muted">{{ translate('missing_cost_data') }}</small>
                    </div>
                    <div class="col-6 col-md-3">
                        <h4 class="mb-0 text-primary">{{ $totalSoldItemsQty }}</h4>
                        <small class="text-muted">{{ translate('items_sold') }}</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Products Table --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ translate('product_wise_breakdown') }}</h5>
                <span class="badge badge-soft-info">{{ $productsWithSales->total() }} {{ translate('products') }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" style="width: 50px">{{ translate('SL') }}</th>
                                <th>{{ translate('product') }}</th>
                                <th class="text-end">{{ translate('selling_price') }}</th>
                                <th class="text-end">{{ translate('buying_price') }}</th>
                                <th class="text-center">{{ translate('stock') }}</th>
                                <th class="text-center">{{ translate('sold') }}</th>
                                <th class="text-end">{{ translate('revenue') }}</th>
                                <th class="text-end">{{ translate('profit/unit') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($productsWithSales as $key => $product)
                                @php
                                    $profitPerUnit = $product->buying_price ? ($product->unit_price - $product->buying_price) : null;
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $productsWithSales->firstItem() + $key }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ getStorageImages(path: $product->thumbnail_full_url, type: 'backend-product') }}"
                                                 class="avatar avatar-md rounded border" alt="{{ $product->name }}">
                                            <div class="flex-grow-1" style="max-width: 200px;">
                                                <a href="{{ route('admin.products.view', ['addedBy' => 'in-house', 'id' => $product->id]) }}"
                                                   class="text-dark d-block text-truncate" title="{{ $product->name }}">
                                                    {{ $product->name }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $product->unit_price), currencyCode: getCurrencyCode()) }}
                                    </td>
                                    <td class="text-end">
                                        @if($product->buying_price && $product->buying_price > 0)
                                            {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $product->buying_price), currencyCode: getCurrencyCode()) }}
                                        @else
                                            <span class="badge badge-not-set">--</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="{{ $product->current_stock < 10 ? 'text-danger fw-bold' : '' }}">
                                            {{ $product->current_stock }}
                                        </span>
                                    </td>
                                    <td class="text-center">{{ (int)($product->total_sold_qty ?? 0) }}</td>
                                    <td class="text-end">
                                        {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $product->total_revenue ?? 0), currencyCode: getCurrencyCode()) }}
                                    </td>
                                    <td class="text-end">
                                        @if($profitPerUnit !== null)
                                            <span class="fw-semibold {{ $profitPerUnit >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $profitPerUnit >= 0 ? '+' : '' }}{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $profitPerUnit), currencyCode: getCurrencyCode()) }}
                                            </span>
                                        @else
                                            <span class="text-muted">--</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="text-center py-5">
                                            <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/empty-state-icon/default.png') }}"
                                                 width="100" alt="">
                                            <p class="text-muted mt-3 mb-0">{{ translate('no_products_found') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($productsWithSales->hasPages())
                    <div class="card-footer border-top">
                        <div class="d-flex justify-content-end">
                            {!! $productsWithSales->links() !!}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Formula Reference (Collapsible) --}}
        <div class="card mt-3">
            <div class="card-header cursor-pointer" data-bs-toggle="collapse" data-bs-target="#formulaInfo">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="tio-info"></i> {{ translate('how_calculations_work') }}
                    </h6>
                    <i class="tio-chevron-down"></i>
                </div>
            </div>
            <div class="collapse" id="formulaInfo">
                <div class="card-body bg-light">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong class="d-block mb-1">{{ translate('total_asset_value') }}</strong>
                                <code class="bg-white px-2 py-1 rounded">{{ translate('stock_qty') }} x {{ translate('buying_price') }}</code>
                            </div>
                            <div class="mb-3">
                                <strong class="d-block mb-1">{{ translate('total_sales') }}</strong>
                                <code class="bg-white px-2 py-1 rounded">{{ translate('selling_price') }} x {{ translate('qty_sold') }}</code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong class="d-block mb-1">{{ translate('total_cost') }}</strong>
                                <code class="bg-white px-2 py-1 rounded">{{ translate('buying_price') }} x {{ translate('qty_sold') }}</code>
                            </div>
                            <div class="mb-3">
                                <strong class="d-block mb-1">{{ translate('profit') }}</strong>
                                <code class="bg-white px-2 py-1 rounded">{{ translate('total_sales') }} - {{ translate('total_cost') }}</code>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-soft-info mb-0 mt-2">
                        <small>
                            <i class="tio-info"></i>
                            {{ translate('note') }}: {{ translate('calculations_only_include_products_with_buying_price_set') }}.
                            {{ translate('products_without_buying_price_are_excluded_from_profit_and_cost_calculations') }}.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).ready(function() {
            $('#date_type').on('change', function() {
                if ($(this).val() === 'custom_date') {
                    $('.custom-date-fields').show();
                } else {
                    $('.custom-date-fields').hide();
                }
            });
        });
    </script>
@endpush
