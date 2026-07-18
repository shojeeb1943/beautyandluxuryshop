<?php

namespace App\Http\Controllers\Admin\POS;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\DigitalProductVariationRepositoryInterface;
use App\Contracts\Repositories\OrderDetailRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StorageRepositoryInterface;
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Enums\SessionKey;
use App\Events\DigitalProductDownloadEvent;
use App\Exceptions\PosInsufficientStockException;
use App\Http\Controllers\BaseController;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\ShippingMethod;
use App\Services\CartService;
use App\Services\OrderDetailsService;
use App\Services\OrderService;
use App\Services\POSService;
use App\Traits\CalculatorTrait;
use App\Traits\CustomerTrait;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class POSOrderController extends BaseController
{
    use CustomerTrait;
    use CalculatorTrait;

    /**
     * @param ProductRepositoryInterface $productRepo
     * @param CustomerRepositoryInterface $customerRepo
     * @param OrderRepositoryInterface $orderRepo
     * @param OrderDetailRepositoryInterface $orderDetailRepo
     * @param VendorRepositoryInterface $vendorRepo
     * @param DigitalProductVariationRepositoryInterface $digitalProductVariationRepo
     * @param StorageRepositoryInterface $storageRepo
     * @param POSService $POSService
     * @param CartService $cartService
     * @param OrderDetailsService $orderDetailsService
     * @param OrderService $orderService
     */
    public function __construct(
        private readonly ProductRepositoryInterface                 $productRepo,
        private readonly CustomerRepositoryInterface                $customerRepo,
        private readonly OrderRepositoryInterface                   $orderRepo,
        private readonly OrderDetailRepositoryInterface             $orderDetailRepo,
        private readonly VendorRepositoryInterface                  $vendorRepo,
        private readonly DigitalProductVariationRepositoryInterface $digitalProductVariationRepo,
        private readonly StorageRepositoryInterface                 $storageRepo,
        private readonly POSService                                 $POSService,
        private readonly CartService                                $cartService,
        private readonly OrderDetailsService                        $orderDetailsService,
        private readonly OrderService                               $orderService,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     */
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $vendorId = auth('seller')->id();
        $vendor = $this->vendorRepo->getFirstWhere(params: ['id' => $vendorId]);
        $getPOSStatus = getWebConfig('seller_pos');
        if ($vendor['pos_status'] == 0 || $getPOSStatus == 0) {
            ToastMagic::warning(translate('access_denied!!'));
            return redirect()->back();
        }
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $id], relations: ['details', 'shipping', 'seller']);
        return view('admin-views.pos.order.order-details', compact('order'));
    }



    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function placeOrder(Request $request): JsonResponse
    {
        $amount = $request['amount'];
        $paidAmount = $request['type'] == 'cash' ? ($request['paid_amount'] ?? 0) : null;
        $cartId = session(SessionKey::CURRENT_USER);
        $condition = $this->POSService->checkConditions(amount: $amount, paidAmount: $paidAmount);
        if ($condition == 'true') {
            return response()->json();
        }
        $userId = $this->cartService->getUserId();
        $checkProductTypeDigital = $this->cartService->checkProductTypeDigital(cartId: $cartId);
        if ($userId == 0 && $checkProductTypeDigital) {
            return response()->json(['checkProductTypeForWalkingCustomer' => true, 'message' => translate('To_order_digital_product') . ',' . translate('_kindly_fill_up_the_”Add_New_Customer”_form') . '.']);
        }

        $orderType = $request['order_type_pos'] == 'delivery' ? 'delivery' : 'walk_in';
        $shippingCost = 0;
        $shippingAddress = null;
        $shippingMethodId = null;
        if ($orderType == 'delivery') {
            if ($userId == 0) {
                return response()->json(['deliveryNeedsCustomer' => true, 'message' => translate('To_place_a_delivery_order') . ',' . translate('_kindly_select_or_add_a_customer') . '.']);
            }
            $addressId = $request['shipping_address_id'];
            if ($addressId === 'profile') {
                $customer = $this->customerRepo->getFirstWhere(params: ['id' => $userId]);
                if (!$customer || empty($customer['street_address'])) {
                    return response()->json(['deliveryNeedsAddress' => true, 'message' => translate('Please_provide_the_delivery_address') . '.']);
                }
                $addressData = [
                    'contact_person_name' => trim(($customer['f_name'] ?? '') . ' ' . ($customer['l_name'] ?? '')),
                    'phone' => $customer['phone'] ?? '',
                    'country' => $customer['country'] ?? '',
                    'city' => $customer['city'] ?? '',
                    'zip' => $customer['zip'] ?? '',
                    'address' => $customer['street_address'] ?? '',
                    'latitude' => '',
                    'longitude' => '',
                ];
            } else {
                $savedAddress = ShippingAddress::find($addressId);
                if (!$savedAddress) {
                    return response()->json(['deliveryNeedsAddress' => true, 'message' => translate('Please_provide_the_delivery_address') . '.']);
                }
                $addressData = [
                    'contact_person_name' => $savedAddress->contact_person_name,
                    'phone' => $savedAddress->phone,
                    'country' => $savedAddress->country,
                    'city' => $savedAddress->city,
                    'zip' => $savedAddress->zip,
                    'address' => $savedAddress->address,
                    'latitude' => '',
                    'longitude' => '',
                ];
            }
            $shippingMethod = ShippingMethod::find($request['shipping_method_id']);
            if (!$shippingMethod) {
                return response()->json(['deliveryNeedsMethod' => true, 'message' => translate('Please_select_a_shipping_method') . '.']);
            }
            $shippingCost = (float)$shippingMethod->cost;
            $shippingMethodId = $shippingMethod->id;
            $shippingAddress = $addressData;
        }

        if ($request['type'] == 'wallet' && $userId != 0) {
            $customerBalance = $this->customerRepo->getFirstWhere(params: ['id' => $userId]) ?? 0;
            if ($customerBalance['wallet_balance'] >= currencyConverter(amount: $amount)) {
                $this->createWalletTransaction(user_id: $userId, amount: floatval($amount), transaction_type: 'order_place', reference: 'order_place_in_pos');
            } else {
                ToastMagic::error(translate('need_Sufficient_Amount_Balance'));
                return response()->json();
            }
        }
        $cart = session($cartId);

        $lock = Cache::lock('pos-order-place-' . $cartId, 15);
        try {
            $lock->block(5);
        } catch (LockTimeoutException $e) {
            return response()->json(['message' => translate('order_already_being_processed')]);
        }

        try {
            try {
                $orderId = DB::transaction(function () use ($cart, $amount, $paidAmount, $request, $userId, $orderType, $shippingAddress, $shippingCost, $shippingMethodId) {
                    $orderData = $this->orderService->getPOSOrderData(
                        cart: $cart,
                        amount: $amount,
                        paidAmount: $request['type'] === 'cash_on_delivery' ? 0 : ($request['type'] == 'cash' ? $paidAmount : $amount),
                        paymentType: $request['type'],
                        addedBy: 'admin',
                        userId: $userId,
                        orderType: $orderType,
                        shippingAddress: $shippingAddress,
                        shippingCost: $shippingCost,
                        shippingMethodId: $shippingMethodId
                    );
                    $createdOrder = $this->orderRepo->add(data: $orderData);
                    $orderId = $createdOrder->id;

                    foreach ($cart as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $product = $this->productRepo->getFirstWhere(params: ['id' => $item['id']], relations: ['clearanceSale' => function ($query) {
                            return $query->active();
                        }]);
                        if (!$product) {
                            continue;
                        }
                        $tax = $this->getTaxAmount($item['price'], $product['tax']);
                        $price = $product['tax_model'] == 'include' ? $item['price'] - $tax : $item['price'];

                        $digitalProductVariation = $this->digitalProductVariationRepo->getFirstWhere(params: ['product_id' => $item['id'], 'variant_key' => $item['variant']], relations: ['storage']);
                        if ($product['product_type'] == 'digital' && $digitalProductVariation) {
                            $price = $product['tax_model'] == 'include' ? $digitalProductVariation['price'] - $tax : $digitalProductVariation['price'];

                            if ($product['digital_product_type'] == 'ready_product') {
                                $getStoragePath = $this->storageRepo->getFirstWhere(params: [
                                    'data_id' => $digitalProductVariation['id'],
                                    "data_type" => "App\Models\DigitalProductVariation",
                                ]);
                                $product['digital_file_ready'] = $digitalProductVariation['file'];
                                $product['storage_path'] = $getStoragePath ? $getStoragePath['value'] : 'public';
                            }
                        } elseif ($product['digital_product_type'] == 'ready_product' && !empty($product['digital_file_ready'])) {
                            $product['storage_path'] = $product['digital_file_ready_storage_type'] ?? 'public';
                        }

                        $orderDetail = $this->orderDetailsService->getPOSOrderDetailsData(
                            orderId: $orderId, item: $item,
                            product: $product, price: $price, tax: $tax
                        );

                        // Re-fetch with a row lock immediately before mutating stock so concurrent
                        // requests for the same product serialize instead of racing (lost updates).
                        $lockedProduct = Product::query()->whereKey($product['id'])->lockForUpdate()->first();
                        if ($lockedProduct) {
                            $stockUpdateData = [];
                            if ($item['variant'] != null) {
                                $variation = json_decode($lockedProduct->variation, true) ?: [];
                                foreach ($variation as &$variant) {
                                    if ($item['variant'] == $variant['type']) {
                                        if ((float)($variant['qty'] ?? 0) < (float)$item['quantity']) {
                                            throw new PosInsufficientStockException($product['name'] ?? ('#' . $product['id']));
                                        }
                                        $variant['qty'] -= $item['quantity'];
                                    }
                                }
                                unset($variant);
                                $stockUpdateData['variation'] = json_encode($variation);
                            }
                            if ($lockedProduct->product_type == 'physical') {
                                if ((float)$lockedProduct->current_stock < (float)$item['quantity']) {
                                    throw new PosInsufficientStockException($product['name'] ?? ('#' . $product['id']));
                                }
                                $stockUpdateData['current_stock'] = $lockedProduct->current_stock - $item['quantity'];
                            }
                            if (!empty($stockUpdateData)) {
                                $this->productRepo->update(id: $product['id'], data: $stockUpdateData);
                            }
                        }
                        $this->orderDetailRepo->add(data: $orderDetail);
                    }

                    return $orderId;
                });
            } catch (PosInsufficientStockException $e) {
                $message = translate('insufficient_stock_for') . ' ' . $e->getMessage();
                ToastMagic::error($message);
                return response()->json(['insufficientStock' => true, 'message' => $message]);
            }

            if ($checkProductTypeDigital) {
                $order = $this->orderRepo->getFirstWhere(params: ['id' => $orderId], relations: ['details.productAllStatus']);
                $data = [
                    'userName' => $order->customer->f_name,
                    'userType' => 'customer',
                    'templateName' => 'digital-product-download',
                    'order' => $order,
                    'subject' => translate('download_Digital_Product'),
                    'title' => translate('Congratulations') . '!',
                    'emailId' => $order->customer['email'],
                ];
                event(new DigitalProductDownloadEvent(email: $order->customer['email'], data: $data));
            }
            session()->forget($cartId);
            session()->flash('last_order', $orderId);
            $this->cartService->getNewCartId();
            ToastMagic::success(translate('order_placed_successfully'));
            return response()->json();
        } finally {
            $lock->release();
        }
    }

    public function cancelOrder(Request $request): JsonResponse
    {
        session()->remove($request['cart_id']);
        $totalHoldOrders = $this->POSService->getTotalHoldOrders();
        $cartNames = $this->POSService->getCartNames();
        $cartItems = $this->getHoldOrderCalculationData(cartNames: $cartNames);
        return response()->json([
            'message' => $request['cart_id'] . ' ' . translate('order_is_cancel'),
            'status' => 'success',
            'view' => view('admin-views.pos.partials._view-hold-orders', compact('totalHoldOrders', 'cartItems'))->render(),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllHoldOrdersView(Request $request): JsonResponse
    {
        $totalHoldOrders = $this->POSService->getTotalHoldOrders();
        $cartNames = $this->POSService->getCartNames();
        $cartItems = $this->getHoldOrderCalculationData(cartNames: $cartNames);
        if (!empty($request['customer'])) {
            $searchValue = strtolower($request['customer']);
            $filteredItems = collect($cartItems)->filter(function ($item) use ($searchValue) {
                return str_contains(strtolower($item['customerName']), $searchValue) !== false;
            });
            $cartItems = $filteredItems->all();
        }
        return response()->json([
            'flag' => 'inactive',
            'totalHoldOrders' => $totalHoldOrders,
            'view' => view('admin-views.pos.partials._view-hold-orders', compact('totalHoldOrders', 'cartItems'))->render(),
        ]);
    }

    /**
     * @return array
     */
    protected function getCustomerDataFromSessionForPOS(): array
    {
        if (Str::contains(session(SessionKey::CURRENT_USER), 'walk-in-customer')) {
            $currentCustomer = 'Walk-In Customer';
            $currentCustomerData = $this->customerRepo->getFirstWhere(params: ['id' => '0']);
        } else {
            $userId = explode('-', session(SessionKey::CURRENT_USER))[2];
            $currentCustomerData = $this->customerRepo->getFirstWhere(params: ['id' => $userId]);
            $currentCustomer = $currentCustomerData['f_name'] . ' ' . $currentCustomerData['l_name'] . ' (' . $currentCustomerData['phone'] . ')';
        }
        return [
            'currentCustomer' => $currentCustomer,
            'currentCustomerData' => $currentCustomerData
        ];
    }


    /**
     * @param array $cartNames
     * @return array
     */
    protected function getHoldOrderCalculationData(array $cartNames): array
    {
        $cartData = [];
        foreach ($cartNames as $cartName) {
            $customerCartData = $this->getCustomerCartData(cartName: $cartName);
            $CartItemData = $this->calculateCartItemsData(cartName: $cartName, customerCartData: $customerCartData);
            $cartData[$cartName] = array_merge($customerCartData[$cartName], $CartItemData);
        }
        return $cartData;
    }

    /**
     * @param string $cartName
     * @return array
     */
    protected function getCustomerCartData(string $cartName): array
    {
        $customerCartData = [];
        if (Str::contains($cartName, 'walk-in-customer')) {
            $currentCustomerInfo = [
                'customerName' => 'Walk-In Customer',
                'customerPhone' => "",
            ];
            $customerId = 0;
        } else {
            $customerId = explode('-', $cartName)[2];
            $currentCustomerData = $this->customerRepo->getFirstWhere(params: ['id' => $customerId]);
            $currentCustomerInfo = $this->cartService->getCustomerInfo(currentCustomerData: $currentCustomerData, customerId: $customerId);

        }
        $customerCartData[$cartName] = [
            'customerName' => $currentCustomerInfo['customerName'],
            'customerPhone' => $currentCustomerInfo['customerPhone'],
            'customerId' => $customerId,
        ];
        return $customerCartData;
    }

    protected function calculateCartItemsData(string $cartName, array $customerCartData): array
    {
        $cartItemValue = [];
        $subTotalCalculation = [
            'countItem' => 0,
            'totalQuantity' => 0,
            'taxCalculate' => 0,
            'totalTaxShow' => 0,
            'totalTax' => 0,
            'totalIncludeTax' => 0,
            'subtotal' => 0,
            'discountOnProduct' => 0,
            'productSubtotal' => 0,
        ];
        if (session()->get($cartName)) {
            foreach (session()->get($cartName) as $cartItem) {
                if (is_array($cartItem)) {
                    $product = $this->productRepo->getFirstWhere(params: ['id' => $cartItem['id']], relations: ['clearanceSale' => function ($query) {
                        return $query->active();
                    }]);
                    $cartSubTotalCalculation = $this->cartService->getCartSubtotalCalculation(
                        product: $product,
                        cartItem: $cartItem,
                        calculation: $subTotalCalculation
                    );
                    if ($cartItem['customerId'] == $customerCartData[$cartName]['customerId']) {
                        $cartItem['productSubtotal'] = $cartSubTotalCalculation['productSubtotal'];
                        $subTotalCalculation['customerOnHold'] = $cartItem['customerOnHold'];
                        $cartItemValue[] = $cartItem;

                        $subTotalCalculation['countItem'] += $cartSubTotalCalculation['countItem'];
                        $subTotalCalculation['totalQuantity'] += $cartSubTotalCalculation['totalQuantity'];
                        $subTotalCalculation['taxCalculate'] += $cartSubTotalCalculation['taxCalculate'];
                        $subTotalCalculation['totalTaxShow'] += $cartSubTotalCalculation['totalTaxShow'];
                        $subTotalCalculation['totalTax'] += $cartSubTotalCalculation['totalTax'];
                        $subTotalCalculation['totalIncludeTax'] += $cartSubTotalCalculation['totalIncludeTax'];
                        $subTotalCalculation['productSubtotal'] += $cartSubTotalCalculation['productSubtotal'];
                        $subTotalCalculation['subtotal'] += $cartSubTotalCalculation['subtotal'];
                        $subTotalCalculation['discountOnProduct'] += $cartSubTotalCalculation['discountOnProduct'];
                    }
                }
            }
        }
        $totalCalculation = $this->cartService->getTotalCalculation(
            subTotalCalculation: $subTotalCalculation, cartName: $cartName
        );
        return [
            'countItem' => $subTotalCalculation['countItem'],
            'total' => $totalCalculation['total'],
            'subtotal' => $subTotalCalculation['subtotal'],
            'taxCalculate' => $subTotalCalculation['taxCalculate'],
            'totalTaxShow' => $subTotalCalculation['totalTaxShow'],
            'totalTax' => $subTotalCalculation['totalTax'],
            'discountOnProduct' => $subTotalCalculation['discountOnProduct'],
            'productSubtotal' => $subTotalCalculation['productSubtotal'],
            'cartItemValue' => $cartItemValue,
            'couponDiscount' => $totalCalculation['couponDiscount'],
            'extraDiscount' => $totalCalculation['extraDiscount'],
            'customerOnHold' => $subTotalCalculation['customerOnHold'] ?? false,
        ];
    }

    protected function getCartData(string $cartName): array
    {
        $customerCartData = $this->getCustomerCartData(cartName: $cartName);
        $cartItemData = $this->calculateCartItemsData(cartName: $cartName, customerCartData: $customerCartData);
        return array_merge($customerCartData[$cartName], $cartItemData);
    }

    public function getCustomerAddresses(string $customerId): JsonResponse
    {
        $addresses = ShippingAddress::where('customer_id', $customerId)
            ->where('is_guest', '!=', 1)
            ->get(['id', 'contact_person_name', 'phone', 'address', 'city', 'zip', 'country', 'address_type']);

        if ($addresses->isEmpty()) {
            $customer = $this->customerRepo->getFirstWhere(params: ['id' => $customerId]);
            if ($customer && $customer['street_address']) {
                $addresses = collect([[
                    'id' => 'profile',
                    'contact_person_name' => trim(($customer['f_name'] ?? '') . ' ' . ($customer['l_name'] ?? '')),
                    'phone' => $customer['phone'] ?? '',
                    'address' => $customer['street_address'] ?? '',
                    'city' => $customer['city'] ?? '',
                    'zip' => $customer['zip'] ?? '',
                    'country' => $customer['country'] ?? '',
                    'address_type' => 'home',
                ]]);
            }
        }

        return response()->json($addresses->values());
    }

    public function getShippingMethods(): JsonResponse
    {
        $methods = ShippingMethod::where(['creator_type' => 'admin', 'status' => 1])
            ->get(['id', 'title', 'cost', 'duration']);
        return response()->json($methods);
    }
}
