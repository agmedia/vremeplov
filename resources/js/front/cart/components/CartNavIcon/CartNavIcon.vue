<template>
    <div class="navbar-tool dropdown ms-1">
        <a class="navbar-tool-icon-box dropdown-toggle" :href="carturl">
            <span class="navbar-tool-label">{{ cartCount }}</span>
            <i class="navbar-tool-icon ci-bag"></i>
        </a>

        <div class="dropdown-menu dropdown-menu-end"  >
            <div class="widget widget-cart px-3 pt-2 pb-3" style="width: 24rem;" v-if="hasCartItems">
                <div data-simplebar-auto-hide="false">
                    <div class="widget-cart-item pb-2 border-bottom" v-for="item in cartItems" :key="item.id || item.rowId || item.name">
                        <button class="btn-close text-danger" type="button" @click.prevent="removeFromCart(item)" aria-label="Remove">
                            <span aria-hidden="true">&times;</span>
                        </button>

                        <div class="d-flex align-items-center">
                            <a class="d-block flex-shrink-0 pt-2" href="#">
                                <img :src="(item.associatedModel && item.associatedModel.image) || ''" :alt="item.name" :title="item.name" style="width: 5rem;">
                            </a>
                            <div class="ps-2">
                                <h6 class="widget-product-title">
                                    <a :href="base_path + ((item.attributes && item.attributes.path) || '')">{{ item.name }}</a>
                                </h6>

                                <div class="widget-product-meta">
                  <span class="text-primary me-2">
                    {{ hasConditions(item)
                      ? (item.associatedModel && item.associatedModel.main_special_text)
                      : (item.associatedModel && item.associatedModel.main_price_text) }}
                  </span>
                                    <span class="text-muted">x {{ item.quantity }}</span>
                                </div>

                                <div class="widget-product-meta" v-if="item.associatedModel && item.associatedModel.secondary_price">
                  <span class="text-dark fs-sm me-2">
                    {{ hasConditions(item)
                      ? (item.associatedModel && item.associatedModel.secondary_special_text)
                      : (item.associatedModel && item.associatedModel.secondary_price_text) }}
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
                        <span class="text-primary fs-base ms-1">{{ fmtMain(cartTotal) }}</span>
                        <span v-if="hasSecondary" class="text-muted">{{ fmtSecondary(cartTotal) }}</span>
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
            const c = this.$store && this.$store.state && this.$store.state.cart
            return c && typeof c === 'object'
                ? c
                : { count: 0, items: [], total: 0, subtotal: 0, detail_con: [], secondary_price: false }
        },
        cartCount() {
            return Number(this.cart.count) || 0
        },
        cartItems() {
            const it = this.cart && this.cart.items
            if (Array.isArray(it)) return it
            if (it && typeof it === 'object') return Object.keys(it).map(k => it[k])
            return []
        },
        cartTotal() {
            return Number(this.cart.total) || 0
        },
        hasCartItems() {
            // vjeruj count-u, ali i fallback na duljinu items
            return (Number(this.cart.count) > 0) || (this.cartItems.length > 0)
        },
        hasSecondary() {
            return !!this.cart.secondary_price
        },
        svc() {
            return (this.$store && this.$store.state && this.$store.state.service) || {}
        }
    },
    mounted() {
        this.checkCart()

        if (window.location.pathname === '/kosarica/success') {
            this.$store.dispatch('flushCart') // pod uvjetom da flushCart NE stavlja state.cart = undefined
        }

        this.mobile = window.innerWidth < 800

        if (window.location.pathname === '/pregled') {
            window.setInterval(this.checkCart, 15000)
        }
    },
    methods: {
        hasConditions(item) {
            return item && item.conditions && Object.keys(item.conditions).length > 0
        },
        fmtMain(value) {
            const s = this.svc
            return s && s.formatMainPrice ? s.formatMainPrice(value) : value
        },
        fmtSecondary(value) {
            const s = this.svc
            return s && s.formatSecondaryPrice ? s.formatSecondaryPrice(value) : value
        },
        checkCart() {
            const storage = this.$store && this.$store.state && this.$store.state.storage
            const storedCart = storage && storage.getCart ? storage.getCart() : null

            if (!storedCart) {
                this.$store.dispatch('getSettings')
                this.$store.dispatch('getCart')
                this.$store.dispatch('checkCart', [])
                return
            }

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
