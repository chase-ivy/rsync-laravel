<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\RoomApplicationController;
use App\Http\Controllers\RoomController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| RSYNC routes. Each legacy .php page gets a route as it is migrated.
| "placeholder" pages are temporary and get replaced in the step named on them.
*/

// Public pages (index.php, rooms.php, room_detail.php)
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{id}', [RoomController::class, 'show'])->name('rooms.show');
Route::post('/rooms/apply', [RoomApplicationController::class, 'store'])->middleware('throttle:20,1')->name('rooms.apply');

// Login / logout (login.php, logout.php)
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::match(['get', 'post'], '/logout', [LoginController::class, 'logout'])->name('logout');

// Registration (registration.php)
Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

// Email verification (new feature). The link in the email works even when not logged in.
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')->name('verification.send');
});
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

// Forgot password (forgot_password.php + the missing verify_otp step + new password)
Route::get('/forgot-password', [ForgotPasswordController::class, 'showEmailForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendCode'])->middleware('throttle:5,1')->name('password.email');
Route::get('/verify-otp', [ForgotPasswordController::class, 'showCodeForm'])->name('password.otp');
Route::post('/verify-otp', [ForgotPasswordController::class, 'checkCode'])->middleware('throttle:10,1')->name('password.otp.check');
Route::get('/reset-password', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('password.update');

// Temporary pages so links and redirects work until those steps are done.
Route::middleware('role:tenant')->group(function () {
    Route::get('/tenant/dashboard', [TenantDashboardController::class, 'index'])->name('tenant.dashboard');   // tenant_dashboard.php
});

Route::middleware('role:landlord')->group(function () {
    Route::view('/landlord/dashboard', 'placeholder', ['title' => 'Landlord dashboard', 'step' => 'Part F'])->name('landlord.dashboard');
});

// Old addresses keep working (bookmarks, old links).
Route::permanentRedirect('/index.php', '/');
Route::get('/rooms.php', function (Request $request) {
    $name = trim((string) $request->query('name', ''));       // rooms.php?name=... search
    return redirect()->route('rooms.index', $name !== '' ? ['name' => $name] : [], 301);
});
Route::get('/room_detail.php', fn (Request $request) => redirect()->route('rooms.show', (int) $request->query('id', 0), 301));
Route::get('/tenant_dashboard.php', fn () => redirect()->route('tenant.dashboard', [], 301));
Route::get('/landlord_dashboard.php', fn () => redirect()->route('landlord.dashboard', [], 301));
Route::permanentRedirect('/login.php', '/login');
Route::permanentRedirect('/logout.php', '/logout');
Route::permanentRedirect('/forgot_password.php', '/forgot-password');
Route::permanentRedirect('/verify_otp.php', '/verify-otp');
Route::get('/registration.php', function (Request $request) {
    $query = $request->getQueryString();          // keeps ?role=landlord&plan=... from old pricing links
    return redirect('/register' . ($query ? '?' . $query : ''), 301);
});