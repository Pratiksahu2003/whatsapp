<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ScheduledMessageController;
use App\Http\Controllers\BulkMessageController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\PageController;

// Public routes
Route::get('/', function () {
    return Auth::check() ? redirect()->route('whatsapp.dashboard') : redirect()->route('login');
});

// Public pages
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy-policy');

// Authentication routes
Route::get('/login', function () {
    return Auth::check() ? redirect()->route('whatsapp.dashboard') : view('auth.login');
})->name('login');

Route::post('/login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (Auth::attempt($credentials, $request->boolean('remember'))) {
        $request->session()->regenerate();
        return redirect()->intended(route('whatsapp.dashboard'));
    }

    return back()->withErrors([
        'email' => 'The provided credentials do not match our records.',
    ])->onlyInput('email');
});

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

Route::post('/logout', function (\Illuminate\Http\Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

// Protected routes - require authentication
Route::middleware('auth')->group(function () {
    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test-connection', [SettingsController::class, 'testConnection'])->name('settings.test-connection');
    Route::post('/settings/verify-phone', [SettingsController::class, 'verifyPhoneNumber'])->name('settings.verify-phone');
    Route::post('/settings/register-phone', [SettingsController::class, 'registerPhoneNumber'])->name('settings.register-phone');
    Route::post('/settings/approve-phone', [SettingsController::class, 'approvePhoneNumber'])->name('settings.approve-phone');
    
    // WhatsApp API Routes
    Route::prefix('whatsapp')->group(function () {
        // Dashboard
        Route::get('/dashboard', [WhatsAppController::class, 'dashboard'])->name('whatsapp.dashboard');
        
        // Conversations
        Route::get('/conversations', [WhatsAppController::class, 'conversations'])->name('whatsapp.conversations');
        Route::get('/conversation/{phoneNumber}', [WhatsAppController::class, 'conversation'])->name('whatsapp.conversation');
        
        // Export messages
        Route::get('/export', [WhatsAppController::class, 'export'])->name('whatsapp.export');
        
        // Send Message Page (GET route must come before POST route)
        Route::get('/send', [WhatsAppController::class, 'sendPage'])->name('whatsapp.send-page');
        
        // Send message endpoints (POST routes)
        Route::post('/send', [WhatsAppController::class, 'sendMessage'])->name('whatsapp.send');
        Route::post('/send-media', [WhatsAppController::class, 'sendMedia'])->name('whatsapp.send-media');
        Route::post('/send-template', [WhatsAppController::class, 'sendTemplate'])->name('whatsapp.send-template');
        
        // Templates Management
        Route::get('/templates', [TemplateController::class, 'index'])->name('whatsapp.templates');
        Route::post('/templates', [TemplateController::class, 'store'])->name('whatsapp.templates.store');
        Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('whatsapp.templates.update');
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('whatsapp.templates.destroy');
        
        // Contacts Management
        Route::get('/contacts', [ContactController::class, 'index'])->name('whatsapp.contacts');
        Route::post('/contacts', [ContactController::class, 'store'])->name('whatsapp.contacts.store');
        Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('whatsapp.contacts.update');
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('whatsapp.contacts.destroy');
        Route::post('/contacts/import', [ContactController::class, 'import'])->name('whatsapp.contacts.import');
        
        // Scheduled Messages
        Route::get('/scheduled', [ScheduledMessageController::class, 'index'])->name('whatsapp.scheduled');
        Route::post('/scheduled', [ScheduledMessageController::class, 'store'])->name('whatsapp.scheduled.store');
        Route::delete('/scheduled/{scheduledMessage}', [ScheduledMessageController::class, 'destroy'])->name('whatsapp.scheduled.destroy');
        
        // Bulk Messages
        Route::get('/bulk', [BulkMessageController::class, 'index'])->name('whatsapp.bulk');
        Route::post('/bulk', [BulkMessageController::class, 'send'])->name('whatsapp.bulk.send');
        
        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('whatsapp.analytics');
        
        // Message Status & Sync
        Route::post('/sync-pending', [WhatsAppController::class, 'syncPending'])->name('whatsapp.sync-pending');
        Route::get('/message-status/{messageId}', [WhatsAppController::class, 'checkMessageStatus'])->name('whatsapp.message-status');
        Route::post('/mark-delivered/{messageId}', [WhatsAppController::class, 'markAsDelivered'])->name('whatsapp.mark-delivered');
    });
});

// Webhook routes (public, but should be secured in production)
Route::prefix('whatsapp')->group(function () {
    Route::match(['get', 'post'], '/webhook', [WhatsAppController::class, 'webhook'])->name('whatsapp.webhook');
    // Meta uses GET for verification, but allow POST as fallback
    Route::match(['get', 'post'], '/verify', [WhatsAppController::class, 'verify'])->name('whatsapp.verify');
});

// Test webhook endpoint (protected)
Route::middleware('auth')->group(function () {
    Route::get('/whatsapp/test-webhook', [WhatsAppController::class, 'testWebhook'])->name('whatsapp.test-webhook');
    Route::get('/whatsapp/verify-diagnostics', [WhatsAppController::class, 'verifyDiagnostics'])->name('whatsapp.verify-diagnostics');
});
