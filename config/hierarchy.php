<?php

declare(strict_types=1);

return [
    /*
     * Default strategy for hierarchy operations
     * Options: 'closure', 'adjacency', 'hybrid'
     */
    'default_strategy' => env('HIERARCHY_STRATEGY', 'hybrid'),

    /*
     * Strategy performance characteristics
     */
    'strategies' => [
        'closure' => [
            'read_performance' => 95,
            'write_performance' => 60,
            'memory_efficiency' => 70,
            'best_for' => ['analytics', 'complex_queries', 'reporting'],
        ],
        'adjacency' => [
            'read_performance' => 75,
            'write_performance' => 95,
            'memory_efficiency' => 95,
            'best_for' => ['frequent_updates', 'simple_queries', 'development'],
        ],
        'hybrid' => [
            'read_performance' => 90,
            'write_performance' => 85,
            'memory_efficiency' => 82,
            'best_for' => ['balanced_workload', 'enterprise', 'flexibility'],
        ],
    ],

    /*
     * Automatic strategy selection rules
     */
    'auto_selection_rules' => [
        'write_operations' => 'adjacency',
        'complex_reads' => 'closure',
        'simple_reads' => 'adjacency',
        'analytics' => 'closure',
        'default' => 'hybrid',
    ],

    /*
     * Performance monitoring thresholds
     */
    'performance_thresholds' => [
        'execution_time' => 1000,           // milliseconds
        'memory_delta' => 50 * 1024 * 1024, // 50MB
        'query_count' => 20,
    ],

    /*
     * Monitoring and alerting configuration
     */
    'monitoring' => [
        'enabled' => env('HIERARCHY_MONITORING', true),
        'log_slow_queries' => true,
        'alert_thresholds' => [
            'execution_time' => 2000,
            'memory_usage' => 100 * 1024 * 1024,
        ],
    ],
];
