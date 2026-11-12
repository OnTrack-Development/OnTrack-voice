<?php

use App\Http\Controllers\Api\KashierWebhookController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Controllers\Api\WidgetController;
use App\Http\Controllers\App\AgentStudioController;
use App\Http\Controllers\App\ConversationController;
use App\Http\Controllers\App\CrmController;
use App\Http\Controllers\App\FlowBuilderController;
use App\Http\Controllers\App\HomeController;
use App\Http\Controllers\App\IntegrationController;
use App\Http\Controllers\App\KnowledgeController;
use App\Http\Controllers\App\KnowledgeSearchController;
use App\Http\Controllers\App\OperationsController;
use App\Http\Controllers\App\OverviewController;
use App\Http\Controllers\App\PlanController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\SmartActionController;
use App\Http\Controllers\App\SocialController;
use App\Http\Controllers\App\TeamController;
use App\Http\Controllers\App\VoiceCampaignController;
use App\Http\Controllers\App\VoiceCheckoutController;
use App\Http\Controllers\App\WhatsappConnectionController;
use App\Http\Controllers\Control\Ai\AuditController;
use App\Http\Controllers\Control\Ai\CostsController;
use App\Http\Controllers\Control\Ai\ModelController;
use App\Http\Controllers\Control\Ai\ProviderController;
use App\Http\Controllers\Control\Ai\RoutingController;
use App\Http\Controllers\Control\DashboardController;
use App\Http\Controllers\Control\PlansController;
use App\Http\Controllers\Enterprise\ActionController;
use App\Http\Controllers\Enterprise\AuthController;
use App\Http\Controllers\Enterprise\DeviceApiController;
use App\Http\Controllers\Enterprise\WorkspaceController;
use App\Http\Controllers\Marketing\PageController;
use Illuminate\Support\Facades\Route;

// Unified Enterprise Root Portal & Workspace (?tab=... supported for Android v61/v62 WebView)
Route::get('/', [WorkspaceController::class, 'home'])->name('home');
Route::post('/', [ActionController::class, 'dispatch'])->middleware('auth')->name('console.root.post');

// Public Marketing Pages
Route::get('/sitemap.xml', [PageController::class, 'sitemap']);
Route::get('/{page}', [PageController::class, 'show'])->where('page', 'platform|pricing|security|enterprise|about|contact|developers|docs');
Route::get('/features/{feature}', [PageController::class, 'feature'])->where('feature', 'agent-builder|smart-buttons|knowledge|integrations|channels|telegram|inbox|operations');
Route::get('/industries/{industry}', [PageController::class, 'industry'])->where('industry', 'ecommerce|real-estate|hospitality|education|healthcare|logistics|marketing-agencies|hosting|professional-services');

// Authentication (No TOTP/2FA on web login)
Route::get('/brand-logo', [AuthController::class, 'brandLogo'])->name('brand.logo');
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.forgot');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth');

// Unified Enterprise Workspaces (Clean REST paths & Android deep-link compatible)
Route::middleware('auth')->group(function () {
    foreach ([
        'overview', 'agents', 'voices', 'devices', 'calls', 'campaigns',
        'automations', 'billing', 'invoices', 'settings', 'whatsapp',
        'accounts', 'plans', 'domains', 'staff', 'integrations', 'releases',
        'workspace', 'admin', 'reseller',
    ] as $path) {
        $tab = match ($path) {
            'workspace', 'admin', 'reseller' => 'overview',
            default => $path,
        };
        Route::get('/'.$path, fn (\Illuminate\Http\Request $request) => app(WorkspaceController::class)->tab($request, $tab))
            ->name('enterprise.'.$path);
    }

    Route::post('/console/action', [ActionController::class, 'dispatch'])->name('enterprise.action');
    Route::get('/api/console/calls/{id}', [WorkspaceController::class, 'callDetailJson'])->whereNumber('id')->name('enterprise.call.detail');
    Route::get('/calls/csv', [WorkspaceController::class, 'exportCallsCsv'])->name('enterprise.calls.csv');
    Route::get('/invoices/{id}/print', [WorkspaceController::class, 'printInvoice'])->whereNumber('id')->name('enterprise.invoice.print');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('password.change');
});

// Unified Android Device Control Plane API
Route::any('/device/v1/index.php', [DeviceApiController::class, 'handle'])->name('device.api.v1');
Route::any('/saas/console/device/v1/index.php', [DeviceApiController::class, 'handle'])->name('device.api.legacy');
Route::any('/device/v1/tts.php', [DeviceApiController::class, 'tts'])->name('device.tts.v1');
Route::any('/saas/console/device/v1/tts.php', [DeviceApiController::class, 'tts'])->name('device.tts.legacy');

// Legacy /saas/console web deep-link compatibility (exclude device/v1 endpoints)
Route::get('/saas/console/{any?}', function (\Illuminate\Http\Request $request) {
    if (! \Illuminate\Support\Facades\Auth::check()) {
        return redirect()->guest(route('login'));
    }
    $query = $request->query();
    $tab = (string) ($query['tab'] ?? 'overview');
    if (in_array($tab, ['flows', 'answering'], true)) {
        $query['tab'] = 'agents';
    }
    if (isset($query['site']) && ! isset($query['site_id'])) {
        $query['site_id'] = $query['site'];
        unset($query['site']);
    }
    if (isset($query['agent']) && ! isset($query['agent_id'])) {
        $query['agent_id'] = $query['agent'];
        unset($query['agent']);
    }
    $qs = http_build_query($query);

    return redirect($qs !== '' ? '/?'.$qs : '/?tab=overview');
})->where('any', '(?!device/v1/).*')->name('console.legacy.redirect');

// Webhooks & External Widgets
Route::get('/api/webhooks/meta/whatsapp/{publicId}', [WhatsappWebhookController::class, 'challenge']);
Route::post('/api/webhooks/meta/whatsapp/{publicId}', [WhatsappWebhookController::class, 'receive']);
Route::get('/api/widget/{publicKey}/config', [WidgetController::class, 'config']);
Route::post('/api/widget/{publicKey}/start', [WidgetController::class, 'start']);
Route::post('/api/widget/{publicKey}/message', [WidgetController::class, 'message']);
Route::post('/api/webhooks/telegram/{publicId}', [TelegramWebhookController::class, 'receive']);
Route::post('/api/billing/kashier/webhook', [KashierWebhookController::class, 'receive'])->name('api.kashier.webhook')->middleware('throttle:100,1');

// Legacy /control and /app/{workspace} overlays (preserved 100% for backward test compatibility)
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
        Route::get('/routing', [RoutingController::class, 'index'])->name('routing.index');
        Route::post('/routing', [RoutingController::class, 'store'])->name('routing.store');
        Route::get('/costs', [CostsController::class, 'index'])->name('costs.index');
    });
});

Route::middleware(['auth', 'workspace.member'])->prefix('app')->name('app.')->group(function () {
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
    Route::get('/{workspace}/agents/{agent}/flow', [FlowBuilderController::class, 'show'])->name('flow.show');
    Route::post('/{workspace}/agents/{agent}/flow/save', [FlowBuilderController::class, 'save'])->name('flow.save');
    Route::post('/{workspace}/agents/{agent}/flow/validate', [FlowBuilderController::class, 'validateGraph'])->name('flow.validate');
    Route::post('/{workspace}/agents/{agent}/flow/publish', [FlowBuilderController::class, 'publish'])->name('flow.publish');
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
    Route::post('/{workspace}/plan/checkout/{plan}', [VoiceCheckoutController::class, 'checkout'])->name('plan.checkout')->middleware('throttle:5,1');
    Route::get('/{workspace}/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/{workspace}/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/{workspace}/crm', [CrmController::class, 'index'])->name('crm.index');
    Route::post('/{workspace}/crm', [CrmController::class, 'store'])->name('crm.store');
    Route::get('/{workspace}/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/{workspace}/team/invite', [TeamController::class, 'invite'])->name('team.invite');
    Route::post('/{workspace}/team/{membership}/role', [TeamController::class, 'updateRole'])->name('team.updateRole');
    Route::post('/{workspace}/team/{membership}/remove', [TeamController::class, 'remove'])->name('team.remove');
    Route::get('/invitations/{invitation}/{token}', [TeamController::class, 'showInvitation'])->name('team.invitation');
    Route::post('/invitations/{invitation}/{token}/accept', [TeamController::class, 'accept'])->name('team.accept');
    Route::get('/{workspace}/operations', [OperationsController::class, 'index'])->name('operations.index');
    Route::get('/{workspace}/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('/{workspace}/integrations', [IntegrationController::class, 'store'])->name('integrations.store');
    Route::get('/{workspace}/integrations/{connector}', [IntegrationController::class, 'show'])->name('integrations.show');
    Route::put('/{workspace}/integrations/{connector}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::delete('/{workspace}/integrations/{connector}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
    Route::post('/{workspace}/integrations/{connector}/endpoints', [IntegrationController::class, 'endpointStore'])->name('integrations.endpoints.store');
    Route::delete('/{workspace}/integrations/{connector}/endpoints/{endpoint}', [IntegrationController::class, 'endpointDestroy'])->name('integrations.endpoints.destroy');
    Route::get('/{workspace}/voice', [VoiceCampaignController::class, 'index'])->name('voice.index');
    Route::get('/{workspace}/voice/{campaign}', [VoiceCampaignController::class, 'show'])->name('voice.show');
    Route::post('/{workspace}/voice/{campaign}/import', [VoiceCampaignController::class, 'import'])->name('voice.import');
    Route::get('/{workspace}/channels/social', [SocialController::class, 'index'])->name('social.index');
    Route::post('/{workspace}/channels/social', [SocialController::class, 'store'])->name('social.store');
    Route::post('/{workspace}/channels/social/{connection}/revoke', [SocialController::class, 'revoke'])->name('social.revoke');
    Route::post('/{workspace}/voice', [VoiceCampaignController::class, 'store'])->name('voice.store');
});
