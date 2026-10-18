<?php

namespace NineteenNinetyFour\AbandonedCart\Widgets;

use NineteenNinetyFour\AbandonedCart\Reports;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/**
 * The dashboard's "Abandoned carts" widget: the last 30 days' baskets left,
 * emailed and recovered. Add it to config/statamic/cp.php:
 *
 *     'widgets' => [
 *         ['type' => 'abandoned_cart', 'width' => 50],
 *     ],
 *
 * Options: title, days (30).
 */
class AbandonedCartWidget extends Widget
{
    protected static $handle = 'abandoned_cart';

    public function component()
    {
        if (! User::current()?->can('view abandoned carts')) {
            return;
        }

        $days = max(1, (int) $this->config('days', 30));
        $reports = app(Reports::class);

        return VueComponent::render('abandoned-cart-widget', [
            'title' => $this->config('title', __('abandoned-cart::messages.cp.widget')),
            'days' => $days,
            'stats' => $reports->stats(now()->subDays($days)),
            'warning' => $reports->schedulerWarning(),
            'url' => cp_route('abandoned-cart.index'),
        ]);
    }
}
