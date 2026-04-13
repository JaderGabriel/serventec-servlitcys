<?php

use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/analytics', [AnalyticsDashboardController::class, 'index'])->name('dashboard.analytics');
    Route::get('/dashboard/analytics/filter-options', [AnalyticsDashboardController::class, 'filterOptions'])->name('dashboard.analytics.filter-options');

    Route::get('cities/{city}/db-status', [CityController::class, 'dbStatus'])->name('cities.db-status');
    Route::resource('cities', CityController::class)->except(['show']);

    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/settings/mail', [MailSettingsController::class, 'edit'])->name('settings.mail.edit');
    Route::put('/settings/mail', [MailSettingsController::class, 'update'])->name('settings.mail.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
