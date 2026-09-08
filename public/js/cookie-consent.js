(function () {
    'use strict';

    var cookieName = 'vremeplov_cookie_consent';
    var modal = document.getElementById('cookie-consent-modal');
    var backdrop = document.getElementById('cookie-consent-backdrop');
    var analytics = document.getElementById('cookie-analytics');
    var marketing = document.getElementById('cookie-marketing');
    var lastFocus = null;

    if (!modal || !backdrop) return;

    function readConsent() {
        var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + cookieName + '=([^;]+)'));
        if (!match) return null;
        try { return JSON.parse(decodeURIComponent(match[1])); } catch (error) { return null; }
    }

    function show() {
        lastFocus = document.activeElement;
        modal.hidden = false;
        modal.classList.add('is-open');
        backdrop.classList.add('is-open');
        document.body.classList.add('cookie-consent-locked');
        modal.querySelector('button').focus();
    }

    function hide() {
        modal.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        modal.hidden = true;
        document.body.classList.remove('cookie-consent-locked');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    function persist(selection) {
        var value = {
            necessary: true,
            analytics: !!selection.analytics,
            marketing: !!selection.marketing,
            updated_at: new Date().toISOString()
        };
        saved = value;
        var secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = cookieName + '=' + encodeURIComponent(JSON.stringify(value)) + '; Path=/; Max-Age=15552000; SameSite=Lax' + secure;
        window.gtag('consent', 'update', {
            analytics_storage: value.analytics ? 'granted' : 'denied',
            ad_storage: value.marketing ? 'granted' : 'denied',
            ad_user_data: value.marketing ? 'granted' : 'denied',
            ad_personalization: value.marketing ? 'granted' : 'denied'
        });
        window.dataLayer.push({
            event: 'cookie_consent_update',
            consent_analytics: value.analytics,
            consent_marketing: value.marketing
        });
        hide();
    }

    var saved = readConsent();
    analytics.checked = !!(saved && saved.analytics);
    marketing.checked = !!(saved && saved.marketing);

    document.querySelectorAll('[data-cookie-open]').forEach(function (button) { button.addEventListener('click', show); });
    document.querySelectorAll('[data-cookie-close]').forEach(function (button) { button.addEventListener('click', hide); });
    document.querySelector('[data-cookie-accept]').addEventListener('click', function () { persist({ analytics: true, marketing: true }); });
    document.querySelector('[data-cookie-required]').addEventListener('click', function () { persist({ analytics: false, marketing: false }); });
    document.querySelector('[data-cookie-save]').addEventListener('click', function () { persist({ analytics: analytics.checked, marketing: marketing.checked }); });
    document.querySelectorAll('[data-cookie-expand]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            if (event.target.closest('.cookie-consent-switch')) return;
            var row = button.closest('.cookie-consent-row');
            var expanded = row.classList.toggle('is-expanded');
            button.setAttribute('aria-expanded', String(expanded));
        });
    });
    modal.querySelectorAll('.cookie-consent-switch').forEach(function (label) {
        label.addEventListener('click', function (event) { event.stopPropagation(); });
    });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && saved) hide(); });

    if (!saved) show();
})();
