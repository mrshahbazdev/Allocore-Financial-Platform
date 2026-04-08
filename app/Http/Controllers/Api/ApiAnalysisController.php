<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiAnalysisController extends Controller
{
    /**
     * GET /api/analyses
     * List all analyses belonging to authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $query = Analysis::with(['company', 'kpiResults'])
            ->where('user_id', $request->user()->id)
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $analyses = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $analyses->items(),
            'meta'    => [
                'current_page' => $analyses->currentPage(),
                'last_page'    => $analyses->lastPage(),
                'total'        => $analyses->total(),
                'per_page'     => $analyses->perPage(),
            ],
        ]);
    }

    /**
     * GET /api/analyses/{id}
     * Get single analysis with full details
     */
    public function show(Request $request, Analysis $analysis): JsonResponse
    {
        if ($analysis->user_id !== $request->user()->id && !$request->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $analysis->load(['company', 'kpiResults', 'gmbhInput', 'jahresabschlussInputs', 'immobilienInput']);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $analysis->id,
                'name'         => $analysis->name,
                'type'         => $analysis->type,
                'type_label'   => $analysis->typeLabel(),
                'status'       => $analysis->status,
                'total_score'  => $analysis->total_score,
                'score_color'  => $analysis->scoreColor(),
                'recommendation' => $analysis->recommendation,
                'company'      => $analysis->company,
                'kpi_results'  => $analysis->kpiResults,
                'created_at'   => $analysis->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /api/analyses/{id}
     */
    public function destroy(Request $request, Analysis $analysis): JsonResponse
    {
        if ($analysis->user_id !== $request->user()->id && !$request->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $analysis->delete();

        return response()->json(['success' => true, 'message' => 'Analyse gelöscht.']);
    }

    /**
     * GET /api/stats
     * Summary stats for the authenticated user
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'success' => true,
            'data'    => [
                'total_analyses'    => Analysis::where('user_id', $userId)->count(),
                'complete'          => Analysis::where('user_id', $userId)->where('status', 'complete')->count(),
                'gmbh'              => Analysis::where('user_id', $userId)->where('type', 'gmbh')->count(),
                'jahresabschluss'   => Analysis::where('user_id', $userId)->where('type', 'jahresabschluss')->count(),
                'immobilien'        => Analysis::where('user_id', $userId)->where('type', 'immobilien')->count(),
                'avg_score'         => round(Analysis::where('user_id', $userId)->whereNotNull('total_score')->avg('total_score'), 1),
                'top_analysis'      => Analysis::where('user_id', $userId)->orderByDesc('total_score')->first()?->only(['id','name','total_score','type']),
            ],
        ]);
    }
}
