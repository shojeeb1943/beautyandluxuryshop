@extends('layouts.admin.app')

@section('title', translate('order_Details'))

@section('content')
    <div class="content container-fluid">

        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <h2 class="h1 mb-0">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/all-orders.png') }}" alt="">
                {{ translate('order_Details') }}
            </h2>
        </div>

        <div class="row gx-2 gy-3" id="printableArea">
            <div class="col-lg-8 col-xl-9">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 flex-md-nowrawp justify-content-between mb-4">
                            <div class="d-flex flex-column gap-2">
                                <h3 class="text-capitalize">
                                    {{ translate('order_ID') }} #{{$order['id']}}
                                    @if($order['order_type'] == 'POS')
                                        <span>({{ 'POS' }})</span>
                                    @endif
                                </h3>
                                <div class="">
                                    <i class="fi fi-rr-calendar"></i> {{date('d M Y H:i:s',strtotime($order['created_at'])) }}
                                </div>
                            </div>
                            <div class="text-sm-right flex-grow-1">
                                <div class="d-flex flex-wrap gap-2 justify-content-sm-end">
                                    <a class="btn btn-primary px-4" target="_blank"
                                       href="{{ route('admin.orders.generate-invoice',[$order['id']]) }}">
                                        <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/icons/uil_invoice.svg') }}"
                                             alt="" class="mr-1">
                                        {{ translate('print_Invoice') }}
                                    </a>
                                </div>
                                <div class="d-flex flex-column gap-2 mt-3">
                                    <div class="order-status d-flex justify-content-sm-end gap-2 text-capitalize">
                                        <span class="text-dark">{{ translate('status') }}: </span>
                                        @if($order['order_status']=='pending')
                                            <span
                                                class="badge badge-info text-bg-info fw-bold rounded-50 d-flex align-items-center py-1 px-2">
                                                {{ translate(str_replace('_',' ',$order['order_status'])) }}
                                            </span>
                                        @elseif($order['order_status']=='failed')
                                            <span
                                                class="badge badge-danger text-bg-danger fw-bold rounded-50 d-flex align-items-center py-1 px-2">
                                                {{ translate(str_replace('_',' ',$order['order_status'])) }}
                                            </span>
                                        @elseif($order['order_status']=='processing' || $order['order_status']=='out_for_delivery')
                                            <span
                                                class="badge badge-warning text-bg-warning fw-bold rounded-50 d-flex align-items-center py-1 px-2">
                                                {{ translate(str_replace('_',' ',$order['order_status'])) }}
                                            </span>
                                        @elseif($order['order_status']=='delivered' || $order['order_status']=='confirmed')
                                            <span
                                                class="badge badge-success text-bg-success fw-bold rounded-50 d-flex align-items-center py-1 px-2">
                                                {{ translate(str_replace('_',' ',$order['order_status'])) }}
                                            </span>
                                        @else
                                            <span
                                                class="badge badge-danger text-bg-danger fw-bold rounded-50 d-flex align-items-center py-1 px-2">
                                                {{ translate(str_replace('_',' ',$order['order_status'])) }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="payment-method d-flex justify-content-sm-end gap-2 text-capitalize">
                                        <span class="text-dark">{{ translate('payment_Method') }} :</span>
                                        <strong>  {{ translate(str_replace('_',' ',$order['payment_method'])) }}</strong>
                                    </div>
                                    @if(isset($order['transaction_ref']) && $order->payment_method != 'cash_on_delivery' && $order->payment_method != 'pay_by_wallet' && !isset($order->offline_payments))
                                        <div
                                            class="reference-code d-flex justify-content-sm-end gap-2 text-capitalize">
                                            <span class="text-dark">{{ translate('reference_Code') }} :</span>
                                            <strong>{{ translate(str_replace('_',' ',$order['transaction_ref'])) }} {{ $order->payment_method == 'offline_payment' ? '('.$order->payment_by.')':'' }}</strong>
                                        </div>
                                    @endif
                                    <div class="payment-status d-flex justify-content-sm-end gap-2">
                                        <span class="text-dark">{{ translate('payment_Status') }}:</span>
                                        @if($order['payment_status']=='paid')
                                            <span class="text-success fw-bold">
                                                {{ translate('paid') }}
                                            </span>
                                        @else
                                            <span class="text-danger fw-bold">
                                                {{ translate('unpaid') }}
                                            </span>
                                        @endif
                                    </div>
                                    @if(getWebConfig('order_verification') && $order->order_type == "default_type")
                                        <span class="ms-2 ms-sm-3">
                                            <b>
                                                {{ translate('order_verification_code') }} : {{$order['verification_code']}}
                                            </b>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive datatable-custom">
                            <table
                                class="table fs-12 table-hover table-borderless align-middle">
                                <thead class="text-capitalize">
                                <tr>
                                    <th>{{ translate('SL') }}</th>
                                    <th>{{ translate('item_details') }}</th>
                                    <th>{{ translate('item_price') }}</th>
                                    <th>{{ translate('tax') }}</th>
                                    <th>{{ translate('item_discount') }}</th>
                                    <th>{{ translate('total_price') }}</th>
                                </tr>
                                </thead>

                                <tbody>
                                @php($item_price=0)
                                @php($subtotal=0)
                                @php($total=0)
                                @php($discount=0)
                                @php($tax=0)
                                @php($product_price=0)
                                @php($total_product_price=0)
                                @foreach($order->details as $key=>$detail)
                                    <?php
                                        if($detail->product) {
                                            $productDetails = $detail->product;
                                        }else {
                                            $productDetails = json_decode($detail->product_details);
                                        }
                                    ?>
                                    @if($productDetails)
                                        <tr>
                                            <td>{{++$key}}</td>
                                            <td>
                                                <div class="media align-items-center gap-2">
                                                    <img class="avatar avatar-60 rounded img-fit"
                                                         src="{{ getStorageImages(path: $productDetails->thumbnail_full_url, type: 'backend-product') }}"
                                                         alt="{{ translate('image_description')}}">
                                                    <div>
                                                        <h5 class="text-dark">{{substr($productDetails->name, 0, 30) }}{{strlen($productDetails->name)>10?'...':''}}</h5>
                                                        <div><strong>{{ translate('qty') }}
                                                                :</strong> {{$detail['qty']}}
                                                        </div>
                                                        <div>
                                                            <strong>{{ translate('unit_price') }} :</strong>
                                                            {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $detail['price'] + ($detail->tax_model =='include' ? ($detail['tax'] / $detail['qty']) :0))) }}
                                                            @if ($detail->tax_model =='include')
                                                                ({{ translate('tax_incl.') }})
                                                            @else
                                                                ({{ translate('tax').":".($productDetails->tax) }}{{$productDetails->tax_type ==="percent" ? '%' :''}})
                                                            @endif
                                                        </div>
                                                        @if ($detail->variant)
                                                            <div>
                                                                <strong>{{ translate('variation') }}:</strong> {{$detail['variant']}}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if(isset($productDetails->digital_product_type) && $productDetails->digital_product_type == 'ready_after_sell')
                                                    <button type="button" class="btn btn-sm btn-primary mt-2"
                                                            title="File Upload" data-bs-toggle="modal"
                                                            data-bs-target="#fileUploadModal-{{ $detail->id }}">
                                                            <i class="fi fi-rr-document"></i> {{ translate('file') }}
                                                    </button>
                                                @endif

                                            </td>
                                            <td>{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $detail['price']*$detail['qty']), currencyCode: getCurrencyCode()) }}</td>
                                            <td>
                                                {{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $detail['tax']), currencyCode: getCurrencyCode()) }}
                                            </td>
                                            <td>{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $detail['discount']), currencyCode: getCurrencyCode()) }}</td>
                                            @php($item_price+=$detail['price']*$detail['qty'])
                                            @php($subtotal=($detail['price']*$detail['qty'])+$detail['tax']-$detail['discount'])
                                            @php($product_price = $detail['price']*$detail['qty'])
                                            @php($total_product_price+=$product_price)
                                            <td>{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $subtotal), currencyCode: getCurrencyCode()) }}</td>
                                            @if($productDetails->product_type == 'digital')
                                                <div class="modal fade" id="fileUploadModal-{{ $detail->id }}"
                                                     tabindex="-1" aria-labelledby="exampleModalLabel"
                                                     aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <form
                                                                action="{{ route('admin.orders.digital-file-upload-after-sell') }}"
                                                                method="post" enctype="multipart/form-data">
                                                                @csrf
                                                                <div class="modal-body">
                                                                    @if(($detail?->digital_file_after_sell_full_url) && isset($detail->digital_file_after_sell_full_url['key']))
                                                                        <div class="mb-4">
                                                                            {{ translate('uploaded_file') }} :
                                                                            <span data-file-path="{{ $detail->digital_file_after_sell_full_url['path'] }}"
                                                                               class="btn btn-success btn-sm getDownloadFileUsingFileUrl"
                                                                               title="{{ translate('download')}}"><i
                                                                                    class="fi fi-rr-download"></i>
                                                                                {{ translate('download')}}
                                                                            </span>
                                                                        </div>
                                                                    @elseif($productDetails->digital_product_type == 'ready_after_sell' && $detail->digital_file_after_sell)
                                                                        <div class="mb-4">
                                                                            {{ translate('uploaded_file') }} :
                                                                            <a href="{{ asset('storage/app/public/product/digital-product/'.$detail->digital_file_after_sell) }}"
                                                                               class="btn btn-success btn-sm"
                                                                               title="{{ translate('download')}}"><i
                                                                                    class="fi fi-rr-download"></i>
                                                                                {{ translate('download')}}</a>
                                                                        </div>
                                                                    @elseif($productDetails->digital_product_type == 'ready_product' && $productDetails->digital_file_ready)
                                                                        <div class="mb-4">
                                                                            {{ translate('uploaded_file').':' }}
                                                                            <a href="{{ asset('storage/app/public/product/digital-product/'.$productDetails->digital_file_ready) }}"
                                                                               class="btn btn-success btn-sm"
                                                                               title="Download"><i
                                                                                    class="fi fi-rr-download"></i>
                                                                                {{ translate('Download')}}</a>
                                                                        </div>
                                                                    @endif

                                                                    @if($productDetails->digital_product_type == 'ready_after_sell')
                                                                        <input type="file"
                                                                               name="digital_file_after_sell"
                                                                               class="form-control">
                                                                        <div
                                                                            class="mt-1 text-info">{{ translate('file_type').': jpg, jpeg, png, gif, zip, pdf' }}
                                                                        </div>
                                                                        <input type="hidden" value="{{ $detail->id }}"
                                                                               name="order_id">
                                                                    @endif
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary"
                                                                            data-bs-dismiss="modal">{{ translate('close') }}</button>
                                                                    @if($productDetails->digital_product_type == 'ready_after_sell')
                                                                        <button type="submit"
                                                                                class="btn btn-primary">{{ translate('upload') }}</button>
                                                                    @endif
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </tr>
                                        @php($discount+=$detail['discount'])
                                        @php($tax+=$detail['tax'])
                                        @php($total+=$subtotal)
                                    @endif
                                    @php($sellerId=$detail->seller_id)
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <hr>
                        @php($orderTotalPriceSummary = \App\Utils\OrderManager::getOrderTotalPriceSummary(order: $order))
                        {{-- <div class="row justify-content-md-end mb-3">
                            <div class="col-md-9 col-lg-8">
                                <dl class="row gy-1 text-sm-end">
                                    <dt class="col-5">{{ translate('item_price') }}</dt>
                                    <dd class="col-6 text-dark">
                                        <strong>{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $orderTotalPriceSummary['itemPrice']), currencyCode: getCurrencyCode()) }}</strong>
                                    </dd>
                                    <dt class="col-5 text-capitalize">{{ translate('item_discount') }}</dt>
                                    <dd class="col-6 text-dark">
                                        -
                                        <strong>{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $orderTotalPriceSummary['itemDiscount']), currencyCode: getCurrencyCode()) }}</strong>
                                    </dd>
                                    <dt class="col-5">{{ translate('extra_discount') }}</dt>
                                    <dd class="col-6 text-dark">
                                        <strong>- {{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $orderTotalPriceSummary['extraDiscount']), currencyCode: getCurrencyCode()) }}</strong>
                                    </dd>
                                    <dt class="col-5 text-capitalize">{{ translate('sub_total') }}</dt>
                                    <dd class="col-6 text-dark">
                                        <strong>{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $orderTotalPriceSummary['subTotal']), currencyCode: getCurrencyCode()) }}</strong>
                                    </dd>
                                    <dt class="col-5">{{ translate('coupon_discount') }}</dt>
                                    <dd class="col-6 text-dark">
                                        <strong>- {{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $orderTotalPriceSummary['couponDiscount']), currencyCode: getCurrencyCode()) }}</strong>
                                    </dd>
                                    <dt class="col-5 text-uppercase">{{ translate('vat') }}/{{ translate('tax') }}</dt>
                                    <dd class="col-6 text-dark">
                                        <strong>{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $orderTotalPriceSummary['taxTotal']), currencyCode: getCurrencyCode()) }}</strong>
                                    </dd>
                                    <dt class="col-5">{{ translate('total') }}</dt>
                                    <dd class="col-6 text-dark">
                                        <strong>{{setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $orderTotalPriceSummary['totalAmount']), currencyCode: getCurrencyCode()) }}</strong>
                                    </dd>
                                    @if ($order->order_type == 'pos' || $order->order_type == 'POS')
                                        <dt class="col-5"><strong>{{ translate('paid_amount')}}</strong></dt>
                                        <dd class="col-6 text-dark">
                                            <strong> {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['paidAmount']), currencyCode: getCurrencyCode()) }}</strong>
                                        </dd>
                                        <dt class="col-5"><strong>{{ translate('change_amount')}}</strong></dt>
                                        <dd class="col-6 text-dark">
                                            <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['changeAmount']), currencyCode: getCurrencyCode()) }}</strong>
                                        </dd>
                                    @endif
                                </dl>
                            </div>
                        </div> --}}
                        <div class="px-sm-5 overflow-x-auto">
                            <table class="table table-borderless table-sm mb-0 text-sm-right text-nowrap">
                                <tbody>
                                    <tr>
                                        <td class="text-end text-body text-capitalize"><strong>{{ translate('item_price') }}</strong></td>
                                        <td class="text-end text-dark">
                                            <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['itemPrice']), currencyCode: getCurrencyCode()) }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-end text-body text-capitalize"><strong>{{ translate('item_discount') }}</strong></td>
                                        <td class="text-end text-dark">
                                            -
                                            <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['itemDiscount']), currencyCode: getCurrencyCode()) }}</strong>
                                        </td>
                                    </tr>
                                     <tr>
                                        <td class="text-end text-body"><strong>{{ translate('extra_discount') }}</strong></td>
                                        <td class="text-end text-dark">
                                            <strong>- {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['extraDiscount']), currencyCode: getCurrencyCode()) }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-end text-body text-capitalize"><strong>{{ translate('sub_total') }}</strong></td>
                                        <td class="text-end text-dark">
                                            <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['subTotal']), currencyCode: getCurrencyCode()) }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-end text-body"><strong>{{ translate('coupon_discount') }}</strong></td>
                                        <td class="text-end text-dark">
                                            <strong>-
                                                {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['couponDiscount']), currencyCode: getCurrencyCode()) }}</strong>
                                        </td>
                                    </tr>
                                    @if($orderTotalPriceSummary['referAndEarnDiscount'] > 0)
                                    <tr>
                                        <td class="text-end text-body"><strong>{{ translate('referral_discount') }}</strong></td>
                                        <td class="text-end text-dark">
                                            <strong>-
                                                {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['referAndEarnDiscount']), currencyCode: getCurrencyCode()) }}</strong>
                                        </td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <td class="text-end text-body text-uppercase"><strong>{{ translate('vat') }}/{{ translate('tax') }}</strong></td>
                                        <td class="text-end text-dark">
                                            <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['taxTotal']), currencyCode: getCurrencyCode()) }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-end text-body"><strong>{{ translate('total') }}</strong></td>
                                        <td class="text-end text-dark">
                                            <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['totalAmount']), currencyCode: getCurrencyCode()) }}</strong>
                                        </td>
                                    </tr>
                                    @if ($order->order_type == 'pos' || $order->order_type == 'POS')
                                    <tr>
                                        <td class="text-end text-body"><strong>{{ translate('paid_amount') }}</strong></td>
                                        <td class="text-end text-dark">
                                            <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['paidAmount']), currencyCode: getCurrencyCode()) }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-end text-body"><strong>{{ translate('change_amount') }}</strong></td>
                                        <td class="text-end text-dark">
                                            <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $orderTotalPriceSummary['changeAmount']), currencyCode: getCurrencyCode()) }}</strong>
                                        </td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-xl-3">
                @php($isPosDelivery = $order->delivery_type == 'self_delivery' || !is_null($order->shipping_address_data))

                @if($isPosDelivery)
                <div class="card mb-3">
                    <div class="card-body text-capitalize d-flex flex-column gap-4">
                        <div class="d-flex flex-column align-items-center gap-2">
                            <h2 class="mb-0 text-center">{{ translate('order_&_Shipping_Info') }}</h2>
                        </div>

                        <div>
                            <label class="form-label fw-semibold">{{ translate('change_order_status') }}</label>
                            <div class="select-wrapper">
                                <select name="order_status" id="order_status" class="status form-select" data-id="{{ $order['id'] }}">
                                    <option value="pending"          {{ $order->order_status == 'pending'          ? 'selected' : '' }}>{{ translate('pending') }}</option>
                                    <option value="confirmed"        {{ $order->order_status == 'confirmed'        ? 'selected' : '' }}>{{ translate('confirmed') }}</option>
                                    <option value="processing"       {{ $order->order_status == 'processing'       ? 'selected' : '' }}>{{ translate('packaging') }}</option>
                                    <option value="out_for_delivery" {{ $order->order_status == 'out_for_delivery' ? 'selected' : '' }}>{{ translate('out_for_delivery') }}</option>
                                    <option value="delivered"        {{ $order->order_status == 'delivered'        ? 'selected' : '' }}>{{ translate('delivered') }}</option>
                                    <option value="returned"         {{ $order->order_status == 'returned'         ? 'selected' : '' }}>{{ translate('returned') }}</option>
                                    <option value="failed"           {{ $order->order_status == 'failed'           ? 'selected' : '' }}>{{ translate('failed_to_Deliver') }}</option>
                                    <option value="canceled"         {{ $order->order_status == 'canceled'         ? 'selected' : '' }}>{{ translate('canceled') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center gap-2 form-control h-auto flex-wrap">
                            <span class="text-dark">{{ translate('payment_status') }}</span>
                            <div class="d-flex justify-content-end align-items-center gap-2">
                                <span class="text-primary fw-bold payment-status-text">
                                    {{ $order->payment_status == 'paid' ? translate('paid') : translate('unpaid') }}
                                </span>
                                <label class="switcher payment-status-text mb-0">
                                    <input class="switcher_input payment-status" type="checkbox" name="status"
                                           data-id="{{ $order->id }}"
                                           value="{{ $order->payment_status }}"
                                           {{ $order->payment_status == 'paid' ? 'checked' : '' }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                @if($order->shipping_address_data)
                    @php($shippingAddr = is_string($order->shipping_address_data) ? json_decode($order->shipping_address_data, true) : (array)$order->shipping_address_data)
                    <div class="card mb-3">
                        <div class="card-body">
                            <h4 class="mb-3 d-flex align-items-center gap-2">
                                <i class="fi fi-rr-marker"></i>
                                {{ translate('shipping_address') }}
                            </h4>
                            <address class="mb-0">
                                <strong>{{ $shippingAddr['contact_person_name'] ?? '' }}</strong><br>
                                {{ $shippingAddr['address'] ?? '' }}<br>
                                {{ $shippingAddr['city'] ?? '' }}@if($shippingAddr['zip'] ?? ''), {{ $shippingAddr['zip'] }}@endif<br>
                                {{ $shippingAddr['country'] ?? '' }}<br>
                                <abbr title="Phone">P:</abbr> {{ $shippingAddr['phone'] ?? '' }}
                            </address>
                        </div>
                    </div>
                @endif
                @endif {{-- end $isPosDelivery --}}

                <div class="card">

                    @if($order->customer)
                        <div class="card-body">
                            <h4 class="mb-4 d-flex align-items-center gap-2">
                                <img src="{{ asset('public/assets/back-end/img/vendor-information.png') }}" alt="">
                                {{ translate('customer_information') }}
                            </h4>

                            <div class="media flex-wrap gap-3 align-items-center">
                                <div class="">
                                    <img class="avatar rounded-circle avatar-70 aspect-1"
                                         src="{{getStorageImages(path: $order->customer->image_full_url,type:'backend-profile')}}"
                                         alt="{{ translate('image')}}">
                                </div>
                                <div class="media-body d-flex flex-column gap-1">
                                    <span
                                        class="text-dark hover-c1 text-capitalize"><strong>{{$order->customer['f_name'].' '.$order->customer['l_name']}}</strong></span>
                                    @if($order->customer->id != 0)
                                        <span
                                            class="text-dark break-all"><strong>{{$order->customer['phone']}}</strong></span>
                                        <span class="text-dark break-all">{{$order->customer['email']}}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="card-body">
                            <div class="media align-items-center">
                                <span>{{ translate('no_customer_found') }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="locationModal" tabindex="-1" role="dialog" aria-labelledby="locationModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0 d-flex justify-content-end">
                    <h3 class="modal-title text-center flex-grow-1" id="locationModalLabel">{{translate('location_Data')}}</h3>
                    <button type="button" class="btn-close border-0 btn-circle bg-section2 shadow-none"
                        data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 modal_body_map">
                            <div class="location-map" id="location-map">
                                <div class="w-100 h-200" id="location_map_canvas"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Required by order.js for status/payment AJAX --}}
    <span id="order-status-url"             data-url="{{ route('admin.orders.status') }}"></span>
    <span id="payment-status-url"           data-url="{{ route('admin.orders.payment-status') }}"></span>
    <span id="message-status-title-text"    data-text="{{ translate('are_you_sure') }}?"></span>
    <span id="message-status-subtitle-text" data-text="{{ translate('you_will_not_be_able_to_revert_this') }}!"></span>
    <span id="message-status-confirm-text"  data-text="{{ translate('yes_change_it') }}!"></span>
    <span id="message-status-cancel-text"   data-text="{{ translate('cancel') }}"></span>
    <span id="message-status-success-text"  data-text="{{ translate('status_change_successfully') }}"></span>
    <span id="message-status-warning-text"  data-text="{{ translate('account_has_been_deleted_you_can_not_change_the_status') }}"></span>
    <span id="message-order-status-delivered-text" data-text="{{ translate('order_is_already_delivered_you_can_not_change_it') }}!"></span>
    <span id="message-order-status-paid-first-text" data-text="{{ translate('before_delivered_you_need_to_make_payment_status_paid') }}!"></span>
    <span id="payment-status-message"
          data-title="{{ translate('confirm_payments_before_change_the_status') }}."
          data-message="{{ translate('change_the_status_paid_only_when_you_received_the_payment_from_customer').translate('_once_you_change_the_status_to_paid').','.translate('_you_cannot_change_the_status_again').'!' }}"></span>
    <span id="payment-status-alert-message" data-message="{{ translate('when_payment_status_paid_then_you_can`t_change_payment_status_paid_to_unpaid') }}."></span>
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin/order.js') }}"></script>
@endpush
