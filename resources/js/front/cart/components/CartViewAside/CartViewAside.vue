<template>
    <div>
        <!-- KOSARICA -->
        <div
            class="rounded-3 p-4"
            v-if="route === 'kosarica'"
            style="border: 2px dashed rgb(230, 209, 171); background-color: #fff !important;"
        >
            <div class="py-2 px-xl-2" v-cloak>
                <div class="text-center mb-2 pb-2">
                    <h2 class="h6 mb-3 pb-1">Ukupno</h2>
                    <h3 class="fw-bold text-primary">{{ fmtMain(cartTotal) }}</h3>
                    <h4 class="fs-sm" v-if="hasSecondary">{{ fmtSecondary(cartTotal) }}</h4>
                </div>
                <a class="btn btn-primary btn-shadow d-block w-100 mt-4" :href="checkouturl">
                    NASTAVI NA NAPLATU <i class="ci-arrow-right fs-sm"></i>
                </a>
            </div>
        </div>

        <!-- NAPLATA -->
        <div
            class="rounded-3 p-4 ms-lg-auto"
            v-if="route === 'naplata'"
            style="border: 2px dashed rgb(230, 209, 171); background-color: #fff !important;"
        >
            <div class="py-2 px-xl-2">
                <div class="widget mb-3">
                    <h2 class="widget-title text-center mb-2">Sažetak narudžbe</h2>

                    <div
                        class="d-flex align-items-center pb-2 border-bottom"
                        v-for="item in cartItems"
                        :key="item.id || item.rowId || item.name"
                    >
                        <a class="d-block flex-shrink-0" :href="base_path + (item.attributes?.path || '')">
                            <img :src="item.associatedModel?.image" :alt="item.name" width="64" />
                        </a>
                        <div class="ps-2">
                            <h6 class="widget-product-title">
                                <a :href="base_path + (item.attributes?.path || '')">{{ item.name }}</a>
                            </h6>

                            <div class="widget-product-meta">
                <span class="text-primary me-2">
                  {{
                        hasConditions(item)
                            ? item.associatedModel?.main_special_text
                            : item.associatedModel?.main_price_text
                    }}
                </span>
                                <span class="text-muted">x {{ item.quantity }}</span>

                                <span
                                    class="text-primary fs-md fw-light"
                                    style="margin-left: 20px;"
                                    v-if="hasConditions(item) && item.associatedModel?.action && item.associatedModel?.action?.coupon === cart.coupon"
                                >
                  Kupon kod: {{ item.associatedModel.action.title }}
                  ({{ Math.round(item.associatedModel.action.discount).toFixed(0) }}%)
                </span>
                            </div>

                            <div class="widget-product-meta" v-if="item.associatedModel?.secondary_price_text">
                <span class="text-muted me-2">
                  {{
                        hasConditions(item)
                            ? item.associatedModel?.secondary_special_text
                            : item.associatedModel?.secondary_price_text
                    }}
                </span>
                                <span class="text-muted">x {{ item.quantity }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="list-unstyled fs-sm pb-2 border-bottom">
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="me-2">Ukupno:</span>
                        <span class="text-end">{{ fmtMain(cartSubtotal) }}</span>
                    </li>
                    <li v-if="hasSecondary" class="d-flex justify-content-between align-items-center">
                        <span class="me-2"></span>
                        <span class="text-end">{{ fmtSecondary(cartSubtotal) }}</span>
                    </li>

                    <div v-for="condition in conditions" :key="condition.name">
                        <li class="d-flex justify-content-between align-items-center">
                            <span class="me-2">{{ condition.name }}</span>
                            <span class="text-end">{{ fmtMain(condition.value) }}</span>
                        </li>
                        <li v-if="hasSecondary" class="d-flex justify-content-between align-items-center">
                            <span class="me-2"></span>
                            <span class="text-end">{{ fmtSecondary(condition.value) }}</span>
                        </li>
                    </div>
                </ul>

                <h3 class="fw-bold text-primary text-center my-2">{{ fmtMain(cartTotal) }}</h3>
                <h4 v-if="hasSecondary" class="fs-sm text-center my-2">{{ fmtSecondary(cartTotal) }}</h4>

                <p class="small text-center mt-0 mb-0">PDV uračunat u cijeni</p>
            </div>
        </div>

        <!-- PREGLED -->
        <div
            class="rounded-3 p-4 ms-lg-auto"
            v-if="route === 'pregled'"
            style="border: 2px dashed rgb(230, 209, 171); background-color: #fff !important;"
        >
            <div class="py-2 px-xl-2">
                <div class="widget mb-3">
                    <h2 class="widget-title text-center">Sažetak narudžbe</h2>
                </div>

                <ul class="list-unstyled fs-sm pb-2 border-bottom">
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="me-2">Ukupno:</span>
                        <span class="text-end">{{ fmtMain(cartSubtotal) }}</span>
                    </li>
                    <li v-if="hasSecondary" class="d-flex justify-content-between align-items-center">
                        <span class="me-2"></span>
                        <span class="text-end">{{ fmtSecondary(cartSubtotal) }}</span>
                    </li>

                    <div v-for="condition in conditions" :key="condition.name">
                        <li class="d-flex justify-content-between align-items-center">
                            <span class="me-2">{{ condition.name }}</span>
                            <span class="text-end">{{ fmtMain(condition.value) }}</span>
                        </li>
                        <li v-if="hasSecondary" class="d-flex justify-content-between align-items-center">
                            <span class="me-2"></span>
                            <span class="text-end">{{ fmtSecondary(condition.value) }}</span>
                        </li>
                    </div>
                </ul>

                <h3 class="fw-bold text-primary text-center my-2">{{ fmtMain(cartTotal) }}</h3>
                <h4 v-if="hasSecondary" class="fs-sm text-center my-2">{{ fmtSecondary(cartTotal) }}</h4>

                <p class="small text-center mt-0 mb-0">PDV uračunat u cijeni</p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        continueurl: String,
        checkouturl: String,
        buttons: { type: Boolean, default: true },
        route: String
    },

    data() {
        return {
            base_path: window.location.origin + '/',
            mobile: false,
            show_delete_btn: true,
            coupon: '',
            tax: 0
        }
    },

    computed: {
        // uvijek vrati valjan oblik košarice da template ne puca
        cart() {
            const c = this.$store?.state?.cart
            return c && typeof c === 'object'
                ? c
                : { count: 0, items: [], subtotal: 0, total: 0, detail_con: [], secondary_price: false, coupon: '' }
        },
        cartItems() {
            return Array.isArray(this.cart.items) ? this.cart.items : []
        },
        cartSubtotal() {
            return Number(this.cart.subtotal) || 0
        },
        cartTotal() {
            return Number(this.cart.total) || 0
        },
        hasSecondary() {
            return !!this.cart.secondary_price
        },
        conditions() {
            return Array.isArray(this.cart.detail_con) ? this.cart.detail_con : []
        },
        svc() {
            return this.$store?.state?.service || {}
        }
    },

    mounted() {
        this.mobile = window.innerWidth < 800

        // sigurni init – ne čitaj storage bez provjere
        this.checkIfEmpty()
        this.setCouponSafe()
    },

    watch: {
        // kad se košarica kasnije dovuče iz backenda, postavi kupon
        cart: {
            handler(n) {
                if (n && typeof n === 'object' && !this.coupon && n.coupon) {
                    this.coupon = n.coupon
                }
            },
            deep: false
        }
    },

    methods: {
        updateCart(item) {
            this.$store.dispatch('updateCart', item)
        },

        removeFromCart(item) {
            this.$store.dispatch('removeFromCart', item)
        },

        CheckQuantity(qty) {
            return qty < 1 ? 1 : qty
        },

        checkIfEmpty() {
            const storage = this.$store?.state?.storage
            const stored = storage?.getCart ? storage.getCart() : null

            if (stored && !stored.count && window.location.pathname !== '/kosarica') {
                window.location.href = '/kosarica'
            }
        },

        // >>> ovo je bila točka pucanja – sada je null-safe
        setCouponSafe() {
            const storage = this.$store?.state?.storage
            const stored = storage?.getCart ? storage.getCart() : null

            // prioritet: storage.coupon -> state.cart.coupon -> ''
            this.coupon = (stored && stored.coupon) || (this.cart && this.cart.coupon) || ''
        },

        checkCoupon() {
            this.$store.dispatch('checkCoupon', this.coupon)
        },

        hasConditions(item) {
            return !!(item && item.conditions && Object.keys(item.conditions).length)
        },

        fmtMain(val) {
            return this.svc.formatMainPrice ? this.svc.formatMainPrice(val) : val
        },

        fmtSecondary(val) {
            return this.svc.formatSecondaryPrice ? this.svc.formatSecondaryPrice(val) : val
        }
    }
}
</script>

<style>
.table th,
.table td {
    padding: 0.75rem 0.45rem !important;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}
.empty th,
.empty td {
    padding: 1rem !important;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}
.mobile-prices {
    font-size: 0.66rem;
    color: #999999;
}
</style>
