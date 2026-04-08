<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Analysis;
use App\Models\Company;
use App\Models\KpiThreshold;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    // ─── Admin Dashboard ──────────────────────────────────────────────
    public function index()
    {
        $stats = [
            'users'      => User::count(),
            'analyses'   => Analysis::count(),
            'companies'  => Company::count(),
            'gmbh'       => Analysis::where('type', 'gmbh')->count(),
            'jahrabs'    => Analysis::where('type', 'jahresabschluss')->count(),
            'immobilien' => Analysis::where('type', 'immobilien')->count(),
            'complete'   => Analysis::where('status', 'complete')->count(),
        ];

        $recentUsers = User::with('roles')->latest()->take(8)->get();

        $topAnalyses = Analysis::with(['company', 'user'])
            ->whereNotNull('total_score')
            ->orderByDesc('total_score')
            ->take(5)
            ->get();

        return view('admin.index', compact('stats', 'recentUsers', 'topAnalyses'));
    }

    // ─── User Management ──────────────────────────────────────────────
    public function users()
    {
        $users = User::with('roles')
            ->withCount('analyses')
            ->latest()
            ->paginate(20);

        $roles = Role::all();

        return view('admin.users', compact('users', 'roles'));
    }

    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user->syncRoles([$request->role]);

        return back()->with('success', "Rolle von {$user->name} auf {$request->role} geändert.");
    }

    // ─── KPI Threshold Management ─────────────────────────────────────
    public function thresholds()
    {
        $thresholds = KpiThreshold::orderBy('tool')->orderBy('kpi_code')->get();
        $grouped    = $thresholds->groupBy('tool');

        return view('admin.thresholds', compact('grouped'));
    }

    public function updateThreshold(Request $request, KpiThreshold $threshold)
    {
        $request->validate([
            'green_min'       => 'nullable|numeric',
            'yellow_min'      => 'nullable|numeric',
            'green_max'       => 'nullable|numeric',
            'yellow_max'      => 'nullable|numeric',
            'weight'          => 'nullable|numeric|min:0|max:100',
            'lower_is_better' => 'boolean',
            'is_active'       => 'boolean',
        ]);

        $threshold->update($request->only([
            'green_min', 'yellow_min', 'green_max', 'yellow_max',
            'weight', 'lower_is_better', 'is_active',
        ]));

        return back()->with('success', "KPI-Schwellwert für {$threshold->kpi_name} aktualisiert.");
    }
}
