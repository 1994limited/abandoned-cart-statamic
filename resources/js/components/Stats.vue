<!--
    The headline numbers: baskets left, emailed, clicked, recovered.
-->
<script>
import { Panel } from '@statamic/cms/ui';

export default {
    components: { Panel },

    props: {
        stats: { type: Object, required: true },
    },
};
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Panel class="mb-0!">
            <div class="px-4 py-3">
                <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Abandoned') }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ stats.abandoned }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __(':count not emailed', { count: stats.not_emailed }) }}</div>
            </div>
        </Panel>
        <Panel class="mb-0!">
            <div class="px-4 py-3">
                <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Emailed') }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ stats.emailed }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __(stats.reminders === 1 ? '1 reminder' : ':count reminders', { count: stats.reminders }) }}</div>
            </div>
        </Panel>
        <Panel class="mb-0!">
            <div class="px-4 py-3">
                <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Came back') }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ stats.clicked }}<span v-if="stats.click_rate" class="ml-2 text-base font-normal text-gray-600 dark:text-gray-400!">{{ stats.click_rate }}</span></div>
                <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('clicked through to their basket') }}</div>
            </div>
        </Panel>
        <Panel class="mb-0!">
            <div class="px-4 py-3">
                <div class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Recovered') }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ stats.recovered }}<span v-if="stats.recovery_rate" class="ml-2 text-base font-normal text-gray-600 dark:text-gray-400!">{{ stats.recovery_rate }}</span></div>
                <div class="text-sm text-gray-600 dark:text-gray-400!">{{ stats.revenue.length ? stats.revenue.join(' · ') : __('no orders yet') }}</div>
            </div>
        </Panel>
    </div>
</template>
