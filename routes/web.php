<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('privacy-policy', 'privacy-policy')->name('privacy');
Route::view('terms-of-service', 'terms-of-service')->name('terms');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('menu', 'menu')
    ->middleware(['auth', 'verified'])
    ->name('menu');

Route::view('bookmarks', 'bookmarks')
    ->middleware(['auth', 'verified'])
    ->name('bookmarks');

Route::view('notifications', 'notifications')
    ->middleware(['auth', 'verified'])
    ->name('notifications');

Volt::route('finder', 'pages.finder')->name('finder')->middleware(['auth', 'verified']);

Volt::route('chat/{conversation?}', 'pages.chat')->name('chat')->middleware(['auth']);
Route::view('lila', 'lila')->name('lila')->middleware(['auth']);

Volt::route('user/{user}', 'pages.user-profile')->name('user.profile')->middleware(['auth']);
Volt::route('artisan/{user}', 'pages.artisan-profile')->name('artisan.profile');
// Volt::route('clips', 'pages.clips')->name('clips')->middleware(['auth', 'verified']);

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    // Admin routes
    Route::middleware(['admin'])->group(function () {
        Volt::route('admin/dashboard', 'admin.dashboard')->name('admin.dashboard');
        Volt::route('admin/reports', 'admin.reports')->name('admin.reports');
    });

    // Challenge routes
    Volt::route('challenges', 'pages.challenges.index')->name('challenges.index');
    Volt::route('challenges/create', 'pages.challenges.create')->name('challenges.create');
    Volt::route('challenge/{slug}', 'pages.challenges.show')->name('challenges.show');
    Volt::route('challenge/{slug}/manage', 'pages.challenges.manage')->name('challenges.manage');
});

// Public post view
Volt::route('posts/{post:post_id}', 'pages.post-show')->name('posts.show');

Route::get('/videos/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);

    if (!file_exists($filePath)) {
        abort(404);
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $headers = [];

    switch ($extension) {
        case 'mp4':
            $headers['Content-Type'] = 'video/mp4';
            break;
        case 'mov':
            $headers['Content-Type'] = 'video/quicktime';
            break;
        case 'webm':
            $headers['Content-Type'] = 'video/webm';
            break;
        case 'avi':
            $headers['Content-Type'] = 'video/x-msvideo';
            break;
    }

    return response()->file($filePath, $headers);
})->where('path', '.*')->name('videos.show');

Route::get('/images/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);

    if (!file_exists($filePath)) {
        abort(404);
    }

    return response()->file($filePath);
})->where('path', '.*')->name('images.show');

// Storage route for production (fallback when symlink doesn't work)
// Utility route to create storage symlink (useful for shared hosting)
Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return 'Storage Linked successfully.';
});
