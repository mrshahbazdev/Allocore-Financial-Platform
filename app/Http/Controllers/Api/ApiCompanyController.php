<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiCompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = Company::withCount('analyses')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $companies]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'industry'    => 'nullable|string|max:100',
            'country'     => 'nullable|string|max:100',
            'currency'    => 'nullable|in:EUR,USD,CHF',
            'description' => 'nullable|string',
        ]);

        $data['user_id'] = $request->user()->id;
        $company = Company::create($data);

        return response()->json(['success' => true, 'data' => $company], 201);
    }

    public function show(Request $request, Company $company): JsonResponse
    {
        if ($company->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $company->load('analyses');
        return response()->json(['success' => true, 'data' => $company]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        if ($company->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'industry'    => 'nullable|string|max:100',
            'country'     => 'nullable|string|max:100',
            'currency'    => 'nullable|in:EUR,USD,CHF',
            'description' => 'nullable|string',
        ]);

        $company->update($data);

        return response()->json(['success' => true, 'data' => $company]);
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        if ($company->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $company->delete();
        return response()->json(['success' => true, 'message' => 'Unternehmen gelöscht.']);
    }
}
