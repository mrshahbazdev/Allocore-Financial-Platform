<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GmbhAnalyseController;
use App\Http\Controllers\JahresabschlussController;
use App\Http\Controllers\ImmobilienController;
use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

// ─── Public ────────────────────────────────────────────────────────────────
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
})->name('home');

// ─── Authenticated Routes ───────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Companies
    Route::resource('companies', CompanyController::class);

    // GmbH Analyse
    Route::get('/gmbh', [GmbhAnalyseController::class, 'index'])->name('gmbh.index');
    Route::get('/gmbh/create', [GmbhAnalyseController::class, 'create'])->name('gmbh.create');
    Route::post('/gmbh', [GmbhAnalyseController::class, 'store'])->name('gmbh.store');
    Route::get('/gmbh/{gmbh}', [GmbhAnalyseController::class, 'show'])->name('gmbh.show');
    Route::get('/gmbh/{gmbh}/edit', [GmbhAnalyseController::class, 'edit'])->name('gmbh.edit');
    Route::patch('/gmbh/{gmbh}', [GmbhAnalyseController::class, 'update'])->name('gmbh.update');
    Route::delete('/gmbh/{gmbh}', [GmbhAnalyseController::class, 'destroy'])->name('gmbh.destroy');
    Route::get('/gmbh/{gmbh}/pdf', [GmbhAnalyseController::class, 'exportPdf'])->name('gmbh.pdf');

    // Jahresabschluss
    Route::get('/jahresabschluss', [JahresabschlussController::class, 'index'])->name('jahresabschluss.index');
    Route::get('/jahresabschluss/create', [JahresabschlussController::class, 'create'])->name('jahresabschluss.create');
    Route::post('/jahresabschluss', [JahresabschlussController::class, 'store'])->name('jahresabschluss.store');
    Route::get('/jahresabschluss/{jahresabschluss}', [JahresabschlussController::class, 'show'])->name('jahresabschluss.show');
    Route::delete('/jahresabschluss/{jahresabschluss}', [JahresabschlussController::class, 'destroy'])->name('jahresabschluss.destroy');
    Route::get('/jahresabschluss/{jahresabschluss}/pdf', [JahresabschlussController::class, 'exportPdf'])->name('jahresabschluss.pdf');

    // Immobilienanalyse
    Route::get('/immobilien', [ImmobilienController::class, 'index'])->name('immobilien.index');
    Route::get('/immobilien/create', [ImmobilienController::class, 'create'])->name('immobilien.create');
    Route::post('/immobilien', [ImmobilienController::class, 'store'])->name('immobilien.store');
    Route::get('/immobilien/compare', [ImmobilienController::class, 'compare'])->name('immobilien.compare');
    Route::get('/immobilien/{immobilien}', [ImmobilienController::class, 'show'])->name('immobilien.show');
    Route::delete('/immobilien/{immobilien}', [ImmobilienController::class, 'destroy'])->name('immobilien.destroy');
    Route::get('/immobilien/{immobilien}/pdf', [ImmobilienController::class, 'exportPdf'])->name('immobilien.pdf');

    // Analyses History (all tools)
    Route::get('/analyses', function () {
        $analyses = \App\Models\Analysis::with('company')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(20);
        return view('analyses.index', compact('analyses'));
    })->name('analyses.index');

    // Excel Import
    Route::get('/import', [\App\Http\Controllers\ExcelImportController::class, 'show'])->name('import.index');
    Route::post('/import', [\App\Http\Controllers\ExcelImportController::class, 'import'])->name('import.upload');
    Route::get('/import/template/{type}', [\App\Http\Controllers\ExcelImportController::class, 'downloadTemplate'])->name('import.template');
});

// ─── Admin Routes ───────────────────────────────────────────────────
Route::middleware(['auth', 'role:Admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminController::class, 'index'])->name('index');
    Route::get('/users', [\App\Http\Controllers\Admin\AdminController::class, 'users'])->name('users');
    Route::patch('/users/{user}/role', [\App\Http\Controllers\Admin\AdminController::class, 'updateRole'])->name('users.role');
    Route::get('/thresholds', [\App\Http\Controllers\Admin\AdminController::class, 'thresholds'])->name('thresholds');
    Route::patch('/thresholds/{threshold}', [\App\Http\Controllers\Admin\AdminController::class, 'updateThreshold'])->name('thresholds.update');
});

require __DIR__.'/auth.php';
