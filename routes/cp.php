<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\AbandonedCart\Http\Controllers\CP\BasketsController;

Route::prefix('abandoned-carts')->name('abandoned-cart.')->group(function () {
    Route::get('/', [BasketsController::class, 'index'])->name('index');
    Route::get('export', [BasketsController::class, 'export'])->name('export');
    Route::get('{basket}', [BasketsController::class, 'show'])->name('show')->whereNumber('basket');
    Route::post('{basket}/test', [BasketsController::class, 'test'])->name('test')->whereNumber('basket');
    Route::post('{basket}/stop', [BasketsController::class, 'stop'])->name('stop')->whereNumber('basket');
});
