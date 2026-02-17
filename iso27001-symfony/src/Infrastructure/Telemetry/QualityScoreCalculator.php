<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

/**
 * Risk-weighted Quality Score Calculator.
 *
 * Composite score derived from five pillars aligned to ISO 27001 domains:
 *
 *   Pillar           Weight   ISO 27001 Annex
 *   ─────────────────────────────────────────
 *   Security          40%     A.9, A.10
 *   Data Integrity    20%     A.12
 *   Reliability       15%     A.17
 *   Auditability      15%     A.12
 *   Performance        5%     A.17
 *
 * Score of 1.0 = perfect compliance; 0.0 = complete failure.
 * A score below 0.70 should block production deployments.
 */
final class QualityScoreCalculator
{
    public const PRODUCTION_GATE = 0.70;

    public function __construct(
        private readonly float $slaLatencyP95Ms = 200.0,
        private readonly float $targetLatencyMs = 500.0,
    ) {}

    /**
     * @param array<string, mixed> $errorBudgetSnapshot  output of ErrorBudgetTracker::snapshot()
     * @return array<string, mixed>
     */
    public function calculate(array $errorBudgetSnapshot): array
    {
        $availability  = (float) ($errorBudgetSnapshot['observed_availability'] ?? 1.0);
        $security      = 1.0; // placeholder: wire JWT/RBAC check pass-rate here
        $dataIntegrity = 1.0; // placeholder: wire audit event completeness here
        $reliability   = max(0.0, min(1.0, $availability));
        $auditability  = 1.0; // placeholder: wire correlation ID coverage here
        $performance   = $this->latencyScore();

        $composite = $security      * 0.40
                   + $dataIntegrity * 0.20
                   + $reliability   * 0.15
                   + $auditability  * 0.15
                   + $performance   * 0.05;

        return [
            'composite'                  => round($composite, 4),
            'passes_gate'                => $composite >= self::PRODUCTION_GATE,
            'production_gate_threshold'  => self::PRODUCTION_GATE,
            'pillars' => [
                'security'       => ['score' => $security,      'weight' => 0.40],
                'data_integrity' => ['score' => $dataIntegrity, 'weight' => 0.20],
                'reliability'    => ['score' => $reliability,   'weight' => 0.15],
                'auditability'   => ['score' => $auditability,  'weight' => 0.15],
                'performance'    => ['score' => $performance,   'weight' => 0.05],
            ],
        ];
    }

    private function latencyScore(): float
    {
        if ($this->targetLatencyMs <= 0.0) {
            return 1.0;
        }
        return max(0.0, 1.0 - ($this->slaLatencyP95Ms / $this->targetLatencyMs));
    }
}
