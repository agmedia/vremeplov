<template>
    <aside class="catalog-filter-column">
        <div class="offcanvas offcanvas-start offcanvas-collapse catalog-filter-panel" id="shop-sidebar" tabindex="-1" aria-labelledby="shop-sidebar-title">
            <div class="catalog-filter-header">
                <h2 id="shop-sidebar-title">
                    <i class="fa-duotone fa-list-tree d-none d-lg-inline-flex" aria-hidden="true"></i>
                    <i class="fa-duotone d-lg-none" :class="filtersEnabled ? 'fa-sliders' : 'fa-list-tree'" aria-hidden="true"></i>
                    <span class="d-none d-lg-inline">Menu</span>
                    <span class="d-lg-none">{{ filtersEnabled ? 'Filter' : 'Menu' }}</span>
                </h2>
                <button class="catalog-filter-close" type="button" data-bs-dismiss="offcanvas" v-on:click="closeWindow" aria-label="Zatvori filter">
                    <i class="fa-regular fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <a class="catalog-filter-back" :href="backUrl" v-if="backUrl">
                <i class="fa-regular fa-arrow-left" aria-hidden="true"></i>
                <span>Natrag na {{ backLabel || 'prethodnu stranicu' }}</span>
            </a>

            <div class="catalog-filter-body catalog-filter-body--loading" v-if="initialLoading" aria-busy="true">
                <span class="visually-hidden" role="status">Učitavanje izbornika</span>
                <div class="catalog-filter-skeleton-row" v-for="section in 7" :key="section">
                    <span class="catalog-skeleton catalog-skeleton--icon"></span>
                    <span class="catalog-skeleton catalog-skeleton--filter-label"></span>
                    <span class="catalog-skeleton catalog-skeleton--chevron"></span>
                </div>
            </div>

            <div class="catalog-filter-body" v-else>
                <section class="catalog-filter-section" v-if="categories.length">
                    <button class="catalog-filter-section__toggle" type="button" v-on:click="toggleSection('categories')" :aria-expanded="openSections.categories ? 'true' : 'false'">
                        <span><i class="fa-duotone fa-books" aria-hidden="true"></i>Kategorije</span>
                        <i class="fa-regular fa-chevron-down" :class="{'is-open': openSections.categories}" aria-hidden="true"></i>
                    </button>
                    <div class="catalog-filter-section__content" v-show="openSections.categories">
                        <label class="catalog-filter-search" v-if="categories.length > 8">
                            <span class="visually-hidden">Pretraži kategorije</span>
                            <input type="search" v-model.trim="searchCategory" class="form-control" placeholder="Pretraži kategorije">
                            <i class="fa-regular fa-magnifying-glass" aria-hidden="true"></i>
                        </label>
                        <ul class="catalog-filter-options" v-if="filteredCategories.length">
                            <li v-for="item in filteredCategories" :key="item.id">
                                <a class="catalog-filter-category" :class="{'is-active': item.active}" :href="item.url">
                                    <span>{{ item.title }}</span>
                                    <span class="catalog-filter-count">{{ formatCount(item.count) }}</span>
                                </a>
                            </li>
                        </ul>
                        <p class="catalog-filter-empty" v-else>Nema kategorija za taj pojam.</p>
                    </div>
                </section>

                <section class="catalog-filter-section" v-if="filtersEnabled">
                    <button class="catalog-filter-section__toggle" type="button" v-on:click="toggleSection('year')" :aria-expanded="openSections.year ? 'true' : 'false'">
                        <span><i class="fa-duotone fa-calendar-range" aria-hidden="true"></i>Godina izdanja</span>
                        <i class="fa-regular fa-chevron-down" :class="{'is-open': openSections.year}" aria-hidden="true"></i>
                    </button>
                    <div class="catalog-filter-section__content" v-show="openSections.year">
                        <div class="catalog-filter-years">
                            <label>
                                <span class="visually-hidden">Godina od</span>
                                <input class="form-control" inputmode="numeric" maxlength="4" placeholder="Od" type="text" v-model.trim="start" @input="scheduleAvailableFiltersRefresh">
                                <small>g</small>
                            </label>
                            <label>
                                <span class="visually-hidden">Godina do</span>
                                <input class="form-control" inputmode="numeric" maxlength="4" placeholder="Do" type="text" v-model.trim="end" @input="scheduleAvailableFiltersRefresh">
                                <small>g</small>
                            </label>
                        </div>
                    </div>
                </section>

                <section class="catalog-filter-section" v-for="(facet, facetIndex) in characteristics" :key="facet.key">
                    <button class="catalog-filter-section__toggle" type="button" v-on:click="toggleSection(facetSectionKey(facet.key))" :aria-expanded="openSections[facetSectionKey(facet.key)] ? 'true' : 'false'">
                        <span><i class="fa-duotone" :class="facetIcon(facet.key)" aria-hidden="true"></i>{{ facet.title }}</span>
                        <span class="catalog-filter-section__end">
                            <span class="catalog-filter-count" v-if="selectedCharacteristics[facet.key] && selectedCharacteristics[facet.key].length">{{ selectedCharacteristics[facet.key].length }}</span>
                            <i class="fa-regular fa-chevron-down" :class="{'is-open': openSections[facetSectionKey(facet.key)]}" aria-hidden="true"></i>
                        </span>
                    </button>
                    <div class="catalog-filter-section__content" v-show="openSections[facetSectionKey(facet.key)]">
                        <ul class="catalog-filter-options catalog-filter-options--checks catalog-filter-options--scroll">
                            <li v-for="(item, itemIndex) in facet.items" :key="item.value">
                                <label class="catalog-filter-check" :for="facetId(facet.key, facetIndex, itemIndex)">
                                    <input class="form-check-input" type="checkbox" :id="facetId(facet.key, facetIndex, itemIndex)" :value="item.value" v-model="selectedCharacteristics[facet.key]" @change="scheduleAvailableFiltersRefresh">
                                    <span>{{ item.label }}</span>
                                    <span class="catalog-filter-count">{{ formatCount(item.count) }}</span>
                                </label>
                            </li>
                        </ul>
                    </div>
                </section>

                <section class="catalog-filter-section" v-if="filtersEnabled && show_authors">
                    <button class="catalog-filter-section__toggle" type="button" v-on:click="toggleSection('authors')" :aria-expanded="openSections.authors ? 'true' : 'false'">
                        <span><i class="fa-duotone fa-user-pen" aria-hidden="true"></i>Autori</span>
                        <span class="catalog-filter-section__end">
                            <span v-if="authors_loading && openSections.authors" class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Učitavanje</span></span>
                            <i class="fa-regular fa-chevron-down" :class="{'is-open': openSections.authors}" aria-hidden="true"></i>
                        </span>
                    </button>
                    <div class="catalog-filter-section__content" v-show="openSections.authors">
                        <label class="catalog-filter-search">
                            <span class="visually-hidden">Pretraži autore</span>
                            <input type="search" v-model.trim="searchAuthor" class="form-control" placeholder="Pretraži autore">
                            <i class="fa-regular fa-magnifying-glass" aria-hidden="true"></i>
                        </label>
                        <ul class="catalog-filter-options catalog-filter-options--checks catalog-filter-options--scroll">
                            <li v-for="(item, index) in authors" :key="item.slug">
                                <label class="catalog-filter-check" :for="'filter-author-' + index">
                                    <input class="form-check-input" type="checkbox" :id="'filter-author-' + index" :value="item.slug" v-model="selectedAuthors" @change="scheduleAvailableFiltersRefresh">
                                    <span>{{ item.title }}</span>
                                    <span class="catalog-filter-count">{{ formatCount(item.products_count) }}</span>
                                </label>
                            </li>
                        </ul>
                    </div>
                </section>

                <section class="catalog-filter-section" v-if="filtersEnabled && show_publishers">
                    <button class="catalog-filter-section__toggle" type="button" v-on:click="toggleSection('publishers')" :aria-expanded="openSections.publishers ? 'true' : 'false'">
                        <span><i class="fa-duotone fa-building" aria-hidden="true"></i>Nakladnici</span>
                        <span class="catalog-filter-section__end">
                            <span v-if="publishers_loading && openSections.publishers" class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Učitavanje</span></span>
                            <i class="fa-regular fa-chevron-down" :class="{'is-open': openSections.publishers}" aria-hidden="true"></i>
                        </span>
                    </button>
                    <div class="catalog-filter-section__content" v-show="openSections.publishers">
                        <label class="catalog-filter-search">
                            <span class="visually-hidden">Pretraži nakladnike</span>
                            <input type="search" v-model.trim="searchPublisher" class="form-control" placeholder="Pretraži nakladnike">
                            <i class="fa-regular fa-magnifying-glass" aria-hidden="true"></i>
                        </label>
                        <ul class="catalog-filter-options catalog-filter-options--checks catalog-filter-options--scroll">
                            <li v-for="(item, index) in publishers" :key="item.slug">
                                <label class="catalog-filter-check" :for="'filter-publisher-' + index">
                                    <input class="form-check-input" type="checkbox" :id="'filter-publisher-' + index" :value="item.slug" v-model="selectedPublishers" @change="scheduleAvailableFiltersRefresh">
                                    <span>{{ item.title }}</span>
                                    <span class="catalog-filter-count">{{ formatCount(item.products_count) }}</span>
                                </label>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>

            <div class="catalog-filter-actions catalog-filter-actions--loading" v-if="initialLoading && filtersEnabled" aria-hidden="true">
                <span class="catalog-skeleton catalog-skeleton--button"></span>
                <span class="catalog-skeleton catalog-skeleton--button catalog-skeleton--button-wide"></span>
            </div>

            <div class="catalog-filter-actions" v-else-if="filtersEnabled">
                <button class="btn catalog-filter-clear" type="button" v-on:click="cleanQuery" :disabled="!hasActiveFilters">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                    <span>Očisti</span>
                </button>
                <button class="btn btn-primary catalog-filter-apply" type="button" v-on:click="applyFilters">
                    Prikaži rezultate
                </button>
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
        catalogRoot: String,
        backUrl: String,
        backLabel: String,
        filtersEnabled: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            categories: [],
            category: null,
            subcategory: null,
            characteristics: [],
            authors: [],
            publishers: [],
            selectedAuthors: [],
            selectedPublishers: [],
            selectedCharacteristics: {
                pismo: [],
                stanje: [],
                uvez: [],
                jezik: [],
            },
            start: '',
            end: '',
            search_query: '',
            searchCategory: '',
            searchAuthor: '',
            searchPublisher: '',
            show_authors: false,
            authors_loaded: false,
            authors_loading: false,
            authors_dirty: false,
            show_publishers: false,
            publishers_loaded: false,
            publishers_loading: false,
            publishers_dirty: false,
            openSections: {
                categories: true,
                year: false,
                authors: false,
                publishers: false,
            },
            searchTimers: {
                authors: null,
                publishers: null,
            },
            availableFiltersTimer: null,
            characteristicRequestId: 0,
            authorRequestId: 0,
            publisherRequestId: 0,
            entityPrefetchHandle: null,
            entityPrefetchUsesIdleCallback: false,
            categories_loaded: false,
            characteristics_loaded: !this.filtersEnabled,
        };
    },

    computed: {
        filteredCategories() {
            const query = this.normalizeSearchValue(this.searchCategory);
            const availableCategories = this.categories.filter(item => Number(item.count || 0) > 0);

            if (!query) {
                return availableCategories;
            }

            return availableCategories.filter(item => this.normalizeSearchValue(item.title).includes(query));
        },

        activeFilterCount() {
            return (this.start ? 1 : 0)
                + (this.end ? 1 : 0)
                + this.selectedAuthors.length
                + this.selectedPublishers.length
                + Object.values(this.selectedCharacteristics).reduce((total, values) => total + values.length, 0);
        },

        hasActiveFilters() {
            return this.activeFilterCount > 0;
        },

        initialLoading() {
            return !this.categories_loaded || (this.filtersEnabled && !this.characteristics_loaded);
        },
    },

    watch: {
        searchAuthor(value) {
            window.clearTimeout(this.searchTimers.authors);
            if (value.length > 2 || value === '') {
                this.searchTimers.authors = window.setTimeout(() => this.getAuthors(), 250);
            }
        },

        searchPublisher(value) {
            window.clearTimeout(this.searchTimers.publishers);
            if (value.length > 2 || value === '') {
                this.searchTimers.publishers = window.setTimeout(() => this.getPublishers(), 250);
            }
        },

        $route(route) {
            const previousSignature = this.availableFilterSignature();
            this.checkQuery(route);

            if (previousSignature !== this.availableFilterSignature()) {
                this.scheduleAvailableFiltersRefresh();
            }
        },
    },

    mounted() {
        this.category = this.parseEntity(this.cat);
        this.subcategory = this.parseEntity(this.subcat);
        this.checkQuery(this.$route);
        this.getCategories();

        if (this.filtersEnabled) {
            this.getCharacteristics();

            if (!this.author) {
                this.show_authors = true;
            }

            if (!this.publisher) {
                this.show_publishers = true;
            }

            this.deferEntityFiltersLoad();
        }
    },

    beforeDestroy() {
        window.clearTimeout(this.searchTimers.authors);
        window.clearTimeout(this.searchTimers.publishers);
        window.clearTimeout(this.availableFiltersTimer);

        if (this.entityPrefetchUsesIdleCallback && typeof window.cancelIdleCallback === 'function') {
            window.cancelIdleCallback(this.entityPrefetchHandle);
        } else {
            window.clearTimeout(this.entityPrefetchHandle);
        }
    },

    methods: {
        getCategories() {
            axios.post('filter/getCategories', {params: this.setParams()})
                .then(response => {
                    this.categories = Array.isArray(response.data) ? response.data : [];
                })
                .catch(() => {
                    this.categories = [];
                })
                .finally(() => {
                    this.categories_loaded = true;
                });
        },

        getCharacteristics() {
            const requestId = ++this.characteristicRequestId;

            axios.post('filter/getCharacteristics', {params: this.setParams()})
                .then(response => {
                    if (requestId !== this.characteristicRequestId) {
                        return;
                    }

                    this.characteristics = Array.isArray(response.data) ? response.data : [];
                })
                .catch(() => {
                    if (requestId === this.characteristicRequestId) {
                        this.characteristics = [];
                    }
                })
                .finally(() => {
                    if (requestId === this.characteristicRequestId) {
                        this.characteristics_loaded = true;
                    }
                });
        },

        getAuthors() {
            const requestId = ++this.authorRequestId;
            const filterSignature = this.availableFilterSignature();
            this.authors_loading = true;
            axios.post('filter/getAuthors', {params: this.setParams()})
                .then(response => {
                    if (requestId !== this.authorRequestId) {
                        return;
                    }

                    this.authors = Array.isArray(response.data) ? response.data : [];
                    this.selectedAuthors = this.syncSelectedEntityGroups(this.selectedAuthors, this.authors);
                    this.authors_loaded = true;
                    this.authors_dirty = filterSignature !== this.availableFilterSignature();
                })
                .catch(() => {
                    if (requestId === this.authorRequestId) {
                        this.authors = [];
                        this.authors_loaded = false;
                        this.authors_dirty = true;
                    }
                })
                .finally(() => {
                    if (requestId === this.authorRequestId) {
                        this.authors_loading = false;
                    }
                });
        },

        getPublishers() {
            const requestId = ++this.publisherRequestId;
            const filterSignature = this.availableFilterSignature();
            this.publishers_loading = true;
            axios.post('filter/getPublishers', {params: this.setParams()})
                .then(response => {
                    if (requestId !== this.publisherRequestId) {
                        return;
                    }

                    this.publishers = Array.isArray(response.data) ? response.data : [];
                    this.selectedPublishers = this.syncSelectedEntityGroups(this.selectedPublishers, this.publishers);
                    this.publishers_loaded = true;
                    this.publishers_dirty = filterSignature !== this.availableFilterSignature();
                })
                .catch(() => {
                    if (requestId === this.publisherRequestId) {
                        this.publishers = [];
                        this.publishers_loaded = false;
                        this.publishers_dirty = true;
                    }
                })
                .finally(() => {
                    if (requestId === this.publisherRequestId) {
                        this.publishers_loading = false;
                    }
                });
        },

        scheduleAvailableFiltersRefresh() {
            this.authors_dirty = true;
            this.publishers_dirty = true;
            window.clearTimeout(this.availableFiltersTimer);
            this.availableFiltersTimer = window.setTimeout(() => {
                this.getCharacteristics();

                if (this.show_authors && this.openSections.authors) {
                    this.getAuthors();
                }

                if (this.show_publishers && this.openSections.publishers) {
                    this.getPublishers();
                }
            }, 200);
        },

        deferEntityFiltersLoad() {
            const load = () => {
                if (this.show_authors && !this.authors_loaded && !this.authors_loading) {
                    this.getAuthors();
                }

                if (this.show_publishers && !this.publishers_loaded && !this.publishers_loading) {
                    this.getPublishers();
                }
            };

            if (typeof window.requestIdleCallback === 'function') {
                this.entityPrefetchUsesIdleCallback = true;
                this.entityPrefetchHandle = window.requestIdleCallback(load, {timeout: 1200});
                return;
            }

            this.entityPrefetchHandle = window.setTimeout(load, 400);
        },

        parseEntity(value) {
            if (!value) {
                return null;
            }

            if (typeof value === 'object') {
                return value;
            }

            try {
                const entity = JSON.parse(value);
                return entity && typeof entity === 'object' ? entity : null;
            } catch (error) {
                return /^\d+$/.test(String(value)) ? {id: Number(value)} : null;
            }
        },

        parseList(value, separator) {
            if (!value) {
                return [];
            }

            if (Array.isArray(value)) {
                return value.filter(Boolean);
            }

            return String(value).split(separator).map(item => item.trim()).filter(Boolean);
        },

        syncSelectedEntityGroups(selected, items) {
            const resolved = (selected || []).map(value => {
                const aliases = String(value).split(',').filter(Boolean);
                const matching = (items || []).find(item => {
                    const itemAliases = Array.isArray(item.slugs) ? item.slugs : String(item.slug || '').split(',');
                    return aliases.some(alias => itemAliases.includes(alias));
                });

                return matching ? matching.slug : value;
            });

            return Array.from(new Set(resolved));
        },

        checkQuery(route) {
            const query = route && route.query ? route.query : {};
            this.start = query.start || '';
            this.end = query.end || '';
            this.search_query = query.pojam || '';
            this.selectedAuthors = this.parseList(query.autor, '+');
            this.selectedPublishers = this.publisher ? [] : this.parseList(query.nakladnik, '+');

            ['pismo', 'stanje', 'uvez', 'jezik'].forEach(key => {
                const selected = this.parseList(query[key], '|');
                this.$set(this.selectedCharacteristics, key, selected);
                this.$set(this.openSections, this.facetSectionKey(key), selected.length > 0);
            });
        },

        setParams() {
            const params = {
                ids: this.ids,
                group: this.group,
                cat: this.category ? this.category.id : this.cat,
                subcat: this.subcategory ? this.subcategory.id : this.subcat,
                author: this.author,
                publisher: this.publisher,
                catalog_root: this.catalogRoot,
                autor: this.selectedAuthors.join('+'),
                nakladnik: this.selectedPublishers.join('+'),
                start: this.start,
                end: this.end,
                pismo: this.selectedCharacteristics.pismo.join('|'),
                stanje: this.selectedCharacteristics.stanje.join('|'),
                uvez: this.selectedCharacteristics.uvez.join('|'),
                jezik: this.selectedCharacteristics.jezik.join('|'),
                search_author: this.searchAuthor,
                search_publisher: this.searchPublisher,
                pojam: this.search_query,
            };

            if (this.author) {
                params.author = this.author;
            }
            if (this.publisher) {
                params.publisher = this.publisher;
                params.nakladnik = this.publisher;
            }

            return params;
        },

        resolveQuery() {
            const current = this.$route && this.$route.query ? this.$route.query : {};
            const params = {
                pojam: current.pojam || this.search_query,
                sort: current.sort || '',
                start: this.start,
                end: this.end,
                autor: this.selectedAuthors.join('+'),
                nakladnik: this.selectedPublishers.join('+'),
                pismo: this.selectedCharacteristics.pismo.join('|'),
                stanje: this.selectedCharacteristics.stanje.join('|'),
                uvez: this.selectedCharacteristics.uvez.join('|'),
                jezik: this.selectedCharacteristics.jezik.join('|'),
            };

            this.checkNoFollowQuery(params);

            return Object.entries(params).reduce((query, [key, value]) => {
                if (value !== '' && value !== null && typeof value !== 'undefined') {
                    query[key] = value;
                }
                return query;
            }, {});
        },

        applyFilters() {
            this.$router.push({query: this.resolveQuery()}).catch(() => {});
            this.closeWindow();
        },

        cleanQuery() {
            this.start = '';
            this.end = '';
            this.searchCategory = '';
            this.selectedAuthors = [];
            this.selectedPublishers = [];
            ['pismo', 'stanje', 'uvez', 'jezik'].forEach(key => this.$set(this.selectedCharacteristics, key, []));

            this.$nextTick(() => {
                this.scheduleAvailableFiltersRefresh();
                this.$router.push({query: this.resolveQuery()}).catch(() => {});
            });
        },

        availableFilterSignature() {
            return JSON.stringify({
                start: this.start,
                end: this.end,
                autor: this.selectedAuthors,
                nakladnik: this.selectedPublishers,
                pismo: this.selectedCharacteristics.pismo,
                stanje: this.selectedCharacteristics.stanje,
                uvez: this.selectedCharacteristics.uvez,
                jezik: this.selectedCharacteristics.jezik,
            });
        },

        checkNoFollowQuery(params) {
            const hasFilters = ['start', 'end', 'autor', 'nakladnik', 'pismo', 'stanje', 'uvez', 'jezik']
                .some(key => Boolean(params[key]));
            let tag = document.querySelector('meta[name="robots"][data-catalog-filter]');

            if (hasFilters && !document.querySelector('meta[name="robots"]')) {
                tag = document.createElement('meta');
                tag.name = 'robots';
                tag.content = 'noindex,nofollow';
                tag.setAttribute('data-catalog-filter', 'true');
                document.head.appendChild(tag);
            } else if (!hasFilters && tag) {
                tag.remove();
            }
        },

        toggleSection(section) {
            const willOpen = !this.openSections[section];
            this.$set(this.openSections, section, willOpen);

            if (willOpen && section === 'authors' && (!this.authors_loaded || this.authors_dirty)) {
                this.getAuthors();
            }

            if (willOpen && section === 'publishers' && (!this.publishers_loaded || this.publishers_dirty)) {
                this.getPublishers();
            }
        },

        closeWindow() {
            if (window.innerWidth >= 992) {
                return;
            }

            const panel = document.getElementById('shop-sidebar');
            if (panel && window.bootstrap && window.bootstrap.Offcanvas) {
                const instance = window.bootstrap.Offcanvas.getInstance(panel);
                if (instance) {
                    instance.hide();
                    return;
                }
            }

            if (panel) {
                panel.classList.remove('show');
                panel.setAttribute('aria-hidden', 'true');
            }
            document.querySelectorAll('.offcanvas-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('offcanvas-open');
            document.body.style.removeProperty('overflow');
        },

        facetId(key, facetIndex, itemIndex) {
            return `filter-${key}-${facetIndex}-${itemIndex}`;
        },

        facetSectionKey(key) {
            return `facet-${key}`;
        },

        facetIcon(key) {
            return {
                pismo: 'fa-font',
                stanje: 'fa-star',
                uvez: 'fa-book',
                jezik: 'fa-language',
            }[key] || 'fa-filter';
        },

        normalizeSearchValue(value) {
            return String(value || '')
                .toLocaleLowerCase('hr-HR')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        },

        formatCount(value) {
            return Number(value || 0).toLocaleString('hr-HR');
        },
    },
};
</script>
