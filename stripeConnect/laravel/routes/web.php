<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/stripe/onboard-success', [StripeController::class, 'onboardSuccess'])
    ->name('stripe.onboard.success');

Route::get('/stripe/onboard-retry', [StripeController::class, 'onboardRetry'])
    ->name('stripe.onboard.retry');
