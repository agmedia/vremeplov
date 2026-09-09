<template>
    <section class="catalog-products-section">
        <div class="catalog-toolbar">
            <div class="catalog-toolbar__controls">
                <button class="catalog-toolbar__filter d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#shop-sidebar" aria-controls="shop-sidebar">
                    <i class="fa-duotone" :class="filtersEnabled ? 'fa-sliders' : 'fa-list-tree'" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ filtersEnabled ? 'Otvori filter' : 'Otvori menu' }}</span>
                    <span class="catalog-toolbar__filter-count" v-if="activeFilterCount">{{ activeFilterCount }}</span>
                </button>

                <label class="catalog-toolbar__sort">
                    <span class="visually-hidden">Sortiraj artikle</span>
                    <select class="form-select" v-model="sorting">
                        <option value="">Sortiraj</option>
                        <option value="novi">Najnovije</option>
                        <option value="price_up">Najmanja cijena</option>
                        <option value="price_down">Najveća cijena</option>
                        <option value="naziv_up">A – Ž</option>
                        <option value="naziv_down">Ž – A</option>
                    </select>
                </label>
            </div>

            <div class="catalog-toolbar__view d-lg-none" role="group" aria-label="Broj stupaca">
                <button type="button" v-on:click="setMobileColumns(1)" :class="{'is-active': mobileColumns === 1}" :aria-pressed="mobileColumns === 1 ? 'true' : 'false'" aria-label="Jedan stupac">
                    <i class="fa-regular fa-square" aria-hidden="true"></i>
                </button>
                <button type="button" v-on:click="setMobileColumns(2)" :class="{'is-active': mobileColumns === 2}" :aria-pressed="mobileColumns === 2 ? 'true' : 'false'" aria-label="Dva stupca">
                    <i class="fa-regular fa-grid-2" aria-hidden="true"></i>
                </button>
            </div>

            <p class="catalog-toolbar__total d-none d-lg-block">Ukupno {{ formattedTotal }} artikala</p>
        </div>

        <div class="catalog-products-loading" v-if="!products_loaded" aria-live="polite">
            <span class="spinner-border" role="status"><span class="visually-hidden">Učitavanje artikala</span></span>
        </div>

        <div class="row catalog-products-grid" :class="'catalog-products-grid--mobile-' + mobileColumns" v-if="products_loaded && products.total">
            <div class="catalog-product-col d-flex align-items-stretch" v-for="product in products.data" :key="product.id">
                <article class="card product-card product-card--refined catalog-product-card">
                    <span class="badge bg-warning product-card__badge" v-if="product.quantity <= 0">Rasprodano</span>
                    <span class="badge rounded-pill bg-primary badge-shadow product-card__badge" v-if="product.special">
                        -{{ $store.state.service.getDiscountAmount(product.price, product.special) }}%
                    </span>
                    <a class="card-img-top product-card__media d-block overflow-hidden" :href="origin + product.url">
                        <img loading="lazy" :src="thumbnail(product)" width="250" height="300" :alt="product.card_name || product.name">
                    </a>
                    <div class="card-body product-card__body">
                        <a class="product-card__category" v-if="product.card_category" :href="product.card_category.url">{{ product.card_category.title }}</a>
                        <div class="product-card__rating" v-if="Number(product.reviews_count) > 0" :aria-label="ratingLabel(product)">
                            <span aria-hidden="true">
                                <i v-for="star in 5" :key="star" :class="[star <= roundedRating(product) ? 'fa-solid' : 'fa-regular', 'fa-star']"></i>
                            </span>
                            <small>({{ product.reviews_count }})</small>
                        </div>
                        <h3 class="product-title product-card__title"><a :href="origin + product.url">{{ product.card_name || product.name }}</a></h3>
                        <div class="product-price product-card__previous-price" v-if="product.special">
                            <small>NC 30 dana: {{ product.main_price_text }}</small>
                        </div>
                        <div class="product-price product-card__price">
                            <span>{{ product.special ? product.main_special_text : product.main_price_text }}</span>
                        </div>
                    </div>
                    <div class="product-floating-btn product-card__action" v-if="product.quantity > 0">
                        <button class="btn btn-primary btn-shadow btn-sm" :class="{'is-blocked': product.disabled}" v-on:click="add(product.id, product.quantity)" type="button" :aria-disabled="product.disabled ? 'true' : 'false'" :title="product.disabled ? 'Nema više dostupnih primjeraka ovog artikla' : 'Dodaj u košaricu'" :aria-label="product.disabled ? 'Nema više dostupnih primjeraka artikla ' + (product.card_name || product.name) : 'Dodaj ' + (product.card_name || product.name) + ' u košaricu'">
                            <i class="fa-regular fa-bag-shopping fs-base" aria-hidden="true"></i>
                        </button>
                    </div>
                </article>
            </div>
        </div>

        <div class="catalog-empty-state" v-if="products_loaded && search_zero_result">
            <i class="fa-duotone fa-magnifying-glass" aria-hidden="true"></i>
            <h2>Nema rezultata pretrage</h2>
            <p>Pretraga za <mark>{{ search_query }}</mark> nije pronašla nijedan artikl. Provjerite unos ili pokušajte s kraćim pojmom.</p>
        </div>

        <div class="catalog-empty-state" v-if="products_loaded && navigation_zero_result">
            <i class="fa-duotone fa-books" aria-hidden="true"></i>
            <h2>Trenutno nema artikala</h2>
            <p>Promijenite filtre ili pogledajte neku drugu kategoriju.</p>
        </div>

        <div class="catalog-empty-state" v-if="products_loaded && load_error">
            <i class="fa-duotone fa-circle-exclamation" aria-hidden="true"></i>
            <h2>Artikle trenutačno nije moguće učitati</h2>
            <p>Osvježite stranicu i pokušajte ponovno.</p>
        </div>

        <div class="catalog-pagination-wrap" v-if="products_loaded && products.total">
            <pagination :data="products" align="center" :show-disabled="true" :limit="2" @pagination-change-page="getProductsPage"></pagination>
            <p class="catalog-pagination-summary">
                Prikazano <strong>{{ formattedNumber(products.from) }}–{{ formattedNumber(products.to) }}</strong>
                od <strong>{{ formattedTotal }}</strong> {{ hr_total }}
            </p>
        </div>
    </section>
</template>

<script>
export default {
    name: 'ProductsList',

    props: {
        ids: String,
        group: String,
        cat: String,
        subcat: String,
        author: String,
        publisher: String,
        filtersEnabled: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            products: {},
            autor: '',
            nakladnik: '',
            start: '',
            end: '',
            pismo: '',
            stanje: '',
            uvez: '',
            jezik: '',
            sorting: '',
            search_query: '',
            page: 1,
            mobileColumns: 2,
            origin: location.origin + '/',
            hr_total: 'rezultata',
            products_loaded: false,
            search_zero_result: false,
            navigation_zero_result: false,
            load_error: false,
            requestSequence: 0,
        };
    },

    computed: {
        activeFilterCount() {
            return ['start', 'end', 'autor', 'nakladnik', 'pismo', 'stanje', 'uvez', 'jezik']
                .reduce((total, key) => {
                    const value = this[key];
                    if (!value) {
                        return total;
                    }
                    return total + String(value).split(key === 'autor' || key === 'nakladnik' ? '+' : '|').filter(Boolean).length;
                }, 0);
        },

        formattedTotal() {
            return this.formattedNumber(this.products.total || 0);
        },
    },

    watch: {
        sorting(value) {
            const routeSort = this.$route && this.$route.query ? (this.$route.query.sort || '') : '';
            if (String(value || '') === String(routeSort)) {
                return;
            }

            this.page = 1;
            this.pushQuery();
        },

        $route(route) {
            this.checkQuery(route);
        },
    },

    mounted() {
        const savedColumns = Number(window.localStorage.getItem('vremeplov.catalog.columns'));
        if (savedColumns === 1 || savedColumns === 2) {
            this.mobileColumns = savedColumns;
        }
        this.checkQuery(this.$route);
    },

    methods: {
        checkQuery(route) {
            const query = route && route.query ? route.query : {};
            this.start = query.start || '';
            this.end = query.end || '';
            this.autor = query.autor || '';
            this.nakladnik = this.publisher ? '' : (query.nakladnik || '');
            this.pismo = query.pismo || '';
            this.stanje = query.stanje || '';
            this.uvez = query.uvez || '';
            this.jezik = query.jezik || '';
            this.page = Math.max(1, Number(query.page || 1));
            this.sorting = query.sort || '';
            this.search_query = query.pojam || '';
            this.getProducts();
        },

        getProducts() {
            const sequence = ++this.requestSequence;
            const params = this.setParams();
            this.products_loaded = false;
            this.search_zero_result = false;
            this.navigation_zero_result = false;
            this.load_error = false;

            axios.post('filter/getProducts?page=' + this.page, {params})
                .then(response => {
                    if (sequence !== this.requestSequence) {
                        return;
                    }

                    this.products = response.data || {};
                    this.checkSpecials();
                    this.checkAvailables();
                    this.checkHrTotal();
                    this.hideServerRenderedProducts();

                    if (!this.products.total) {
                        this.search_zero_result = Boolean(params.pojam);
                        this.navigation_zero_result = !params.pojam;
                    }
                })
                .catch(() => {
                    if (sequence === this.requestSequence) {
                        this.load_error = true;
                    }
                })
                .finally(() => {
                    if (sequence === this.requestSequence) {
                        this.products_loaded = true;
                    }
                });
        },

        getProductsPage(page) {
            const target = Math.max(1, Number(page || 1));
            if (target === this.page) {
                return;
            }

            this.page = target;
            this.pushQuery();

            const catalog = document.getElementById('filter-app');
            if (catalog) {
                catalog.scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        },

        pushQuery() {
            this.closeFilter();
            this.$router.push({query: this.resolveQuery()}).catch(() => {});
        },

        resolveQuery() {
            const params = {
                start: this.start,
                end: this.end,
                autor: this.autor,
                nakladnik: this.nakladnik,
                pismo: this.pismo,
                stanje: this.stanje,
                uvez: this.uvez,
                jezik: this.jezik,
                sort: this.sorting,
                pojam: this.search_query,
                page: this.page > 1 ? this.page : '',
            };

            return Object.entries(params).reduce((query, [key, value]) => {
                if (value !== '' && value !== null && typeof value !== 'undefined') {
                    query[key] = value;
                }
                return query;
            }, {});
        },

        setParams() {
            const params = {
                ids: this.ids,
                group: this.group,
                cat: this.cat,
                subcat: this.subcat,
                autor: this.autor,
                nakladnik: this.nakladnik,
                start: this.start,
                end: this.end,
                pismo: this.pismo,
                stanje: this.stanje,
                uvez: this.uvez,
                jezik: this.jezik,
                sort: this.sorting,
                pojam: this.search_query,
            };

            if (this.author) {
                params.autor = this.author;
            }
            if (this.publisher) {
                params.nakladnik = this.publisher;
            }

            return params;
        },

        hideServerRenderedProducts() {
            this.$nextTick(() => {
                const serverProducts = document.getElementById('catalog-ssr');
                if (serverProducts) {
                    serverProducts.hidden = true;
                }
            });
        },

        checkSpecials() {
            (this.products.data || []).forEach(product => {
                if (Number(product.main_price) <= Number(product.main_special)) {
                    product.special = false;
                }
            });
        },

        checkAvailables() {
            const cart = this.$store.state.storage.getCart();
            const cartItems = cart && cart.items ? cart.items : {};

            (this.products.data || []).forEach(product => {
                product.disabled = Object.keys(cartItems).some(key => {
                    const item = cartItems[key];
                    return Number(product.id) === Number(item.id) && Number(product.quantity) <= Number(item.quantity);
                });
            });
        },

        checkHrTotal() {
            const total = Number(this.products.total || 0);
            this.hr_total = total === 1 ? 'rezultat' : 'rezultata';
        },

        setMobileColumns(columns) {
            this.mobileColumns = columns === 1 ? 1 : 2;
            window.localStorage.setItem('vremeplov.catalog.columns', String(this.mobileColumns));
        },

        thumbnail(product) {
            const image = product && product.image ? String(product.image) : '';
            return image.includes('.webp') ? image.replace('.webp', '-thumb.webp') : image;
        },

        formattedNumber(value) {
            return Number(value || 0).toLocaleString('hr-HR');
        },

        roundedRating(product) {
            return Math.max(0, Math.min(5, Math.round(Number(product.reviews_avg_stars || 0))));
        },

        ratingLabel(product) {
            const average = Number(product.reviews_avg_stars || 0).toLocaleString('hr-HR', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1,
            });
            return `Ocjena ${average} od 5 na temelju ${product.reviews_count} recenzija`;
        },

        add(id, productQuantity) {
            const cart = this.$store.state.storage.getCart();
            const cartItems = cart && cart.items ? cart.items : {};

            for (const key in cartItems) {
                if (Number(id) === Number(cartItems[key].id) && Number(productQuantity) <= Number(cartItems[key].quantity)) {
                    return window.ToastWarning.fire('Nema više dostupnih primjeraka ovog artikla.');
                }
            }

            this.$store.dispatch('addToCart', {id, quantity: 1});
        },

        closeFilter() {
            if (window.innerWidth >= 992) {
                return;
            }

            const panel = document.getElementById('shop-sidebar');
            if (panel && window.bootstrap && window.bootstrap.Offcanvas) {
                const instance = window.bootstrap.Offcanvas.getInstance(panel);
                if (instance) {
                    instance.hide();
                }
            }
        },
    },
};
</script>
