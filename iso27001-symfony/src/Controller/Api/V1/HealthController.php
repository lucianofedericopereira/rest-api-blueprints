<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Infrastructure\Telemetry\ErrorBudgetTracker;
use App\Infrastructure\Telemetry\QualityScoreCalculator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/health')]
final class HealthController extends AbstractController
{
    public function __construct(
        private readonly ErrorBudgetTracker $errorBudget,
        private readonly QualityScoreCalculator $qualityScore,
    ) {}

    /** A.17: Liveness — is the process running? */
    #[Route('', methods: ['GET'])]
    public function liveness(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]);
    }

    /** A.17: Readiness — can it serve traffic? */
    #[Route('/ready', methods: ['GET'])]
    public function readiness(Connection $connection): JsonResponse
    {
        $checks = [];
        $overall = true;

        try {
            $start = microtime(true);
            $connection->executeQuery('SELECT 1');
            $checks['database'] = [
                'status' => 'ok',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error'];
            $overall = false;
        }

        return $this->json(
            ['status' => $overall ? 'ok' : 'degraded', 'checks' => $checks],
            $overall ? 200 : 503,
        );
    }

    /** A.17: Detailed diagnostics — admin only */
    #[Route('/detailed', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function detailed(): JsonResponse
    {
        $snapshot      = $this->errorBudget->snapshot();
        $qualityScore  = $this->qualityScore->calculate($snapshot);

        return $this->json([
            'status'         => 'ok',
            'version'        => $_ENV['APP_VERSION'] ?? 'unknown',
            'environment'    => $_ENV['APP_ENV'] ?? 'dev',
            'php_version'    => PHP_VERSION,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'timestamp'      => (new \DateTimeImmutable())->format('c'),
            'error_budget'   => $snapshot,
            'quality_score'  => $qualityScore,
        ]);
    }
}
