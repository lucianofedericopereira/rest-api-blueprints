<?php

declare(strict_types=1);

return [

    /*
     * AWS CloudWatch metrics (optional — degrades gracefully when absent).
     * A.17: Observability configuration kept in config, not scattered env() calls.
     */
    'aws' => [
        'region'               => env('AWS_DEFAULT_REGION', 'eu-west-1'),
        'cloudwatch_namespace' => env('AWS_CLOUDWATCH_NAMESPACE', 'ISO27001/API'),
    ],

];
