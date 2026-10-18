<!--
    One basket: who it's for and what they agreed to, what was in it, the
    reminders sent and what happened, and sending yourself a test.
-->
<script>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Button, Header, Panel, Select, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import StatusBadge from '../components/StatusBadge.vue';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Button, Head, Header, Link, Panel, Select, StatusBadge, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows },

    props: {
        basket: { type: Object, required: true },
        reminders: { type: Array, required: true },
        steps: { type: Array, required: true },
        canManage: { type: Boolean, default: false },
        urls: { type: Object, required: true },
    },

    data() {
        return { step: this.steps[0]?.value ?? 1 };
    },

    computed: {
        icon: () => icon,

        title() {
            return this.basket.email || __('Basket');
        },

        stoppable() {
            return this.canManage && ['waiting', 'emailed'].includes(this.basket.status) && !this.basket.reason;
        },
    },

    methods: {
        sendTest() {
            router.post(this.urls.test, { step: this.step }, { preserveScroll: true });
        },

        stop() {
            if (confirm(__('Send no more reminders for this basket?'))) {
                router.post(this.urls.stop, {}, { preserveScroll: true });
            }
        },
    },
};
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto max-w-5xl">
        <div class="mb-2 text-sm"><Link :href="urls.index" class="hover:underline!">← {{ __('Abandoned Carts') }}</Link></div>

        <Header :title="title" :icon="icon">
            <Button v-if="stoppable" :text="__('Stop reminders')" @click="stop" />
        </Header>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <Panel :heading="__('Basket')">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableColumns>
                                <TableColumn>{{ __('Product') }}</TableColumn>
                                <TableColumn class="text-right">{{ __('Quantity') }}</TableColumn>
                                <TableColumn class="text-right">{{ __('Total') }}</TableColumn>
                            </TableColumns>
                            <TableRows>
                                <TableRow v-for="(item, i) in basket.items" :key="i">
                                    <TableCell>
                                        <div class="font-medium">{{ item.title }}</div>
                                        <div v-if="item.variant" class="text-sm text-gray-600 dark:text-gray-400!">{{ item.variant }}</div>
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">{{ item.quantity }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ item.total }}</TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell class="font-semibold">{{ __('Total') }}</TableCell>
                                    <TableCell />
                                    <TableCell class="text-right font-semibold tabular-nums">{{ basket.total }}</TableCell>
                                </TableRow>
                            </TableRows>
                        </Table>
                    </div>
                </Panel>

                <Panel :heading="__('Reminders')">
                    <div v-if="reminders.length" class="overflow-x-auto">
                        <Table>
                            <TableColumns>
                                <TableColumn>{{ __('Reminder') }}</TableColumn>
                                <TableColumn>{{ __('Sent') }}</TableColumn>
                                <TableColumn>{{ __('Code') }}</TableColumn>
                                <TableColumn>{{ __('Opened the link') }}</TableColumn>
                                <TableColumn>{{ __('Brought the basket back') }}</TableColumn>
                            </TableColumns>
                            <TableRows>
                                <TableRow v-for="reminder in reminders" :key="reminder.id">
                                    <TableCell>
                                        {{ __('Reminder :number', { number: reminder.step }) }}
                                        <span v-if="reminder.test" class="ml-1 text-xs text-gray-600 dark:text-gray-400!">{{ __('test') }}</span>
                                    </TableCell>
                                    <TableCell><span :title="reminder.sent_at">{{ reminder.sent_ago }}</span></TableCell>
                                    <TableCell>
                                        <span v-if="reminder.code" class="font-mono text-sm">{{ reminder.code }}</span>
                                        <span v-else class="text-gray-500">—</span>
                                    </TableCell>
                                    <TableCell>{{ reminder.viewed_at || '—' }}</TableCell>
                                    <TableCell>
                                        {{ reminder.clicked_at || '—' }}
                                        <span v-if="reminder.clicks > 1" class="text-xs text-gray-600 dark:text-gray-400!">({{ reminder.clicks }}×)</span>
                                    </TableCell>
                                </TableRow>
                            </TableRows>
                        </Table>
                    </div>
                    <p v-else class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400!">{{ __('No reminders sent.') }}</p>
                </Panel>
            </div>

            <div>
                <Panel :heading="__('Details')">
                    <dl class="space-y-3 px-4 py-3 text-sm">
                        <div>
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('Status') }}</dt>
                            <dd class="mt-1">
                                <StatusBadge :status="basket.status" :label="basket.status_label" />
                                <div v-if="basket.reason" class="mt-1">{{ basket.reason }}</div>
                            </dd>
                        </div>
                        <div v-if="basket.order">
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('Order') }}</dt>
                            <dd>
                                <a v-if="basket.order_url" :href="basket.order_url" class="hover:underline!">{{ basket.order }}</a>
                                <span v-else>{{ basket.order }}</span>
                                <span v-if="basket.order_total"> · {{ basket.order_total }}</span>
                                <div class="text-gray-600 dark:text-gray-400!">{{ basket.ordered_at }}</div>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('Shopper') }}</dt>
                            <dd>
                                <template v-if="basket.anonymised">{{ __('Removed after the retention period') }}</template>
                                <template v-else>
                                    <div v-if="basket.name">{{ basket.name }}</div>
                                    <div>{{ basket.email || __('No email address') }}</div>
                                    <a v-if="basket.customer" :href="basket.customer" class="hover:underline!">{{ __('Customer account') }}</a>
                                    <div v-else class="text-gray-600 dark:text-gray-400!">{{ __('Guest') }}</div>
                                </template>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('Consent') }}</dt>
                            <dd>
                                {{ basket.consent_label }}
                                <div v-if="basket.consent_at" class="text-gray-600 dark:text-gray-400!">{{ basket.consent_at }}</div>
                                <blockquote v-if="basket.consent_text" class="mt-1 border-l-2 border-gray-300 pl-2 text-gray-700 dark:border-gray-600! dark:text-gray-300!">{{ basket.consent_text }}</blockquote>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('Last changed') }}</dt>
                            <dd>{{ basket.updated_at }} <span class="text-gray-600 dark:text-gray-400!">({{ basket.updated_ago }})</span></dd>
                        </div>
                        <div>
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('Site') }}</dt>
                            <dd>{{ basket.site }}</dd>
                        </div>
                    </dl>
                </Panel>

                <Panel v-if="canManage && basket.items.length" :heading="__('Send yourself a test')">
                    <div class="space-y-3 px-4 py-3 text-sm">
                        <p class="text-gray-600 dark:text-gray-400!">{{ __('Emails you a reminder for this basket, as the shopper would get it. Nothing about the basket changes.') }}</p>
                        <Select v-if="steps.length > 1" v-model="step" :options="steps" option-label="label" option-value="value" :aria-label="__('Reminder')" />
                        <Button :text="__('Send test')" variant="primary" @click="sendTest" />
                    </div>
                </Panel>
            </div>
        </div>
    </div>
</template>
