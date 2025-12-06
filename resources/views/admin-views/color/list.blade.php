@extends('layouts.admin.app')

@section('title', translate('color_List'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/brand.png') }}" alt="">
                {{ translate('color_List') }}
                <span class="badge text-dark bg-body-secondary fw-semibold rounded-50">{{ $colors->total() }}</span>
            </h2>
        </div>
        <div class="row mt-20">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body d-flex flex-column gap-20">
                        <div class="d-flex justify-content-between align-items-center gap-20 flex-wrap">
                            <form action="{{ url()->current() }}" method="GET">
                                <div class="input-group flex-grow-1 max-w-280">
                                    <input id="datatableSearch_" type="search" name="searchValue" class="form-control"
                                        placeholder="{{ translate('search_by_color_name_or_code') }}"
                                        aria-label="{{ translate('search_by_color_name_or_code') }}"
                                        value="{{ request('searchValue') }}" required>
                                    <div class="input-group-append search-submit">
                                        <button type="submit">
                                            <i class="fi fi-rr-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                            <div class="d-flex gap-2">
                                <a type="button" class="btn btn-primary text-nowrap"
                                    href="{{ route('admin.color.add-new') }}">
                                    <i class="fi fi-rr-plus"></i>
                                    <span class="ps-2">{{ translate('add_new_color') }}</span>
                                </a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless align-middle">
                                <thead class="text-capitalize">
                                    <tr>
                                        <th>{{ translate('SL') }}</th>
                                        <th>{{ translate('color_Name') }}</th>
                                        <th>{{ translate('color_Code') }}</th>
                                        <th>{{ translate('color_Preview') }}</th>
                                        <th class="text-center">{{ translate('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($colors as $key => $color)
                                        <tr>
                                            <td>{{ $colors->firstItem() + $key }}</td>
                                            <td>
                                                <span class="d-inline-block text-truncate w-100">
                                                    {{ $color->name }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $color->code }}</span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div style="width: 40px; height: 40px; background-color: {{ $color->code }}; border: 1px solid #ddd; border-radius: 4px;"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-3">
                                                    <a class="btn btn-outline-info icon-btn"
                                                        title="{{ translate('edit') }}"
                                                        href="{{ route('admin.color.update', [$color->id]) }}">
                                                        <i class="fi fi-sr-pencil"></i>
                                                    </a>
                                                    <a class="btn btn-outline-danger icon-btn delete-color"
                                                        title="{{ translate('delete') }}"
                                                        data-id="{{ $color->id }}"
                                                        data-name="{{ $color->name }}">
                                                        <i class="fi fi-rr-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="table-responsive mt-4">
                            <div class="d-flex justify-content-lg-end">
                                {{ $colors->links() }}
                            </div>
                        </div>
                        @if (count($colors) == 0)
                            @include(
                                'layouts.admin.partials._empty-state',
                                ['text' => 'no_color_found'],
                                ['image' => 'default']
                            )
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <span id="route-admin-color-delete" data-url="{{ route('admin.color.delete') }}"></span>
@endsection

@push('script')
    <script>
        'use strict';
        
        $(document).on('click', '.delete-color', function() {
            let colorId = $(this).data('id');
            let colorName = $(this).data('name');
            let url = $('#route-admin-color-delete').data('url');
            
            Swal.fire({
                title: '{{ translate('are_you_sure') }}?',
                text: '{{ translate('you_want_to_delete') }} ' + colorName + '?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '{{ translate('yes_delete_it') }}!',
                cancelButtonText: '{{ translate('cancel') }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    
                    $.post({
                        url: url,
                        data: {
                            id: colorId
                        },
                        success: function(data) {
                            toastr.success(data.message);
                            location.reload();
                        },
                        error: function(data) {
                            toastr.error('{{ translate('failed_to_delete') }}');
                        }
                    });
                }
            });
        });
    </script>
@endpush
