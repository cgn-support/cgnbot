<?php

use App\Http\Controllers\Api\ClientsController;
use App\Http\Controllers\Api\CrawlRunsController;
use App\Http\Controllers\Api\IssuesController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(AuthenticateApiToken::class)->group(function () {
    Route::get('/clients', [ClientsController::class, 'index'])->name('api.clients.index');
    Route::get('/clients/{client}', [ClientsController::class, 'show'])->name('api.clients.show');
    Route::get('/clients/{client}/issues', [ClientsController::class, 'issues'])->name('api.clients.issues');
    Route::post('/clients/{client}/crawl', [ClientsController::class, 'crawl'])->name('api.clients.crawl');

    Route::get('/issues', [IssuesController::class, 'index'])->name('api.issues.index');

    Route::get('/crawl-runs/latest', [CrawlRunsController::class, 'latest'])->name('api.crawlRuns.latest');
});
