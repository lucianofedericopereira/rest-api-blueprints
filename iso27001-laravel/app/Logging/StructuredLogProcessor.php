<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * A.12: Monolog processor that injects correlation ID and redacts sensitive fields.
 */
final class StructuredLogProcessor implements ProcessorInterface
{
    private const REDACTED = [
        'password', 'token', 'secret', 'authorization',
        'api_key', 'credit_card', 'ssn', 'refresh_token',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            extra: array_merge($record->extra, [
                'request_id'  => config('request.id', 'cli'),
                'service'     => config('app.name', 'iso27001-api'),
                'environment' => config('app.env', 'production'),
            ]),
            context: $this->redact($record->context),
        );
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
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
