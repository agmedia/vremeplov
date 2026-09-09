const DEFAULT_IMAGE = '/media/img/thumb-product.webp';

const CHECK_ICON = `
    <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="M9.55 17.3 4.8 12.55l1.4-1.4 3.35 3.35 8.25-8.25 1.4 1.4Z"/>
    </svg>
`;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function cartItems(cart) {
    return Object.values(cart?.items || {});
}

function findCartItem(cart, itemId) {
    return cart?.items?.[itemId]
        || cartItems(cart).find((item) => String(item?.id) === String(itemId));
}

function resolveQuantity(value) {
    return Math.max(1, parseInt(value, 10) || 1);
}

function resolvePrice(cartItem) {
    const product = cartItem?.associatedModel || {};
    const hasConditions = Object.keys(cartItem?.conditions || {}).length > 0;

    if (hasConditions && product.main_special_text) {
        return product.main_special_text;
    }

    return product.main_price_text || product.main_special_text || cartItem?.price || '';
}

function resolveImage(cartItem) {
    const product = cartItem?.associatedModel || {};

    return product.thumb || product.image || DEFAULT_IMAGE;
}

function buildModalHtml(cartItem, requestedItem) {
    const quantityAdded = resolveQuantity(requestedItem?.quantity);
    const quantityInCart = resolveQuantity(cartItem?.quantity);
    const productName = cartItem?.name || 'Odabrani artikl';
    const price = resolvePrice(cartItem);

    return `
        <div class="cart-add-modal">
            <div class="cart-add-modal__hero">
                <span class="cart-add-modal__hero-icon">${CHECK_ICON}</span>
                <div class="cart-add-modal__hero-copy">
                    <h2 class="cart-add-modal__heading">Artikl je u košarici</h2>
                    <p class="cart-add-modal__lead">Možete nastaviti pregledavati ili dovršiti kupnju.</p>
                </div>
            </div>

            <div class="cart-add-modal__card">
                <div class="cart-add-modal__image-wrap">
                    <img
                        class="cart-add-modal__image"
                        src="${escapeHtml(resolveImage(cartItem))}"
                        alt="${escapeHtml(productName)}"
                        onerror="this.onerror=null;this.src='${DEFAULT_IMAGE}'"
                    >
                </div>

                <div class="cart-add-modal__body">
                    ${price ? `<span class="cart-add-modal__price">${escapeHtml(price)}</span>` : ''}
                    <h3 class="cart-add-modal__name">${escapeHtml(productName)}</h3>
                    <div class="cart-add-modal__chips">
                        <span class="cart-add-modal__chip">Dodano: <strong>${quantityAdded} kom</strong></span>
                        ${quantityInCart > quantityAdded ? `<span class="cart-add-modal__chip">Ukupno u košarici: <strong>${quantityInCart} kom</strong></span>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

export function showCartAddSuccessModal(swal, payload = {}) {
    const requestedItem = payload.item || {};
    const cartItem = findCartItem(payload.cart || {}, requestedItem.id);

    if (! swal || ! cartItem) {
        return null;
    }

    return swal.fire({
        html: buildModalHtml(cartItem, requestedItem),
        showCloseButton: true,
        showCancelButton: true,
        showConfirmButton: true,
        reverseButtons: true,
        confirmButtonText: 'Dovrši kupnju',
        cancelButtonText: 'Nastavi kupovati',
        closeButtonAriaLabel: 'Zatvori',
        focusConfirm: false,
        buttonsStyling: false,
        customClass: {
            container: 'cart-add-modal-container',
            popup: 'cart-add-modal-popup',
            htmlContainer: 'cart-add-modal-html',
            closeButton: 'cart-add-modal-close',
            actions: 'cart-add-modal-actions',
            confirmButton: 'btn btn-shadow cart-add-modal-confirm',
            cancelButton: 'btn cart-add-modal-cancel'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '/kosarica';
        }

        return result;
    });
}
