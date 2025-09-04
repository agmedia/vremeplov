<template>
    <div>
        <div class="d-block pt-3 pb-2 mt-1 text-center text-sm-start">
            <h2 class="h6 text-primary  mb-0">Artikli</h2>
        </div>
        <div class="d-flex pt-3 pb-2 mt-1" v-if="!($store.state.cart && ($store.state.cart && $store.state.cart.count))">
            <p class="text-dark mb-0">Vaša košarica je prazna!</p>
        </div>

      <!--  <div class="d-flex border p-2" style="background-color: rgba(245,245,245,0.96);" v-if="$store.state.cart.total < freeship && ($store.state.cart && $store.state.cart.count)">
            <p class="small mb-0">Još € {{ $store.state.service.formatMainPrice(freeship - $store.state.cart.total) }} <span v-if="$store.state.cart.secondary_price">({{ $store.state.service.formatSecondaryPrice(freeship - $store.state.cart.total) }})</span> do besplatne dostave!</p>
        </div>
        <div class="d-flex border p-2" style="background-color: rgba(245,245,245,0.96);" v-if="$store.state.cart.total > freeship && ($store.state.cart && $store.state.cart.count)">
            <p class="small mb-0">Ostvarili ste pravo na besplatnu dostavu!</p>
        </div>-->

        <!-- Item-->
        <div class="d-sm-flex justify-content-between align-items-center my-2 pb-3 border-bottom" v-for="item in (($store.state.cart && $store.state.cart.items) || [])">
            <div class="d-block d-sm-flex align-items-center text-center text-sm-start">
                <a class="d-inline-block flex-shrink-0 mx-auto me-sm-4" :href="base_path + item.attributes.path">
                    <img :src="item.associatedModel.image" width="120" :alt="item.name" :title="item.name">
                </a>
                <div class="pt-2">
                    <h3 class="product-title fs-base mb-2"><a :href="base_path + item.attributes.path">{{ item.name }}</a></h3>

                    <div class="fs-lg text-primary pt-2">{{ Object.keys(item.conditions).length ? item.associatedModel.main_special_text : item.associatedModel.main_price_text }}</div>
                    <div class="fs-sm text-dark pt-2" v-if="item.associatedModel.secondary_price">{{ Object.keys(item.conditions).length ? item.associatedModel.secondary_special_text : item.associatedModel.secondary_price_text }}</div>
                </div>
            </div>
            <div class="pt-2 pt-sm-0 ps-sm-3 mx-auto mx-sm-0 text-center text-sm-start" style="max-width: 9rem;">
                <label class="form-label">Količina: {{item.quantity}}</label>
                <input class="form-control" type="number" v-model="item.quantity" min="1" :max="item.associatedModel.quantity" @click.prevent="updateCart(item)">
                <button class="btn btn-link px-0 text-danger" type="button" @click.prevent="removeFromCart(item)"><i class="ci-close-circle me-2"></i><span class="fs-sm">Ukloni</span></button>
            </div>
        </div>

        <div class="d-block pt-3 pb-4 pb-sm-5 mt-1 text-center text-sm-start" v-if="show_buttons">
            <a class="btn btn-outline-dark btn-sm ps-2" :href="continueurl"><i class="ci-arrow-left me-2"></i>Natrag na trgovinu</a>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            continueurl: String,
            checkouturl: String,
            freeship: String,
            buttons: {type: String, default: 'true'},
        },
        data() {
            return {
                base_path: window.location.origin + '/',
                mobile: false,
                show_delete_btn: true,
                coupon: '',
                show_buttons: true,
            }
        },
        mounted() {
            if (window.innerWidth < 800) {
                this.mobile = true;
            }

            if (this.buttons == 'false') {
                this.show_buttons = false;
            } else {
                this.show_buttons = true;
            }

            this.checkIfEmpty();
            this.setCoupon();
        },

        methods: {

            /**
             *
             * @param item
             */
            updateCart(item) {
                this.$store.dispatch('updateCart', item);
            },

            /**
             *
             * @param item
             */
            removeFromCart(item) {
                this.$store.dispatch('removeFromCart', item);
            },

            /**
             *
             * @param qty
             * @returns {number|*}
             * @constructor
             */
            CheckQuantity(qty) {
                if (qty < 1) {
                    return 1;
                }

                return qty;
            },

            /**
             *
             */
            checkIfEmpty() {
                const p = window.location.pathname;
                const fetched = this.$store.state.fetched && this.$store.state.fetched.cart;
                // Only enforce empty-cart redirect AFTER first fetch, and NEVER on checkout
                if (!fetched || p.indexOf('/naplata') === 0) return;

                const cart = this.$store.state.storage.getCart() || this.$store.state.cart;
                if (cart && !cart.count && p !== '/kosarica') {
                    window.location.href = '/kosarica';
                }
            },

            /**
             *
             */
            setCoupon() {
                let cart = this.$store.state.storage.getCart();

                this.coupon = cart.coupon;
            },

            /**
             *
             */
            checkCoupon() {
                this.$store.dispatch('checkCoupon', this.coupon);
            }
        }
    };
</script>


<style>
.table th, .table td {
    padding: 0.75rem 0.45rem !important;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}
.empty th, .empty td {
    padding: 1rem !important;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}
.mobile-prices {
    font-size: .66rem;
    color: #999999;
}
</style>
