<?php

use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Controllers\App\AgentStudioController;
use App\Http\Controllers\App\ConversationController;
use App\Http\Controllers\App\HomeController;
use App\Http\Controllers\App\KnowledgeController;
use App\Http\Controllers\App\OverviewController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\PlanController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\SmartActionController;
use App\Http\Controllers\App\WhatsappConnectionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Control\Ai\ProviderController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Marketing\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/api/webhooks/meta/whatsapp/{publicId}', [WhatsappWebhookController::class, 'challenge']);
Route::post('/api/webhooks/meta/whatsapp/{publicId}', [WhatsappWebhookController::class, 'receive']);

Route::middleware('auth')->prefix('control/ai')->name('control.ai.')->group(function () {
    Route::get('/providers', [ProviderController::class, 'index'])->name('providers.index');
    Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
    Route::delete('/providers/{profile}', [ProviderController::class, 'destroy'])->name('providers.destroy');
});

Route::middleware('auth')->prefix('app')->name('app.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/{workspace}/overview', [OverviewController::class, 'index'])->name('overview');

    Route::get('/{workspace}/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/{workspace}/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/{workspace}/projects', [ProjectController::class, 'store'])->name('projects.store');

    Route::get('/{workspace}/agents', [AgentStudioController::class, 'index'])->name('agents.index');
    Route::get('/{workspace}/agents/create', [AgentStudioController::class, 'create'])->name('agents.create');
    Route::post('/{workspace}/agents', [AgentStudioController::class, 'store'])->name('agents.store');
    Route::get('/{workspace}/agents/{agent}', [AgentStudioController::class, 'show'])->name('agents.show');
    Route::put('/{workspace}/agents/{agent}', [AgentStudioController::class, 'update'])->name('agents.update');
    Route::post('/{workspace}/agents/{agent}/publish', [AgentStudioController::class, 'publish'])->name('agents.publish');

    Route::get('/{workspace}/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::post('/{workspace}/knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');

    Route::get('/{workspace}/smart-actions', [SmartActionController::class, 'index'])->name('smart-actions.index');
    Route::post('/{workspace}/smart-actions', [SmartActionController::class, 'store'])->name('smart-actions.store');
    Route::post('/{workspace}/smart-actions/{rule}/invoke', [SmartActionController::class, 'invoke'])->name('smart-actions.invoke');

    Route::get('/{workspace}/inbox', [ConversationController::class, 'index'])->name('inbox.index');
    Route::get('/{workspace}/inbox/{conversation}', [ConversationController::class, 'show'])->name('inbox.show');
    Route::post('/{workspace}/inbox/{conversation}/reply', [ConversationController::class, 'reply'])->name('inbox.reply');
    Route::post('/{workspace}/inbox/{conversation}/lock', [ConversationController::class, 'lock'])->name('inbox.lock');
    Route::post('/{workspace}/inbox/{conversation}/release', [ConversationController::class, 'release'])->name('inbox.release');

    Route::get('/{workspace}/channels/whatsapp', [WhatsappConnectionController::class, 'index'])->name('channels.whatsapp.index');
    Route::post('/{workspace}/channels/whatsapp', [WhatsappConnectionController::class, 'store'])->name('channels.whatsapp.store');

    Route::get('/{workspace}/plan', [PlanController::class, 'index'])->name('plan.index');
    Route::get('/{workspace}/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/{workspace}/settings', [SettingsController::class, 'update'])->name('settings.update');
});