@if(count($bannerTypeMainBanner) > 0)
<section class="bg-transparent" style="padding: 0; margin: 0;">
    <div class="container-fluid px-0">
        <div class="row no-gutters m-0">
            <div class="col-12 p-0">
                <div class="owl-theme owl-carousel hero-slider" data-loop="{{ count($bannerTypeMainBanner) > 1 ? 1 : 0 }}">
                    @foreach($bannerTypeMainBanner as $key=>$banner)
                        <a href="{{$banner['url']}}" class="d-block" target="_blank">
                            <img class="w-100" style="object-fit: cover; height: auto; display: block;" alt=""
                                src="{{ getStorageImages(path: $banner->photo_full_url, type: 'banner') }}">
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endif
