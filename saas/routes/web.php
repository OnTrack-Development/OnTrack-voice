<?php

use App\Http\Controllers\Api\WidgetController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Controllers\Marketing\PageController;
use App\Http\Controllers\App\AgentStudioController;
use App\Http\Controllers\App\ConversationController;
use App\Http\Controllers\App\CrmController;
use App\Http\Controllers\App\HomeController;
use App\Http\Controllers\App\IntegrationController;
use App\Http\Controllers\App\KnowledgeController;
use App\Http\Controllers\App\KnowledgeSearchController;
use App\Http\Controllers\App\OperationsController;
use App\Http\Controllers\App\OverviewController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\PlanController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\SocialController;
use App\Http\Controllers\App\SmartActionController;
use App\Http\Controllers\App\WhatsappConnectionController;
use App\Http\Controllers\App\VoiceCampaignController;
use App\Http\Controllers\App\TeamController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Control\Ai\ProviderController;
use App\Http\Controllers\Control\Ai\ModelController;
use App\Http\Controllers\Control\Ai\AuditController;
use App\Http\Controllers\Control\DashboardController;
use App\Http\Controllers\Control\PlansController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Marketing\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/sitemap.xml', [PageController::class, 'sitemap']);
Route::get('/{page}', [PageController::class, 'show'])->where('page', 'platform|pricing|security|enterprise|about|contact|developers|docs');
Route::get('/features/{feature}', [PageController::class, 'feature'])->where('feature', 'agent-builder|smart-buttons|knowledge|integrations|channels|inbox|operations');
Route::get('/industries/{industry}', [PageController::class, 'industry'])->where('industry', 'ecommerce|real-estate|hospitality|education|healthcare|logistics|marketing-agencies|hosting|professional-services');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/api/webhooks/meta/whatsapp/{publicId}', [WhatsappWebhookController::class, 'challenge']);
Route::post('/api/webhooks/meta/whatsapp/{publicId}', [WhatsappWebhookController::class, 'receive']);
Route::get('/api/widget/{publicKey}/config', [WidgetController::class, 'config']);
Route::post('/api/widget/{publicKey}/start', [WidgetController::class, 'start']);
Route::post('/api/widget/{publicKey}/message', [WidgetController::class, 'message']);

Route::middleware(['auth', 'platform.admin'])->prefix('control')->name('control.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/plans', [PlansController::class, 'index'])->name('plans.index');
    Route::post('/plans', [PlansController::class, 'store'])->name('plans.store');

    Route::prefix('ai')->name('ai.')->group(function () {
        Route::get('/providers', [ProviderController::class, 'index'])->name('providers.index');
        Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
        Route::delete('/providers/{profile}', [ProviderController::class, 'destroy'])->name('providers.destroy');
        Route::get('/models', [ModelController::class, 'index'])->name('models.index');
        Route::post('/models', [ModelController::class, 'store'])->name('models.store');
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });
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
    Route::get('/{workspace}/knowledge/search', [KnowledgeSearchController::class, 'search'])->name('knowledge.search');

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

    Route::get('/{workspace}/crm', [CrmController::class, 'index'])->name('crm.index');
    Route::post('/{workspace}/crm', [CrmController::class, 'store'])->name('crm.store');

    Route::get('/{workspace}/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/{workspace}/team/invite', [TeamController::class, 'invite'])->name('team.invite');
    Route::post('/{workspace}/team/{membership}/role', [TeamController::class, 'updateRole'])->name('team.updateRole');
    Route::post('/{workspace}/team/{membership}/remove', [TeamController::class, 'remove'])->name('team.remove');
    Route::get('/invitations/{invitation}/accept', [TeamController::class, 'accept'])->name('team.accept');

    Route::get('/{workspace}/operations', [OperationsController::class, 'index'])->name('operations.index');

    Route::get('/{workspace}/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('/{workspace}/integrations', [IntegrationController::class, 'store'])->name('integrations.store');

    Route::get('/{workspace}/voice', [VoiceCampaignController::class, 'index'])->name('voice.index');
    Route::get('/{workspace}/channels/social', [SocialController::class, 'index'])->name('social.index');
    Route::post('/{workspace}/channels/social', [SocialController::class, 'store'])->name('social.store');
    Route::post('/{workspace}/channels/social/{connection}/revoke', [SocialController::class, 'revoke'])->name('social.revoke');
    Route::post('/{workspace}/voice', [VoiceCampaignController::class, 'store'])->name('voice.store');
});