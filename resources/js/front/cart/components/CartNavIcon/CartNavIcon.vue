<template>
    <div class="navbar-tool dropdown ms-1"><a class="navbar-tool-icon-box  dropdown-toggle" :href="carturl"><span class="navbar-tool-label">{{ $store.state.cart ? $store.state.cart.count : 0 }}</span><i class="navbar-tool-icon ci-bag"></i></a>
        <!-- Cart dropdown-->
        <div class="dropdown-menu dropdown-menu-end">
            <div class="widget widget-cart px-3 pt-2 pb-3" style="width: 24rem;" v-if="$store.state.cart.count">
                <div data-simplebar-auto-hide="false" v-for="item in $store.state.cart.items">
                    <div class="widget-cart-item pb-2 border-bottom">
                        <button class="btn-close text-danger" type="button" @click.prevent="removeFromCart(item)" aria-label="Remove"><span aria-hidden="true">&times;</span></button>
                        <div class="d-flex align-items-center">
                            <a class="d-block flex-shrink-0 pt-2" href="#"><img :src="item.associatedModel.image" :alt="item.name" :title="item.name" style="width: 5rem;"></a>
                            <div class="ps-2">
                                <h6 class="widget-product-title"><a :href="base_path + item.attributes.path">{{ item.name }}</a></h6>
                                <div class="widget-product-meta"><span class="text-primary me-2">{{ Object.keys(item.conditions).length ? item.associatedModel.main_special_text : item.associatedModel.main_price_text }}</span><span class="text-muted">x {{ item.quantity }}</span></div>
                                <div class="widget-product-meta"><span class="text-dark fs-sm me-2" v-if="item.associatedModel.secondary_price">{{ Object.keys(item.conditions).length ? item.associatedModel.secondary_special_text : item.associatedModel.secondary_price_text }}</span><span class="text-muted">x {{ item.quantity }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center py-3">
                    <div class="fs-sm me-2 py-2">
                        <span class="text-muted">Ukupno:</span><span class="text-primary fs-base ms-1">{{ $store.state.service.formatMainPrice($store.state.cart.total) }}</span>
                        <span v-if="$store.state.cart.secondary_price" class="text-muted">{{ $store.state.service.formatSecondaryPrice($store.state.cart.total) }}</span>
                    </div>

                </div><a class="btn btn-primary btn-sm d-block w-100" :href="carturl"><i class="ci-card me-2 fs-base align-middle"></i>Dovrši kupnju</a>
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
    //
    data() {
        return {
            base_path: window.location.origin + '/',
            success_path: window.location.origin + '/kosarica/success',
            mobile: false
        }
    },
    //
    mounted() {
        this.checkCart();

        if (window.location.pathname == '/kosarica/success') {
            this.$store.dispatch('flushCart');
        }

        if (window.innerWidth < 800) {
            this.mobile = true;
        }

        if (window.location.pathname == '/pregled') {
            window.setInterval(this.checkCart, 15000);
        }
    },
    //
    methods: {
        /**
         *
         */
        checkCart() {
            let kos = [];
            let cart = this.$store.state.storage.getCart();

            console.log(cart)

            this.$store.dispatch('getSettings');

            if ( ! cart) {
                return this.$store.dispatch('getCart');
            }

            Object.keys(cart.items).forEach(function(key) {
                kos.push(cart.items[key].id)
            });

            this.$store.dispatch('checkCart', kos);
        },

        /**
         *
         * @param item
         */
        removeFromCart(item) {
            this.$store.dispatch('removeFromCart', item);
            //setTimeout(window.location.reload(), 1000);
        }
    }
};
</script>
