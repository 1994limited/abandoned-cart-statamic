<?php

namespace NineteenNinetyFour\AbandonedCart\Listeners;

use DuncanMcClean\Cargo\Events\CartDeleted;
use DuncanMcClean\Cargo\Events\CartSaved;
use DuncanMcClean\Cargo\Events\OrderCancelled;
use DuncanMcClean\Cargo\Events\OrderCreated;
use DuncanMcClean\Cargo\Events\OrderReturned;
use Illuminate\Events\Dispatcher;
use NineteenNinetyFour\AbandonedCart\Tracker;
use Throwable;

/**
 * Following Cargo's carts and orders. A problem here is reported, never
 * thrown: the shop's own saving and checkout come first.
 */
class CargoSubscriber
{
    public function __construct(protected Tracker $tracker) {}

    public function cartSaved(CartSaved $event): void
    {
        $this->safely(fn () => $this->tracker->track($event->cart));
    }

    public function cartDeleted(CartDeleted $event): void
    {
        $this->safely(fn () => $this->tracker->cartDeleted($event->cart));
    }

    public function orderCreated(OrderCreated $event): void
    {
        $this->safely(fn () => $this->tracker->orderPlaced($event->order));
    }

    public function orderCancelled(OrderCancelled|OrderReturned $event): void
    {
        $this->safely(fn () => $this->tracker->orderCancelled($event->order));
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            CartSaved::class => 'cartSaved',
            CartDeleted::class => 'cartDeleted',
            OrderCreated::class => 'orderCreated',
            OrderCancelled::class => 'orderCancelled',
            OrderReturned::class => 'orderCancelled',
        ];
    }

    protected function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
