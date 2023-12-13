<template>
    <aside class="col-lg-auto">
        <!-- Sidebar-->
        <div class="offcanvas offcanvas-collapse bg-white  rounded-3 shadow-lg py-1 pt-0" id="shop-sidebar" style="max-width: 22rem;">
            <div class="offcanvas-cap bg-white align-items-center shadow-sm">
                <h2 class="h3 mb-0 text-primary">Filtriraj</h2>
                <button class="btn-close ms-auto" type="button" data-bs-dismiss="offcanvas" v-on:click="closeWindow" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body py-grid-gutter px-lg-grid-gutter">
                <!-- Categories-->
                <div class="widget widget-categories mb-2 pb-2 " v-if="categories">
                    <h3 class="widget-title " >Kategorije</h3>
                    <div class="accordion mt-n1" id="shop-categories">
                        <div class="accordion-item " v-for="cat in categories">
                            <h3 class="accordion-header" v-if="category && (category.id == cat.id)" >
                                <a :href="cat.url" v-if="cat.subs " class="accordion-button py-1 none" data-bs-toggle="collapse" :data-bs-target="'#id' + cat.id" aria-expanded="true" :aria-controls="'id'+ cat.id" role="link">
                                    {{ cat.title }} <span class="badge bg-dark ms-2 position-absolute end-0 fw-bold">{{ Number(cat.count).toLocaleString('hr-HR') }}</span>
                                </a>
                                <a :href="cat.url" v-if="!cat.subs" class="accordion-button py-1 none collapsed" role="link">
                                    {{ cat.title }} <span class="badge bg-dark ms-2 position-absolute end-0 fw-bold">{{ Number(cat.count).toLocaleString('hr-HR') }}</span>
                                </a>
                            </h3>

                            <h3 class="accordion-header" v-else>
                                <a :href="cat.url" v-if="cat.subs" class="accordion-button py-1 none collapsed" data-bs-toggle="collapse"  :data-bs-target="'#id' + cat.id" aria-expanded="false" :aria-controls="'id'+ cat.id" role="link">
                                    {{ cat.title }} <span class="badge bg-dark ms-2 position-absolute end-0 fw-bold">{{ Number(cat.count).toLocaleString('hr-HR') }}</span>
                                </a>
                                <a :href="cat.url" v-if="!cat.subs" class="accordion-button py-1 none collapsed" role="link">
                                    {{ cat.title }} <span class="badge bg-dark ms-2 position-absolute end-0 fw-bold">{{ Number(cat.count).toLocaleString('hr-HR') }}</span>
                                </a>
                            </h3>

                             <div class="collapse show" :id="'id'+ cat.id" v-if="cat.subs && category && (category.id == cat.id)" data-bs-parent="#shop-categories">
                                <div class=" pt-2 pb-2 pe-2">
                                    <div class="widget widget-links">
                                        <ul class="widget-list" v-for="subcategory in cat.subs" >
                                            <li class="widget-list-item"><a class="widget-list-link" :href="subcategory.url">{{ subcategory.title }} </a></li>
                                        </ul>
                                        <ul class="widget-list" >
                                            <li class="widget-list-item"><a class="widget-list-link" :href="cat.url">Pogledajte sve</a></li>
                                        </ul>
                                    </div>
                                </div>
                             </div>

                            <div class="collapse" :id="'id'+ cat.id"  v-else data-bs-parent="#shop-categories">
                                <div class=" pt-2 pb-2 pe-2">
                                    <div class="widget widget-links">
                                        <ul class="widget-list" v-for="subcategory in cat.subs" >
                                            <li class="widget-list-item"><a class="widget-list-link" :href="subcategory.url">{{ subcategory.title }} </a></li>
                                        </ul>
                                        <ul class="widget-list" >
                                            <li class="widget-list-item"><a class="widget-list-link" :href="cat.url">Pogledajte sve</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Date range-->
                <div class="widget mb-4 pb-4 border-bottom">
                    <h3 class="widget-title">Godina izdanja</h3>
                    <div >
                        <div class="d-flex pb-1">
                            <div class="w-50 pe-2 me-2">
                                <div class="input-group input-group-sm">
                                    <input class="form-control range-slider-value-min" placeholder="Od" type="text" v-model="start">
                                    <span class="input-group-text">g</span>
                                </div>
                            </div>
                            <div class="w-50 ps-2">
                                <div class="input-group input-group-sm">
                                    <input class="form-control range-slider-value-max" placeholder="Do" type="text" v-model="end">
                                    <span class="input-group-text">g</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Publishers -->
                <div class="widget widget-filter mb-4 pb-4 border-bottom" v-if="show_publishers">
                    <h3 class="widget-title">Nakladnici<span v-if="!publishers_loaded" class="spinner-border spinner-border-sm" style="float: right;"></span></h3>
                    <div class="input-group input-group-sm mb-2 autocomplete">
                        <input type="search" v-model="searchPublisher" class="form-control rounded-end pe-5" placeholder="Pretraži nakladnike"><i class="ci-search position-absolute top-50 end-0 translate-middle-y fs-sm me-3"></i>
                    </div>
                    <ul class="widget-list widget-filter-list list-unstyled pt-1" style="max-height: 11rem;" data-simplebar data-simplebar-auto-hide="false">
                        <li class="widget-filter-item d-flex justify-content-between align-items-center mb-1" v-for="publisher in publishers">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" :id="publisher.slug" :value="publisher.slug" v-model="selectedPublishers">
                                <label class="form-check-label widget-filter-item-text" :for="publisher.slug">{{ publisher.title }}</label>
                            </div><span class="fs-xs text-muted"><a :href="origin + publisher.url">{{ Number(publisher.products_count).toLocaleString('hr-HR') }}</a></span>
                        </li>
                    </ul>
                </div>
                <!-- Authors -->
                <div class="widget widget-filter mb-4 pb-4 border-bottom" v-if="show_authors">
                    <h3 class="widget-title">Autori<span v-if="!authors_loaded" class="spinner-border spinner-border-sm" style="float: right;"></span></h3>
                    <div class="input-group input-group-sm mb-2 autocomplete">
                        <input type="search" v-model="searchAuthor" class="form-control rounded-end pe-5" placeholder="Pretraži autora"><i class="ci-search position-absolute top-50 end-0 translate-middle-y fs-sm me-3"></i>
                    </div>
                    <ul class="widget-list widget-filter-list list-unstyled pt-1" style="max-height: 11rem;" data-simplebar data-simplebar-auto-hide="false">
                        <li class="widget-filter-item d-flex justify-content-between align-items-center mb-1" v-for="author in authors">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" :id="author.slug" :value="author.slug" v-model="selectedAuthors">
                                <label class="form-check-label widget-filter-item-text" :for="author.slug">{{ author.title }}</label>
                            </div><span class="fs-xs text-muted"><a :href="origin + author.url">{{ Number(author.products_count).toLocaleString('hr-HR') }}</a></span>
                        </li>
                    </ul>
                </div>
                <button type="button" class="btn btn-primary mt-4" v-on:click="cleanQuery"><i class=" ci-trash"></i> Očisti sve</button>
            </div>
        </div>
    </aside>
</template>

<script>
    export default {
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
                categories: [],
                category: null,
                subcategory: null,
                authors: [],
                publishers: [],
                selectedAuthors: [],
                selectedPublishers: [],
                start: '',
                end: '',
                autor: '',
                nakladnik: '',
                search_query: '',
                searchAuthor: '',
                searchPublisher: '',
                show_authors: false,
                authors_loaded: false,
                show_publishers: false,
                publishers_loaded: false,
                origin: location.origin + '/'
            }
        },
        //
        watch: {
            start(currentValue) {
                this.setQueryParam('start', currentValue);
            },
            end(currentValue) {
                this.setQueryParam('end', currentValue);
            },
            selectedAuthors(value) {
                this.autor = value.join('+');
                this.setQueryParamOther('autor', this.autor);
            },
            selectedPublishers(value) {
                this.nakladnik = value.join('+');
                this.setQueryParamOther('nakladnik', this.nakladnik);
            },
            searchAuthor(value) {
                if (value.length > 2 || value == '') {
                    return this.getAuthors();
                }
            },
            searchPublisher(value) {
                if (value.length > 2 || value == '') {
                    return this.getPublishers();
                }
            },
            $route(params) {
                this.checkQuery(params);
            }
        },

        //
        mounted() {
            this.checkQuery(this.$route);
            this.checkCategory();
            this.getCategories();

            if (this.author == '') {
                this.show_authors = true;
                this.getAuthors();
            }

            if (this.publisher == '') {
                this.show_publishers = true;
                this.getPublishers();
            }

            this.preselect();
        },

        methods: {
            /**
            *
            **/
            getCategories() {
                let params = this.setParams();

                axios.post('filter/getCategories', { params }).then(response => {
                    this.categories = response.data;

                    if (this.group != '') {
                        this.categories.forEach((item) => {
                            let slug = item.url.substring(item.url.lastIndexOf('/') + 1);

                            if (this.group == slug) {
                                this.category = item;
                            }
                        });
                    }
                });
            },

            /**
             *
             **/
            checkCategory() {
                if (this.cat != '') {
                    this.category = JSON.parse(this.cat);
                }
                if (this.subcat != '') {
                    this.subcategory = JSON.parse(this.subcat);
                }
            },

            /**
             *
             **/
            getAuthors() {
                this.authors_loaded = false;
                let params = this.setParams();

                axios.post('filter/getAuthors', { params }).then(response => {
                    this.authors_loaded = true;
                    this.authors = response.data;
                });
            },

            /**
             *
             **/
            getPublishers() {
                this.publishers_loaded = false;
                let params = this.setParams();

                axios.post('filter/getPublishers', { params }).then(response => {
                    this.publishers_loaded = true;
                    this.publishers = response.data;
                });
            },

            /**
             *
             **/
            setQueryParam(type, value) {
                if (value.length > 3 && value.length < 5) {
                    this.closeWindow();
                    this.$router.push({query: this.resolveQuery()}).catch(()=>{});
                }

                if (value == '') {
                    this.closeWindow();
                    this.$router.push({query: this.resolveQuery()}).catch(()=>{});
                }
            },

            /**
             *
             **/
            setQueryParamOther(type, value) {
                this.closeWindow();
                this.$router.push({query: this.resolveQuery()}).catch(()=>{});

                if (value == '') {
                    this.$router.push({query: this.resolveQuery()}).catch(()=>{});
                }
            },

            /**
             *
             **/
            resolveQuery() {
                let params = {
                    start: this.start,
                    end: this.end,
                    autor: this.autor,
                    nakladnik: this.nakladnik,
                    page: this.page,
                    pojam: this.search_query,
                };

                this.checkNoFollowQuery(params);

                return Object.entries(params).reduce((acc, [key, val]) => {
                    if (!val) return acc
                    return { ...acc, [key]: val }
                }, {});
            },

            /**
             *
             */
            checkNoFollowQuery(param) {
                if (param.nakladnik || param.autor || param.start || param.end) {
                    if (!document.querySelectorAll('meta[name="robots"]').length > 0) {
                        $('head').append('<meta name=robots content=noindex,nofollow>');
                    }
                } else {
                    if (document.querySelectorAll('meta[name="robots"]').length > 0) {
                        document.querySelector("[name='robots']").remove()
                    }
                }
            },

            /**
             *
             **/
            checkQuery(params) {
                this.start = params.query.start ? params.query.start : '';
                this.end = params.query.end ? params.query.end : '';
                this.autor = params.query.autor ? params.query.autor : '';
                this.nakladnik = params.query.nakladnik ? params.query.nakladnik : '';
                this.search_query = params.query.pojam ? params.query.pojam : '';
            },

            /**
             *
             */
            setParams() {
                let params = {
                    ids: this.ids,
                    group: this.group,
                    cat: this.category ? this.category.id : this.cat,
                    subcat: this.subcategory ? this.subcategory.id : this.subcat,
                    author: this.author,
                    publisher: this.publisher,
                    search_author: this.searchAuthor,
                    search_publisher: this.searchPublisher,
                    pojam: this.search_query
                };

                if (this.author != '') {
                    params.author = this.author;
                }
                if (this.publisher != '') {
                    params.publisher = this.publisher;
                }

                return params;
            },

            /**
             *
             */
            preselect() {
                if (this.autor != '') {
                    if ((this.autor).includes('+')) {
                        this.selectedAuthors = (this.autor).split('+');
                    } else {
                        this.selectedAuthors = [this.autor];
                    }
                }
                if (this.nakladnik != '') {
                    if ((this.nakladnik).includes('+')) {
                        this.selectedPublishers = (this.nakladnik).split('+');
                    } else {
                        this.selectedPublishers = [this.nakladnik];
                    }
                }
            },

            /**
             *
             */
            cleanQuery() {
                this.$router.push({query: {}}).catch(()=>{});
                this.selectedAuthors = [];
                this.selectedPublishers = [];
                this.start = '';
                this.end = '';
            },

            /**
             *
             */
            closeWindow() {
                $('#shop-sidebar').removeClass('collapse show');
            }
        }
    };
</script>


<style>

</style>
