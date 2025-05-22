<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\CsvController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth'])->name('dashboard');



Route::middleware(['auth', 'is_active'])->group(function () {


    Route::resource('users', UserController::class)->names('users');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/home', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('home.dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    Route::post('/csv/validate', [CsvController::class, 'validateCsv'])->name('csv.validate');

    // SMS Management Routes
    Route::prefix('sms')->group(function () {
        // SMS Sending
        Route::get('/send', [\App\Http\Controllers\TextController::class, 'create'])->name('sms.create');
        Route::post('/send', [\App\Http\Controllers\TextController::class, 'store'])->name('sms.store');
        Route::post('/preview', [\App\Http\Controllers\TextController::class, 'preview'])->name('sms.preview');

        // SMS Management
        Route::get('/', [\App\Http\Controllers\TextController::class, 'index'])->name('sms.index');
        Route::get('/export/csv', [\App\Http\Controllers\TextController::class, 'exportCsv'])->name('sms.export.csv');
        Route::get('/export/{text}/detail', [\App\Http\Controllers\TextController::class, 'exportSmsDetail'])->name('sms.export.detail');
        Route::get('/download-csv/{filename}', [\App\Http\Controllers\TextController::class, 'downloadCsv'])->name('sms.download.csv');
        Route::get('/progress', [\App\Http\Controllers\TextController::class, 'getProgress'])->name('sms.progress');
        Route::get('/statistics', [\App\Http\Controllers\TextController::class, 'getSmsStatistics'])->name('sms.statistics');

        // SMS Logs
        Route::get('/logs/all', [\App\Http\Controllers\TextController::class, 'logs'])->name('sms.logs');

        // Scheduled SMS
        Route::get('/scheduled', [\App\Http\Controllers\TextController::class, 'scheduled'])->name('sms.scheduled');

        // Routes with parameters should come last
        Route::get('/{text}/edit', [\App\Http\Controllers\TextController::class, 'edit'])->name('sms.edit');
        Route::put('/{text}', [\App\Http\Controllers\TextController::class, 'update'])->name('sms.update');
        Route::delete('/{text}', [\App\Http\Controllers\TextController::class, 'destroy'])->name('sms.destroy');
        Route::get('/{text}', [\App\Http\Controllers\TextController::class, 'show'])->name('sms.show');
    });

    // Contact Management Routes
    Route::resource('contacts', \App\Http\Controllers\ContactController::class);

    // API route for fetching contacts for select2
    Route::post('/contacts/get-contacts', [\App\Http\Controllers\ContactController::class, 'getContacts'])->name('contacts.get-contacts');

    // API route for fetching contact lists by IDs
    Route::get('/api/contacts/lists', [\App\Http\Controllers\ContactController::class, 'getContactListsByIds'])->name('api.contacts.lists');
});



require __DIR__ . '/auth.php';
