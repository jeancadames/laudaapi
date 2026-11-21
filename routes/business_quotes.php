<?php

use App\Http\Controllers\BusinessContractActivationController;
use App\Http\Controllers\BusinessImplementationController;
use App\Http\Controllers\BusinessQuoteCheckoutController;
use App\Http\Controllers\BusinessQuoteController;
use App\Http\Controllers\BusinessRenewalController;
use App\Http\Middleware\BusinessIdentityResponse;
use Illuminate\Support\Facades\Route;

Route::middleware([BusinessIdentityResponse::class, 'auth', 'verified', 'throttle:20,1'])
    ->prefix('app/business/quotes')->name('business.quotes.')->group(function (): void {
        Route::get('/', [BusinessQuoteController::class, 'index'])->name('index');
        Route::get('/{publicId}', [BusinessQuoteController::class, 'show'])->whereUuid('publicId')->name('show');
        Route::post('/{publicId}/accept', [BusinessQuoteController::class, 'accept'])->whereUuid('publicId')->name('accept');
        Route::get('/{publicId}/checkout', [BusinessQuoteCheckoutController::class, 'show'])->whereUuid('publicId')->name('checkout.show');
        Route::post('/{publicId}/checkout', [BusinessQuoteCheckoutController::class, 'store'])->whereUuid('publicId')->name('checkout.store');
        Route::get('/{publicId}/activation', [BusinessContractActivationController::class, 'show'])->whereUuid('publicId')->name('activation.show');
        Route::get('/{publicId}/implementation', [BusinessImplementationController::class, 'show'])->whereUuid('publicId')->name('implementation.show');
        Route::post('/{publicId}/implementation', [BusinessImplementationController::class, 'store'])->whereUuid('publicId')->name('implementation.store');
        Route::get('/{publicId}/renewal', [BusinessRenewalController::class, 'show'])->whereUuid('publicId')->name('renewal.show');
        Route::post('/{publicId}/renewal/accept', [BusinessRenewalController::class, 'accept'])->whereUuid('publicId')->name('renewal.accept');
    });

Route::middleware([BusinessIdentityResponse::class, 'auth', 'verified', 'role:admin', 'throttle:20,1'])
    ->prefix('admin/business-implementations')->name('admin.business_renewal.')->group(function (): void {
        Route::get('/{publicId}/renewal', [BusinessRenewalController::class, 'show'])->whereUuid('publicId')->name('show');
        Route::post('/{publicId}/renewal/propose', [BusinessRenewalController::class, 'propose'])->whereUuid('publicId')->name('propose');
        Route::post('/{publicId}/renewal/invoice', [BusinessRenewalController::class, 'invoice'])->whereUuid('publicId')->name('invoice');
    });

Route::middleware([BusinessIdentityResponse::class, 'auth', 'verified', 'role:admin', 'throttle:20,1'])
    ->prefix('admin/business-implementations')->name('admin.business_implementations.')->group(function (): void {
        Route::get('/', [BusinessImplementationController::class, 'index'])->name('index');
        Route::get('/{publicId}', [BusinessImplementationController::class, 'show'])->whereUuid('publicId')->name('show');
        Route::post('/{publicId}', [BusinessImplementationController::class, 'store'])->whereUuid('publicId')->name('update');
    });

Route::middleware([BusinessIdentityResponse::class, 'auth', 'verified', 'role:admin', 'throttle:20,1'])
    ->prefix('admin/business-implementations')->name('admin.business_activation.')->group(function (): void {
        Route::get('/{publicId}/activation', [BusinessContractActivationController::class, 'show'])->whereUuid('publicId')->name('show');
        Route::post('/{publicId}/activation', [BusinessContractActivationController::class, 'store'])->whereUuid('publicId')->name('store');
    });
