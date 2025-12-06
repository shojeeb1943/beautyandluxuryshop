@extends('layouts.admin.app')

@section('title', translate('add_New_Color'))

@section('content')
    <div class="content container-fluid">

        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/new/back-end/img/brand.png') }}" alt="">
                {{ translate('add_New_Color') }}
            </h2>
        </div>

        <div class="row g-3">
            <div class="col-md-12">
                <div class="card mb-3">
                    <div class="card-body text-start">
                        <form action="{{ route('admin.color.add-new') }}" method="post" class="color-setup-form">
                            @csrf
                            
                            <div class="row gy-4 mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="colorName">
                                            {{ translate('color_Name') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="name" id="colorName" class="form-control"
                                               placeholder="{{ translate('ex') }} : {{ translate('Red') }}" 
                                               value="{{ old('name') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="colorCode">
                                            {{ translate('color_Code') }}
                                            <span class="text-danger">*</span>
                                            <small class="text-muted">({{ translate('hex_format') }})</small>
                                        </label>
                                        <div class="input-group">
                                            <input type="text" name="code" id="colorCode" class="form-control"
                                                   placeholder="#FF0000" 
                                                   value="{{ old('code', '#000000') }}" 
                                                   pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
                                                   required>
                                            <input type="color" id="colorPicker" class="form-control form-control-color" 
                                                   value="{{ old('code', '#000000') }}" 
                                                   title="{{ translate('choose_your_color') }}">
                                        </div>
                                        <small class="text-muted">{{ translate('example') }}: #FF0000, #00FF00, #0000FF</small>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-4 shadow-none">
                                <div class="card-body">
                                    <div class="d-flex flex-column gap-20">
                                        <div class="text-center">
                                            <label class="form-label fw-semibold mb-1">
                                                {{ translate('color_Preview') }}
                                            </label>
                                        </div>
                                        <div class="d-flex justify-content-center align-items-center">
                                            <div id="colorPreview" 
                                                 style="width: 100px; height: 100px; background-color: {{ old('code', '#000000') }}; border: 2px solid #ddd; border-radius: 8px;">
                                            </div>
                                        </div>
                                        <p class="fs-10 mb-0 text-center text-muted">
                                            {{ translate('preview_of_selected_color') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-3 justify-content-end">
                                <a href="{{ route('admin.color.list') }}" class="btn btn-secondary px-4">
                                    {{ translate('cancel') }}
                                </a>
                                <button type="submit" class="btn btn-primary px-4">
                                    {{ translate('submit') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        'use strict';
        
        // Sync color picker with text input
        $('#colorPicker').on('input', function() {
            let color = $(this).val().toUpperCase();
            $('#colorCode').val(color);
            $('#colorPreview').css('background-color', color);
        });
        
        // Sync text input with color picker
        $('#colorCode').on('input', function() {
            let color = $(this).val();
            if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(color)) {
                $('#colorPicker').val(color);
                $('#colorPreview').css('background-color', color);
            }
        });
        
        // Initialize preview on page load
        $(document).ready(function() {
            let initialColor = $('#colorCode').val();
            $('#colorPreview').css('background-color', initialColor);
        });
    </script>
@endpush
