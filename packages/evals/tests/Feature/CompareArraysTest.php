<?php

// Test identical arrays
use Cognesy\Evals\Utils\CompareNestedArrays;

it('returns no differences for identical arrays', function () {
    $expected = [
        'name' => 'John Doe',
        'age' => 30,
        'details' => [
            'email' => 'john@example.com',
            'scores' => [85, 90, 95],
        ],
    ];

    $actual = [
        'name' => 'John Doe',
        'age' => 30,
        'details' => [
            'email' => 'john@example.com',
            'scores' => [85, 90, 95],
        ],
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toBeEmpty();
});

// Test differing keys
it('detects keys that are missing or added', function () {
    $expected = [
        'name' => 'John Doe',
        'age' => 30,
    ];

    $actual = [
        'name' => 'John Doe',
        'age' => 30,
        'email' => 'john@example.com', // Added key
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toHaveCount(1);
    expect($differences)->toHaveKey('email');
    expect($differences['email'])->toEqual([
        'expected' => null,
        'actual' => 'john@example.com',
    ]);
});

// Test differing values
it('detects differences in values', function () {
    $expected = [
        'name' => 'John Doe',
        'age' => 30,
    ];

    $actual = [
        'name' => 'Jane Doe', // Different value
        'age' => 30,
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toHaveCount(1);
    expect($differences)->toHaveKey('name');
    expect($differences['name'])->toEqual([
        'expected' => 'John Doe',
        'actual' => 'Jane Doe',
    ]);
});

// Test nested arrays
it('detects differences in nested arrays', function () {
    $expected = [
        'details' => [
            'email' => 'john@example.com',
            'scores' => [
                'a' => 85,
                'b' => 90,
                'c' => 95
            ],
        ],
    ];

    $actual = [
        'details' => [
            'email' => 'john@example.com',
            'scores' => [
                'a' => 85,
                'b' => 92,
                'c' => 95
            ], // Different second score
        ],
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toHaveCount(1);
    expect($differences)->toHaveKey('details.scores.b');
    expect($differences['details.scores.b'])->toMatchArray([
        'expected' => 90,
        'actual' => 92,
    ]);
});

// Test ignoring specific keys
it('ignores specified keys during comparison', function () {
    $expected = [
        'name' => 'John Doe',
        'timestamp' => '2024-04-01T10:00:00Z',
    ];

    $actual = [
        'name' => 'Jane Doe',
        'timestamp' => '2024-04-01T10:05:00Z',
    ];

    $comparer = new CompareNestedArrays(['timestamp']);
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toHaveCount(1);
    expect($differences)->toHaveKey('name');
    expect($differences['name'])->toEqual([
        'expected' => 'John Doe',
        'actual' => 'Jane Doe',
    ]);
});

// Test floating-point precision
it('handles floating-point precision differences within tolerance', function () {
    $expected = [
        'score' => 85.00001,
    ];

    $actual = [
        'score' => 85.00002, // Within tolerance of 0.0001
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toBeEmpty();
});

// Test floating-point precision beyond tolerance
it('detects floating-point differences beyond tolerance', function () {
    $expected = [
        'score' => 85.0001,
    ];

    $actual = [
        'score' => 85.001, // Beyond tolerance
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toHaveCount(1);
    expect($differences)->toHaveKey('score');
    expect($differences['score'])->toEqual([
        'expected' => 85.0001,
        'actual' => 85.001,
    ]);
});

// Test arrays with different data types
it('handles different data types correctly', function () {
    $expected = [
        'active' => true,
        'count' => 10,
        'ratio' => 0.5,
    ];

    $actual = [
        'active' => false,
        'count' => '10', // Different type (string vs integer)
        'ratio' => 0.5,
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toHaveCount(2);
    expect($differences)->toHaveKey('active');
    expect($differences['active'])->toEqual([
        'expected' => true,
        'actual' => false,
    ]);

    expect($differences)->toHaveKey('count');
    expect($differences['count'])->toEqual([
        'expected' => 10,
        'actual' => '10',
    ]);
});

// Test empty arrays
it('handles empty arrays correctly', function () {
    $expected = [];
    $actual = [];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toBeEmpty();
});

// Test one empty array and one non-empty array
it('detects all keys when one array is empty', function () {
    $expected = [];
    $actual = [
        'name' => 'John Doe',
        'age' => 30,
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toHaveCount(2);
    expect($differences)->toHaveKey('name');
    expect($differences['name'])->toEqual([
        'expected' => null,
        'actual' => 'John Doe',
    ]);
    expect($differences)->toHaveKey('age');
    expect($differences['age'])->toEqual([
        'expected' => null,
        'actual' => 30,
    ]);
});

// Test complex nested arrays
it('handles complex nested arrays and multiple differences', function () {
    $expected = [
        'user' => [
            'name' => 'John Doe',
            'roles' => ['admin', 'editor'],
            'preferences' => [
                'notifications' => true,
                'theme' => 'dark',
            ],
        ],
        'status' => 'active',
    ];

    $actual = [
        'user' => [
            'name' => 'John Doe',
            'roles' => ['admin', 'subscriber'], // Changed 'editor' to 'subscriber'
            'preferences' => [
                'notifications' => false, // Changed from true to false
                // 'theme' is missing
                'language' => 'en', // Added
            ],
        ],
        'status' => 'inactive', // Changed
        'last_login' => '2024-04-01T12:00:00Z', // Added
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toHaveCount(6);

    expect($differences)->toHaveKey('user.roles.1');
    expect($differences['user.roles.1'])->toEqual([
        'expected' => 'editor',
        'actual' => 'subscriber',
    ]);

    expect($differences)->toHaveKey('user.preferences.notifications');
    expect($differences['user.preferences.notifications'])->toEqual([
        'expected' => true,
        'actual' => false,
    ]);

    expect($differences)->toHaveKey('user.preferences.theme');
    expect($differences['user.preferences.theme'])->toEqual([
        'expected' => 'dark',
        'actual' => null,
    ]);

    expect($differences)->toHaveKey('user.preferences.language');
    expect($differences['user.preferences.language'])->toEqual([
        'expected' => null,
        'actual' => 'en',
    ]);

    expect($differences)->toHaveKey('status');
    expect($differences['status'])->toEqual([
        'expected' => 'active',
        'actual' => 'inactive',
    ]);

    expect($differences)->toHaveKey('last_login');
    expect($differences['last_login'])->toEqual([
        'expected' => null,
        'actual' => '2024-04-01T12:00:00Z',
    ]);
});

it('identifies keys missing in actual from expected', function () {
    $expected = [
        'user' => [
            'name' => 'John Doe',
            'roles' => ['admin', 'editor'],
            'preferences' => [
                'notifications' => true,
                'theme' => 'dark',
            ],
        ],
        'status' => 'active',
    ];

    $actual = [
        'user' => [
            'name' => 'John Doe',
            'roles' => ['admin'], // Missing 'editor'
            'preferences' => [
                'notifications' => true,
                // Missing 'theme'
            ],
        ],
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toHaveKey('user.roles.1');
    expect($differences['user.roles.1'])->toEqual([
        'expected' => 'editor',
        'actual' => null,
    ]);

    expect($differences)->toHaveKey('user.preferences.theme');
    expect($differences['user.preferences.theme'])->toEqual([
        'expected' => 'dark',
        'actual' => null,
    ]);

    expect($differences)->toHaveKey('status');
    expect($differences['status'])->toEqual([
        'expected' => 'active',
        'actual' => null,
    ]);
});

it('identifies keys found in expected but missing from actual', function () {
    $expected = [
        'user' => [
            'name' => 'John Doe',
        ],
    ];

    $actual = [
        'user' => [
            'name' => 'John Doe',
            'age' => 30, // Extra key
        ],
        'status' => 'active', // Extra key
    ];

    $comparer = new CompareNestedArrays();
    $differences = $comparer->compare($expected, $actual);

    expect($differences)->toHaveKey('user.age');
    expect($differences['user.age'])->toEqual([
        'expected' => null,
        'actual' => 30,
    ]);

    expect($differences)->toHaveKey('status');
    expect($differences['status'])->toEqual([
        'expected' => null,
        'actual' => 'active',
    ]);
});