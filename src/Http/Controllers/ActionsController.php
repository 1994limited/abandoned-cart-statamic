<?php

namespace NineteenNinetyFour\AbandonedCart\Http\Controllers;

use DuncanMcClean\Cargo\Facades\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use NineteenNinetyFour\AbandonedCart\AbandonedCart;
use NineteenNinetyFour\AbandonedCart\Consent;
use NineteenNinetyFour\AbandonedCart\Http\ReadsText;
use NineteenNinetyFour\AbandonedCart\Models\Basket;
use NineteenNinetyFour\AbandonedCart\Models\Reminder;
use NineteenNinetyFour\AbandonedCart\Restorer;
use NineteenNinetyFour\AbandonedCart\Settings;
use NineteenNinetyFour\AbandonedCart\Tracker;
use Statamic\Facades\Site;

class ActionsController extends Controller
{
    use ReadsText;

    public function __construct(protected AbandonedCart $abandonedCart, protected Restorer $restorer) {}

    /**
     * The consent box: ticked or unticked, for the visitor's cart. Before
     * there's a cart, it waits in the session for one.
     */
    public function consent(Request $request): JsonResponse|RedirectResponse
    {
        $given = filter_var($this->text($request, 'consent') ?? '0', FILTER_VALIDATE_BOOL);
        $text = Consent::text();

        $request->session()->put(Tracker::SESSION_KEY, ['given' => $given, 'text' => $text]);

        $basket = Cart::hasCurrentCart() && ($cart = Cart::current())->id()
            ? $this->abandonedCart->consent($given, $cart, $text)
            : null;

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'consent' => $given, 'saved' => $basket !== null]);
        }

        return back();
    }

    /**
     * The link in a reminder: the basket as it is now, and a button to
     * bring it back.
     */
    public function basket(Request $request, string $reminder): Response
    {
        $reminder = Reminder::with('basket')->find($reminder);

        if ($response = $this->checkLink($request, $reminder)) {
            return $response;
        }

        if (! $reminder->viewed_at) {
            $reminder->forceFill(['viewed_at' => now()])->save();
        }

        $cart = $this->restorer->cart($reminder);

        if ($problem = $this->restorer->problem($reminder, $cart)) {
            return $this->problem($problem, $reminder->basket);
        }

        $basket = $reminder->basket;
        $site = $basket->siteObject();
        $lines = $this->restorer->lines($cart);

        app()->setLocale($basket->locale());

        return response()->view(config('abandoned-cart.views.basket', 'abandoned-cart::basket'), [
            'title' => __('abandoned-cart::messages.basket.title'),
            'intro' => __('abandoned-cart::messages.basket.intro', ['site' => $site->name()]),
            'lines' => $lines,
            'hasUnavailable' => collect($lines)->contains('available', false),
            'code' => $reminder->discount_code,
            'test' => $reminder->test,
            'action' => $reminder->restorePostUrl(),
            'button' => Settings::text('button', ['site' => $site->name()], $basket->locale()),
            'reminder' => $reminder,
            'basket' => $basket,
            'siteName' => $site->name(),
            'siteUrl' => $site->absoluteUrl(),
            'locale' => str_replace('_', '-', $site->locale()),
            'colour' => Settings::colour(),
            'onColour' => Settings::onColour(),
        ])->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }

    /**
     * The button: the basket becomes the visitor's cart.
     */
    public function restore(Request $request, string $reminder): Response|RedirectResponse
    {
        $reminder = Reminder::with('basket')->find($reminder);

        if ($response = $this->checkLink($request, $reminder)) {
            return $response;
        }

        if ($reminder->test) {
            return $this->page('test_title', __('abandoned-cart::messages.basket.test'), $reminder->basket);
        }

        $cart = $this->restorer->cart($reminder);

        if ($problem = $this->restorer->problem($reminder, $cart)) {
            return $this->problem($problem, $reminder->basket);
        }

        $removed = $this->restorer->restore($reminder, $cart);

        $message = $removed
            ? __('abandoned-cart::messages.restored_removed', ['items' => implode(', ', $removed)], $reminder->basket->locale())
            : __('abandoned-cart::messages.restored', [], $reminder->basket->locale());

        return redirect($this->redirectUrl($reminder->basket))
            ->with('abandoned_cart_restored', $message)
            ->with('abandoned_cart_removed', $removed);
    }

    public function unsubscribePage(string $reminder): Response
    {
        $reminder = Reminder::with('basket')->find($reminder);
        $email = $reminder?->email ?? $reminder?->basket?->email;

        if (! $email) {
            return $this->page('unsubscribed_title', __('abandoned-cart::messages.already_unsubscribed'));
        }

        return $this->page('unsubscribe_title', __('abandoned-cart::messages.page.unsubscribe_text', ['email' => $email]), $reminder->basket, [
            'action' => $reminder->unsubscribePostUrl(),
            'button' => __('abandoned-cart::messages.page.unsubscribe_button'),
        ]);
    }

    public function unsubscribe(Request $request, string $reminder): Response
    {
        $reminder = Reminder::with('basket')->find($reminder);
        $email = $reminder?->email ?? $reminder?->basket?->email;

        // Forgotten since the email (or a test): nothing left to stop.
        if ($email) {
            $this->abandonedCart->unsubscribe($email);
        }

        // Mail apps' one-click unsubscribe just needs a 200.
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('', 200);
        }

        return $this->page('unsubscribed_title', __('abandoned-cart::messages.unsubscribed'), $reminder?->basket);
    }

    /**
     * A reminder's link: signed, and not past its expiry.
     */
    protected function checkLink(Request $request, ?Reminder $reminder): ?Response
    {
        if (! URL::hasCorrectSignature($request)) {
            abort(403);
        }

        if (! $reminder || ! $reminder->basket) {
            return $this->page('gone_title', __('abandoned-cart::messages.basket.gone'));
        }

        if (! URL::signatureHasNotExpired($request) || $reminder->hasExpired()) {
            return $this->page('expired_title', __('abandoned-cart::messages.basket.expired'), $reminder->basket, status: 410);
        }

        return null;
    }

    protected function problem(string $problem, Basket $basket): Response
    {
        return match ($problem) {
            'ordered' => $this->page('ordered_title', __('abandoned-cart::messages.basket.ordered'), $basket),
            'sign_in' => $this->page('sign_in_title', __('abandoned-cart::messages.basket.sign_in'), $basket, link: [
                'url' => config('abandoned-cart.login_url') ?: $basket->siteObject()->absoluteUrl(),
                'text' => __('abandoned-cart::messages.basket.sign_in_button'),
            ]),
            default => $this->page('gone_title', __('abandoned-cart::messages.basket.gone'), $basket, status: 410),
        };
    }

    protected function redirectUrl(Basket $basket): string
    {
        $to = (string) config('abandoned-cart.redirect', '/checkout');

        if (str_starts_with($to, 'http://') || str_starts_with($to, 'https://')) {
            return $to;
        }

        // A path on the basket's own site (/fr/checkout on a French one).
        return rtrim($basket->siteObject()->absoluteUrl(), '/').'/'.ltrim($to, '/');
    }

    /**
     * @param  array<string, string>|null  $form
     * @param  array<string, string>|null  $link
     */
    protected function page(string $title, string $message, ?Basket $basket = null, ?array $form = null, ?array $link = null, int $status = 200): Response
    {
        $site = $basket?->siteObject() ?? Site::current();

        app()->setLocale($basket?->locale() ?? $site->lang());

        return response()->view(config('abandoned-cart.views.page', 'abandoned-cart::page'), [
            'title' => __('abandoned-cart::messages.page.'.$title),
            'message' => $message,
            'form' => $form,
            'link' => $link,
            'siteName' => $site->name(),
            'siteUrl' => $site->absoluteUrl(),
            'locale' => str_replace('_', '-', $site->locale()),
            'colour' => Settings::colour(),
            'onColour' => Settings::onColour(),
        ], $status)->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }
}
