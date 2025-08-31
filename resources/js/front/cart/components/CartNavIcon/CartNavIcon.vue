<template>
    <div class="navbar-tool dropdown ms-1">
        <a class="navbar-tool-icon-box dropdown-toggle" :href="carturl">
            <span class="navbar-tool-label">{{ cartCount }}</span>
            <i class="navbar-tool-icon ci-bag"></i>
        </a>

        <!-- Cart dropdown -->
        <div class="dropdown-menu dropdown-menu-end">
            <div
                class="widget widget-cart px-3 pt-2 pb-3"
                style="width: 24rem;"
                v-if="hasCartItems"
            >
                <div data-simplebar-auto-hide="false">
                    <div
                        class="widget-cart-item pb-2 border-bottom"
                        v-for="item in cartItems"
                        :key="item.id || item.rowId || item.name"
                    >
                        <button
                            class="btn-close text-danger"
                            type="button"
                            @click.prevent="removeFromCart(item)"
                            aria-label="Remove"
                        >
                            <span aria-hidden="true">&times;</span>
                        </button>

                        <div class="d-flex align-items-center">
                            <a class="d-block flex-shrink-0 pt-2" href="#">
                                <img
                                    :src="item.associatedModel && item.associatedModel.image"
                                    :alt="item.name"
                                    :title="item.name"
                                    style="width: 5rem;"
                                />
                            </a>

                            <div class="ps-2">
                                <h6 class="widget-product-title">
                                    <a :href="base_path + (item.attributes && item.attributes.path || '')">
                                        {{ item.name }}
                                    </a>
                                </h6>

                                <div class="widget-product-meta">
                  <span class="text-primary me-2">
                    {{
                          hasConditions(item)
                              ? (item.associatedModel && item.associatedModel.main_special_text)
                              : (item.associatedModel && item.associatedModel.main_price_text)
                      }}
                  </span>
                                    <span class="text-muted">x {{ item.quantity }}</span>
                                </div>

                                <div class="widget-product-meta" v-if="item.associatedModel && item.associatedModel.secondary_price">
                  <span class="text-dark fs-sm me-2">
                    {{
                          hasConditions(item)
                              ? (item.associatedModel && item.associatedModel.secondary_special_text)
                              : (item.associatedModel && item.associatedModel.secondary_price_text)
                      }}
                  </span>
                                    <span class="text-muted">x {{ item.quantity }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center py-3">
                    <div class="fs-sm me-2 py-2">
                        <span class="text-muted">Ukupno:</span>
                        <span class="text-primary fs-base ms-1">
              {{ formatMainPrice(cartTotal) }}
            </span>
                        <span v-if="hasSecondaryTotal" class="text-muted">
              {{ formatSecondaryPrice(cartTotal) }}
            </span>
                    </div>
                </div>

                <a class="btn btn-primary btn-sm d-block w-100" :href="carturl">
                    <i class="ci-card me-2 fs-base align-middle"></i>Dovrši kupnju
                </a>
            </div>

            <div class="widget widget-cart text-center pt-2" style="width: 20rem;" v-else>
                <h1 class="mb-2 mt-1"><i class="ci-cart"></i></h1>
                <p>Vaša košarica je prazna!</p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        carturl: String,
        checkouturl: String
    },

    data() {
        return {
            base_path: window.location.origin + '/',
            success_path: window.location.origin + '/kosarica/success',
            mobile: false
        }
    },

    computed: {
        cart() {
            // Always return an object so templates don’t explode when cart is missing.
            return (this.$store && this.$store.state && this.$store.state.cart) || { count: 0, items: [], total: 0 }
        },
        cartCount() {
            return Number(this.cart.count) || 0
        },
        cartItems() {
            return Array.isArray(this.cart.items) ? this.cart.items : []
        },
        cartTotal() {
            return Number(this.cart.total) || 0
        },
        hasCartItems() {
            return this.cartCount > 0 && this.cartItems.length > 0
        },
        hasSecondaryTotal() {
            return Boolean(this.cart.secondary_price)
        }
    },

    mounted() {
        this.checkCart()

        if (window.location.pathname === '/kosarica/success') {
            // This is safe provided your store’s flushCart keeps state.cart as a valid object.
            this.$store.dispatch('flushCart')
        }

        this.mobile = window.innerWidth < 800

        if (window.location.pathname === '/pregled') {
            window.setInterval(this.checkCart, 15000)
        }
    },

    methods: {
        hasConditions(item) {
            // robustly handle missing conditions
            return item && item.conditions && Object.keys(item.conditions).length > 0
        },

        formatMainPrice(value) {
            const svc = this.$store && this.$store.state && this.$store.state.service
            return svc && svc.formatMainPrice ? svc.formatMainPrice(value) : value
        },

        formatSecondaryPrice(value) {
            const svc = this.$store && this.$store.state && this.$store.state.service
            return svc && svc.formatSecondaryPrice ? svc.formatSecondaryPrice(value) : ''
        },

        /**
         * Make the logic clear and reachable in both branches.
         */
        checkCart() {
            const storage = this.$store && this.$store.state && this.$store.state.storage
            const storedCart = storage && storage.getCart ? storage.getCart() : null

            // If there’s no stored cart yet, fetch it and bail.
            if (!storedCart) {
                this.$store.dispatch('getSettings')
                this.$store.dispatch('getCart')
                this.$store.dispatch('checkCart', [])
                return
            }

            // We have a stored cart – proceed.
            this.$store.dispatch('getSettings')

            const ids = []
            if (storedCart.items) {
                Object.keys(storedCart.items).forEach(key => {
                    const it = storedCart.items[key]
                    if (it && it.id) ids.push(it.id)
                })
            }

            this.$store.dispatch('checkCart', ids)
        },

        removeFromCart(item) {
            this.$store.dispatch('removeFromCart', item)
        }
    }
}
</script>
