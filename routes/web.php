<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory => view('welcome'));

Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])
    ->name('telegram.webhook');
