<?php

use App\Http\Controllers\App\AgentStudioController;
use App\Http\Controllers\App\HomeController;
use App\Http\Controllers\App\KnowledgeController;
use App\Http\Controllers\App\OverviewController;
use App\Http\Controllers\App\ProjectController;
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
});