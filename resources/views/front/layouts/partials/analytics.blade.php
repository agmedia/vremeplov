<script>
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };

    (function () {
        var consent = null;
        var match = document.cookie.match(/(?:^|;\s*)vremeplov_cookie_consent=([^;]+)/);

        if (match) {
            try {
                consent = JSON.parse(decodeURIComponent(match[1]));
            } catch (error) {
                consent = null;
            }
        }

        window.gtag('consent', 'default', {
            analytics_storage: consent && consent.analytics ? 'granted' : 'denied',
            ad_storage: consent && consent.marketing ? 'granted' : 'denied',
            ad_user_data: consent && consent.marketing ? 'granted' : 'denied',
            ad_personalization: consent && consent.marketing ? 'granted' : 'denied',
            functionality_storage: 'granted',
            security_storage: 'granted',
            wait_for_update: 500
        });

        var measurementId = 'G-RK67XDPD14';
        var directGa4Events = {
            add_to_cart: true,
            remove_from_cart: true,
            begin_checkout: true,
            add_shipping_info: true,
            add_payment_info: true,
            add_to_wishlist: true,
            view_item_list: true,
            select_item: true,
            search: true,
            form_submit: true,
            generate_lead: true
        };

        // The current GTM container has a paused add_to_cart tag and no tags for
        // the rest of these funnel events. Configure the same GA4 property as a
        // direct fallback without generating a second page_view.
        window.gtag('config', measurementId, { send_page_view: false });

        window.VremeplovAnalytics = {
            track: function (eventName, parameters) {
                if (!eventName) return;
                var ecommerce = parameters && parameters.ecommerce;
                if (ecommerce && Array.isArray(ecommerce.items) && ecommerce.items.length) {
                    if (!ecommerce.currency) ecommerce.currency = ecommerce.items[0].currency || 'EUR';
                    if (typeof ecommerce.value === 'undefined') {
                        ecommerce.value = Number(ecommerce.items.reduce(function (total, item) {
                            return total + ((Number(item.price) || 0) * (Number(item.quantity) || 1));
                        }, 0).toFixed(2));
                    }
                }
                window.dataLayer.push({ ecommerce: null });
                window.dataLayer.push(Object.assign({ event: eventName }, parameters || {}));

                if (directGa4Events[eventName]) {
                    var directParameters = Object.assign({}, parameters || {});
                    if (directParameters.ecommerce) {
                        directParameters = Object.assign({}, directParameters, directParameters.ecommerce);
                        delete directParameters.ecommerce;
                    }
                    directParameters.send_to = measurementId;
                    window.gtag('event', eventName, directParameters);
                }
            },
            item: function (cartItem, quantity) {
                var product = (cartItem && cartItem.associatedModel) || {};
                var data = Object.assign({}, product.dataLayer || {});
                data.quantity = Number(quantity == null ? cartItem.quantity : quantity) || 1;
                return data;
            }
        };

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form[data-analytics-form]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    window.VremeplovAnalytics.track('form_submit', {
                        form_name: form.getAttribute('data-analytics-form')
                    });
                });
            });

            document.addEventListener('click', function (event) {
                var link = event.target.closest('[data-analytics-item] a[href]');
                if (!link) return;
                var card = link.closest('[data-analytics-item]');
                try {
                    var item = JSON.parse(card.getAttribute('data-analytics-item'));
                    window.VremeplovAnalytics.track('select_item', {
                        ecommerce: { item_list_name: 'Katalog', items: [item] }
                    });
                } catch (error) {}
            });

            window.addEventListener('ga4-event', function (event) {
                var detail = event.detail || {};
                var name = detail.event;
                delete detail.event;
                window.VremeplovAnalytics.track(name, detail);
            });
        });
    })();
</script>

@yield('google_data_layer')

@if (config('app.env') == 'production')
    <!-- Google Tag Manager; Consent Mode defaults are set before this request. -->
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-K4CB5GR');
    </script>
@endif
