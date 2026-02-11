<?php

declare(strict_types=1);

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

/**
 * A.12: Structured JSON logging with dedicated audit channel.
 */
return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace'   => false,
    ],

    'channels' => [
        'stack' => [
            'driver'            => 'stack',
            'channels'          => ['structured'],
            'ignore_exceptions' => false,
        ],

        // A.12: Primary structured JSON log channel.
        // Uses StructuredJsonFormatter to produce the same top-level JSON shape
        // as the FastAPI StructuredLogger — enabling identical CloudWatch Logs
        // Insights queries across all three stacks.
        'structured' => [
            'driver'    => 'monolog',
            'handler'   => StreamHandler::class,
            'formatter' => \App\Logging\StructuredJsonFormatter::class,
            'with'      => ['stream' => 'php://stdout'],
            'level'     => env('LOG_LEVEL', 'info'),
            'processors' => [
                // Injects correlation ID + service metadata into record->extra
                // before the formatter promotes them to top-level fields.
                \App\Logging\StructuredLogProcessor::class,
            ],
        ],

        // A.12: Dedicated audit channel — immutable, append-only log file
        'audit' => [
            'driver'    => 'monolog',
            'handler'   => StreamHandler::class,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'with'      => [
                'stream' => storage_path('logs/audit.log'),
                'level'  => 'info',
            ],
        ],

        'null' => [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ],
    ],
];
