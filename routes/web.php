<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
| RSYNC routes. Each legacy .php page gets a route as it is migrated.
| "placeholder" pages are temporary and get replaced in the step named on them.
*/

// Home (index.php) — real page comes in Step 4.
Route::get('/', fn () => view('welcome'))->name('home');

// Login / logout (login.php, logout.php)
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::match(['get', 'post'], '/logout', [LoginController::class, 'logout'])->name('logout');

// Temporary pages so links and redirects work until those steps are done.
Route::view('/register', 'placeholder', ['title' => 'Create account', 'step' => 'Step 3'])->name('register');
Route::view('/forgot-password', 'placeholder', ['title' => 'Forgot password', 'step' => 'Step 8'])->name('password.request');

Route::middleware('role:tenant')->group(function () {
    Route::view('/tenant/dashboard', 'placeholder', ['title' => 'Tenant dashboard', 'step' => 'Step 5'])->name('tenant.dashboard');
});

Route::middleware('role:landlord')->group(function () {
    Route::view('/landlord/dashboard', 'placeholder', ['title' => 'Landlord dashboard', 'step' => 'Step 6'])->name('landlord.dashboard');
});

// Old addresses keep working (bookmarks, old links).
Route::permanentRedirect('/login.php', '/login');
Route::permanentRedirect('/logout.php', '/logout');