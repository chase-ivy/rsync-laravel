<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFinanceController;
use App\Http\Controllers\Admin\AdminMaintenanceController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandlordController;
use App\Http\Controllers\LandlordTenantController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\RoomApplicationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\TenantRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| RSYNC routes. Each legacy .php page gets a route as it is migrated.
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
// Account page (new): details, email verification and password in one place. Any logged-in user.
Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::post('/account/profile', [AccountController::class, 'updateProfile'])->middleware('throttle:10,1')->name('account.profile');
    Route::post('/account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1')->name('account.password');
});
// Forgot password (forgot_password.php + the missing verify_otp step + new password)
Route::get('/forgot-password', [ForgotPasswordController::class, 'showEmailForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendCode'])->middleware('throttle:5,1')->name('password.email');
Route::get('/verify-otp', [ForgotPasswordController::class, 'showCodeForm'])->name('password.otp');
Route::post('/verify-otp', [ForgotPasswordController::class, 'checkCode'])->middleware('throttle:10,1')->name('password.otp.check');
Route::get('/reset-password', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('password.update');

// Tenant dashboard (tenant_dashboard.php)
Route::middleware('role:tenant')->group(function () {
    Route::get('/tenant/dashboard', [TenantDashboardController::class, 'index'])->name('tenant.dashboard');   // tenant_dashboard.php

    // New: tenant forms (need a verified email).
    Route::middleware('verified')->group(function () {
        Route::post('/tenant/maintenance', [TenantRequestController::class, 'storeMaintenance'])->name('tenant.maintenance.store');
        Route::post('/tenant/payments', [TenantRequestController::class, 'storePayment'])->name('tenant.payments.store');
    });
});

// Landlord dashboard (landlord_dashboard.php). Looking is allowed; changing anything needs a verified email.
Route::middleware('role:landlord')->prefix('landlord')->name('landlord.')->group(function () {
    Route::get('/dashboard', [LandlordController::class, 'index'])->name('dashboard');
    Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance');   // maintenance.php

    Route::middleware('verified')->group(function () {
        Route::post('/rooms', [LandlordController::class, 'store'])->name('rooms.store');
        Route::post('/rooms/update', [LandlordController::class, 'update'])->name('rooms.update');
        Route::post('/rooms/delete', [LandlordController::class, 'destroy'])->name('rooms.delete');
        Route::post('/rooms/images', [LandlordController::class, 'images'])->name('rooms.images');
        Route::post('/rooms/images/delete', [LandlordController::class, 'destroyImage'])->name('rooms.images.delete');

        Route::post('/rooms/tenants', [LandlordTenantController::class, 'roomTenants'])->name('rooms.tenants');
        Route::post('/rooms/applicants', [LandlordTenantController::class, 'applicants'])->name('rooms.applicants');
        Route::post('/tenants/assign', [LandlordTenantController::class, 'assign'])->name('tenants.assign');
        Route::post('/tenants/remove', [LandlordTenantController::class, 'remove'])->name('tenants.remove');
        Route::post('/applications/verify', [LandlordTenantController::class, 'verify'])->name('applications.verify');
        Route::post('/applications/reject', [LandlordTenantController::class, 'reject'])->name('applications.reject');
    });
});

// Admin (admin_dashboard.php). Only admins; actions need a verified email.
Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/tenants', [AdminTenantController::class, 'index'])->name('tenants');   // tenants.php
    Route::get('/finance', [AdminFinanceController::class, 'index'])->name('finance');   // report.php
    Route::get('/maintenance', [AdminMaintenanceController::class, 'index'])->name('maintenance');   // maintainance.php

    Route::middleware('verified')->group(function () {
        Route::post('/users/{id}/approve', [AdminDashboardController::class, 'approveUser'])->whereNumber('id')->name('users.approve');
        Route::post('/users/{id}/reject', [AdminDashboardController::class, 'rejectUser'])->whereNumber('id')->name('users.reject');
        Route::post('/payments/{id}/verify', [AdminDashboardController::class, 'verifyPayment'])->whereNumber('id')->name('payments.verify');
        Route::post('/contracts/update', [AdminTenantController::class, 'updateContract'])->name('contracts.update');
        Route::post('/contracts/status', [AdminTenantController::class, 'updateStatus'])->name('contracts.status');
        Route::post('/transactions', [AdminFinanceController::class, 'store'])->name('transactions.store');
        Route::post('/transactions/paid', [AdminFinanceController::class, 'markPaid'])->name('transactions.paid');
        Route::post('/maintenance/status', [AdminMaintenanceController::class, 'updateStatus'])->name('maintenance.status');
    });
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
Route::get('/report.php', fn () => redirect()->route('admin.finance', [], 301));
Route::get('/maintainance.php', fn () => redirect()->route('admin.maintenance', [], 301));
Route::get('/tenants.php', fn () => redirect()->route('admin.tenants', [], 301));
Route::get('/admin_dashboard.php', fn () => redirect()->route('admin.dashboard', [], 301));
Route::get('/maintenance.php', fn () => redirect()->route('landlord.maintenance', [], 301));
Route::permanentRedirect('/login.php', '/login');
Route::permanentRedirect('/logout.php', '/logout');
Route::permanentRedirect('/forgot_password.php', '/forgot-password');
Route::permanentRedirect('/verify_otp.php', '/verify-otp');
Route::get('/registration.php', function (Request $request) {
    $query = $request->getQueryString();          // keeps ?role=landlord&plan=... from old pricing links
    return redirect('/register' . ($query ? '?' . $query : ''), 301);
});