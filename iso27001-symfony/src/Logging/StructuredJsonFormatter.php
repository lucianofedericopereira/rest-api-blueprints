<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * A.12: Normalised JSON log formatter.
 *
 * Produces the same top-level JSON shape as the FastAPI StructuredLogger so
 * every log line — regardless of stack — can be queried with identical
 * CloudWatch Logs Insights expressions:
 *
 *   fields timestamp, level, message, service, request_id, environment
 *   | filter level = "ERROR"
 *
 * Output shape:
 * {
 *   "timestamp":   "2025-02-10T14:30:00.123Z",
 *   "level":       "INFO",
 *   "message":     "request.completed",
 *   "service":     "iso27001-symfony",
 *   "environment": "prod",
 *   "request_id":  "550e8400-...",
 *   "context":     { ... }
 * }
 *
 * Fields injected by StructuredProcessor into record->extra are promoted to
 * top-level. All other context/extra fields are nested under "context".
 */
final class StructuredJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $entry = [
            'timestamp'   => $record->datetime->format(\DateTimeInterface::RFC3339_EXTENDED),
            'level'       => $record->level->getName(),
            'message'     => $record->message,
            'service'     => $record->extra['service']     ?? 'iso27001-symfony',
            'environment' => $record->extra['environment'] ?? 'production',
            'request_id'  => $record->extra['request_id']  ?? 'cli',
            'context'     => $this->mergeContext($record),
        ];

        return $this->toJson($entry, true) . "\n";
    }

    /**
     * Merge Monolog context + extra (minus fields already promoted to top-level).
     *
     * @return array<string, mixed>|null
     */
    private function mergeContext(LogRecord $record): ?array
    {
        $promoted = ['service', 'environment', 'request_id'];

        $extra = array_diff_key($record->extra, array_flip($promoted));
        $ctx   = array_merge($extra, $record->context);

        return $ctx !== [] ? $ctx : null;
    }
}
