@if(count($combinations) > 0)
    <div class="table-responsive">
        <table class="table physical_product_show table-borderless">
            <thead class="thead-light thead-50 text-capitalize">
            <tr>
                <th class="text-center">
                    <label for="" class="control-label">
                        {{ translate('SL') }}
                    </label>
                </th>
                <th class="text-center">
                    <label for="" class="control-label">
                        {{ translate('Sort_Order') }}
                    </label>
                </th>
                <th class="text-center">
                    <label for="" class="control-label">
                        {{ translate('attribute_Variation') }}
                    </label>
                </th>
                <th class="text-center">
                    <label for="" class="control-label">
                        {{ translate('variation_Wise_Price') }}
                        ({{ getCurrencySymbol() }})
                    </label>
                </th>
                <th class="text-center">
                    <label for="" class="control-label">
                        {{ translate('Discount_Type') }}
                    </label>
                </th>
                <th class="text-center">
                    <label for="" class="control-label">
                        {{ translate('Discount') }}
                    </label>
                </th>
                <th class="text-center">
                    <label for="" class="control-label">
                        {{ translate('SKU') }}
                    </label>
                </th>
                <th class="text-center">
                    <label for="" class="control-label">
                        {{ translate('Variation_Wise_Stock') }}
                    </label>
                </th>
            </tr>
            </thead>
            <tbody>

            @php
                $serial = 1;
            @endphp

            @foreach ($combinations as $key => $combination)
                @php
                    $fieldName = str_replace([' ', '.'], '_', $combination['type']);
                    $discountType = $combination['discount_type'] ?? 'flat';
                @endphp
                <tr>
                    <td class="text-center">
                        {{ $serial++ }}
                    </td>
                    <td>
                        <input type="number" name="sort_order_{{ $fieldName }}"
                               value="{{ $combination['sort_order'] ?? $serial }}" min="1"
                               step="1"
                               class="form-control w-max-content text-center" style="width: 70px;" placeholder="{{ $serial }}">
                    </td>
                    <td>
                        <label for="" class="control-label">{{ $combination['type'] }}</label>
                        <input value="{{ $combination['type'] }}" name="type[]" class="d-none">
                    </td>
                    <td>
                        <input type="number" name="price_{{ $fieldName }}"
                               value="{{ usdToDefaultCurrency(amount: $combination['price']) }}" min="0"
                               step="0.01"
                               class="form-control w-max-content variation-price-input"
                               data-field="{{ $fieldName }}"
                               required placeholder="{{ translate('ex').': 100'}}">
                    </td>
                    <td>
                        <select name="discount_type_{{ $fieldName }}"
                                class="form-control w-max-content variation-discount-type"
                                data-field="{{ $fieldName }}"
                                style="min-width: 90px;">
                            <option value="flat" {{ $discountType == 'flat' ? 'selected' : '' }}>{{ translate('Flat') }}</option>
                            <option value="percent" {{ $discountType == 'percent' ? 'selected' : '' }}>{{ translate('Percent') }}</option>
                        </select>
                    </td>
                    <td>
                        <div class="input-group" style="min-width: 120px;">
                            <input type="number" name="discount_{{ $fieldName }}"
                                   value="{{ $discountType == 'flat' ? usdToDefaultCurrency(amount: ($combination['discount'] ?? 0)) : ($combination['discount'] ?? 0) }}" min="0"
                                   step="0.01"
                                   class="form-control variation-discount-input"
                                   data-field="{{ $fieldName }}"
                                   placeholder="{{ translate('ex').': 10'}}">
                            <div class="input-group-append">
                                <span class="input-group-text discount-symbol-{{ $fieldName }}">
                                    {{ $discountType == 'percent' ? '%' : getCurrencySymbol() }}
                                </span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <input type="text" name="sku_{{ $fieldName }}" value="{{ $combination['sku'] }}"
                               class="form-control w-max-content store-keeping-unit" required>
                    </td>
                    <td>
                        <input type="number" name="qty_{{ $fieldName }}"
                               value="{{ $combination['qty'] }}" min="0" max="100000" step="1"
                               class="form-control w-max-content" placeholder="{{ translate('ex') }}: {{ translate('5') }}"
                               required>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <script>
        // Handle discount type change - update symbol and validate
        $(document).on('change', '.variation-discount-type', function() {
            var fieldName = $(this).data('field');
            var discountType = $(this).val();
            var symbol = discountType === 'percent' ? '%' : '{{ getCurrencySymbol() }}';
            $('.discount-symbol-' + fieldName).text(symbol);

            // Validate percent discount doesn't exceed 100
            var discountInput = $('input[name="discount_' + fieldName + '"]');
            if (discountType === 'percent' && parseFloat(discountInput.val()) > 100) {
                discountInput.val(100);
            }
        });

        // Validate percent discount on input
        $(document).on('input', '.variation-discount-input', function() {
            var fieldName = $(this).data('field');
            var discountType = $('select[name="discount_type_' + fieldName + '"]').val();
            if (discountType === 'percent' && parseFloat($(this).val()) > 100) {
                $(this).val(100);
            }
        });
    </script>
@endif
