<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $stats = [
            'companies'      => Company::where('user_id', $user->id)->count(),
            'gmbh'           => Analysis::where('user_id', $user->id)->where('type', 'gmbh')->count(),
            'jahresabschluss'=> Analysis::where('user_id', $user->id)->where('type', 'jahresabschluss')->count(),
            'immobilien'     => Analysis::where('user_id', $user->id)->where('type', 'immobilien')->count(),
        ];

        $recentAnalyses = Analysis::with('company')
            ->where('user_id', $user->id)
            ->whereNotNull('total_score')
            ->latest()
            ->take(8)
            ->get();

        $companies = Company::where('user_id', $user->id)
            ->withCount('analyses')
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', compact('stats', 'recentAnalyses', 'companies'));
    }
}
