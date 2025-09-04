<template>
    <a class="d-table-cell handheld-toolbar-item" :href="carturl"><span class="handheld-toolbar-icon"><i class="ci-cart"></i><span class="badge bg-primary rounded-pill ms-1">{{ $store.state.cart ? $store.state.cart.count : 0 }}</span></span><span class="handheld-toolbar-label">Košarica<!--{{ $store.state.service.formatMainPrice(($store.state.cart && $store.state.cart.total) ? $store.state.cart.total : 0) }}--></span></a>
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
        this.getCart();

        if (window.location.pathname == '/kosarica/success') {
            this.$store.dispatch('flushCart');
        }

        if (window.innerWidth < 800) {
            this.mobile = true;
        }
    },
    //
    methods: {
        //
        getCart() {
            this.$store.dispatch('getCart')
        },
        //
        removeFromCart(item) {
            //this.$store.dispatch('removeItem', item);
        }
    }
};
</script>
