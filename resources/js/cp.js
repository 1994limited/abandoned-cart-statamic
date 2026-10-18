import Index from './pages/Index.vue';
import Show from './pages/Show.vue';
import AbandonedCartWidget from './widgets/AbandonedCartWidget.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('abandoned-cart::Index', Index);
    Statamic.$inertia.register('abandoned-cart::Show', Show);
    Statamic.$components.register('abandoned-cart-widget', AbandonedCartWidget);
});
