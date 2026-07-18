<form action="{{route('admin.pos.place-order') }}" method="post" id='order-place'>
    @csrf
    @include('admin-views.pos.partials._cart-content', compact('cartId', 'cartItems'))
</form>

@push('script_2')
<script>
    'use strict';
    $('#type_ext_dis').on('change', function (){
        let type = $('#type_ext_dis').val();
        if(type === 'amount'){
            $('#dis_amount').attr('placeholder', 'Ex: 500');
        }else if(type === 'percent'){
            $('#dis_amount').attr('placeholder', 'Ex: 10%');
        }
    });
    $(function () {
        $('[data-bs-toggle="tooltip"]').tooltip()
    })
</script>
@endpush
