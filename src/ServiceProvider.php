<?php

namespace NineteenNinetyFour\AbandonedCart;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\AbandonedCart\Commands\Forget;
use NineteenNinetyFour\AbandonedCart\Commands\Prune;
use NineteenNinetyFour\AbandonedCart\Commands\Send;
use NineteenNinetyFour\AbandonedCart\Listeners\CargoSubscriber;
use NineteenNinetyFour\AbandonedCart\Tags\AbandonedCartTags;
use NineteenNinetyFour\AbandonedCart\Widgets\AbandonedCartWidget;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\YAML;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    /** The Control Panel nav icon (resources/brand/nav-icon.svg). */
    public const ICON = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path d="M4.25 6.25A2.75 2.75 0 0 1 9.75 6.25" stroke="currentColor" stroke-width="1" stroke-linecap="round"></path><path d="M2 6.75H12L11.4 9.5H9.25V12.25H3.2Z" stroke="currentColor" stroke-width="1" stroke-linejoin="round"></path><path d="M9.25 12.25L11.4 9.5" stroke="currentColor" stroke-width="1" stroke-linecap="round"></path></svg>';

    protected $tags = [
        AbandonedCartTags::class,
    ];

    protected $commands = [
        Send::class,
        Prune::class,
        Forget::class,
    ];

    protected $widgets = [
        AbandonedCartWidget::class,
    ];

    protected $subscribe = [
        CargoSubscriber::class,
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    /**
     * The package is 1994/abandoned-cart-statamic; its config, views and
     * translations are read as "abandoned-cart", so they're loaded by hand.
     * Routes too: actions live at /!/abandoned-cart.
     */
    protected $config = false;

    protected $translations = false;

    protected $routes = [
        'web' => null,
        'cp' => __DIR__.'/../routes/cp.php',
        'actions' => null,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/abandoned-cart.php', 'abandoned-cart');

        $this->app->singleton(Cargo\Carts::class);
        $this->app->singleton(Tracker::class);
        $this->app->singleton(Discounts::class);
        $this->app->singleton(Sender::class);
        $this->app->singleton(Restorer::class);
        $this->app->singleton(Reports::class);
        $this->app->singleton(AbandonedCart::class);
    }

    public function bootAddon(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'abandoned-cart');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'abandoned-cart');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([__DIR__.'/../config/abandoned-cart.php' => config_path('abandoned-cart.php')], 'abandoned-cart-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/abandoned-cart')], 'abandoned-cart-views');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/abandoned-cart')], 'abandoned-cart-translations');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'abandoned-cart-migrations');

        $this->registerFrontendRoutes();
        $this->registerSchedule();
        $this->registerSettings();

        Permission::extend(function () {
            Permission::group('abandoned-cart', __('abandoned-cart::messages.permissions.group'), function () {
                Permission::register('view abandoned carts', function ($permission) {
                    $permission->label(__('abandoned-cart::messages.permissions.view'))->children([
                        Permission::make('manage abandoned carts')->label(__('abandoned-cart::messages.permissions.manage')),
                        Permission::make('export abandoned carts')->label(__('abandoned-cart::messages.permissions.export')),
                    ]);
                });
            });
        });

        Nav::extend(function ($nav) {
            $nav->create(__('abandoned-cart::messages.cp.title'))
                ->section('Store')
                ->route('abandoned-cart.index')
                ->icon(self::ICON)
                ->can('view abandoned carts');
        });
    }

    /**
     * Tools → Addons → Abandoned Cart → Settings (resources/settings.yaml),
     * with the shop's Cargo discounts to choose from, and the reminders'
     * defaults from the config, so a setting nobody has touched doesn't
     * override config/abandoned-cart.php.
     */
    protected function registerSettings(): void
    {
        $this->registerSettingsBlueprint(function () {
            $blueprint = YAML::file(__DIR__.'/../resources/settings.yaml')->parse();
            $discounts = app(Discounts::class)->options();
            $configured = array_values((array) config('abandoned-cart.reminders', []));

            foreach ($blueprint['tabs']['reminders']['sections'] as $i => &$section) {
                $config = (array) ($configured[$i] ?? []);
                $discount = $config['discount'] ?? null;

                foreach ($section['fields'] as &$field) {
                    $handle = preg_replace('/^reminder_\d_/', '', $field['handle']);

                    match ($handle) {
                        'enabled' => $field['field']['default'] = (bool) ($config['enabled'] ?? false),
                        'delay' => $field['field']['placeholder'] = (string) ($config['delay'] ?? $field['field']['placeholder']),
                        'discount' => $field['field']['default'] = match (true) {
                            is_string($discount) && $discount !== '' => 'existing',
                            is_array($discount) => 'generate',
                            default => 'none',
                        },
                        'discount_handle' => $field['field'] = array_merge($field['field'], ['options' => $discounts], is_string($discount) ? ['default' => $discount] : []),
                        'discount_type' => is_array($discount) && isset($discount['type']) ? $field['field']['default'] = $discount['type'] : null,
                        'discount_value' => is_array($discount) && isset($discount['value']) ? $field['field']['default'] = $discount['value'] : null,
                        default => null,
                    };
                }
            }

            foreach ($blueprint['tabs']['who']['sections'][0]['fields'] as &$field) {
                if ($field['handle'] === 'consent_mode') {
                    $field['field']['default'] = config('abandoned-cart.consent') === 'soft_opt_in' ? 'soft_opt_in' : 'opt_in';
                }
            }

            foreach ($blueprint['tabs']['who']['sections'][1]['fields'] as &$field) {
                if ($field['handle'] === 'audience') {
                    $field['field']['default'] = in_array(config('abandoned-cart.audience'), ['guests', 'customers'], true) ? config('abandoned-cart.audience') : 'all';
                }
            }

            // The logo needs an asset container: the first one, if there
            // are several. Without one, there's no logo field.
            $container = AssetContainer::all()->first();
            $fields = &$blueprint['tabs']['emails']['sections'][0]['fields'];

            foreach ($fields as $i => &$field) {
                if ($field['handle'] === 'logo') {
                    if ($container) {
                        $field['field']['container'] = $container->handle();
                    } else {
                        unset($fields[$i]);
                    }
                }
            }

            $fields = array_values($fields);

            return $blueprint;
        });
    }

    protected function registerFrontendRoutes(): void
    {
        RateLimiter::for('abandoned-cart', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // The links in the emails post without a CSRF token (mail apps'
        // one-click unsubscribe can't send one); their signature is checked
        // instead. VerifyCsrfToken's list is shared by Laravel 12's
        // ValidateCsrfToken and Laravel 13's PreventRequestForgery, and by
        // a site's own subclass.
        $actions = trim((string) config('statamic.routes.action', '!'), '/');
        VerifyCsrfToken::except([
            $actions.'/abandoned-cart/basket/*',
            $actions.'/abandoned-cart/unsubscribe/*',
        ]);

        Statamic::pushActionRoutes(function () {
            Route::prefix('abandoned-cart')->name('abandoned-cart.')->group(__DIR__.'/../routes/actions.php');
        });
    }

    protected function registerSchedule(): void
    {
        if (! config('abandoned-cart.schedule', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('abandoned-cart:send')->everyMinute()->withoutOverlapping();
            $schedule->command('abandoned-cart:prune')->daily()->withoutOverlapping();
        });
    }
}
