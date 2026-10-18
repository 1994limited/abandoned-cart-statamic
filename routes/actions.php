<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\AbandonedCart\Http\Controllers\ActionsController;

// At /!/abandoned-cart/...

// The consent box ({{ abandoned_cart:consent }}). Limited per visitor.
Route::post('consent', [ActionsController::class, 'consent'])->middleware('throttle:abandoned-cart')->name('consent');

// The links in the emails. They're signed, so they can't be guessed or
// changed: the basket link lasts until the reminder expires, unsubscribing
// doesn't expire. Each opens a page with a button, as mail apps and virus
// checkers open links in emails by themselves; only the button (a POST)
// does anything. The signature stands in for a CSRF token: the service
// provider exempts these from Laravel's CSRF check.
Route::get('basket/{reminder}', [ActionsController::class, 'basket'])->name('restore');
Route::post('basket/{reminder}', [ActionsController::class, 'restore'])->name('restore.post');

Route::middleware('signed')->group(function () {
    Route::get('unsubscribe/{reminder}', [ActionsController::class, 'unsubscribePage'])->name('unsubscribe');

    // Mail apps' one-click unsubscribe (RFC 8058) posts here too.
    Route::post('unsubscribe/{reminder}', [ActionsController::class, 'unsubscribe'])->name('unsubscribe.post');
});
