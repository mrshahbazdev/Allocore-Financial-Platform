<?php

namespace App\Services;

use App\Models\GmbhInput;
use App\Models\Analysis;
use App\Models\KpiResult;

class GmbhScoringService
{
    private GmbhInput $input;

    public function __construct(GmbhInput $input)
    {
        $this->input = $input;
    }

    // ─────────────────────────────────────────────────────────────
    //  KPI Calculations
    // ─────────────────────────────────────────────────────────────

    /** Umsatzwachstum in % */
    public function umsatzwachstum(): ?float
    {
        if (!$this->input->revenue_prev || $this->input->revenue_prev == 0) return null;
        return (($this->input->revenue_current - $this->input->revenue_prev) / $this->input->revenue_prev) * 100;
    }

    /** EBITDA-Marge in % */
    public function ebitdaMarge(): ?float
    {
        if (!$this->input->revenue_current || $this->input->revenue_current == 0) return null;
        $ebitda = $this->input->ebitda ?? ($this->input->net_profit + $this->input->depreciation + $this->input->interest);
        return ($ebitda / $this->input->revenue_current) * 100;
    }

    /** Debt / Equity Ratio */
    public function debtEquityRatio(): ?float
    {
        if (!$this->input->equity || $this->input->equity == 0) return null;
        return $this->input->total_debt / $this->input->equity;
    }

    /** Current Ratio (Liquidität 2. Grades) */
    public function currentRatio(): ?float
    {
        if (!$this->input->current_liabilities || $this->input->current_liabilities == 0) return null;
        return $this->input->current_assets / $this->input->current_liabilities;
    }

    /** Runway in Monaten */
    public function runway(): ?float
    {
        if (!$this->input->monthly_burn || $this->input->monthly_burn == 0) return null;
        return $this->input->cash / $this->input->monthly_burn;
    }

    /** LTV/CAC Ratio */
    public function ltvCacRatio(): ?float
    {
        if (!$this->input->cac || $this->input->cac == 0) return null;
        return $this->input->ltv / $this->input->cac;
    }

    /** Eigenkapitalquote in % */
    public function eigenkapitalQuote(): ?float
    {
        if (!$this->input->total_assets || $this->input->total_assets == 0) return null;
        return ($this->input->equity / $this->input->total_assets) * 100;
    }

    // ─────────────────────────────────────────────────────────────
    //  Scoring Engine (0–100)
    // ─────────────────────────────────────────────────────────────

    private function scoreKpi(float $value, array $thresholds): float
    {
        // thresholds: [green_min => 100pts, yellow_min => 60pts, else => 20pts]
        if ($value >= $thresholds['green']) return 100.0;
        if ($value >= $thresholds['yellow']) return 60.0;
        return 20.0;
    }

    private function scoreLowerIsBetter(float $value, array $thresholds): float
    {
        if ($value <= $thresholds['green']) return 100.0;
        if ($value <= $thresholds['yellow']) return 60.0;
        return 20.0;
    }

    public function weightedScore(): float
    {
        $scores = [];

        // Umsatzwachstum (15%) — green ≥ 10%, yellow ≥ 0%
        $v = $this->umsatzwachstum();
        $scores[] = ['weight' => 15, 'score' => $v !== null ? $this->scoreKpi($v, ['green' => 10, 'yellow' => 0]) : 50];

        // EBITDA-Marge (20%) — green ≥ 15%, yellow ≥ 5%
        $v = $this->ebitdaMarge();
        $scores[] = ['weight' => 20, 'score' => $v !== null ? $this->scoreKpi($v, ['green' => 15, 'yellow' => 5]) : 50];

        // Debt/Equity (15%, lower is better) — green ≤ 1.0, yellow ≤ 2.5
        $v = $this->debtEquityRatio();
        $scores[] = ['weight' => 15, 'score' => $v !== null ? $this->scoreLowerIsBetter($v, ['green' => 1.0, 'yellow' => 2.5]) : 50];

        // Current Ratio (10%) — green ≥ 1.5, yellow ≥ 1.0
        $v = $this->currentRatio();
        $scores[] = ['weight' => 10, 'score' => $v !== null ? $this->scoreKpi($v, ['green' => 1.5, 'yellow' => 1.0]) : 50];

        // Runway (10%) — green ≥ 18 months, yellow ≥ 6
        $v = $this->runway();
        $scores[] = ['weight' => 10, 'score' => $v !== null ? $this->scoreKpi($v, ['green' => 18, 'yellow' => 6]) : 50];

        // LTV/CAC (10%) — green ≥ 3, yellow ≥ 1.5
        $v = $this->ltvCacRatio();
        $scores[] = ['weight' => 10, 'score' => $v !== null ? $this->scoreKpi($v, ['green' => 3.0, 'yellow' => 1.5]) : 50];

        // Management Score (10%) — 1-10 scale → green ≥ 7, yellow ≥ 5
        $v = $this->input->mgmt_score;
        $scores[] = ['weight' => 10, 'score' => $v !== null ? $this->scoreKpi($v, ['green' => 7, 'yellow' => 5]) : 50];

        // Market Score (10%) — 1-10 scale → green ≥ 7, yellow ≥ 5
        $v = $this->input->market_score;
        $scores[] = ['weight' => 10, 'score' => $v !== null ? $this->scoreKpi($v, ['green' => 7, 'yellow' => 5]) : 50];

        $total = 0;
        $weightSum = 0;
        foreach ($scores as $s) {
            $total += $s['score'] * ($s['weight'] / 100);
            $weightSum += $s['weight'];
        }

        return $weightSum > 0 ? round($total, 2) : 0;
    }

    public function getRecommendation(float $score): string
    {
        if ($score >= 75) return 'Sehr gut — Finanzierung empfohlen';
        if ($score >= 60) return 'Gut — mit Auflagen finanzierbar';
        if ($score >= 45) return 'Mittelmäßig — kritische KPIs prüfen';
        if ($score >= 30) return 'Schwach — erhebliche Risiken vorhanden';
        return 'Kritisch — Finanzierung nicht empfohlen';
    }

    public function getTrafficLight(float $score): string
    {
        if ($score >= 60) return 'green';
        if ($score >= 40) return 'yellow';
        return 'red';
    }

    // ─────────────────────────────────────────────────────────────
    //  Calculate & Save All KPIs to DB
    // ─────────────────────────────────────────────────────────────

    public function calculateAndSave(Analysis $analysis): array
    {
        $kpis = [
            ['code' => 'UMSATZ_WACHSTUM', 'name' => 'Umsatzwachstum',      'value' => $this->umsatzwachstum(),   'unit' => '%',   'weight' => 15],
            ['code' => 'EBITDA_MARGE',    'name' => 'EBITDA-Marge',         'value' => $this->ebitdaMarge(),      'unit' => '%',   'weight' => 20],
            ['code' => 'DEBT_EQUITY',     'name' => 'Debt/Equity Ratio',    'value' => $this->debtEquityRatio(),  'unit' => 'x',   'weight' => 15],
            ['code' => 'CURRENT_RATIO',   'name' => 'Current Ratio',         'value' => $this->currentRatio(),    'unit' => 'x',   'weight' => 10],
            ['code' => 'RUNWAY',          'name' => 'Runway (Monate)',       'value' => $this->runway(),           'unit' => 'Mo',  'weight' => 10],
            ['code' => 'LTV_CAC',         'name' => 'LTV/CAC Ratio',         'value' => $this->ltvCacRatio(),     'unit' => 'x',   'weight' => 10],
            ['code' => 'EK_QUOTE',        'name' => 'Eigenkapitalquote',     'value' => $this->eigenkapitalQuote(), 'unit' => '%', 'weight' => 10],
        ];

        $score = $this->weightedScore();

        // Delete old results, re-insert
        $analysis->kpiResults()->delete();

        foreach ($kpis as $kpi) {
            if ($kpi['value'] === null) continue;
            KpiResult::create([
                'analysis_id'   => $analysis->id,
                'kpi_code'      => $kpi['code'],
                'kpi_name'      => $kpi['name'],
                'value'         => $kpi['value'],
                'score'         => $score,
                'weight'        => $kpi['weight'],
                'unit'          => $kpi['unit'],
                'traffic_light' => $this->getTrafficLight($score),
            ]);
        }

        $recommendation = $this->getRecommendation($score);
        $analysis->update([
            'total_score'    => $score,
            'recommendation' => $recommendation,
            'status'         => 'complete',
        ]);

        return [
            'score'          => $score,
            'recommendation' => $recommendation,
            'traffic_light'  => $this->getTrafficLight($score),
            'kpis'           => $kpis,
        ];
    }
}
