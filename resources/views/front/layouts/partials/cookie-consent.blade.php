<style>
    .cookie-consent-backdrop{position:fixed;inset:0;background:rgba(24,31,34,.56);z-index:1095;display:none}
    .cookie-consent-backdrop.is-open{display:block}
    .cookie-consent-modal{position:fixed;z-index:1100;left:50%;top:50%;transform:translate(-50%,-50%);width:min(690px,calc(100% - 28px));max-height:calc(100vh - 28px);overflow:auto;background:#fffdf9;border-radius:18px;border-top:5px solid #d8ac63;box-shadow:0 24px 70px rgba(45,34,36,.28);display:none;color:#2d2224}
    .cookie-consent-modal.is-open{display:block}
    .cookie-consent-head,.cookie-consent-actions{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:20px 22px;border-bottom:1px solid #e7dece}
    .cookie-consent-actions{border-bottom:0;border-top:1px solid #e7dece;flex-wrap:wrap}
    .cookie-consent-primary-actions{display:flex;align-items:center;gap:10px}
    .cookie-consent-title{font-family:Georgia,'Times New Roman',serif;font-size:1.15rem;font-weight:700;margin:0}
    .cookie-consent-close{width:42px;height:42px;border:1px solid #dfd2bb;border-radius:12px;background:#fff;color:#2d2224;font-size:25px;line-height:1}
    .cookie-consent-body{padding:10px 22px 120px}
    .cookie-consent-row{margin:10px 0;border:1px solid #e5dbc8;border-radius:12px;background:#fbf8f2}
    .cookie-consent-summary{display:flex;align-items:center;width:100%;gap:13px;padding:14px 17px;border:0;background:transparent;color:#2d2224;text-align:left;font-weight:700}
    .cookie-consent-chevron{width:20px;height:20px;border-radius:50%;background:#d8ac63;color:#2d2224;text-align:center;line-height:19px;font-size:12px;transition:transform .2s}
    .cookie-consent-row.is-expanded .cookie-consent-chevron{transform:rotate(180deg)}
    .cookie-consent-copy{display:none;margin:0;padding:0 17px 15px 50px;color:#6e716d;font-size:.86rem;line-height:1.55}
    .cookie-consent-row.is-expanded .cookie-consent-copy{display:block}
    .cookie-consent-switch{margin-left:auto;position:relative;width:53px;height:27px;flex:0 0 auto}
    .cookie-consent-switch input{position:absolute;opacity:0}
    .cookie-consent-slider{position:absolute;inset:0;border-radius:20px;background:#a5aaa6;transition:.2s}
    .cookie-consent-slider:after{content:'\2713';position:absolute;right:3px;top:3px;width:21px;height:21px;border-radius:50%;background:#fff;color:#2d2224;text-align:center;line-height:21px;font-size:12px;transition:.2s}
    .cookie-consent-switch input:checked + .cookie-consent-slider{background:#2d2224}
    .cookie-consent-switch input:not(:checked) + .cookie-consent-slider:after{right:29px;content:''}
    .cookie-consent-switch input:disabled + .cookie-consent-slider{background:#d8ac63}
    .cookie-consent-actions .btn{border-radius:11px;font-weight:700;padding:.7rem 1.1rem}
    .cookie-settings-trigger{position:fixed;left:20px;bottom:20px;z-index:1085;display:flex;align-items:center;justify-content:center;width:52px;height:52px;border:0;border-radius:50%;background:#d8ac63;color:#2d2224;box-shadow:0 7px 25px rgba(0,0,0,.25);transition:background-color .2s,color .2s,transform .2s}
    .cookie-settings-trigger:hover,.cookie-settings-trigger:focus-visible{background:#2d2224;color:#e6d1ab;transform:translateY(-2px)}
    .cookie-settings-trigger i{display:block;font-size:26px;line-height:1}
    body.cookie-consent-locked{overflow:hidden}
    @media(max-width:575px){.cookie-consent-modal{top:auto;bottom:0;transform:translateX(-50%);width:100%;max-height:94vh;border-radius:18px 18px 0 0}.cookie-consent-body{padding:8px 14px 35px}.cookie-consent-head,.cookie-consent-actions{padding:15px}.cookie-consent-primary-actions{width:100%}.cookie-consent-primary-actions .btn{flex:1 1 50%}.cookie-consent-actions>[data-cookie-save]{width:100%}.cookie-settings-trigger{right:16px;bottom:8.25rem;left:auto;width:46px;height:46px}.cookie-settings-trigger i{font-size:22px}}
</style>

<div class="cookie-consent-backdrop" id="cookie-consent-backdrop" aria-hidden="true"></div>
<section class="cookie-consent-modal" id="cookie-consent-modal" role="dialog" aria-modal="true" aria-labelledby="cookie-consent-title" hidden>
    <div class="cookie-consent-head">
        <h2 class="cookie-consent-title" id="cookie-consent-title">Odaberite kolačiće</h2>
        <button class="cookie-consent-close" type="button" data-cookie-close aria-label="Zatvori">&times;</button>
    </div>
    <div class="cookie-consent-body">
        <div class="cookie-consent-row">
            <button class="cookie-consent-summary" type="button" data-cookie-expand aria-expanded="false">
                <span class="cookie-consent-chevron">⌄</span><span>Nužni kolačići</span>
                <label class="cookie-consent-switch" aria-label="Nužni kolačići uvijek su uključeni"><input type="checkbox" checked disabled><span class="cookie-consent-slider"></span></label>
            </button>
            <p class="cookie-consent-copy">Potrebni su za sigurnost, košaricu, naplatu i spremanje vašeg odabira. Ne mogu se isključiti.</p>
        </div>
        <div class="cookie-consent-row">
            <button class="cookie-consent-summary" type="button" data-cookie-expand aria-expanded="false">
                <span class="cookie-consent-chevron">⌄</span><span>Analitika</span>
                <label class="cookie-consent-switch" aria-label="Analitički kolačići"><input id="cookie-analytics" type="checkbox"><span class="cookie-consent-slider"></span></label>
            </button>
            <p class="cookie-consent-copy">Pomažu nam razumjeti korištenje trgovine i mjeriti kupovni proces putem Google Analyticsa. Podaci se koriste zbirno.</p>
        </div>
        <div class="cookie-consent-row">
            <button class="cookie-consent-summary" type="button" data-cookie-expand aria-expanded="false">
                <span class="cookie-consent-chevron">⌄</span><span>Marketing</span>
                <label class="cookie-consent-switch" aria-label="Marketinški kolačići"><input id="cookie-marketing" type="checkbox"><span class="cookie-consent-slider"></span></label>
            </button>
            <p class="cookie-consent-copy">Omogućuju mjerenje oglasa i relevantnije marketinške poruke. Aktiviraju se samo uz vaš odabir.</p>
        </div>
    </div>
    <div class="cookie-consent-actions">
        <div class="cookie-consent-primary-actions">
            <button class="btn btn-primary" type="button" data-cookie-accept>Prihvati sve</button>
            <button class="btn btn-primary" type="button" data-cookie-required>Samo nužni</button>
        </div>
        <button class="btn btn-outline-primary" type="button" data-cookie-save>Spremi odabir</button>
    </div>
</section>
<button class="cookie-settings-trigger" type="button" data-cookie-open aria-label="Postavke kolačića" title="Postavke kolačića">
    <i class="fa-solid fa-cookie-bite" aria-hidden="true"></i>
</button>

<script src="/js/cookie-consent.js?v=1.0" defer></script>
