<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // Upload + ZIP (Administrator / Document Manager)
    Route::middleware('can:manage')->group(function () {
        Route::get('/upload', [UploadController::class, 'form'])->name('upload');
        Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');
        Route::get('/zip-import', [UploadController::class, 'zipForm'])->name('zip');
        Route::post('/zip-import', [UploadController::class, 'zipStore'])->name('zip.store');
        Route::get('/zip-imports/{zip}', [UploadController::class, 'zipShow'])->name('zip.show');
        Route::get('/zip-imports/{zip}/status', [UploadController::class, 'zipStatus'])->name('zip.status');
        Route::get('/ocr', [AdminController::class, 'ocr'])->name('ocr');
        Route::post('/ocr/retry-selected', [DocumentController::class, 'retrySelected'])->name('ocr.retry-selected');
    });

    // Documents (all authenticated users may view; export gate is checked in controllers)
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::prefix('/documents/{document}')->group(function () {
        Route::get('/', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('/status', [DocumentController::class, 'status'])->name('documents.status');
        Route::get('/search', [SearchController::class, 'inDocument'])->name('documents.search');
        Route::get('/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::get('/print', [DocumentController::class, 'print'])->name('documents.print');
        Route::get('/searchable', [DocumentController::class, 'downloadSearchable'])->name('documents.searchable');

        Route::get('/pages/{page}/image', [PageController::class, 'image'])->whereNumber('page')->name('pages.image');
        Route::get('/pages/{page}/ocr', [PageController::class, 'ocr'])->whereNumber('page')->name('pages.ocr');
        Route::get('/pages/{page}/download', [PageController::class, 'download'])->whereNumber('page')->name('pages.download');
        Route::get('/pages/{page}/print', [PageController::class, 'print'])->whereNumber('page')->name('pages.print');
        Route::post('/pages/download-selected', [PageController::class, 'downloadSelected'])->name('pages.download-selected');
        Route::post('/pages/print-selected', [PageController::class, 'printSelected'])->name('pages.print-selected');

        Route::middleware('can:manage')->group(function () {
            Route::get('/edit', [DocumentController::class, 'edit'])->name('documents.edit');
            Route::put('/', [DocumentController::class, 'update'])->name('documents.update');
            Route::delete('/', [DocumentController::class, 'destroy'])->name('documents.destroy');
            Route::post('/ocr/retry', [DocumentController::class, 'retryOcr'])->name('documents.ocr-retry');
            Route::post('/searchable/generate', [DocumentController::class, 'generateSearchable'])->name('documents.searchable.generate');
            Route::post('/pages/{page}/ocr/retry', [PageController::class, 'retryOcr'])->whereNumber('page')->name('pages.ocr-retry');
        });
    });

    // Administrator only
    Route::middleware('can:admin')->group(function () {
        Route::get('/categories', [AdminController::class, 'categories'])->name('categories');
        Route::post('/categories', [AdminController::class, 'categoryStore'])->name('categories.store');
        Route::put('/categories/{category}', [AdminController::class, 'categoryUpdate'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminController::class, 'categoryDestroy'])->name('categories.destroy');
        Route::get('/document-types', [AdminController::class, 'types'])->name('types');
        Route::post('/document-types', [AdminController::class, 'typeStore'])->name('types.store');
        Route::delete('/document-types/{type}', [AdminController::class, 'typeDestroy'])->name('types.destroy');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'userStore'])->name('users.store');
        Route::put('/users/{user}', [AdminController::class, 'userUpdate'])->name('users.update');
        Route::get('/audit-logs', [AdminController::class, 'audit'])->name('audit');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminController::class, 'settingsUpdate'])->name('settings.update');
        Route::post('/settings/reset', [AdminController::class, 'settingsReset'])->name('settings.reset');
        Route::get('/deleted-documents', [DocumentController::class, 'trash'])->name('documents.trash');
        Route::post('/deleted-documents/{uuid}/restore', [DocumentController::class, 'restore'])->name('documents.restore');
        Route::delete('/deleted-documents/{uuid}', [DocumentController::class, 'purge'])->name('documents.purge');
    });
});
