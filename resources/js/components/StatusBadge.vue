<!--
    A basket's status as a coloured badge.
-->
<script>
import { Badge } from '@statamic/cms/ui';

const colours = { waiting: 'amber', emailed: 'blue', not_emailed: 'gray', recovered: 'green', ordered: 'emerald', cancelled: 'red', deleted: 'gray' };

export default {
    components: { Badge },

    props: {
        status: { type: String, required: true },
        label: { type: String, required: true },
    },

    computed: {
        colour() {
            return colours[this.status] ?? 'default';
        },
    },
};
</script>

<template>
    <Badge :text="label" :color="colour" size="sm" />
</template>
