<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A.12: Monolog processor that injects correlation ID,
 * service metadata, and redacts sensitive fields into every log entry.
 */
final readonly class StructuredProcessor implements ProcessorInterface
{
    private const REDACTED = [
        'password', 'token', 'secret', 'authorization',
        'api_key', 'credit_card', 'ssn', 'refresh_token',
    ];

    public function __construct(
        private RequestStack $requestStack,
        private string $serviceName,
        private string $environment,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();
        $extra = $record->extra;

        $extra['service'] = $this->serviceName;
        $extra['environment'] = $this->environment;
        $extra['request_id'] = $request?->attributes->get('request_id', 'cli');

        $record = $record->with(extra: $extra);

        // Redact sensitive context fields
        $context = $this->redact($record->context);
        return $record->with(context: $context);
    }

    /**
     * @param array<string, mixed> $data
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
