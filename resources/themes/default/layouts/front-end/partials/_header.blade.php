@php($announcement=getWebConfig(name: 'announcement'))

@if (isset($announcement) && $announcement['status']==1)
    <div class="text-center position-relative px-4 py-1 d--none" id="announcement"
         style="background-color: {{ $announcement['color'] }};color:{{$announcement['text_color']}}">
        <span>{{ $announcement['announcement'] }} </span>
        <span class="__close-announcement web-announcement-slideUp">X</span>
    </div>
@endif

<header class="rtl __inline-10">
    <div class="topbar">
        <div class="container">
            <div>
                <div class="topbar-text dropdown d-md-none ms-auto">
                    <a class="topbar-link direction-ltr" href="tel: {{ $web_config['phone'] }}">
                        <i class="fa fa-phone"></i> {{ $web_config['phone'] }}
                    </a>
                </div>
                <div class="d-none d-md-block mr-2 text-nowrap">
                    <a class="topbar-link d-none d-md-inline-block direction-ltr" href="tel:{{ $web_config['phone'] }}">
                        <i class="fa fa-phone"></i> {{ $web_config['phone'] }}
                    </a>
                </div>
            </div>

            <div>
                @php($currency_model = getWebConfig(name: 'currency_model'))
                @if($currency_model=='multi_currency')
                    <div class="topbar-text dropdown disable-autohide mr-4">
                        <a class="topbar-link dropdown-toggle" href="#" data-toggle="dropdown">
                            <span>{{session('currency_code')}} {{session('currency_symbol')}}</span>
                        </a>
                        <ul class="text-align-direction dropdown-menu dropdown-menu-{{Session::get('direction') === "rtl" ? 'right' : 'left'}} min-width-160px">
                            @foreach (\App\Models\Currency::where('status', 1)->get() as $key => $currency)
                                <li class="dropdown-item cursor-pointer get-currency-change-function"
                                    data-code="{{$currency['code']}}">
                                    {{ $currency->name }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="topbar-text dropdown disable-autohide  __language-bar text-capitalize">
                    <a class="topbar-link dropdown-toggle" href="#" data-toggle="dropdown">
                        @foreach($web_config['language'] as $data)
                            @if($data['code'] == getDefaultLanguage())
                                <img class="mr-2" width="20"
                                     src="{{theme_asset(path: 'public/assets/front-end/img/flags/'.$data['code'].'.png')}}"
                                     alt="{{$data['name']}}">
                                {{$data['name']}}
                            @endif
                        @endforeach
                    </a>
                    <ul class="text-align-direction dropdown-menu dropdown-menu-{{Session::get('direction') === "rtl" ? 'right' : 'left'}}">
                        @foreach($web_config['language'] as $key =>$data)
                            @if($data['status']==1)
                                <li class="change-language" data-action="{{route('change-language')}}" data-language-code="{{$data['code']}}">
                                    <a class="dropdown-item pb-1" href="javascript:">
                                        <img class="mr-2"
                                             width="20"
                                             src="{{theme_asset(path: 'public/assets/front-end/img/flags/'.$data['code'].'.png')}}"
                                             alt="{{$data['name']}}"/>
                                        <span class="text-capitalize">{{$data['name']}}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="navbar-sticky bg-light mobile-head">
        <div class="navbar navbar-expand-md navbar-light">
            <div class="container ">
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <a class="navbar-brand d-none d-sm-block me-3 flex-shrink-0 __min-w-7rem"
                   href="{{route('home')}}">
                    <img class="__inline-11"
                         src="{{ getStorageImages(path: $web_config['web_logo'], type: 'logo') }}"
                         alt="{{$web_config['company_name']}}">
                </a>
                <a class="navbar-brand d-sm-none"
                   href="{{route('home')}}">
                    <img class="mobile-logo-img"
                         src="{{ getStorageImages(path: $web_config['mob_logo'], type: 'logo') }}"
                         alt="{{$web_config['company_name']}}"/>
                </a>

                <div class="input-group-overlay mx-lg-4 search-form-mobile text-align-direction">
                    <form action="{{route('products')}}" type="submit" class="search_form">
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-control appended-form-control search-bar-input" type="search"
                                   autocomplete="off" data-given-value=""
                                   placeholder="{{ translate("search_for_items")}}..."
                                   name="name" value="{{ request('name') }}">

                            <input type="hidden" name="global_search_input" value="1">

                            <button class="input-group-append-overlay search_button d-none d-md-block" type="submit">
                                <span class="input-group-text __text-20px">
                                    <i class="czi-search text-white"></i>
                                </span>
                            </button>

                            <span class="close-search-form-mobile fs-14 font-semibold text-muted d-md-none text-nowrap" type="submit">
                                {{ translate('cancel') }}
                            </span>
                        </div>

                        <input name="data_from" value="search" hidden>
                        <input name="page" value="1" hidden>
                        <diV class="card search-card mobile-search-card">
                            <div class="card-body">
                                <div class="search-result-box __h-400px overflow-x-hidden overflow-y-auto"></div>
                            </div>
                        </diV>
                    </form>
                </div>

                <div class="navbar-toolbar d-flex flex-shrink-0 align-items-center">
                    <a class="navbar-tool navbar-stuck-toggler" href="#">
                        <span class="navbar-tool-tooltip">{{ translate('expand_Menu') }}</span>
                        <div class="navbar-tool-icon-box">
                            <i class="navbar-tool-icon czi-menu open-icon"></i>
                            <i class="navbar-tool-icon czi-close close-icon"></i>
                        </div>
                    </a>
                    <div class="navbar-tool open-search-form-mobile d-lg-none {{Session::get('direction') === "rtl" ? 'mr-md-3' : 'ml-md-3'}}">
                        <a class="navbar-tool-icon-box bg-secondary" href="javascript:">
                            <i class="tio-search"></i>
                        </a>
                    </div>
                    <div class="navbar-tool dropdown d-none d-md-block {{Session::get('direction') === "rtl" ? 'mr-md-3' : 'ml-md-3'}}">
                        <a class="navbar-tool-icon-box bg-secondary dropdown-toggle" href="{{route('wishlists')}}">
                            <span class="navbar-tool-label">
                                <span class="countWishlist">
                                    {{session()->has('wish_list')?count(session('wish_list')):0}}
                                </span>
                           </span>
                            <i class="navbar-tool-icon czi-heart"></i>
                        </a>
                    </div>
                    @if(auth('customer')->check())
                        <div class="dropdown">
                            <a class="navbar-tool ml-3" type="button" data-toggle="dropdown" aria-haspopup="true"
                               aria-expanded="false">
                                <div class="navbar-tool-icon-box bg-secondary">
                                    <div class="navbar-tool-icon-box bg-secondary">
                                        <img class="img-profile rounded-circle __inline-14" alt=""
                                             src="{{ getStorageImages(path: auth('customer')->user()->image_full_url, type: 'avatar') }}">
                                    </div>
                                </div>
                                <div class="navbar-tool-text">
                                    <small>
                                        {{ translate('hello')}}, {{ Str::limit(auth('customer')->user()->f_name, 10) }}
                                    </small>
                                    {{ translate('dashboard')}}
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-{{Session::get('direction') === "rtl" ? 'left' : 'right'}}"
                                 aria-labelledby="dropdownMenuButton">
                                <a class="dropdown-item"
                                   href="{{route('account-oder')}}"> {{ translate('my_Order')}} </a>
                                <a class="dropdown-item"
                                   href="{{route('user-account')}}"> {{ translate('my_Profile')}}</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item"
                                   href="{{route('customer.auth.logout')}}">{{ translate('logout')}}</a>
                            </div>
                        </div>
                    @else
                        <div class="dropdown">
                            <a class="navbar-tool {{Session::get('direction') === "rtl" ? 'mr-md-3' : 'ml-md-3'}}"
                               type="button" data-toggle="dropdown" aria-haspopup="true" href="#" rel="nofollow"
                               aria-expanded="false">
                                <div class="navbar-tool-icon-box bg-secondary">
                                    <div class="navbar-tool-icon-box bg-secondary">
                                        <svg width="16" height="17" viewBox="0 0 16 17" fill="none"
                                             xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4.25 4.41675C4.25 6.48425 5.9325 8.16675 8 8.16675C10.0675 8.16675 11.75 6.48425 11.75 4.41675C11.75 2.34925 10.0675 0.666748 8 0.666748C5.9325 0.666748 4.25 2.34925 4.25 4.41675ZM14.6667 16.5001H15.5V15.6667C15.5 12.4509 12.8825 9.83341 9.66667 9.83341H6.33333C3.11667 9.83341 0.5 12.4509 0.5 15.6667V16.5001H14.6667Z"
                                                  fill="{{ $web_config['primary_color'] ?? '#1B7FED'}}"/>
                                        </svg>

                                    </div>
                                </div>
                            </a>
                            <div class="text-align-direction dropdown-menu __auth-dropdown dropdown-menu-{{Session::get('direction') === "rtl" ? 'left' : 'right'}}"
                                 aria-labelledby="dropdownMenuButton">
                                <a class="dropdown-item" href="{{route('customer.auth.login')}}">
                                    <i class="fa fa-sign-in mr-2"></i> {{ translate('sign_in')}}
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="{{route('customer.auth.sign-up')}}">
                                    <i class="fa fa-user-circle mr-2"></i>{{ translate('sign_up')}}
                                </a>
                            </div>
                        </div>
                    @endif
                    <div id="cart_items">
                        @include('layouts.front-end.partials._cart')
                    </div>
                </div>
            </div>
        </div>

        <div class="navbar navbar-expand-md navbar-stuck-menu">
            <div class="container px-10px">
                <div class="collapse navbar-collapse text-align-direction" id="navbarCollapse">
                    <div class="w-100 d-md-none text-align-direction">
                        <button class="navbar-toggler p-0" type="button" data-toggle="collapse"
                                data-target="#navbarCollapse">
                            <i class="tio-clear __text-26px"></i>
                        </button>
                    </div>

                    <ul class="navbar-nav d-block d-md-none">
                        <li class="nav-item dropdown {{request()->is('/')?'active':''}}">
                            <a class="nav-link" href="{{route('home')}}">{{ translate('home')}}</a>
                        </li>
                        @php($topCategoriesMobile = \App\Utils\CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting(dataLimit: 5))
                        @foreach($topCategoriesMobile as $topCategoryMobile)
                            <li class="nav-item">
                                <a class="nav-link" href="{{route('products',['category_id'=> $topCategoryMobile['id'],'data_from'=>'category','page'=>1])}}">
                                    {{ $topCategoryMobile['name'] }}
                                </a>
                            </li>
                        @endforeach
                        @if(getWebConfig(name: 'product_brand'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{route('brands')}}">{{ translate('brand') }}</a>
                            </li>
                        @endif
                    </ul>

                    @php($categories = \App\Utils\CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting(dataLimit: 11))

                    <ul class="navbar-nav mega-nav1 pr-md-2 pl-md-2 d-block d-xl-none">
                        <li class="nav-item dropdown d-md-none">
                            <a class="nav-link dropdown-toggle ps-0"
                               href="javascript:" data-toggle="dropdown">
                                <i class="czi-menu align-middle mt-n1 me-2"></i>
                                <span class="me-4">
                                    {{ translate('categories')}}
                                </span>
                            </a>
                            <ul class="dropdown-menu __dropdown-menu-2 text-align-direction">
                                @php($categoryIndex=0)
                                @foreach($categories as $category)
                                    @php($categoryIndex++)
                                    @if($categoryIndex < 10)
                                        <li class="dropdown">

                                            <a href="{{route('products',['category_id'=> $category['id'],'data_from'=>'category','page'=>1])}}" class="d-flex gap-10px align-items-center">
                                                <img class="aspect-1 rounded-circle" width="20" src="{{ getStorageImages(path: $category?->icon_full_url, type: 'category') }}" alt="{{ $category['name'] }}">
                                                <span>{{$category['name']}}</span>
                                            </a>
                                            @if ($category->childes->count() > 0)
                                                <a data-toggle='dropdown' class='__ml-50px'>
                                                    <i class="czi-arrow-{{Session::get('direction') === "rtl" ? 'left' : 'right'}} __inline-16"></i>
                                                </a>
                                            @endif

                                            @if($category->childes->count()>0)
                                                <ul class="dropdown-menu text-align-direction">
                                                    @foreach($category['childes'] as $subCategory)
                                                        <li class="dropdown">
                                                            <a href="{{route('products',['sub_category_id'=> $subCategory['id'],'data_from'=>'category','page'=>1])}}">
                                                                <span>{{$subCategory['name']}}</span>
                                                            </a>

                                                            @if($subCategory->childes->count()>0)
                                                                <a class="header-subcategories-links"
                                                                   data-toggle='dropdown'>
                                                                    <i class="czi-arrow-{{Session::get('direction') === "rtl" ? 'left' : 'right'}} __inline-16"></i>
                                                                </a>
                                                                <ul class="dropdown-menu">
                                                                    @foreach($subCategory['childes'] as $subSubCategory)
                                                                        <li>
                                                                            <a class="dropdown-item"
                                                                               href="{{route('products',['sub_sub_category_id'=> $subSubCategory['id'],'data_from'=>'category','page'=>1])}}">{{$subSubCategory['name']}}</a>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </li>
                                    @endif
                                @endforeach
                                <li class="__inline-17">
                                    <div>
                                        <a class="dropdown-item web-text-primary" href="{{ route('categories') }}">
                                            {{ translate('view_more') }}
                                        </a>
                                    </div>
                                </li>
                            </ul>
                        </li>
                    </ul>

                    <ul class="navbar-nav">
                        <li class="nav-item dropdown d-none d-md-block {{request()->is('/')?'active':''}}">
                            <a class="nav-link" href="{{route('home')}}">{{ translate('home')}}</a>
                        </li>

                        @php($topCategories = \App\Utils\CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting(dataLimit: 5))
                        @foreach($topCategories as $topCategory)
                            <li class="nav-item dropdown d-none d-md-block">
                                <a class="nav-link {{$topCategory->childes->count() > 0 ? 'dropdown-toggle' : ''}}" 
                                   href="{{route('products',['category_id'=> $topCategory['id'],'data_from'=>'category','page'=>1])}}" 
                                   {{$topCategory->childes->count() > 0 ? 'data-toggle=dropdown' : ''}}>
                                    {{ $topCategory['name'] }}
                                </a>
                                @if($topCategory->childes->count() > 0)
                                    <ul class="dropdown-menu text-align-direction">
                                        @foreach($topCategory->childes as $subCategory)
                                            <li>
                                                <a class="dropdown-item" href="{{route('products',['sub_category_id'=> $subCategory['id'],'data_from'=>'category','page'=>1])}}">
                                                    {{$subCategory['name']}}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach

                        @if(getWebConfig(name: 'product_brand'))
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#"
                                   data-toggle="dropdown">{{ translate('brand') }}</a>
                                <ul class="text-align-direction dropdown-menu __dropdown-menu-sizing dropdown-menu-{{Session::get('direction') === "rtl" ? 'right' : 'left'}} scroll-bar">
                                    @php($brandIndex=0)
                                    @foreach(\App\Utils\BrandManager::getActiveBrandWithCountingAndPriorityWiseSorting() as $brand)
                                        @php($brandIndex++)
                                        @if($brandIndex < 10)
                                            <li class="__inline-17">
                                                <div>
                                                    <a class="dropdown-item"
                                                       href="{{route('products',['brand_id'=> $brand['id'],'data_from'=>'brand','page'=>1])}}">
                                                        {{$brand['name']}}
                                                    </a>
                                                </div>
                                                <div class="align-baseline">
                                                    @if($brand['brand_products_count'] > 0 )
                                                        <span class="count-value px-2">( {{ $brand['brand_products_count'] }} )</span>
                                                    @endif
                                                </div>
                                            </li>
                                        @endif
                                    @endforeach
                                    <li class="__inline-17">
                                        <div>
                                            <a class="dropdown-item web-text-primary" href="{{route('brands')}}">
                                                {{ translate('view_more') }}
                                            </a>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        @if(
                            count(getFeaturedDealsProductList()) > 0 &&
                            !(($web_config['flash_deals'] || count($web_config['flash_deals_products']) > 0) || $web_config['discount_product'] > 0 || $web_config['clearance_sale_product_count'] > 0))
                            <li class="nav-item dropdown">
                                <a class="nav-link text-capitalize"
                                   href="{{ route('products',['offer_type'=>'featured_deal']) }}">
                                    {{ translate('featured_Deal')}}
                                </a>
                            </li>
                        @elseif(
                            ($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0) &&
                            !(count(getFeaturedDealsProductList()) > 0 || $web_config['discount_product'] > 0 || $web_config['clearance_sale_product_count'] > 0)
                            )
                            <li class="nav-item dropdown">
                                <a class="nav-link text-capitalize"
                                   href="{{ route('flash-deals', [$web_config['flash_deals']['id'] ?? 0]) }}">
                                    {{ translate('flash_deal')}}
                                </a>
                            </li>
                        @elseif(
                            ($web_config['discount_product'] > 0) &&
                            !(count(getFeaturedDealsProductList()) > 0 || ($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0) || $web_config['clearance_sale_product_count'] > 0)
                            )
                            <li class="nav-item dropdown">
                                <a class="nav-link text-capitalize"
                                   href="{{ route('products', ['offer_type' => 'discounted', 'page' => 1]) }}">
                                    {{ translate('discounted_products')}}
                                </a>
                            </li>
                        @elseif(
                            ($web_config['clearance_sale_product_count'] > 0) &&
                            !(count(getFeaturedDealsProductList()) > 0 || ($web_config['flash_deals'] || count($web_config['flash_deals_products']) > 0) || $web_config['discount_product'] > 0)
                            )
                            <li class="nav-item dropdown">
                                <a class="nav-link text-capitalize"
                                   href="{{ route('products', ['offer_type' => 'clearance_sale', 'page' => 1]) }}">
                                    {{ translate('clearance_Sale')}}
                                </a>
                            </li>
                        @elseif(count(getFeaturedDealsProductList()) > 0 || ($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0) || $web_config['discount_product'] > 0 || $web_config['clearance_sale_product_count'] > 0)
                            <li class="nav-item">
                                <div class="dropdown">
                                    <button class="btn dropdown-toggle text-white text-max-md-dark text-capitalize ps-2"
                                            type="button" id="dropdownMenuButton"
                                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        {{ translate('offers')}}
                                    </button>
                                    <div class="dropdown-menu __dropdown-menu-3 __min-w-165px text-align-direction"
                                         aria-labelledby="dropdownMenuButton">
                                        @if(count(getFeaturedDealsProductList()) > 0)
                                            <a class="dropdown-item text-nowrap text-capitalize" href="{{ route('products',['offer_type'=>'featured_deal']) }}">
                                                {{ translate('featured_Deal')}}
                                            </a>
                                        @endif

                                        @if($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0)
                                            @if(count(getFeaturedDealsProductList()) > 0)
                                                <div class="dropdown-divider"></div>
                                            @endif
                                            <a class="dropdown-item text-nowrap text-capitalize" href="{{ route('flash-deals',[ $web_config['flash_deals']['id'] ?? 0]) }}">
                                                {{ translate('flash_deal')}}
                                            </a>
                                        @endif

                                        @if($web_config['discount_product'] > 0)
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-nowrap text-capitalize" href="{{ route('products', ['offer_type' => 'discounted', 'page' => 1]) }}">
                                                {{ translate('discounted_products')}}
                                            </a>
                                        @endif

                                        @if($web_config['clearance_sale_product_count'] > 0)
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-nowrap" href="{{ route('products', ['offer_type' => 'clearance_sale', 'page' => 1]) }}">
                                                {{ translate('clearance_Sale')}}
                                            </a>
                                        @endif

                                    </div>
                                </div>
                            </li>
                        @endif

                        @if ($web_config['digital_product_setting'] && count($web_config['publishing_houses']) == 1)
                            <li class="nav-item dropdown d-none d-md-block {{request()->is('/')?'active':''}}">
                                <a class="nav-link" href="{{ route('products',['publishing_house_id' => 0, 'product_type' => 'digital', 'page'=>1]) }}">
                                    {{ translate('Publication_House') }}
                                </a>
                            </li>
                        @elseif ($web_config['digital_product_setting'] && count($web_config['publishing_houses']) > 1)
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                                    {{ translate('Publication_House') }}
                                </a>
                                <ul class="text-align-direction dropdown-menu __dropdown-menu-sizing dropdown-menu-{{Session::get('direction') === "rtl" ? 'right' : 'left'}} scroll-bar">
                                    @php($publishingHousesIndex=0)
                                    @foreach($web_config['publishing_houses'] as $publishingHouseItem)
                                        @if($publishingHousesIndex < 10 && $publishingHouseItem['name'] != 'Unknown')
                                            @php($publishingHousesIndex++)
                                            <li class="__inline-17">
                                                <div>
                                                    <a class="dropdown-item"
                                                       href="{{ route('products',['publishing_house_id'=> $publishingHouseItem['id'], 'product_type' => 'digital', 'page'=>1]) }}">
                                                        {{ $publishingHouseItem['name'] }}
                                                    </a>
                                                </div>
                                                <div class="align-baseline">
                                                    @if($publishingHouseItem['publishing_house_products_count'] > 0 )
                                                        <span class="count-value px-2">( {{ $publishingHouseItem['publishing_house_products_count'] }} )</span>
                                                    @endif
                                                </div>
                                            </li>
                                        @endif
                                    @endforeach
                                    <li class="__inline-17">
                                        <div>
                                            <a class="dropdown-item web-text-primary"
                                               href="{{ route('products', ['product_type' => 'digital', 'page' => 1]) }}">
                                                {{ translate('view_more') }}
                                            </a>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        @php($businessMode = getWebConfig(name: 'business_mode'))
                        @if ($businessMode == 'multi')
                            <li class="nav-item dropdown {{request()->is('/')?'active':''}}">
                                <a class="nav-link text-capitalize"
                                   href="{{route('vendors')}}">{{ translate('all_vendors')}}</a>
                            </li>
                        @endif

                        @if(auth('customer')->check())
                            <li class="nav-item d-md-none">
                                <a href="{{route('user-account')}}" class="nav-link text-capitalize">
                                    {{ translate('user_profile')}}
                                </a>
                            </li>
                            <li class="nav-item d-md-none">
                                <a href="{{route('wishlists')}}" class="nav-link">
                                    {{ translate('Wishlist')}}
                                </a>
                            </li>
                        @else
                            <li class="nav-item d-md-none">
                                <a class="dropdown-item pl-2" href="{{route('customer.auth.login')}}">
                                    <i class="fa fa-sign-in mr-2"></i> {{ translate('sign_in')}}
                                </a>
                                <div class="dropdown-divider"></div>
                            </li>
                            <li class="nav-item d-md-none">
                                <a class="dropdown-item pl-2" href="{{route('customer.auth.sign-up')}}">
                                    <i class="fa fa-user-circle mr-2"></i>{{ translate('sign_up')}}
                                </a>
                            </li>
                        @endif
                        @if ($businessMode == 'multi')
                            @if(getWebConfig(name: 'seller_registration'))
                                <li class="nav-item">
                                    <div class="dropdown">
                                        <button class="btn dropdown-toggle text-white text-max-md-dark text-capitalize ps-2"
                                                type="button" id="dropdownMenuButton"
                                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            {{ translate('vendor_zone')}}
                                        </button>
                                        <div class="dropdown-menu __dropdown-menu-3 __min-w-165px text-align-direction"
                                             aria-labelledby="dropdownMenuButton">
                                            <a class="dropdown-item text-nowrap text-capitalize" href="{{route('vendor.auth.registration.index')}}">
                                                {{ translate('become_a_vendor')}}
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-nowrap" href="{{route('vendor.auth.login')}}">
                                                {{ translate('vendor_login')}}
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            @endif
                        @endif
                    </ul>
                    @if(auth('customer')->check())
                        <div class="logout-btn mt-auto d-md-none">
                            <hr>
                            <a href="{{route('customer.auth.logout')}}" class="nav-link">
                                <strong class="text-base">{{ translate('logout')}}</strong>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="megamenu-wrap">
            <div class="container">
                <div class="category-menu-wrap">
                    <ul class="category-menu">
                        @foreach ($categories as $key=>$category)
                            <li>
                                <a href="{{route('products',['category_id'=> $category['id'],'data_from'=>'category','page'=>1])}}">
                                    <span class="d-flex gap-10px justify-content-start align-items-center">
                                        <img class="aspect-1 rounded-circle" width="20" src="{{ getStorageImages(path: $category?->icon_full_url, type: 'category') }}" alt="{{ $category['name'] }}">
                                        <span class="line--limit-2">{{ $category->name }}</span>
                                    </span>
                                </a>
                                @if ($category->childes->count() > 0)
                                    <div class="mega_menu z-2">
                                        @foreach ($category->childes as $sub_category)
                                            <div class="mega_menu_inner">
                                                <h6>
                                                    <a href="{{route('products',['sub_category_id'=> $sub_category['id'],'data_from'=>'category','page'=>1])}}">{{$sub_category->name}}</a>
                                                </h6>
                                                @if ($sub_category->childes->count() >0)
                                                    @foreach ($sub_category->childes as $sub_sub_category)
                                                        <div>
                                                            <a href="{{route('products',['sub_sub_category_id'=> $sub_sub_category['id'],'data_from'=>'category','page'=>1])}}">{{$sub_sub_category->name}}</a>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                        <li class="text-center">
                            <a href="{{route('categories')}}" class="text-primary font-weight-bold justify-content-center">
                                {{ translate('View_All') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

@push('script')
    <script>
        "use strict";

        $(".category-menu").find(".mega_menu").parents("li")
            .addClass("has-sub-item").find("> a")
            .append("<i class='czi-arrow-{{Session::get('direction') === "rtl" ? 'left' : 'right'}}'></i>");
    </script>
@endpush
