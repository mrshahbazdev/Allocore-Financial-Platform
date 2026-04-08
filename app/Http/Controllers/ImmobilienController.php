<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Company;
use App\Models\ImmobilienInput;
use App\Services\ImmobilienScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class ImmobilienController extends Controller
{
    private const WEIGHT_CODES = [
        'CASHFLOW',
        'NETTORENDITE',
        'CF_RENDITE',
        'DSCR',
        'LTV',
        'MIET_MULTI',
        'MIETSTEIGERUNG',
        'LOCATION_SCORE',
        'CONDITION_SCORE',
    ];

    public function index()
    {
        $analyses = Analysis::with('company')
            ->where('user_id', Auth::id())
            ->where('type', 'immobilien')
            ->latest()
            ->paginate(10);

        return view('immobilien.index', compact('analyses'));
    }

    public function create()
    {
        $companies = Company::where('user_id', Auth::id())->get();
        return view('immobilien.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id'     => 'required|exists:companies,id',
            'name'           => 'required|string|max:255',
            'purchase_price' => 'required|numeric|min:1',
            'closing_costs'  => 'required|numeric|min:0',
            'equity'         => 'required|numeric|min:0',
            'rent_net'       => 'required|numeric|min:0',
            'loan_rate'      => 'required|numeric|min:0|max:20',
            'repayment_rate' => 'required|numeric|min:0|max:20',
            'location_score' => 'required|integer|min:1|max:10',
            'condition_score'=> 'required|integer|min:1|max:10',
            'weights'        => 'nullable|array',
            'weights.*'      => 'nullable|numeric|min:0|max:100',
        ]);

        $analysis = Analysis::create([
            'company_id' => $request->company_id,
            'user_id'    => Auth::id(),
            'type'       => 'immobilien',
            'name'       => $request->name,
            'status'     => 'draft',
        ]);

        $inputData = $request->except(['company_id', 'name', '_token', 'weights']);
        $inputData['custom_weights'] = $this->normalizeWeights($request->input('weights', []));
        $input = ImmobilienInput::create(array_merge($inputData, ['analysis_id' => $analysis->id]));

        $service = new ImmobilienScoringService($input);
        $service->calculateAndSave($analysis);

        return redirect()->route('immobilien.show', $analysis)
            ->with('success', 'Immobilienanalyse erfolgreich erstellt.');
    }

    public function show(Analysis $immobilien)
    {
        $this->authorize('view', $immobilien);
        $immobilien->load(['company', 'immobilienInput', 'kpiResults']);

        $service = new ImmobilienScoringService($immobilien->immobilienInput);
        $derived = [
            'gesamtinvestition' => $service->gesamtinvestition(),
            'darlehen'          => $service->darlehen(),
            'noi'               => $service->noi(),
            'cashflow'          => $service->cashflow(),
            'schuldendienst'    => $service->schuldendienst(),
        ];

        return view('immobilien.show', [
            'analysis' => $immobilien,
            'derived'  => $derived,
        ]);
    }

    public function destroy(Analysis $immobilien)
    {
        $this->authorize('delete', $immobilien);
        $immobilien->delete();
        return redirect()->route('immobilien.index')
            ->with('success', 'Analyse gelöscht.');
    }

    /** Compare multiple properties */
    public function compare(Request $request)
    {
        $ids = $request->input('ids', []);
        $analyses = Analysis::with(['immobilienInput', 'kpiResults', 'company'])
            ->where('user_id', Auth::id())
            ->where('type', 'immobilien')
            ->whereIn('id', $ids)
            ->get();

        return view('immobilien.compare', compact('analyses'));
    }

    public function exportPdf(Analysis $immobilien)
    {
        $this->authorize('view', $immobilien);
        $immobilien->load(['company', 'immobilienInput', 'kpiResults']);

        $service = new ImmobilienScoringService($immobilien->immobilienInput);
        $derived = [
            'gesamtinvestition' => $service->gesamtinvestition(),
            'darlehen'          => $service->darlehen(),
            'noi'               => $service->noi(),
            'cashflow'          => $service->cashflow(),
            'schuldendienst'    => $service->schuldendienst(),
        ];

        $pdf = Pdf::loadView('immobilien.pdf', [
            'analysis' => $immobilien,
            'derived'  => $derived,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('immobilien-analyse-' . $immobilien->id . '.pdf');
    }

    private function normalizeWeights(array $weights): array
    {
        $normalized = [];
        foreach (self::WEIGHT_CODES as $code) {
            if (!array_key_exists($code, $weights)) {
                continue;
            }
            $value = (float)$weights[$code];
            $normalized[$code] = round(max(0, min(100, $value)), 2);
        }

        return $normalized;
    }
}
