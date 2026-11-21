<?php

use App\Http\Controllers\Admin\AdminBusinessCatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin/business-catalog')->name('admin.business_catalog.')->group(function () {
    Route::get('/', [AdminBusinessCatalogController::class, 'index'])->name('index');
    Route::post('/initialize', [AdminBusinessCatalogController::class, 'initialize'])->name('initialize');
    Route::get('/companies', [AdminBusinessCatalogController::class, 'companies'])->name('companies');
    Route::get('/quotes/{quote}', [AdminBusinessCatalogController::class, 'quote'])->name('quote');
    Route::put('/{version}', [AdminBusinessCatalogController::class, 'save'])->name('save');
    Route::post('/{version}/revise', [AdminBusinessCatalogController::class, 'revise'])->name('revise');
    Route::post('/{version}/publish', [AdminBusinessCatalogController::class, 'publish'])->name('publish');
    Route::post('/{version}/retire', [AdminBusinessCatalogController::class, 'retire'])->name('retire');
    Route::post('/{version}/preview', [AdminBusinessCatalogController::class, 'preview'])->name('preview');
    Route::post('/{version}/issue', [AdminBusinessCatalogController::class, 'issue'])->name('issue');
});
