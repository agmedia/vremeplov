/* */
let storage_cart = {
    name: 'sl_cart',
    cart: { count: 0, items: [], total: 0, subtotal: 0, secondary_price: 0, coupon: null, detail_con: null }
};
let messages = {
    error: 'Whoops!... Greška u vezi sa poslužiteljem!',
    cartAdd: 'Proizvod dodan u košaricu.',
    cartUpdate: 'Količina proizvoda je promjenjena',
    cartRemove: 'Proizvod maknut iz košarice.',
    couponSuccess: 'Kupon je uspješno dodan u košaricu.',
    couponError: 'Nažalost nema kupona pod tim kodom.',
}


class AgService {

    /**
     *
     * @returns {*}
     */
    getCart() {
        return axios.get('cart/get')
        .then(response => response.data)
        .catch(() => {
            // Silent fallback on first load (no toast)
            try { return store.state.storage.getCart() || storage_cart.cart; }
            catch (e) { return storage_cart.cart; }
        });
    }

    /**
     *
     * @param item
     * @returns {*}
     */
    checkCart(ids) {
        // If nothing to validate, skip server call and avoid error toast
        if (!ids || (Array.isArray(ids) && ids.length === 0)) {
            return Promise.resolve({
                cart: (store.state.storage.getCart() || storage_cart.cart),
                message: null
            });
        }

        return axios.post('cart/check', { ids })
        .then(response => response.data)
        .catch(() => ({
            cart: (store.state.storage.getCart() || storage_cart.cart),
            message: null
        }));
    }


    /**
     *
     * @param item
     * @returns {*}
     */
    addToCart(item) {
        return axios.post('cart/add', {item: item})
        .then(response => {
            if (response.data.error) {
                this.returnError(response.data.error);
                return false;
            }


            let product = response.data.items[item.id].associatedModel;

            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                'event': 'add_to_cart',
                'ecommerce': {
                    'items': [ product.dataLayer ]
                }
            });

            this.returnSuccess(messages.cartAdd);
            return response.data
        })
        .catch(error => { return this.returnError(messages.error) })
    }

    /**
     *
     * @param item
     * @returns {*}
     */
    updateCart(item) {
        return axios.post('cart/update/' + item.id, {item: item})
        .then(response => {
            if (response.data.error) {
                this.returnError(response.data.error);
                return false;
            }

            this.returnSuccess(messages.cartUpdate);
            return response.data
        })
        .catch(error => { return this.returnError(messages.error) })
    }

    /**
     *
     * @param item
     * @returns {*}
     */
    removeItem(item) {
        return axios.get('cart/remove/' + item.id)
        .then(response => {
            this.returnSuccess(messages.cartRemove);
            return response.data
        })
        .catch(error => { return this.returnError(messages.error) })
    }

    /**
     *
     * @param coupon
     * @returns {*}
     */
    checkCoupon(coupon) {
        if ( ! coupon) {
            coupon = null;
        }
        return axios.get('cart/coupon/' + coupon)
        .then(response => {
            this.returnSuccess(messages.couponSuccess);
            return response.data
        })
        .catch(error => { return this.returnError(messages.error) })
    }

    /**
     *
     * @returns {*}
     */
    
    getSettings() {
        return axios.get('settings/get')
        .then(response => {
            const data = (response && response.data) ? response.data : null;
            if (data) {
                try { store.state.settings = data; } catch (e) {}
                return data;
            }
            // fallback if response was malformed
            return { 'currency.list': [ { main: true, value: 1, symbol_left: '', symbol_right: '€', decimal_places: 2 } ] };
        })
        .catch(() => {
            // Never return undefined; return last known or a sane default
            const fallback = (store && store.state && store.state.settings) ? store.state.settings : { 'currency.list': [ { main: true, value: 1, symbol_left: '', symbol_right: '€', decimal_places: 2 } ] };
            return fallback;
        });
    }

    /**
     *
     * @param msg
     * @returns {*}
     */
    returnSettings(settings) {
        window.AGSettings = settings;
    }

    /**
     *
     * @param msg
     * @returns {*}
     */
    returnError(msg) {
        window.ToastWarning.fire(msg);
    }

    /**
     *
     * @param msg
     * @returns {*}
     */
    returnSuccess(msg) {
        window.ToastSuccess.fire(msg);
    }

    /**
     * Returns HR formated price string.
     *
     * @param price
     * @returns {string}
     */
    formatPrice(price) {
        return Number(price).toLocaleString('hr-HR', {
            style: 'currency',
            //currencyDisplay: 'narrowSymbol',
            currencyDisplay: 'symbol',
            currency: 'HRK'
        });
    }

    /**
     * Returns HR formated price string.
     *
     * @param price
     * @returns {string}
     */
    
    formatMainPrice(price) {
        // If settings not yet loaded, kick off load but return a synchronous fallback
        if (!store.state.settings) {
            this.getSettings().then((settings) => {
                if (settings && settings['currency.list']) {
                    try { store.state.settings = settings; } catch (e) {}
                }
            });
            return this.formatPrice(price);
        }
        return this.resolvePrice(store.state.settings['currency.list'], price);
    }



    resolvePrice(currency_list, price, main = true) {
        let list = Array.isArray(currency_list) ? currency_list : [];
        if (!list.length) { return this.formatPrice(price); }
        let main_currency = {};

        list.forEach((item) => {
            if (main) {
                if (item.main) {
                    main_currency = item;
                }
            } else {
                if ( ! item.main) {
                    main_currency = item;
                    return;
                }
            }

        });

        let left = main_currency.symbol_left ? main_currency.symbol_left + ' ' : '';
        let right = main_currency.symbol_right ? ' ' + main_currency.symbol_right : '';

        return left + Number(price * main_currency.value).toFixed(main_currency.decimal_places) + right;
    }

    /**
     * Returns HR formated price string.
     *
     * @param price
     * @returns {string}
     */
    
    formatSecondaryPrice(price) {
        if (!store.state.settings) {
            this.getSettings().then((settings) => {
                if (settings && settings['currency.list']) {
                    try { store.state.settings = settings; } catch (e) {}
                }
            });
            return this.formatPrice(price);
        }
        return this.resolvePrice(store.state.settings['currency.list'], price, false);
    }


    /**
     * Calculate tax on items.
     * Item can be number or object.
     *
     * @param items
     * @return {string}
     */
    getDiscountAmount(price, special) {
        let discount = ((price - special) / price) * 100;

        return Math.round(discount).toFixed(0);
    }

    /**
     * Calculate tax on items.
     * Item can be number or object.
     *
     * @param items
     * @return {string}
     */
    calculateItemsTax(items) {
        let tax = 0;

        if (isNaN(items)) {
            for (const key in items) {
                tax += items[key].price - (items[key].price / (Number(items[key].attributes.tax.rate) / 100 + 1));
            }
        } else {
            tax = items - (items / 1.25);
        }

        return tax;
    }
}


class AgStorage {

    /**
     *
     * @returns {JSON}
     */
    getCart() {
        let item = localStorage.getItem(storage_cart.name);

        return (item && item != 'undefined') ? JSON.parse(item) : storage_cart.cart;
    }

    /**
     *
     * @param value
     * @returns localStorage item
     */
    setCart(value) {
        return localStorage.setItem(storage_cart.name, JSON.stringify(value));
    }
}

/**/
let store = {
    state: {
        storage: new AgStorage(),
        service: new AgService(),
        cart: storage_cart.cart,
        messages: messages,
        settings: null,
        fetched: { cart: false }
    },

    actions: {
        /**
         *
         * @param context
         * @returns {*}
         */
        async getCart({ state, commit }) {
            try {
                const cart = await state.service.getCart();
                commit('setCart', cart);
                state.storage.setCart(cart);
            } catch (e) {
                commit('setCart', state.storage.getCart());
            } finally {
                state.fetched = state.fetched || {};
                state.fetched.cart = true;
            }
        },


        /**
         *
         * @param context
         * @param item
         */
        addToCart(context, item) {
            let state = context.state;

            state.service.addToCart(item).then(cart => {
                if (cart) {
                    state.storage.setCart(cart);
                    state.cart = cart;
                }
            });
        },

        /**
         *
         * @param context
         * @param item
         */
        updateCart(context, item) {
            let state = context.state;

            state.service.updateCart(item).then(cart => {
                if (cart) {
                    state.storage.setCart(cart);
                    state.cart = cart;
                }
            });
        },

        /**
         *
         * @param context
         * @param item
         */
        removeFromCart(context, item) {
            let state = context.state;

            state.service.removeItem(item).then(cart => {
                state.storage.setCart(cart);
                state.cart = cart;
            });
        },

        /**
         *
         * @param context
         * @param ids
         */
        checkCart(context, ids) {
            let state = context.state;

            let response ='';

            if (state.storage && response) {

                state.service.checkCart(ids).then(response => {
                    state.storage.setCart(response.cart);

                    if (response.message && window.location.pathname !== '/uspjeh') {
                        const p = window.location.pathname;
                        const isCheckout = p.indexOf('/naplata') === 0 || p.indexOf('/pregled') === 0;

                        // Don’t show toast on checkout to avoid flicker
                        if (!isCheckout) {
                            window.ToastWarningLong.fire(response.message);
                        }

                        // Don’t redirect away from checkout
                        if (!isCheckout && p !== '/kosarica') {
                            window.setTimeout(() => { window.location.href = '/kosarica'; }, 5000);
                        }
                    }
                })
            }
        },

        /**
         *
         * @param context
         * @param coupon
         */
        checkCoupon(context, coupon) {
            let state = context.state;

            state.cart.coupon = coupon;
            state.storage.setCart(state.cart);

            state.service.checkCoupon(coupon).then(response => {
                if (response) {
                    state.service.returnSuccess(messages.couponSuccess);
                } else {
                    state.service.returnError(messages.couponError);
                }

                context.commit('setCart');
            });
        },

        /**
         *
         * @param context
         */
        flushCart(context) {
            context.state.cart = context.state.storage.setCart(storage_cart.cart);
        },

        /**
         *
         * @param context
         * @param item
         */
        getSettings(context, item) {
            let state = context.state;

            state.service.getSettings(item).then(settings => {
                if (settings) {
                    state.settings = settings;
                }
            });
        },
    },

    mutations: {

        /**
         *
         * @param state
         * @returns {*}
         */
        setCart(state, cart) {
            state.cart = cart || storage_cart.cart;
        }
    },
};

export default store;
