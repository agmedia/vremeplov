<template>
    <renderless-laravel-vue-pagination
        :data="data"
        :limit="limit"
        :show-disabled="showDisabled"
        :size="size"
        :align="align"
        v-on:pagination-change-page="onPaginationChangePage">

        <nav class="catalog-pagination" aria-label="Stranice kataloga"
            v-if="computed.total > computed.perPage"
            slot-scope="{ data, limit, showDisabled, size, align, computed, prevButtonEvents, nextButtonEvents, pageButtonEvents }">
        <ul class="pagination"
            :class="{
                'pagination-sm': size == 'small',
                'pagination-lg': size == 'large',
                'justify-content-center': align == 'center',
                'justify-content-end': align == 'right'
            }">

            <li class="page-item pagination-prev-nav" :class="{'disabled': !computed.prevPageUrl}" v-if="computed.prevPageUrl || showDisabled">
                <a class="page-link" href="#" aria-label="Prethodna" :tabindex="!computed.prevPageUrl && -1" v-on="prevButtonEvents">
                    <slot name="prev-nav">
                        <i class="fa-regular fa-chevron-left" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline">Prethodna</span>
                    </slot>
                </a>
            </li>

            <li class="page-item pagination-page-nav d-none d-sm-block" v-for="(page, key) in computed.pageRange" :key="key" :class="{ 'active': page == computed.currentPage }">
                <a class="page-link" href="#" v-on="pageButtonEvents(page)" :aria-current="page == computed.currentPage ? 'page' : null">
                    {{ page == '...' ? page : Number(page).toLocaleString('hr-HR') }}
                </a>
            </li>

            <li class="page-item pagination-mobile-summary disabled d-sm-none" aria-current="page">
                <span class="page-link">{{ Number(computed.currentPage).toLocaleString('hr-HR') }} / {{ Number(computed.lastPage).toLocaleString('hr-HR') }}</span>
            </li>

            <li class="page-item pagination-next-nav" :class="{'disabled': !computed.nextPageUrl}" v-if="computed.nextPageUrl || showDisabled">
                <a class="page-link" href="#" aria-label="Sljedeća" :tabindex="!computed.nextPageUrl && -1" v-on="nextButtonEvents">
                    <slot name="next-nav">
                        <span class="d-none d-sm-inline">Sljedeća</span>
                        <i class="fa-regular fa-chevron-right" aria-hidden="true"></i>
                    </slot>
                </a>
            </li>

        </ul>
        </nav>

    </renderless-laravel-vue-pagination>
</template>

<script>
import RenderlessLaravelVuePagination from './RenderlessLaravelVuePagination.vue';

export default {
    props: {
        data: {
            type: Object,
            default: () => {}
        },
        limit: {
            type: Number,
            default: 0
        },
        showDisabled: {
            type: Boolean,
            default: false
        },
        size: {
            type: String,
            default: 'default',
            validator: value => {
                return ['small', 'default', 'large'].indexOf(value) !== -1;
            }
        },
        align: {
            type: String,
            default: 'left',
            validator: value => {
                return ['left', 'center', 'right'].indexOf(value) !== -1;
            }
        }
    },

    methods: {
        onPaginationChangePage (page) {
            this.$emit('pagination-change-page', page);
        }
    },

    components: {
        RenderlessLaravelVuePagination
    }
}
</script>
