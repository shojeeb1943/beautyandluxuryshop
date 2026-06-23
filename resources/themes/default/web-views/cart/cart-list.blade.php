@extends('layouts.front-end.app')

@section('title', translate('My_Shopping_Cart'))

@push('css_or_js')
    <meta property="og:image" content="{{$web_config['web_logo']['path']}}"/>
    <meta property="og:title" content="{{$web_config['company_name']}} "/>
    <meta property="og:url" content="{{env('APP_URL')}}">
    <meta property="og:description" content="{{ $web_config['meta_description'] }}">
    <meta property="twitter:card" content="{{$web_config['web_logo']['path']}}"/>
    <meta property="twitter:title" content="{{$web_config['company_name']}}"/>
    <meta property="twitter:url" content="{{env('APP_URL')}}">
    <meta property="twitter:description" content="{{ $web_config['meta_description'] }}">
    <link rel="stylesheet" href="{{dynamicStorage(path: 'public/assets/front-end/css/shop-cart.css')}}">
@endpush

@section('content')
    <div class="container mt-3 rtl px-0 px-md-3 text-align-direction" id="cart-summary">
        @include(VIEW_FILE_NAMES['products_cart_details_partials'])
    </div>

    <span id="get-cart-select-cart-items" data-route="{{ route('cart.select-cart-items') }}"></span>
@endsection

@push('script')
    <script>
        cartQuantityInitialize();
    </script>
@endpush

@push('script')
@php
    $isGuestUser    = !auth('customer')->check();
    $cid            = $isGuestUser ? session('guest_id') : auth('customer')->id();
    $pixelCartItems = $cid ? \App\Models\Cart::where('customer_id', $cid)->where('is_guest', $isGuestUser ? 1 : 0)->get() : collect();
    $pixelCartValue = round($pixelCartItems->sum(fn($c) => ($c->price - $c->discount) * $c->quantity), 2);
    $pixelCartIds   = $pixelCartItems->pluck('product_id')->map('strval')->values()->toArray();
    $pixelCartCount = (int) $pixelCartItems->sum('quantity');
@endphp
@if(isset($web_config['analytic_scripts']))
    @foreach($web_config['analytic_scripts'] as $analyticScript)
        @if($analyticScript['is_active'] && $analyticScript['type'] == 'meta_pixel' && $analyticScript['script_id'])
        <script>
            if (typeof fbq !== 'undefined') {
                fbq('track', 'ViewCart', {
                    content_ids: {!! json_encode($pixelCartIds) !!},
                    content_type: 'product',
                    num_items: {{ $pixelCartCount }},
                    value: {{ $pixelCartValue }},
                    currency: window.PIXEL_CURRENCY || 'BDT'
                });
            }
        </script>
        @endif
        @if($analyticScript['is_active'] && $analyticScript['type'] == 'tiktok_tag' && $analyticScript['script_id'])
        <script>
            if (typeof ttq !== 'undefined') {
                ttq.track('ViewCart', {
                    value: {{ $pixelCartValue }},
                    currency: window.PIXEL_CURRENCY || 'BDT'
                });
            }
        </script>
        @endif
    @endforeach
@endif
@endpush
