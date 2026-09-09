/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

import Vue from "vue";
window.Vue = Vue;
import Vuex from 'vuex';
window.Vuex = Vuex;
Vue.use(Vuex);

import VueRouter from 'vue-router'
Vue.use(VueRouter)

const router = new VueRouter({
    mode: 'history',
});

import VueSweetalert2 from "vue-sweetalert2";
import 'sweetalert2/dist/sweetalert2.min.css';
import './cart-add-modal.css';
Vue.use(VueSweetalert2)

import store from './store.js';
import { showCartAddSuccessModal } from './cart-add-modal';

//import Storage from './services/Storage'

Vue.component('cart-nav-icon', require('./components/CartNavIcon/CartNavIcon').default);
Vue.component('cart-view', require('./components/CartView/CartView').default);
Vue.component('cart-view-aside', require('./components/CartViewAside/CartViewAside').default);
Vue.component('cart-footer-icon', require('./components/CartFooterIcon/CartFooterIcon').default);
Vue.component('add-to-cart-btn', require('./components/AddToCartBtn/AddToCartBtn').default);
Vue.component('add-to-cart-btn-simple', require('./components/AddToCartBtnSimple/AddToCartBtnSimple').default);
// FILTERS
Vue.component('filter-view', require('./../filter/components/Filter/Filter').default);
Vue.component('products-view', require('./../filter/components/ProductsList/ProductsList').default);
Vue.component('pagination', require('./../filter/components/Pagination/LaravelVuePagination').default);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

// Prevent double boot if the script is included twice (layout + view)
if (!window.__AG_CART_BOOTED__) {
    window.__AG_CART_BOOTED__ = true;

    const storeInstance = new Vuex.Store(store);

    storeInstance.state.service.getSettings().then(s => {
        if (s) storeInstance.state.settings = s;
    });

    storeInstance.dispatch('getCart');

    const app = new Vue({
        el: '#agapp',
        router,
        store: storeInstance
    });

    window.ToastSuccess = app.$swal.mixin({
        toast: true,
        icon: 'success',
        position: 'top-end',
        showConfirmButton: false,
        timer: 2500,
    });

    window.ToastWarning = app.$swal.mixin({
        toast: true,
        icon: 'warning',
        position: 'top-end',
        showConfirmButton: false,
        timer: 2500,
    });

    window.ToastWarningLong = app.$swal.mixin({
        toast: true,
        icon: 'warning',
        position: 'top-end',
        showConfirmButton: false,
        timer: 5000,
    });

    window.CartAddSuccess = (payload = {}) => showCartAddSuccessModal(app.$swal, payload);

    if (!window.__AG_CLONED_CART_BUTTONS__) {
        window.__AG_CLONED_CART_BUTTONS__ = true;

        const syncClonedCartButtons = () => {
            const cart = storeInstance.state.storage.getCart() || {};
            const cartItems = Object.values(cart.items || {});

            document.querySelectorAll('.tns-slide-cloned .product-card-add-button[data-product-id]').forEach(button => {
                const available = Math.max(0, Number(button.dataset.productAvailable) || 0);
                const item = cartItems.find(cartItem => String(cartItem.id) === String(button.dataset.productId));
                const quantityInCart = item ? Math.max(0, Number(item.quantity) || 0) : 0;
                const blocked = available < 1 || quantityInCart >= available;
                const label = blocked
                    ? 'Nema više dostupnih primjeraka ovog artikla'
                    : 'Dodaj u košaricu';

                button.classList.toggle('is-blocked', blocked);
                button.setAttribute('aria-disabled', blocked ? 'true' : 'false');
                button.setAttribute('aria-label', label);
                button.setAttribute('title', label);
            });
        };

        storeInstance.watch(
            state => state.cart,
            () => window.requestAnimationFrame(syncClonedCartButtons),
            {deep: true}
        );

        if (document.readyState === 'complete') {
            window.requestAnimationFrame(syncClonedCartButtons);
        } else {
            window.addEventListener('load', syncClonedCartButtons, {once: true});
        }

        document.addEventListener('click', async (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const button = target && target.closest('.tns-slide-cloned .product-card-add-button[data-product-id]');

            if (!button) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (button.dataset.cartPending === 'true') {
                return;
            }

            const id = button.dataset.productId;
            const available = Math.max(0, Number(button.dataset.productAvailable) || 0);
            const cart = storeInstance.state.storage.getCart() || {};
            const cartItems = Object.values(cart.items || {});
            const existing = cartItems.find(item => String(item.id) === String(id));
            const quantityInCart = existing ? Math.max(0, Number(existing.quantity) || 0) : 0;

            if (available < 1 || quantityInCart >= available) {
                window.ToastWarning.fire('Nema više dostupnih primjeraka ovog artikla.');
                return;
            }

            const action = quantityInCart > 0 ? 'updateCart' : 'addToCart';
            const item = quantityInCart > 0
                ? {
                    id,
                    quantity: quantityInCart + 1,
                    show_add_modal: true,
                    added_quantity: 1,
                }
                : {id, quantity: 1};

            button.dataset.cartPending = 'true';
            button.setAttribute('aria-busy', 'true');

            try {
                await storeInstance.dispatch(action, item);
            } finally {
                delete button.dataset.cartPending;
                button.setAttribute('aria-busy', 'false');
                syncClonedCartButtons();
            }
        });
    }
}
