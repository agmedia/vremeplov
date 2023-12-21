<template>
    <section class="col">
        <!-- Toolbar-->
        <div class="d-flex justify-content-center justify-content-sm-between align-items-center pt-2 pb-4 pb-sm-2">
            <div class="d-flex flex-wrap">
                <div class="dropdown me-2 d-sm-none"><a class="btn btn-primary dropdown-toggle collapsed" href="#shop-sidebar" data-bs-toggle="collapse" aria-expanded="false"><i class="ci-filter-alt"></i> Kategorije</a></div>
                <div class="d-flex align-items-center flex-nowrap me-3 me-sm-4 pb-3">

                    <select class="form-select pe-2" v-model="sorting">
                        <option value="">Sortiraj</option>
                        <option value="novi">Najnovije</option>
                        <option value="price_up">Najmanja cijena</option>
                        <option value="price_down">Najveća cijena</option>
                        <option value="naziv_up">A - Ž</option>
                        <option value="naziv_down">Ž - A</option>
                    </select>
                </div>
            </div>
            <div class="d-flex pb-3"><span class="fs-sm text-dark btn btn-white btn-sm text-nowrap ms-2 d-none d-sm-block">Ukupno {{ products.total ? Number(products.total).toLocaleString('hr-HR') : 0 }} artikala</span></div>
        </div>
        <!-- Products grid-->
        <div class="row mx-n2 mb-3" v-if="products.total">
            <div class="col-md-3 col-6 px-2 mb-4 d-flex align-items-stretch" v-for="product in products.data">
                <div class="card product-card shadow pb-2">
                    <span class="badge bg-warning mt-1 ms-1"  v-if="product.quantity <= 0">Rasprodano</span>
                    <span class="badge rounded-pill bg-primary mt-1 ms-1 badge-shadow" v-if="product.special">-{{ ($store.state.service.getDiscountAmount(product.price, product.special)) }}%</span>
                       <a class="card-img-top d-block overflow-hidden" :href="origin + product.url">
                           <img load="lazy" :src="product.image.replace('.webp', '-thumb.webp')" width="250" height="300" :alt="product.name">
                    </a>
                    <div class="card-body py-2">
                        <h3 class="product-title fs-sm mt-2 mb-1"><a :href="origin + product.url">{{ product.name }}</a></h3>
                        <div class="product-price">
                            <span class="text-muted"  v-if="product.special"><small>NC 30 dana: {{ product.main_price_text }} </small></span>
                        </div>
                        <div class="product-price">
                            <span class="text-primary" v-if="product.special">{{ product.main_special_text }}</span>
                         </div>
                        <div class="product-price">
                            <span class="text-primary" v-if="!product.special">{{ product.main_price_text }}</span>

                        </div>
                    </div>
                    <div class="product-floating-btn" v-if="product.quantity > 0">
                        <button class="btn btn-primary btn-shadow btn-sm" :disabled="product.disabled" v-on:click="add(product.id, product.quantity)" type="button">+<i class="ci-cart fs-base ms-1"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <pagination :data="products" align="center" :show-disabled="true" :limit="4" @pagination-change-page="getProductsPage"></pagination>

        <div class="row" v-if="!products_loaded">
            <div class="col-md-12 d-flex justify-content-center mt-4">
                <div class="spinner-border text-primary opacity-75" role="status" style="width: 9rem; height: 9rem;"></div>
            </div>
        </div>
        <div class="col-md-12 d-flex justify-content-center mt-4" v-if="products.total">
            <p class="fs-sm">Prikazano
                <span class="font-weight-bolder mx-1">{{ products.from ? Number(products.from).toLocaleString('hr-HR') : 0 }}</span> do
                <span class="font-weight-bolder mx-1">{{ products.to ? Number(products.to).toLocaleString('hr-HR') : 0 }}</span> od
                <span class="font-weight-bold mx-1">{{ products.total ? Number(products.total).toLocaleString('hr-HR') : 0 }}</span> {{ hr_total }}
            </p>
        </div>
        <div class="col-md-12 px-2 mb-4" v-if="products_loaded && search_zero_result">
            <h2>Nema rezultata pretrage</h2>
            <p> Vaša pretraga za  <mark>{{ search_query }}</mark> pronašla je 0 rezultata.</p>
            <h4 class="h5">Savjeti i smjernica</h4>
            <ul class="list-style">
                <li>Dvaput provjerite pravopis.</li>
                <li>Ograničite pretragu na samo jedan ili dva pojma.</li>
                <li>Budite manje precizni u terminologiji. Koristeći više općenitih termina prije ćete doći do sličnih i povezanih proizvoda.</li>
            </ul>
            <hr class="d-sm-none">
        </div>
        <div class="col-md-12 px-2 mb-4" v-if="products_loaded && navigation_zero_result">
            <h2>Trenutno nema proizvoda</h2>
            <p> Pogledajte u nekoj drugoj kategoriji ili probajte sa tražilicom :-)</p>
            <hr class="d-sm-none">
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
        },
        //
        data() {
            return {
                products: {},
                autor: '',
                nakladnik: '',
                start: '',
                end: '',
                sorting: '',
                search_query: '',
                page: 1,
                origin: location.origin + '/',
                hr_total: 'rezultata',
                products_loaded: false,
                search_zero_result: false,
                navigation_zero_result: false,
            }
        },
        //
        watch: {
            sorting(value) {
                this.setQueryParam('sort', value);
            },
            $route(params) {
                this.checkQuery(params);
            }
        },
        //
        mounted() {
            this.checkQuery(this.$route);

            /*console.log('twindow.AGSettings')
            console.log(window.AGSettings)*/
        },

        methods: {
            /**
             *
             */
            getProducts() {
                this.search_zero_result = false;
                this.navigation_zero_result = false;
                this.products_loaded = false;
                let params = this.setParams();

                axios.post('filter/getProducts', { params }).then(response => {
                    this.products_loaded = true;
                    this.products = response.data;
                    this.checkHrTotal();
                    this.checkSpecials();
                    this.checkAvailables();

                    if (params.pojam != '' && !this.products.total) {
                        this.search_zero_result = true;
                    }

                    if (params.pojam == '' && !this.products.total) {
                        this.navigation_zero_result = true;
                    }
                });
            },

            /**
             *
             * @param page
             */
            getProductsPage(page = 1) {
                this.products_loaded = false;
                this.page = page;
                this.setQueryParam('page', page);

                let params = this.setParams();
                window.scrollTo({top: 0, behavior: 'smooth'});

                axios.post('filter/getProducts?page=' + page, { params }).then(response => {
                    this.products_loaded = true;
                    this.products = response.data;
                    this.checkHrTotal();
                    this.checkSpecials();
                    this.checkAvailables();
                });
            },

            /**
             *
             * @param type
             * @param value
             */
            setQueryParam(type, value) {
                this.closeFilter();
                this.$router.push({query: this.resolveQuery()}).catch(()=>{});

                if (value == '' || value == 1) {
                    this.$router.push({query: this.resolveQuery()}).catch(()=>{});
                }
            },

            /**
             *
             * @return {{}}
             */
            resolveQuery() {
                let params = {
                    start: this.start,
                    end: this.end,
                    autor: this.autor,
                    nakladnik: this.nakladnik,
                    sort: this.sorting,
                    pojam: this.search_query,
                    page: this.page
                };

                return Object.entries(params).reduce((acc, [key, val]) => {
                    if (!val) return acc
                    return { ...acc, [key]: val }
                }, {});
            },

            /**
             *
             * @param params
             */
            checkQuery(params) {
                this.start = params.query.start ? params.query.start : '';
                this.end = params.query.end ? params.query.end : '';
                this.autor = params.query.autor ? params.query.autor : '';
                this.nakladnik = params.query.nakladnik ? params.query.nakladnik : '';
                this.page = params.query.page ? params.query.page : '';
                this.sorting = params.query.sort ? params.query.sort : '';
                this.search_query = params.query.pojam ? params.query.pojam : '';

                if (this.page != '') {
                    this.getProductsPage(this.page);
                } else {
                    this.getProducts();
                }
            },

            /**
             *
             * @return {{cat: String, start: string, pojam: string, subcat: String, end: string, sort: string, nakladnik: string, autor: string, group: String}}
             */
            setParams() {
                let params = {
                    ids: this.ids,
                    group: this.group,
                    cat: this.cat,
                    subcat: this.subcat,
                    autor: this.autor,
                    nakladnik: this.nakladnik,
                    start: this.start,
                    end: this.end,
                    sort: this.sorting,
                    pojam: this.search_query
                };

                if (this.author != '') {
                    params.autor = this.author;
                }
                if (this.publisher != '') {
                    params.nakladnik = this.publisher;
                }

                return params;
            },

            /**
             *
             */
            checkSpecials() {
                let now = new Date();

                for (let i = 0; i < this.products.data.length; i++) {
                    if (Number(this.products.data[i].main_price) <= Number(this.products.data[i].main_special)) {
                        this.products.data[i].special = false;
                    }
                }
            },

            /**
             *
             */
            checkAvailables() {
                let cart = this.$store.state.storage.getCart();

                for (let i = 0; i < this.products.data.length; i++) {
                    this.products.data[i].disabled = false;

                    for (const key in cart.items) {
                        if (this.products.data[i].id == cart.items[key].id) {
                            if (this.products.data[i].quantity <= cart.items[key].quantity) {
                                this.products.data[i].disabled = true;
                            }
                        }
                    }
                }
            },

            /**
             *
             */
            checkHrTotal() {
                this.hr_total = 'rezultata';

                if ((this.products.total).toString().slice(-1) == '1') {
                    this.hr_total = 'rezultat';
                }
            },

            /**
             *
             * @param id
             */
            add(id, product_quantity) {
                let cart = this.$store.state.storage.getCart();

                for (const key in cart.items) {
                    if (id == cart.items[key].id) {
                        if (product_quantity <= cart.items[key].quantity) {
                            return window.ToastWarning.fire('Nažalost nema dovoljnih količina artikla..!');
                        }
                    }
                }

                this.$store.dispatch('addToCart', {
                    id: id,
                    quantity: 1
                });
            },

            /**
             *
             */
            closeFilter() {
                $('#shop-sidebar').removeClass('collapse show');
            }
        }
    };
</script>

<style>
</style>
