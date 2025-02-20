<?php

use Aimeos\Map;
use Cognesy\Utils\DataMap;

test('wildcard path "b.*.x" returns [1, 2]', function () {
    $dataMap = new DataMap([
        'a' => 1,
        'b' => [
            ['x' => 1],
            ['x' => 2]
        ]
    ]);

    $map = $dataMap->toMap('b.*.x');

    $result = $map->toArray();

    expect($result)->toBe([1, 2]);
});

test('wildcard path "b.*.x" returns [["x" => 1], ["x" => 2]]', function () {
    $dataMap = new DataMap([
        'a' => 1,
        'b' => [
            ['x' => ['x' => 1]],
            ['x' => ['x' => 2]]
        ]
    ]);

    $map = $dataMap->toMap('b.*.x');

    $result = $map->toArray();

    expect($result)->toBe([
        ['x' => 1],
        ['x' => 2],
    ]);
});

test('wildcard path "b.*.x.x" returns [1, 2]', function () {
    $dataMap = new DataMap([
        'a' => 1,
        'b' => [
            ['x' => ['x' => 1]],
            ['x' => ['x' => 2]],
        ]
    ]);

    $map = $dataMap->toMap('b.*.x.x');
    $result = $map->toArray();
    expect($result)->toBe([1, 2]);
});

test('wildcard path "b.*.x.*.x" returns empty map', function () {
    $dataMap = new DataMap([
        'a' => 1,
        'b' => [
            ['x' => ['x' => 1]],
            ['x' => ['x' => 2]],
        ]
    ]);

    $map = $dataMap->toMap('b.*.x.*.x');
    expect($map->isEmpty())->toBeTrue();
});

test('wildcard path "b.*.x.*.x" returns [1, 2]', function () {
    $dataMap = new DataMap([
        'a' => 1,
        'b' => [
            ['x' => [['x' => 1]]],
            ['x' => [['x' => 2]]],
        ]
    ]);

    $map = $dataMap->toMap('b.*.x.*.x');

    $result = $map->toArray();

    expect($result)->toBe([1, 2]);
});

test('wildcard path "b.*.x.x" returns [1, 3] with mixed types', function () {
    $dataMap = new DataMap([
        'a' => 1,
        'b' => [
            ['x' => ['x' => 1]],
            ['x' => 2], // 'x' is not an array here
            ['x' => ['x' => 3]]
        ]
    ]);

    $map = $dataMap->toMap('b.*.x.x');

    $result = $map->toArray();

    expect($result)->toBe([1, 3]);
});

test('wildcard path "b.*.x.*.x" returns [1, 3] with mixed types', function () {
    $dataMap = new DataMap([
        'a' => 1,
        'b' => [
            ['x' => [['x' => 1]]],
            ['x' => ['x' => 2]],
            ['x' => 3], // 'x' is not an array here
            ['x' => [['x' => 4]]]
        ]
    ]);

    $map = $dataMap->toMap('b.*.x.*.x');

    $result = $map->toArray();

    expect($result)->toBe([1, 4]);
});

test('wildcard path "b.*.x.*.y" returns empty Map', function () {
    $dataMap = new DataMap([
        'a' => 1,
        'b' => [
            ['x' => ['x' => 1]],
            ['x' => ['x' => 2]]
        ]
    ]);
    $result = $dataMap->toMap('b.*.x.*.y');

    expect($result)->toBeInstanceOf(Map::class);
    expect($result->isEmpty())->toBeTrue();
});

test('wildcard path "a.*" when used on a scalar path returns empty Map', function () {
    $dataMap = new DataMap([
        'a' => 1,
        'b' => [
            ['x' => 1],
            ['x' => 2]
        ]
    ]);
    $result = $dataMap->toMap('a.*');

    expect($result)->toBeInstanceOf(Map::class);
    expect($result->isEmpty())->toBeTrue();
});

test('wildcard path "c.*.x" returns empty Map when path does not exist', function () {
    $dataMap = new DataMap([
        'a' => 1,
        'b' => [
            ['x' => 1],
            ['x' => 2]
        ]
    ]);

    $result = $dataMap->toMap('c.*.x');

    expect($result)->toBeInstanceOf(Map::class);
    expect($result->isEmpty())->toBeTrue();
});

test('wildcard path "departments.*.teams.*.members" appends a new member', function () {
    $dataMap = new DataMap([
        'departments' => [
            'Engineering' => [
                'teams' => [
                    'Backend' => [
                        'members' => [
                            ['name' => 'Alice', 'role' => 'Developer'],
                            ['name' => 'Bob', 'role' => 'Developer'],
                        ],
                    ],
                    'Frontend' => [
                        'members' => [
                            ['name' => 'Charlie', 'role' => 'Designer'],
                            ['name' => 'Diana', 'role' => 'Developer'],
                        ],
                    ],
                ],
            ],
            'HR' => [
                'teams' => [
                    'Recruitment' => [
                        'members' => [
                            ['name' => 'Eve', 'role' => 'Recruiter'],
                            ['name' => 'Frank', 'role' => 'Recruiter'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $membersMap = $dataMap->toMap('departments.*.teams.*.members.*');
    expect($membersMap)->toBeInstanceOf(Map::class);
    expect($membersMap->count())->toBe(6);

    // Append a new member
    $newMember = ['name' => 'Grace', 'role' => 'Intern'];
    $membersMap = $membersMap->push($newMember);
    expect(count($membersMap))->toBe(7);
});

test('wildcard path "users.*.contact.email" returns all emails', function () {
    $dataMap = new DataMap([
        'users' => [
            [
                'name' => 'Alice',
                'age' => 30,
                'contact' => [
                    'email' => 'Alice@Example.com',
                    'phone' => '123-456-7890',
                ],
            ],
            [
                'name' => 'Bob',
                'age' => 22,
                'contact' => [
                    'email' => 'Bob@Example.com',
                    'phone' => '234-567-8901',
                ],
            ],
            [
                'name' => 'Charlie',
                'age' => 27,
                'contact' => [
                    'email' => 'Charlie@Example.com',
                    'phone' => '345-678-9012',
                ],
            ],
        ],
        'settings' => [
            'theme' => 'dark',
            'notifications' => true,
        ],
    ]);

    $map = $dataMap->toMap('users.*.contact.email');

    $result = $map->toArray();

    expect($result)->toBe([
        'Alice@Example.com',
        'Bob@Example.com',
        'Charlie@Example.com',
    ]);
});

test('wildcard path "users.*.contact.phone" returns all phone numbers', function () {
    $dataMap = new DataMap([
        'users' => [
            [
                'name' => 'Alice',
                'age' => 30,
                'contact' => [
                    'email' => 'Alice@Example.com',
                    'phone' => '123-456-7890',
                ],
            ],
            [
                'name' => 'Bob',
                'age' => 22,
                'contact' => [
                    'email' => 'Bob@Example.com',
                    'phone' => '234-567-8901',
                ],
            ],
            [
                'name' => 'Charlie',
                'age' => 27,
                'contact' => [
                    'email' => 'Charlie@Example.com',
                    'phone' => '345-678-9012',
                ],
            ],
        ],
        'settings' => [
            'theme' => 'dark',
            'notifications' => true,
        ],
    ]);

    $map = $dataMap->toMap('users.*.contact.phone');

    $result = $map->toArray();

    expect($result)->toBe([
        '123-456-7890',
        '234-567-8901',
        '345-678-9012',
    ]);
});
