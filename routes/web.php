<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;

Route::get('/', function () {
    return view('welcome');
});

// WhatsApp API Routes
Route::prefix('whatsapp')->group(function () {
    // Webhook verification (GET) and message receiving (POST)
    Route::match(['get', 'post'], '/webhook', [WhatsAppController::class, 'webhook'])->name('whatsapp.webhook');
    Route::get('/verify', [WhatsAppController::class, 'verify'])->name('whatsapp.verify');
    
    // Send message endpoints
    Route::post('/send', [WhatsAppController::class, 'sendMessage'])->name('whatsapp.send');
    Route::post('/send-media', [WhatsAppController::class, 'sendMedia'])->name('whatsapp.send-media');
    Route::post('/send-template', [WhatsAppController::class, 'sendTemplate'])->name('whatsapp.send-template');
});
