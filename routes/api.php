<?php

use App\Http\Controllers\Api\ZaloWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/zalo/webhook', [ZaloWebhookController::class, 'handle']);
