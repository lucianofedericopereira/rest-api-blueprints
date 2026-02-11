<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * A.12: Monolog processor that injects correlation ID and redacts sensitive fields.
 */
final class StructuredLogProcessor
{
    private const REDACTED = [
        'password', 'token', 'secret', 'authorization',
        'api_key', 'credit_card', 'ssn', 'refresh_token',
    ];

    public function __invoke(array $record): array
    {
        // Inject correlation ID from the current request
        $record['extra']['request_id']   = config('request.id', 'cli');
        $record['extra']['service']      = config('app.name', 'iso27001-api');
        $record['extra']['environment']  = config('app.env', 'production');

        // A.12: Redact sensitive fields in the log context
        $record['context'] = $this->redact($record['context'] ?? []);

        return $record;
    }

    private function redact(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED, true)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = $this->redact($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
