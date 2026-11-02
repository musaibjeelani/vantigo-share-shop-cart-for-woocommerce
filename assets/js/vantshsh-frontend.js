/**
 * Vantigo Share Shop Cart for WooCommerce - Frontend Scripts
 * Complete support for Classic Cart, Blocks, Paid Themes, Modal, Social & QR Code
 */
(function ($) {
    'use strict';

    var cfg = window.vantshsh_data || {};
    var i18n = (cfg && cfg.i18n) || {};
    var qrCodeInstance = null;

    /**
     * Detect iOS devices.
     */
    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    /**
     * Select text inside input with cross-browser compatibility.
     */
    function selectField(el) {
        if (!el) return;

        if (isIOS()) {
            var wasReadOnly = el.readOnly;
            var wasEditable = el.contentEditable;

            el.contentEditable = 'true';
            el.readOnly = false;

            var range = document.createRange();
            range.selectNodeContents(el);

            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);

            el.setSelectionRange(0, 999999);

            el.contentEditable = wasEditable;
            el.readOnly = wasReadOnly;
            return;
        }

        try {
            el.focus({ preventScroll: true });
        } catch (e) {
            el.focus();
        }

        el.select();

        try {
            el.setSelectionRange(0, el.value.length);
        } catch (e) {}
    }

    /**
     * Fallback execCommand copy method.
     */
    function legacyCopy(text, field) {
        var el = field;
        var temporary = false;
        var succeeded = false;

        if (!el || el.value !== text) {
            el = document.createElement('textarea');
            el.value = text;
            el.setAttribute('readonly', '');
            el.style.position = 'fixed';
            el.style.top = '0';
            el.style.left = '0';
            el.style.width = '1px';
            el.style.height = '1px';
            el.style.padding = '0';
            el.style.border = '0';
            el.style.margin = '0';
            el.style.fontSize = '16px';
            document.body.appendChild(el);
            temporary = true;
        }

        try {
            selectField(el);
            succeeded = document.execCommand('copy');
        } catch (e) {
            succeeded = false;
        }

        if (temporary && el.parentNode) {
            document.body.removeChild(el);
        }

        return succeeded;
    }

    /**
     * Copy text to clipboard synchronously.
     */
    function copyToClipboard(text, field) {
        if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
            return navigator.clipboard.writeText(text).catch(function () {
                return legacyCopy(text, field) ? Promise.resolve() : Promise.reject();
            });
        }
        return legacyCopy(text, field) ? Promise.resolve() : Promise.reject();
    }

    /**
     * Get active share URL.
     */
    function getShareUrl() {
        var modalInput = $('#vantshsh-modal-link-input').val();
        if (modalInput) {
            return modalInput;
        }
        var firstWrapper = $('.vantshsh-share-cart-wrapper, .scurl-share-cart').first();
        return firstWrapper.attr('data-share-url') || '';
    }

    /**
     * Render QR code in modal.
     */
    function generateModalQR(url) {
        var qrWrap = document.getElementById('vantshsh-qr-canvas-wrap');
        if (!qrWrap || typeof QRCode === 'undefined' || !url) {
            return;
        }

        try {
            qrWrap.innerHTML = '';

            // Use MutationObserver to detect when the library adds/modifies the <img>
            // so we can immediately remove the <canvas> — no timing guesswork needed.
            var observer = new MutationObserver(function (mutations) {
                var canvas = qrWrap.querySelector('canvas');
                var img    = qrWrap.querySelector('img');

                if (img && img.src && img.src.length > 10 && canvas) {
                    // Library has set the img src — remove canvas, keep only img
                    canvas.parentNode && canvas.parentNode.removeChild(canvas);
                    img.style.display = 'block';
                    img.style.width   = '180px';
                    img.style.height  = '180px';
                    observer.disconnect();
                }
            });

            observer.observe(qrWrap, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['src', 'style']
            });

            qrCodeInstance = new QRCode(qrWrap, {
                text: url,
                width: 180,
                height: 180,
                colorDark: '#1e293b',
                colorLight: '#ffffff',
                correctLevel: (QRCode.CorrectLevel && QRCode.CorrectLevel.M !== undefined) ? QRCode.CorrectLevel.M : 0
            });

            // Fallback: if after 1 second observer hasn't fired (canvas-only env),
            // convert canvas directly and remove it.
            setTimeout(function () {
                observer.disconnect();
                var canvas = qrWrap.querySelector('canvas');
                var img    = qrWrap.querySelector('img');

                if (canvas && (!img || !img.src || img.src.length < 10)) {
                    // Build our own img from canvas
                    var dataUrl = canvas.toDataURL('image/png');
                    var newImg  = document.createElement('img');
                    newImg.src    = dataUrl;
                    newImg.width  = 180;
                    newImg.height = 180;
                    newImg.style.display = 'block';
                    newImg.alt    = 'QR Code';
                    canvas.parentNode.replaceChild(newImg, canvas);
                    // remove the hidden img the library created
                    if (img) img.parentNode && img.parentNode.removeChild(img);
                } else if (canvas && img && img.src) {
                    // Observer didn't fire but img is ready — remove canvas now
                    canvas.parentNode && canvas.parentNode.removeChild(canvas);
                    img.style.display = 'block';
                }
            }, 1000);

        } catch (err) {
            console.error('VANTSHSH QR Generation Error:', err);
        }
    }


    /**
     * Open Modal.
     */
    function openModal(shareUrl) {
        var $modal = $('#vantshsh-modal');
        if (!$modal.length) return;

        if (shareUrl) {
            $modal.find('#vantshsh-modal-link-input').val(shareUrl);
        }

        var activeUrl = getShareUrl();

        $modal.addClass('vantshsh-modal-open').attr('aria-hidden', 'false');
        $('body').addClass('vantshsh-modal-locked');

        // Reset feedback text
        $modal.find('.vantshsh-modal-feedback').text('').removeClass('vantshsh-success vantshsh-error');

        // Pre-generate QR code so it appears instantly when accordion is toggled
        generateModalQR(activeUrl);
    }

    /**
     * Close Modal.
     */
    function closeModal() {
        var $modal = $('#vantshsh-modal');
        $modal.removeClass('vantshsh-modal-open').attr('aria-hidden', 'true');
        $('body').removeClass('vantshsh-modal-locked');
    }

    /**
     * Handle Copy inside Modal.
     */
    function handleModalCopy() {
        var field = document.getElementById('vantshsh-modal-link-input');
        var url = field ? field.value : getShareUrl();
        var $feedback = $('.vantshsh-modal-feedback');

        copyToClipboard(url, field).then(function () {
            $feedback.text(i18n.copied || 'Link copied to clipboard!').removeClass('vantshsh-error').addClass('vantshsh-success');
            var $btnText = $('.vantshsh-modal-copy-btn .vantshsh-copy-text');
            var originalText = $btnText.text();
            $btnText.text('Copied!');
            setTimeout(function () {
                $btnText.text(originalText);
            }, 2000);
        }).catch(function () {
            $feedback.text(i18n.copy_failed || 'Failed to copy. Please select manually.').removeClass('vantshsh-success').addClass('vantshsh-error');
            selectField(field);
        });
    }

    /**
     * Handle Copy inside Inline Layout.
     */
    function handleInlineCopy($wrapper) {
        var url = $wrapper.attr('data-share-url');
        if (!url) return;

        var $output = $wrapper.find('.vantshsh-share-output, .scurl-share-output');
        $output.prop('hidden', false);

        var field = $wrapper.find('.vantshsh-share-input, .scurl-share-input')[0];
        var $feedback = $wrapper.find('.vantshsh-share-feedback, .scurl-share-feedback');

        copyToClipboard(url, field).then(function () {
            $feedback.text(i18n.copied || 'Link copied to clipboard!').css('color', '#16a34a');
        }).catch(function () {
            $feedback.text(i18n.copy_failed || 'Failed to copy. Please select manually.').css('color', '#dc2626');
            selectField(field);
        });
    }

    /**
     * Social sharing dispatcher.
     */
    function handleSocialShare(channel) {
        var url = getShareUrl();
        if (!url) return;

        var title = cfg.site_name || document.title;
        var encodedUrl = encodeURIComponent(url);
        var shareLink = '';

        switch (channel) {
            case 'whatsapp':
                var template = cfg.whatsapp_template || 'Check out what I selected in my shopping cart! Click to view: {cart_url}';
                var msg = template.replace('{cart_url}', url);
                shareLink = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(msg);
                break;

            case 'facebook':
                shareLink = 'https://www.facebook.com/sharer/sharer.php?u=' + encodedUrl;
                break;

            case 'twitter':
                shareLink = 'https://twitter.com/intent/tweet?url=' + encodedUrl + '&text=' + encodeURIComponent(title);
                break;

            case 'telegram':
                shareLink = 'https://t.me/share/url?url=' + encodedUrl + '&text=' + encodeURIComponent(title);
                break;

            case 'email':
                var subject = encodeURIComponent('Shopping Cart from ' + title);
                var body = encodeURIComponent('Hi, check out my shopping cart here:\n\n' + url);
                shareLink = 'mailto:?subject=' + subject + '&body=' + body;
                break;

            case 'sms':
                shareLink = 'sms:?&body=' + encodeURIComponent('Check out my cart: ' + url);
                break;

            case 'native':
                if (navigator.share) {
                    navigator.share({
                        title: title,
                        url: url
                    }).catch(function () {});
                    return;
                }
                break;
        }

        if (shareLink) {
            window.open(shareLink, '_blank', 'noopener,noreferrer,width=600,height=500');
        }
    }

    /**
     * Download QR code as image.
     */
    function downloadQR() {
        var $wrap = $('#vantshsh-qr-canvas-wrap');
        var dataUrl = '';
        var $canvas = $wrap.find('canvas');
        var $img = $wrap.find('img');
        var $svg = $wrap.find('svg');

        if ($canvas.length && $canvas[0].toDataURL) {
            dataUrl = $canvas[0].toDataURL('image/png');
        } else if ($img.length && $img.attr('src')) {
            dataUrl = $img.attr('src');
        } else if ($svg.length) {
            var svgData = new XMLSerializer().serializeToString($svg[0]);
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            var img = new Image();

            canvas.width = 300;
            canvas.height = 300;

            img.onload = function () {
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 20, 20, 260, 260);

                var a = document.createElement('a');
                a.download = 'cart-qr-code.png';
                a.href = canvas.toDataURL('image/png');
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            };

            img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
            return;
        }

        if (dataUrl) {
            var a = document.createElement('a');
            a.download = 'cart-qr-code.png';
            a.href = dataUrl;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    }

    /**
     * Background refresh of share URLs when cart contents change.
     */
    function refreshShareUrls() {
        var $widgets = $('.vantshsh-share-cart-wrapper, .scurl-share-cart, .vantshsh-floating-wrapper');
        if (!$widgets.length) return;
        var $controls = $widgets.find('button').add($('#vantshsh-modal button').not('.vantshsh-modal-close'));
        $controls.prop('disabled', true);

        return $.ajax({
            url: cfg.ajax_url,
            type: 'POST',
            data: {
                action: 'vantshsh_generate_share_link',
                nonce: cfg.nonce
            }
        }).done(function (res) {
            if (res && res.success && res.data.url) {
                $controls.prop('disabled', false);
                var newUrl = res.data.url;
                $('.vantshsh-share-cart-wrapper, .scurl-share-cart, .vantshsh-floating-wrapper')
                    .attr('data-share-url', newUrl)
                    .find('.vantshsh-share-input, .scurl-share-input').val(newUrl);

                $('#vantshsh-modal-link-input').val(newUrl);

                // Update summary if present
                if (res.data.summary) {
                    $('.vantshsh-overview-count').text(res.data.summary.count + ' item(s) in cart');
                    $('.vantshsh-overview-total').html(res.data.summary.subtotal);
                }

                if (qrCodeInstance) {
                    generateModalQR(newUrl);
                }
            } else {
                $('.vantshsh-modal-feedback, .vantshsh-share-feedback').text(i18n.refresh_err || 'Could not refresh link. Please reload page.');
            }
        }).fail(function () {
            $('.vantshsh-modal-feedback, .vantshsh-share-feedback').text(i18n.refresh_err || 'Could not refresh link. Please reload page.');
        });
    }

    /**
     * Store API Block Cart watcher.
     */
    function watchBlockCart() {
        if (!window.wp || !wp.data || typeof wp.data.subscribe !== 'function') {
            return false;
        }

        var store = wp.data.select('wc/store/cart');
        if (!store || typeof store.getCartData !== 'function') {
            return false;
        }

        var signature = null;

        wp.data.subscribe(function () {
            var cart = wp.data.select('wc/store/cart');
            if (!cart.hasFinishedResolution('getCartData')) return;

            var data = cart.getCartData();
            var items = (data.items || []).map(function (i) { return i.key + ':' + i.quantity; }).join('|');
            var coupons = (data.coupons || []).map(function (c) { return c.code; }).join('|');
            var currentSig = items + '#' + coupons;

            if (signature === null) {
                signature = currentSig;
                return;
            }

            if (currentSig === signature) return;

            signature = currentSig;
            refreshShareUrls();
        });

        return true;
    }

    // Document Ready Initialization
    $(function () {
        // Persist the cart through the nonce-protected endpoint before enabling sharing.
        refreshShareUrls();
        // Show native buttons if device supports Web Share API
        if (navigator.share) {
            $('.vantshsh-native-share-btn, .scurl-native-share-btn').prop('hidden', false).show();
        }

        // Trigger Modal Open (button or floating button)
        $(document.body).on('click', '.vantshsh-trigger-modal', function (e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.vantshsh-share-cart-wrapper, .vantshsh-floating-wrapper');
            var url = $wrapper.attr('data-share-url') || '';

            // If layout is inline, expand inline instead of opening modal
            if ($wrapper.hasClass('vantshsh-layout-inline')) {
                handleInlineCopy($wrapper);
            } else {
                openModal(url);
            }
        });

        // Trigger Copy inside Modal
        $(document.body).on('click', '.vantshsh-modal-copy-btn', function (e) {
            e.preventDefault();
            handleModalCopy();
        });

        // Trigger Inline Copy
        $(document.body).on('click', '.vantshsh-copy-btn, .scurl-copy-btn', function (e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.vantshsh-share-cart-wrapper, .scurl-share-cart');
            handleInlineCopy($wrapper);
        });

        // Social Button Click
        $(document.body).on('click', '.vantshsh-social-btn', function (e) {
            e.preventDefault();
            var channel = $(this).data('channel');
            handleSocialShare(channel);
        });

        // Toggle QR Code Accordion in Modal
        $(document.body).on('click', '.vantshsh-qr-toggle', function (e) {
            e.preventDefault();
            var $container = $(this).siblings('.vantshsh-qr-container');
            var isHidden = $container.prop('hidden');

            $container.prop('hidden', !isHidden);
            $(this).attr('aria-expanded', isHidden ? 'true' : 'false');
            $(this).toggleClass('vantshsh-active', isHidden);

            if (isHidden) {
                generateModalQR(getShareUrl());
            }
        });

        // Download QR Code Click
        $(document.body).on('click', '.vantshsh-download-qr-btn', function (e) {
            e.preventDefault();
            downloadQR();
        });

        // Modal Close triggers
        $(document.body).on('click', '.vantshsh-modal-close, .vantshsh-modal-backdrop', function (e) {
            e.preventDefault();
            closeModal();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                closeModal();
            }
        });

        // Live cart update listeners for classic cart
        $(document.body).on('updated_wc_div updated_cart_totals added_to_cart removed_from_cart', refreshShareUrls);

        // Block cart watcher initialization
        if (!watchBlockCart()) {
            var attempts = 0;
            var timer = setInterval(function () {
                attempts++;
                if (watchBlockCart() || attempts > 20) {
                    clearInterval(timer);
                }
            }, 300);
        }
    });

})(jQuery);
