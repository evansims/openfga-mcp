#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate SARIF report from fuzzing crashes
 */

$crashDir = __DIR__ . '/crashes';
$crashes = glob($crashDir . '/*.crash');

$sarif = [
    'version' => '2.1.0',
    '$schema' => 'https://raw.githubusercontent.com/oasis-tcs/sarif-spec/master/Schemata/sarif-schema-2.1.0.json',
    'runs' => [
        [
            'tool' => [
                'driver' => [
                    'name' => 'PHP Fuzzer',
                    'version' => '1.0.0',
                    'informationUri' => 'https://github.com/nikic/PHP-Fuzzer',
                    'rules' => [
                        [
                            'id' => 'FUZZ001',
                            'name' => 'FuzzingCrash',
                            'shortDescription' => [
                                'text' => 'Input causes unexpected crash or error'
                            ],
                            'fullDescription' => [
                                'text' => 'The fuzzer discovered an input that causes the application to crash or throw an unexpected error.'
                            ],
                            'help' => [
                                'text' => 'Review the crash details and add appropriate input validation.'
                            ],
                            'defaultConfiguration' => [
                                'level' => 'error'
                            ]
                        ]
                    ]
                ]
            ],
            'results' => []
        ]
    ]
];

foreach ($crashes as $crashFile) {
    $crash = json_decode(file_get_contents($crashFile), true);
    
    if (!$crash) {
        continue;
    }
    
    // Try to extract file and line from the trace
    $location = extractLocation($crash['trace'] ?? '');
    
    $result = [
        'ruleId' => 'FUZZ001',
        'level' => 'error',
        'message' => [
            'text' => sprintf(
                'Fuzzing crash in %s: %s (Input length: %d)',
                $crash['target'] ?? 'unknown',
                $crash['error'] ?? 'unknown error',
                strlen($crash['input'] ?? '')
            )
        ],
        'locations' => [
            [
                'physicalLocation' => [
                    'artifactLocation' => [
                        'uri' => $location['file'] ?? 'unknown',
                        'uriBaseId' => 'SRCROOT'
                    ],
                    'region' => [
                        'startLine' => $location['line'] ?? 1
                    ]
                ]
            ]
        ],
        'partialFingerprints' => [
            'fuzzerTarget' => $crash['target'] ?? 'unknown',
            'errorType' => getErrorType($crash['error'] ?? '')
        ],
        'properties' => [
            'inputSample' => substr($crash['input'] ?? '', 0, 100),
            'inputLength' => strlen($crash['input'] ?? ''),
            'crashFile' => basename($crashFile)
        ]
    ];
    
    $sarif['runs'][0]['results'][] = $result;
}

echo json_encode($sarif, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

function extractLocation(string $trace): array
{
    // Try to find the first source file (not vendor) in the trace
    if (preg_match('/#\d+\s+([^(]+)\((\d+)\)/', $trace, $matches)) {
        $file = $matches[1];
        $line = (int)$matches[2];
        
        // Convert absolute path to relative
        $srcRoot = realpath(__DIR__ . '/../..');
        if (strpos($file, $srcRoot) === 0) {
            $file = substr($file, strlen($srcRoot) + 1);
        }
        
        return ['file' => $file, 'line' => $line];
    }
    
    return ['file' => 'unknown', 'line' => 1];
}

function getErrorType(string $error): string
{
    if (stripos($error, 'memory') !== false) {
        return 'memory_exhaustion';
    }
    if (stripos($error, 'timeout') !== false) {
        return 'timeout';
    }
    if (stripos($error, 'injection') !== false) {
        return 'injection_attempt';
    }
    if (stripos($error, 'overflow') !== false) {
        return 'buffer_overflow';
    }
    
    return 'general_error';
}