<?php

use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Artisan;
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

Volt::route('chat/{conversation?}', 'pages.chat')->name('chat')->middleware(['auth', 'verified']);
Route::view('lila', 'lila')->name('lila')->middleware(['auth', 'verified']);

Volt::route('user/{user}', 'pages.user-profile')->name('user.profile')->middleware(['auth']);
Volt::route('artisans/u:{user}', 'pages.artisan-profile')->name('artisan.profile');
// Volt::route('clips', 'pages.clips')->name('clips')->middleware(['auth', 'verified']);

Route::middleware(['auth'])->group(function () {
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    Route::get('/push-subscriptions/latest', [PushSubscriptionController::class, 'latest'])->name('push-subscriptions.latest');

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

// Utility routes for production maintenance
Route::get('/run-migrations', function () {
    Artisan::call('migrate', ['--force' => true]);
    return '<pre>' . Artisan::output() . '</pre>';
});

// Route::get('/seed-deal', function () {
//     Artisan::call('db:seed', ['--class' => 'ProfessionalDealSeeder', '--force' => true]);
//     return '<pre>' . Artisan::output() . '</pre>';
// });

Route::get('/clear-all-cache', function () {
    Artisan::call('optimize:clear');
    return "Optimize cache cleared successfully!";
})->middleware(['auth', 'admin']);

// Route::get('/storage-link', function () {
//     Artisan::call('storage:link');
//     return 'Storage Linked successfully.';
// });

// Route::get('/generate-sitemap', function () {
//     Artisan::call('sitemap:generate');
//     return 'Sitemap generated successfully.';
// });

// Route::get('/generate-user-slugs', function () {
//     Artisan::call('users:generate-slugs');
//     return 'User slugs generated successfully.';
// });

$ignoredEmails = [
    'hello@allsers.com',
    'support@allsers.com',
    'ronkejanet@yahoo.com',
    'bumtech2008@yahoo.com',
    'bolaji.2782@bouesti.edu.ng',
    'ajayiolumuyiwa89@yahoo.com',
    'adekogbasinaayo@yahoo.com',
    'kolmic1@yahoo.com'
];

Route::middleware(['auth', 'admin'])->group(function () use ($ignoredEmails) {
    Route::get('/list-fake-users', function () use ($ignoredEmails) {
        $users = \App\Models\User::where('email', 'NOT LIKE', '%@gmail.com')
            ->whereNotIn('email', $ignoredEmails)
            ->get(['id', 'name', 'username', 'email', 'created_at']);
            
        return response()->json([
            'count' => $users->count(),
            'users' => $users
        ]);
    });

    Route::get('/delete-fake-users', function () use ($ignoredEmails) {
        $count = \App\Models\User::where('email', 'NOT LIKE', '%@gmail.com')
            ->whereNotIn('email', $ignoredEmails)
            ->delete();
            
        return response()->json([
            'message' => "Successfully deleted {$count} fake users (protected emails ignored)."
        ]);
    });
});
