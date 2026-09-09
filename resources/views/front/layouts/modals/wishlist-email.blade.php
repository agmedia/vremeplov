<div class="modal fade" id="wishlist-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-secondary">
                <span class="fs-lg fw-bolder">Obavijesti me o dostupnosti</span>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body tab-content py-4">
                <form method="POST" class="needs-validation" action="{{ route('wishlist') }}" autocomplete="on" novalidate id="wishlist-tab">
                    @csrf
                    <div class="mb-4 text-center">
                        <p class="fs-md fw-light">Upisite Email adresu na koju zelite obavijest kad artikl opet bude dostupan.</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="wishlist-email">Email adresa</label>
                        <input class="form-control" type="email" id="wishlist-email" name="email" required>
                        <div class="invalid-feedback">Molimo unesite ispravnu email adresu.</div>
                    </div>

                    <input type="hidden" name="recaptcha" id="recaptcha_wishlist">
                    <input type="hidden" name="product_id" value="{{ $prod->id }}">
                    <button class="btn btn-primary btn-shadow d-block w-100" type="submit">Obavijesti me</button>
                    @include('front.layouts.partials.recaptcha-notice')
                </form>
            </div>
        </div>
    </div>
</div>

@push('js_after')
    @include('front.layouts.partials.recaptcha-js', [
        'action' => 'wishlist',
        'fieldId' => 'recaptcha_wishlist',
        'formId' => 'wishlist-tab',
    ])
@endpush
