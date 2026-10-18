<?php

namespace NineteenNinetyFour\AbandonedCart\Http\Controllers\CP;

use DuncanMcClean\Cargo\Facades\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use NineteenNinetyFour\AbandonedCart\AbandonedCart;
use NineteenNinetyFour\AbandonedCart\Cargo\Carts;
use NineteenNinetyFour\AbandonedCart\Consent;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use NineteenNinetyFour\AbandonedCart\Reports;
use NineteenNinetyFour\AbandonedCart\Sender;
use NineteenNinetyFour\AbandonedCart\Settings;
use NineteenNinetyFour\AbandonedCart\Status;
use NineteenNinetyFour\AbandonedCart\Step;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Toast;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Store → Abandoned Carts: what was left, what was sent, and what came back.
 */
class BasketsController extends CpController
{
    public const FILTERS = ['all', 'waiting', 'emailed', 'not_emailed', 'recovered', 'ordered'];

    public const PERIODS = ['7', '30', '90', 'all'];

    public function __construct(Request $request, protected Reports $reports, protected Carts $carts)
    {
        parent::__construct($request);
    }

    public function index(Request $request): Response
    {
        $this->authorize('view abandoned carts');

        [$search, $show, $period] = $this->filters($request);
        $since = $this->since($period);

        $paginator = $this->query($search, $show, $since)
            ->withCount(['reminders as clicked_count' => fn ($query) => $query->where('test', false)->whereNotNull('clicked_at')])
            ->latest('cart_updated_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('abandoned-cart::Index', [
            'search' => $search,
            'show' => $show,
            'period' => $period,
            'baskets' => collect($paginator->items())->map(fn (Basket $basket) => $this->row($basket))->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'stats' => $this->reports->stats($since),
            'warning' => $this->reports->schedulerWarning(),
            'testMode' => Settings::testMode(),
            'multisite' => Site::multiEnabled(),
            'canExport' => User::current()->can('export abandoned carts'),
            'urls' => [
                'index' => cp_route('abandoned-cart.index'),
                'export' => cp_route('abandoned-cart.export'),
                'settings' => $this->settingsUrl(),
            ],
        ]);
    }

    public function show(Basket $basket): Response
    {
        $this->authorize('view abandoned carts');

        $site = $basket->siteObject();
        $order = null;

        if ($basket->order_id) {
            try {
                $order = Order::find($basket->order_id);
            } catch (Throwable) {
            }
        }

        return Inertia::render('abandoned-cart::Show', [
            'basket' => $this->row($basket) + [
                'name' => $basket->name,
                'customer' => $basket->user_id ? (User::find($basket->user_id)?->editUrl()) : null,
                'consent' => $basket->consent,
                'consent_label' => $this->consentLabel($basket),
                'consent_text' => $basket->consent_text,
                'consent_at' => $basket->consent_at?->toDayDateTimeString(),
                'items' => collect($basket->items ?? [])->map(fn (array $item) => [
                    'title' => $item['title'] ?? '',
                    'variant' => $item['variant_name'] ?? null,
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'total' => $this->carts->format((int) ($item['total'] ?? 0), $site),
                ])->all(),
                'order_url' => $order?->editUrl(),
                'order_total' => $basket->order_total !== null ? $this->carts->format($basket->order_total, $site) : null,
                'ordered_at' => $basket->ordered_at?->toDayDateTimeString(),
                'anonymised' => $basket->anonymised_at !== null,
            ],
            'reminders' => $basket->reminders()->latest('sent_at')->get()->map(fn (Reminder $reminder) => [
                'id' => $reminder->id,
                'step' => $reminder->step,
                'test' => $reminder->test,
                'code' => $reminder->discount_code,
                'sent_at' => $reminder->sent_at?->toDayDateTimeString(),
                'sent_ago' => $reminder->sent_at?->diffForHumans(),
                'expires_at' => $reminder->expires_at?->toFormattedDateString(),
                'viewed_at' => $reminder->viewed_at?->toDayDateTimeString(),
                'clicked_at' => $reminder->clicked_at?->toDayDateTimeString(),
                'clicks' => $reminder->clicks,
            ])->all(),
            'steps' => collect(Settings::steps())->map(fn (Step $step) => [
                'value' => $step->number,
                'label' => __('abandoned-cart::messages.cp.reminder', ['number' => $step->number]),
            ])->values()->all(),
            'canManage' => User::current()->can('manage abandoned carts'),
            'urls' => [
                'index' => cp_route('abandoned-cart.index'),
                'test' => cp_route('abandoned-cart.test', $basket->id),
                'stop' => cp_route('abandoned-cart.stop', $basket->id),
            ],
        ]);
    }

    /**
     * Email a reminder for this basket to whoever's signed in: what the
     * shopper gets (or would get). Nothing about the basket changes.
     */
    public function test(Request $request, Basket $basket, Sender $sender): JsonResponse|RedirectResponse
    {
        $this->authorize('manage abandoned carts');

        $number = (int) $request->input('step', 1);
        $step = collect(Settings::steps())->first(fn (Step $step) => $step->number === $number) ?? Settings::steps()[0];
        $email = User::current()->email();

        $reminder = $email ? $sender->sendTest($basket, $step, $email) : null;

        $message = $reminder
            ? __('abandoned-cart::messages.cp.test_sent', ['email' => $email])
            : __('abandoned-cart::messages.cp.test_failed');

        return $this->done($request, $message, (bool) $reminder);
    }

    public function stop(Request $request, Basket $basket, AbandonedCart $abandonedCart): JsonResponse|RedirectResponse
    {
        $this->authorize('manage abandoned carts');

        $abandonedCart->stop($basket);

        return $this->done($request, __('abandoned-cart::messages.cp.stopped'));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export abandoned carts');

        [$search, $show, $period] = $this->filters($request);
        $query = $this->query($search, $show, $this->since($period))->latest('cart_updated_at');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Email', 'Name', 'Site', 'Items', 'Total', 'Currency', 'Last changed', 'Reminders', 'Status', 'Not emailed because', 'Order', 'Order total', 'Ordered']);

            $query->chunk(500, function ($baskets) use ($out) {
                foreach ($baskets as $basket) {
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $basket->email,
                        $basket->name,
                        $basket->site,
                        $basket->items_count,
                        number_format($basket->total / 100, 2, '.', ''),
                        $basket->currency,
                        $basket->cart_updated_at?->toIso8601String(),
                        $basket->reminders_sent,
                        $basket->status->value,
                        Reports::reason($basket->skip_reason),
                        $basket->order_reference,
                        $basket->order_total !== null ? number_format($basket->order_total / 100, 2, '.', '') : null,
                        $basket->ordered_at?->toIso8601String(),
                    ]));
                }
            });

            fclose($out);
        }, 'abandoned-carts-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Spreadsheets run cells starting = + - @ as formulas: not from shoppers' data.
     */
    public function csvSafe(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @return array{0: string|null, 1: string, 2: string}
     */
    protected function filters(Request $request): array
    {
        $search = $request->query('search');
        $search = is_string($search) && trim($search) !== '' ? mb_substr(trim($search), 0, 200) : null;
        $show = in_array($request->query('show'), self::FILTERS, true) ? $request->query('show') : 'all';
        $period = in_array((string) $request->query('period'), self::PERIODS, true) ? (string) $request->query('period') : '30';

        return [$search, $show, $period];
    }

    protected function since(string $period): ?Carbon
    {
        return $period === 'all' ? null : now()->subDays((int) $period);
    }

    protected function query(?string $search, string $show, ?Carbon $since): Builder
    {
        $query = $show === 'ordered'
            ? Basket::query()->where('items_count', '>', 0)->when($since, fn ($q) => $q->where('cart_updated_at', '>=', $since))
            : $this->reports->abandoned($since);

        return $query
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('email', 'like', '%'.addcslashes(mb_strtolower($search), '%_\\').'%')
                ->orWhere('name', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->when($show === 'waiting', fn ($q) => $q->where('status', Status::Open->value)->where('reminders_sent', 0)->whereNull('skip_reason'))
            ->when($show === 'emailed', fn ($q) => $q->where('reminders_sent', '>', 0))
            ->when($show === 'not_emailed', fn ($q) => $q->where('reminders_sent', 0)->whereNotNull('skip_reason'))
            ->when($show === 'recovered', fn ($q) => $q->where('status', Status::Recovered->value))
            ->when($show === 'ordered', fn ($q) => $q->where('status', Status::Ordered->value));
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Basket $basket): array
    {
        $site = $basket->siteObject();

        return [
            'id' => $basket->id,
            'email' => $basket->email,
            'name' => $basket->name,
            'guest' => $basket->isGuest(),
            'site' => $site->name(),
            'items_count' => $basket->items_count,
            'total' => $this->carts->format($basket->total, $site),
            'updated_at' => $basket->cart_updated_at?->toDayDateTimeString(),
            'updated_ago' => $basket->cart_updated_at?->diffForHumans(),
            'reminders_sent' => $basket->reminders_sent,
            'clicked' => (int) ($basket->clicked_count ?? 0) > 0,
            'status' => $this->status($basket),
            'status_label' => __('abandoned-cart::messages.cp.statuses.'.$this->status($basket)),
            'reason' => Reports::reason($basket->skip_reason),
            'order' => $basket->order_reference,
            'url' => cp_route('abandoned-cart.show', $basket->id),
        ];
    }

    /**
     * One word for the list: waiting, emailed, not_emailed, recovered, ordered, cancelled, deleted.
     */
    protected function status(Basket $basket): string
    {
        return match ($basket->status) {
            Status::Recovered => 'recovered',
            Status::Ordered => 'ordered',
            Status::Cancelled => 'cancelled',
            Status::Deleted => $basket->reminders_sent > 0 ? 'emailed' : 'deleted',
            default => $basket->reminders_sent > 0 ? 'emailed' : ($basket->skip_reason ? 'not_emailed' : 'waiting'),
        };
    }

    protected function consentLabel(Basket $basket): string
    {
        $key = match ($basket->consent) {
            'given' => 'given',
            'refused' => 'refused',
            default => 'none',
        };

        $label = __('abandoned-cart::messages.cp.consent.'.$key);

        return Consent::allows($basket) ? $label : $label.' · '.__('abandoned-cart::messages.cp.consent.not_emailed');
    }

    protected function done(Request $request, string $message, bool $success = true): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : 422);
        }

        $success ? Toast::success($message) : Toast::error($message);

        return back();
    }

    protected function settingsUrl(): ?string
    {
        try {
            $addon = Addon::get(Settings::ADDON);

            return $addon && User::current()->can('editSettings', $addon) ? cp_route('addons.settings.edit', $addon->slug()) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
