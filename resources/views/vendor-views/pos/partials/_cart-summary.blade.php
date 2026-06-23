@php($currentCustomerData = $summaryData['currentCustomerData'] ?? null)
@php($cartNames = $summaryData['cartNames'] ?? [])

<div class="d-none pos-current-customer-data"
     data-name="{{ trim($currentCustomerData?->f_name.' '.$currentCustomerData?->l_name) }}"
     data-phone="{{ $currentCustomerData?->phone }}"
     data-address="{{ $currentCustomerData?->street_address }}"
     data-city="{{ $currentCustomerData?->city }}"
     data-zip="{{ $currentCustomerData?->zip }}"
     data-country="{{ $currentCustomerData?->country }}"></div>

@if ($summaryData['currentCustomer'] != 'Walk-In Customer')
    <div class="pos-home-delivery mb-4">
        <div class="d-flex justify-content-between gap-2 mb-3">
            <div class="d-flex gap-2">
                <i class="tio-user-big"></i>
                <h4 class="card-title">{{ translate('customer_Information') }} </h4>
            </div>
        </div>

        <div class="row gy-2">
            <div class="col-sm-12">
                <div class="pair-list">
                    <div>
                        <span class="key custom-flex-basis">{{ translate('name') }}</span>
                        <span>:</span>
                        <span class="value">{{ $currentCustomerData?->f_name.' '.$currentCustomerData?->l_name }}</span>
                    </div>
                    <div>
                        <span class="key custom-flex-basis">{{ translate('contact') }}</span>
                        <span>:</span>
                        <a href="tel:{{ $currentCustomerData?->phone }}"
                           class="value text-dark">{{ $currentCustomerData?->phone }}</a>
                    </div>
                </div>
            </div>
            @php( $walletStatus = getWebConfig('wallet_status') ?? 0)
            @if ($walletStatus)
                <input type="hidden" class="form-control customer-wallet-balance"
                       value="{{usdToDefaultCurrency(amount: $currentCustomerData?->wallet_balance ?? 0)}}"
                       readonly>
            @endif
        </div>
    </div>
@endif
<div class="d-flex gap-2 flex-wrap mb-3">
    <div class="dropdown flex-grow-1" id="dropdown-order-select">
        <button class="form-control text-start dropdown-toggle" data-toggle="dropdown" aria-haspopup="true"
                aria-expanded="false" id="cart_id_primary">
            {{ session('current_user') }}
        </button>
        <div class="dropdown-menu px-2">
            @foreach ($cartNames as $cartName)
                <button class="dropdown-item border rounded mb-1 action-cart-change" data-cart="{{ $cartName }}">{{ $cartName }}</button>
            @endforeach
            <button class="dropdown-item border rounded mt-2 action-view-all-hold-orders">
                <span class="d-flex align-items-center gap-2">
                    <i class="tio-pause"></i>
                    {{translate('view_all_hold_orders')}}
                    <span class="badge badge-danger rounded-circle">{{ $summaryData['totalHoldOrders'] }}</span>
                </span>
            </button>
        </div>
    </div>
    <a class="btn btn-secondary rounded text-nowrap action-clear-cart">
        {{ translate('clear_Cart')}}
    </a>
    <a class="btn btn--primary rounded text-nowrap action-new-order">
        {{ translate('new_Order')}}
    </a>
</div>

{{-- Order type toggle + delivery block --}}
<div class="mb-3">
    <div class="text-dark font-weight-bold mb-2">{{ translate('order_type') }}:</div>
    <ul class="list-unstyled d-flex flex-wrap gap-2 align-items-center pos-order-type-buttons mb-0">
        <li>
            <button type="button" class="btn btn-dark btn-sm mb-0 pos-order-type-btn" data-value="walk_in">{{ translate('walk_in') }}</button>
        </li>
        <li>
            <button type="button" class="btn btn-outline-dark btn-sm mb-0 pos-order-type-btn" data-value="delivery">{{ translate('home_delivery') }}</button>
        </li>
    </ul>
</div>

<div class="pos-delivery-section d-none mb-3 p-3 border rounded">
    <div class="text-dark font-weight-bold mb-2">{{ translate('delivery_details') }}:</div>

    <div class="form-group mb-2">
        <label class="form-label small">{{ translate('delivery_address') }}</label>
        <select id="pos-address-select" class="form-control">
            <option value="">-- {{ translate('select_address') }} --</option>
        </select>
        <small class="pos-no-address-msg text-muted d-none">{{ translate('no_saved_address_for_this_customer') }}</small>
    </div>

    <div class="form-group mb-2">
        <label class="form-label small">{{ translate('shipping_method') }}</label>
        <select id="pos-shipping-method-select" class="form-control">
            <option value="">-- {{ translate('select_shipping_method') }} --</option>
        </select>
        <small class="pos-no-method-msg text-muted d-none">{{ translate('no_shipping_method_configured') }}</small>
    </div>

    <div id="pos-address-preview" class="d-none small text-muted mt-1"></div>
</div>

@php($customerAddressesUrl = route('vendor.pos.customer-addresses', ['customerId' => '__CID__']))
@php($shippingMethodsUrl = route('vendor.pos.shipping-methods'))
<div class="d-none"
     id="pos-delivery-urls"
     data-addresses-url="{{ $customerAddressesUrl }}"
     data-methods-url="{{ $shippingMethodsUrl }}"
     data-customer-id="{{ $currentCustomerData?->id ?? 0 }}"></div>

@include('vendor-views.pos.partials._cart')
