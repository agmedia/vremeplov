<template>
    <button
        class="btn btn-primary btn-shadow btn-sm product-card-add-button"
        :class="{'is-blocked': blocked}"
        :disabled="pending"
        :aria-disabled="blocked ? 'true' : 'false'"
        :aria-busy="pending ? 'true' : 'false'"
        :aria-label="buttonLabel"
        :title="buttonLabel"
        :data-product-id="id"
        :data-product-available="availableQuantity"
        @click="add"
        type="button"
    ><i class="fa-regular fa-bag-shopping fs-base" aria-hidden="true"></i></button>
</template>

<script>
export default {
    props: {
        id: String,
        available: String
    },

    data() {
        return {
            quantity: 1,
            has_in_cart: false,
            pending: false
        }
    },

    computed: {
        availableQuantity() {
            const available = Number(this.available);

            return Number.isFinite(available) ? Math.max(0, available) : 0;
        },

        quantityInCart() {
            return this.has_in_cart ? Math.max(0, Number(this.quantity) || 0) : 0;
        },

        soldOut() {
            return this.availableQuantity < 1;
        },

        limitReached() {
            return !this.soldOut && this.quantityInCart >= this.availableQuantity;
        },

        blocked() {
            return this.soldOut || this.limitReached;
        },

        buttonLabel() {
            return this.blocked
                ? 'Nema više dostupnih primjeraka ovog artikla'
                : 'Dodaj u košaricu';
        }
    },

    mounted() {
        this.syncWithCart(this.$store.state.storage.getCart());

        this.$watch(
            () => this.$store.state.cart,
            cart => this.syncWithCart(cart),
            {deep: true}
        );
    },

    methods: {
        syncWithCart(cart) {
            const items = Object.values((cart && cart.items) || {});
            const item = items.find(cartItem => String(cartItem.id) === String(this.id));

            if (item) {
                this.has_in_cart = true;
                this.quantity = Math.max(1, Number(item.quantity) || 1);
                return;
            }

            this.has_in_cart = false;
            this.quantity = 1;
        },

        async add() {
            if (this.soldOut || this.limitReached) {
                if (window.ToastWarning) {
                    window.ToastWarning.fire('Nema više dostupnih primjeraka ovog artikla.');
                }
                return;
            }

            if (this.pending) {
                return;
            }

            const quantityInCart = this.quantityInCart;
            const action = quantityInCart > 0 ? 'updateCart' : 'addToCart';
            const item = quantityInCart > 0
                ? {
                    id: this.id,
                    quantity: quantityInCart + 1,
                    show_add_modal: true,
                    added_quantity: 1
                }
                : {
                    id: this.id,
                    quantity: 1
                };

            this.pending = true;

            try {
                await this.$store.dispatch(action, item);
            } finally {
                this.pending = false;
                this.syncWithCart(this.$store.state.cart || this.$store.state.storage.getCart());
            }
        }
    }
};
</script>

<style>
.product-card-add-button,
.product-card-add-button:disabled {
    opacity: 1 !important;
}

.product-card-add-button.is-blocked {
    cursor: not-allowed;
}
</style>
