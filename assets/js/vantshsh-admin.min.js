/**
 * Vantigo Share Shop Cart for WooCommerce - Admin JavaScript
 */
jQuery(function ($) {
    'use strict';

    // Initialize Color Pickers
    if ($.fn.wpColorPicker) {
        $('.vantshsh-color-picker').wpColorPicker();
    }

    $('.vantshsh-reset-form').on('submit', function (e) {
        if (!window.confirm($(this).data('confirm'))) e.preventDefault();
    });

    // Tab Switching
    $('.vantshsh-nav-tab').on('click', function (e) {
        e.preventDefault();
        var tabId = $(this).data('tab');

        // Update tabs active class
        $('.vantshsh-nav-tab').removeClass('active');
        $(this).addClass('active');

        // Update panels active class
        $('.vantshsh-tab-panel').removeClass('active');
        $('#tab-' + tabId).addClass('active');

        // Update hidden field
        $('#vantshsh_active_tab').val(tabId);

        // Update URL hash without jump
        if (history.pushState) {
            var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=vantigo-share-shop-cart-for-woocommerce&tab=' + tabId;
            window.history.pushState({ path: newUrl }, '', newUrl);
        }
    });

    // Handle hash on page load
    var urlParams = new URLSearchParams(window.location.search);
    var currentTab = urlParams.get('tab');
    if (currentTab && $('#tab-' + currentTab).length) {
        $('.vantshsh-nav-tab[data-tab="' + currentTab + '"]').trigger('click');
    }
});
