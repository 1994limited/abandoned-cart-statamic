<!--
    Store → Abandoned Carts: the period's numbers, and the baskets left,
    latest first, with a search on email or name.
-->
<script>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Button, Header, Input, Panel, Select, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import StatusBadge from '../components/StatusBadge.vue';
import Stats from '../components/Stats.vue';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Button, Head, Header, Input, Link, Panel, Select, Stats, StatusBadge, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows },

    props: {
        search: { type: String, default: null },
        show: { type: String, default: 'all' },
        period: { type: String, default: '30' },
        baskets: { type: Array, required: true },
        pagination: { type: Object, required: true },
        stats: { type: Object, required: true },
        warning: { type: String, default: null },
        testMode: { type: Boolean, default: false },
        multisite: { type: Boolean, default: false },
        canExport: { type: Boolean, default: false },
        urls: { type: Object, required: true },
    },

    data() {
        return { query: this.search ?? '', filter: this.show, days: this.period, timer: null };
    },

    computed: {
        icon: () => icon,

        showOptions() {
            return [
                { value: 'all', label: __('All abandoned') },
                { value: 'waiting', label: __('Waiting for a reminder') },
                { value: 'emailed', label: __('Emailed') },
                { value: 'not_emailed', label: __('Not emailed') },
                { value: 'recovered', label: __('Recovered') },
                { value: 'ordered', label: __('Ordered without a reminder') },
            ];
        },

        periodOptions() {
            return [
                { value: '7', label: __('Last 7 days') },
                { value: '30', label: __('Last 30 days') },
                { value: '90', label: __('Last 90 days') },
                { value: 'all', label: __('All time') },
            ];
        },

        filtered() {
            return Boolean(this.search) || this.show !== 'all';
        },

        exportUrl() {
            const query = new URLSearchParams(this.params()).toString();
            return this.urls.export + (query ? `?${query}` : '');
        },
    },

    watch: {
        query() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.reload(), 300);
        },

        filter() {
            this.reload();
        },

        days() {
            this.reload();
        },
    },

    methods: {
        params(page = 1) {
            const params = {};
            if (this.query) params.search = this.query;
            if (this.filter !== 'all') params.show = this.filter;
            if (this.days !== '30') params.period = this.days;
            if (page > 1) params.page = page;
            return params;
        },

        reload() {
            router.get(this.urls.index, this.params(), { preserveState: true, replace: true });
        },

        pageUrl(page) {
            const query = new URLSearchParams(this.params(page)).toString();
            return this.urls.index + (query ? `?${query}` : '');
        },
    },
};
</script>

<template>
    <Head :title="__('Abandoned Carts')" />

    <div class="mx-auto max-w-6xl">
        <Header :title="__('Abandoned Carts')" :icon="icon">
            <Button v-if="urls.settings" :href="urls.settings" :text="__('Settings')" icon="cog" />
            <Button v-if="canExport" :href="exportUrl" :text="__('Download CSV')" icon="download" />
        </Header>

        <div v-if="warning" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700! dark:bg-amber-950! dark:text-amber-200!" role="status">{{ warning }}</div>
        <div v-if="testMode" class="mb-4 rounded-lg border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-700! dark:bg-blue-950! dark:text-blue-200!" role="status">{{ __('Test mode is on: only the test addresses in the settings are emailed.') }}</div>

        <div class="mb-3 flex justify-end">
            <div class="w-full sm:w-48">
                <Select v-model="days" :options="periodOptions" option-label="label" option-value="value" :aria-label="__('Period')" />
            </div>
        </div>

        <Stats :stats="stats" class="mb-6" />

        <p v-if="stats.reasons.length" class="-mt-3 mb-6 text-sm text-gray-600 dark:text-gray-400!">
            {{ __('Not emailed:') }}
            <span v-for="(reason, i) in stats.reasons" :key="reason.reason">{{ reason.label }} ({{ reason.total }})<span v-if="i < stats.reasons.length - 1">, </span></span>
        </p>

        <div class="mb-3 flex flex-col gap-3 sm:flex-row">
            <div class="grow">
                <Input v-model="query" icon="magnifying-glass" :placeholder="__('Search by email or name')" clearable :aria-label="__('Search')" />
            </div>
            <div class="sm:w-64">
                <Select v-model="filter" :options="showOptions" option-label="label" option-value="value" :aria-label="__('Show')" />
            </div>
        </div>

        <Panel>
            <div v-if="baskets.length" class="overflow-x-auto">
                <Table>
                    <TableColumns>
                        <TableColumn>{{ __('Shopper') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Basket') }}</TableColumn>
                        <TableColumn>{{ __('Left') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Reminders') }}</TableColumn>
                        <TableColumn>{{ __('Status') }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="basket in baskets" :key="basket.id">
                            <TableCell>
                                <Link :href="basket.url" class="font-medium hover:underline!">{{ basket.email || __('No email address') }}</Link>
                                <div class="text-sm text-gray-600 dark:text-gray-400!">
                                    <span v-if="basket.name">{{ basket.name }} · </span>{{ basket.guest ? __('Guest') : __('Customer') }}<span v-if="multisite"> · {{ basket.site }}</span>
                                </div>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                <div class="font-medium">{{ basket.total }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __(basket.items_count === 1 ? '1 item' : ':count items', { count: basket.items_count }) }}</div>
                            </TableCell>
                            <TableCell><span :title="basket.updated_at">{{ basket.updated_ago }}</span></TableCell>
                            <TableCell class="text-right tabular-nums">{{ basket.reminders_sent }}<span v-if="basket.clicked" class="ml-1 text-xs text-gray-600 dark:text-gray-400!">{{ __('clicked') }}</span></TableCell>
                            <TableCell>
                                <StatusBadge :status="basket.status" :label="basket.status_label" />
                                <div v-if="basket.reason" class="mt-1 text-xs text-gray-600 dark:text-gray-400!">{{ basket.reason }}</div>
                                <div v-else-if="basket.order" class="mt-1 text-xs text-gray-600 dark:text-gray-400!">{{ basket.order }}</div>
                            </TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </div>

            <div v-else class="px-6 py-10 text-center">
                <p v-if="filtered" class="text-gray-600 dark:text-gray-400!">{{ __('Nothing matches.') }}</p>
                <template v-else>
                    <p class="font-medium">{{ __('No abandoned baskets yet') }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400!">{{ __('Baskets left without an order show here, with the reminders sent and what came back.') }}</p>
                </template>
            </div>
        </Panel>

        <nav v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-between text-sm" :aria-label="__('Pages')">
            <Link v-if="pagination.page > 1" :href="pageUrl(pagination.page - 1)" class="hover:underline!">← {{ __('Previous') }}</Link>
            <span v-else />
            <span class="text-gray-600 dark:text-gray-400!">{{ __(':page of :last', { page: pagination.page, last: pagination.last_page }) }}</span>
            <Link v-if="pagination.page < pagination.last_page" :href="pageUrl(pagination.page + 1)" class="hover:underline!">{{ __('Next') }} →</Link>
            <span v-else />
        </nav>
    </div>
</template>
