<?php

use App\Http\Controllers\Webhook\BitrixWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::any('/leads', [BitrixWebhookController::class, 'leads']);