"use strict";

$(document).ready(function () {
    const $stickyElement = $(".bottom-sticky");
    const $offsetElement = $(".product-details-shipping-details");

    $(window).on("scroll", function () {
        const elementOffset = $offsetElement?.offset()?.top;
        const scrollTop = $(window).scrollTop();

        if (scrollTop >= elementOffset) {
            $stickyElement.addClass("stick");
            $(".floating-btn-grp").removeClass("style-2");
        } else {
            $stickyElement.removeClass("stick");
            $(".floating-btn-grp").addClass("style-2");
        }
    });
});

$(document).ready(function () {
    // Constants
    const DESKTOP_BREAKPOINT = 767;
    const ANIMATION_DELAY = 150;

    // Cache selectors
    const $window = $(window);
    const $stickyTop = $('.product-details-sticky-top');
    const $stickySection = $('.product-details-sticky');

    function bindStickyHover() {
        if ($stickySection.hasClass('multi-variation-product')) {
            $stickySection.hover(
                function () {
                    $stickyTop.stop(true, true).delay(ANIMATION_DELAY).slideDown();
                },
                function () {
                    $stickyTop.stop(true, true).delay(ANIMATION_DELAY).slideUp();
                }
            );
        }
    }

    function unbindStickyHover() {
        $stickySection.off('mouseenter mouseleave');
        $stickyTop.stop(true, true).hide();
    }

    function handleBreakpoint() {
        const windowWidth = $window.width();

        if (windowWidth > DESKTOP_BREAKPOINT) {
            bindStickyHover();
        } else {
            unbindStickyHover();
        }
    }

    let resizeTimeout;
    $window.on('resize', function () {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(handleBreakpoint, 100);
    });

    handleBreakpoint();
});


// Select the element
const targetElement = document.querySelector('.product-add-and-buy-section-parent');

// Define the action to take when the element is in the viewport
function handleIntersect(entries) {
    let getHeight = $('.product-details-sticky-bottom').height();
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            $('.product-details-sticky').removeClass('active');
            $('.floating-btn-grp').removeClass('has-product-details-sticky');
            $('body').css('padding-bottom', "0px");
        } else {
            $('.product-details-sticky').addClass('active');
            $('.floating-btn-grp').addClass('has-product-details-sticky');
            $('body').css('padding-bottom', `calc(${getHeight}px + 2rem)`);
        }
    });
}

// Create an intersection observer
const observer = new IntersectionObserver(handleIntersect, {
    root: null, // Use the viewport as the root
    threshold: 0.1 // Trigger when 10% of the element is visible
});

// Start observing the target element
if (targetElement) {
    observer.observe(targetElement);
}

cartQuantityInitialize();
getVariantPrice(".add-to-cart-details-form");
getVariantPrice(".add-to-cart-sticky-form");

$(".view_more_button").on("click", function () {
    loadReviewOnDetailsPage();
});

let loadReviewCount = 1;

function loadReviewOnDetailsPage() {
    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
        },
    });
    $.ajax({
        type: "post",
        url: $("#route-review-list-product").data("url"),
        data: {
            product_id: $("#products-details-page-data").data("id"),
            offset: loadReviewCount,
        },
        success: function (data) {
            $("#product-review-list").append(data.productReview);
            if (data.checkReviews == 0) {
                $(".view_more_button").removeClass("d-none").addClass("d-none");
            } else {
                $(".view_more_button").addClass("d-none").removeClass("d-none");
            }

            $(".show-instant-image").on("click", function () {
                let link = $(this).data("link");
                showInstantImage(link);
            });
        },
    });
    loadReviewCount++;
}

$("#chat-form").on("submit", function (e) {
    e.preventDefault();

    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
        },
    });

    $.ajax({
        type: "post",
        url: $("#route-messages-store").data("url"),
        data: $("#chat-form").serialize(),
        success: function (respons) {
            toastr.success($("#message-send-successfully").data("text"), {
                CloseButton: true,
                ProgressBar: true,
            });
            $("#chat-form").trigger("reset");
        },
    });
});

function renderFocusPreviewImageByColor() {
    $(".focus-preview-image-by-color").on("click", function () {
        let id = $(this).data("colorid");
        $(`.color-variants-${id}`).click();
    });
}
renderFocusPreviewImageByColor();

// Color Variant Dropdown Sync Behavior
$(document).ready(function() {
    const dropdown = $('#colorVariantDropdown');
    const trigger = $('#colorDropdownTrigger');
    const list = $('#colorDropdownList');
    const selectedDot = $('#selectedColorDot');
    const selectedText = $('#selectedColorText');
    
    // Toggle dropdown open/close
    trigger.on('click', function(e) {
        e.stopPropagation();
        dropdown.toggleClass('open');
    });
    
    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!dropdown.is(e.target) && dropdown.has(e.target).length === 0) {
            dropdown.removeClass('open');
        }
    });
    
    // Dropdown option click -> update swatch
    $('.color-dropdown-option').on('click', function() {
        const colorId = $(this).data('color-id');
        const colorValue = $(this).data('color');
        const colorName = $(this).data('color-name');
        
        // Update dropdown UI
        selectedDot.css('background', colorValue);
        selectedText.text(colorName);
        
        // Update selected state in dropdown list
        $('.color-dropdown-option').removeClass('selected');
        $(this).addClass('selected');
        
        // Trigger the label click (not just radio change) to fire image update logic
        $('label[for="' + colorId + '"]').trigger('click');
        
        // Close dropdown
        dropdown.removeClass('open');
    });
    
    // Swatch click -> update dropdown
    $('.checkbox-color input[type="radio"]').on('change', function() {
        if ($(this).is(':checked')) {
            const colorValue = $(this).val();
            const colorName = $(this).next('label').data('title');
            
            // Update dropdown UI
            selectedDot.css('background', colorValue);
            selectedText.text(colorName);
            
            // Update selected state in dropdown list
            $('.color-dropdown-option').removeClass('selected');
            $('.color-dropdown-option[data-color="' + colorValue + '"]').addClass('selected');
        }
    });
});

// Sync main product color selection to mobile sticky bottom panel
$(document).ready(function() {
    // Single source of truth: listen to ALL color radio changes
    $('input[type="radio"][name="color"]').on('change', function() {
        if ($(this).is(':checked')) {
            const selectedColor = $(this).val();
            const colorName = $(this).next('label').data('title');
            const $changedRadio = $(this);
            
            // Sync all other color radios with same value (main + sticky panel)
            $('input[type="radio"][name="color"]').not(this).each(function() {
                if ($(this).val() === selectedColor) {
                    // Update checked state without triggering change event (prevent loop)
                    $(this).prop('checked', true);
                }
            });
            
            // Update sticky panel color name display
            if (colorName) {
                $('.product-details-sticky-color-name').text('(' + colorName + ')');
            }
            
            // Trigger image update by clicking the corresponding label (if exists)
            // This ensures image preview updates when sticky panel color is clicked
            const colorKey = selectedColor.replace('#', '');
            const $mainLabel = $('label[data-key="' + colorKey + '"]').not('.focus-preview-image-by-color').first();
            if ($mainLabel.length && !$changedRadio.closest('.product-details-sticky').length) {
                // Only trigger if change came from main section (avoid double trigger)
            } else if ($changedRadio.closest('.product-details-sticky').length) {
                // Change came from sticky panel - trigger main label click for image update
                const $mainColorLabel = $('.add-to-cart-details-form').find('label[data-key="' + colorKey + '"]').first();
                if ($mainColorLabel.length) {
                    $mainColorLabel.trigger('click');
                }
            }
            
            // Trigger price update for both forms
            getVariantPrice(".add-to-cart-details-form");
            getVariantPrice(".add-to-cart-sticky-form");
        }
    });
});
