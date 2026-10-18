<!--
    The dashboard's "Abandoned carts" widget: the period's baskets left,
    emailed, brought back and recovered.
-->
<script>
import { Widget } from '@statamic/cms/ui';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Widget },

    props: {
        title: { type: String, required: true },
        days: { type: Number, default: 30 },
        stats: { type: Object, required: true },
        warning: { type: String, default: null },
        url: { type: String, required: true },
    },

    computed: {
        icon: () => icon,
    },
};
</script>

<template>
    <Widget :title="title" :icon="icon" :href="url">
        <div class="px-4 py-3 text-sm">
            <p v-if="warning" class="mb-3 text-amber-700 dark:text-amber-300!">{{ warning }}</p>
            <dl class="grid grid-cols-2 gap-3">
                <div>
                    <dt class="text-gray-600 dark:text-gray-400!">{{ __('Abandoned') }}</dt>
                    <dd class="text-xl font-semibold tabular-nums">{{ stats.abandoned }}</dd>
                </div>
                <div>
                    <dt class="text-gray-600 dark:text-gray-400!">{{ __('Emailed') }}</dt>
                    <dd class="text-xl font-semibold tabular-nums">{{ stats.emailed }}</dd>
                </div>
                <div>
                    <dt class="text-gray-600 dark:text-gray-400!">{{ __('Came back') }}</dt>
                    <dd class="text-xl font-semibold tabular-nums">{{ stats.clicked }} <span v-if="stats.click_rate" class="text-sm font-normal text-gray-600 dark:text-gray-400!">{{ stats.click_rate }}</span></dd>
                </div>
                <div>
                    <dt class="text-gray-600 dark:text-gray-400!">{{ __('Recovered') }}</dt>
                    <dd class="text-xl font-semibold tabular-nums">{{ stats.recovered }} <span v-if="stats.recovery_rate" class="text-sm font-normal text-gray-600 dark:text-gray-400!">{{ stats.recovery_rate }}</span></dd>
                </div>
            </dl>
            <p class="mt-3 text-gray-600 dark:text-gray-400!">
                <template v-if="stats.revenue.length">{{ __('Recovered :revenue', { revenue: stats.revenue.join(' · ') }) }} · </template>{{ __('last :days days', { days }) }}
            </p>
        </div>
    </Widget>
</template>
