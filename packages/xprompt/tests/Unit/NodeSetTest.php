<?php

declare(strict_types=1);

use Cognesy\Xprompt\NodeSet;

$fixtureDir = __DIR__ . '/../Fixtures/data';

it('renders inline items with default format', function () {
    $set = new class extends NodeSet {
        public array $items = [
            ['id' => 'a', 'label' => 'Alpha', 'content' => 'First item'],
            ['id' => 'b', 'label' => 'Beta', 'content' => 'Second item'],
        ];
    };
    $text = $set->render();
    expect($text)->toContain('1. **Alpha** -- First item');
    expect($text)->toContain('2. **Beta** -- Second item');
});

it('falls back to id when label is missing', function () {
    $set = new class extends NodeSet {
        public array $items = [
            ['id' => 'my_id', 'content' => 'Some content'],
        ];
    };
    $text = $set->render();
    expect($text)->toContain('1. **my_id** -- Some content');
});

it('renders label only when content is missing', function () {
    $set = new class extends NodeSet {
        public array $items = [
            ['id' => 'x', 'label' => 'Label Only'],
        ];
    };
    $text = $set->render();
    expect($text)->toBe('1. **Label Only**');
});

it('renders children as indented sub-items', function () {
    $set = new class extends NodeSet {
        public array $items = [
            [
                'id' => 'parent',
                'label' => 'Parent',
                'content' => 'Parent content',
                'children' => [
                    ['id' => 'c1', 'content' => 'Child one'],
                    ['id' => 'c2', 'content' => 'Child two'],
                ],
            ],
        ];
    };
    $text = $set->render();
    expect($text)->toContain('1. **Parent** -- Parent content');
    expect($text)->toContain('   - Child one');
    expect($text)->toContain('   - Child two');
});

it('sorts items by sortKey', function () {
    $set = new class extends NodeSet {
        public string $sortKey = 'priority';
        public array $items = [
            ['id' => 'c', 'label' => 'C', 'content' => 'Third', 'priority' => 3],
            ['id' => 'a', 'label' => 'A', 'content' => 'First', 'priority' => 1],
            ['id' => 'b', 'label' => 'B', 'content' => 'Second', 'priority' => 2],
        ];
    };
    $text = $set->render();
    // After sorting by priority: A(1), B(2), C(3)
    expect($text)->toContain('1. **A** -- First');
    expect($text)->toContain('2. **B** -- Second');
    expect($text)->toContain('3. **C** -- Third');
});

it('loads items from YAML data file', function () use ($fixtureDir) {
    $set = new class extends NodeSet {
        public string $dataFile = 'criteria.yml';
    };
    $set->templateDir = $fixtureDir;
    $text = $set->render();
    expect($text)->toContain('**Clarity**');
    expect($text)->toContain('**Accuracy**');
    expect($text)->toContain('Sources are cited');
});

it('sorts YAML data by sortKey', function () use ($fixtureDir) {
    $set = new class extends NodeSet {
        public string $dataFile = 'criteria.yml';
        public string $sortKey = 'priority';
    };
    $set->templateDir = $fixtureDir;
    $text = $set->render();
    // priority 1=Accuracy, 2=Clarity, 3=Brevity
    $accPos = strpos($text, 'Accuracy');
    $claPos = strpos($text, 'Clarity');
    $brePos = strpos($text, 'Brevity');
    expect($accPos)->toBeLessThan($claPos);
    expect($claPos)->toBeLessThan($brePos);
});

it('supports custom renderNode override', function () {
    $set = new class extends NodeSet {
        public array $items = [
            ['id' => 'x', 'label' => 'Test', 'content' => 'Details'],
        ];
        public function renderNode(int $index, array $node, mixed ...$ctx): string {
            return "[{$index}] {$node['label']}: {$node['content']}";
        }
    };
    $text = $set->render();
    expect($text)->toBe('[1] Test: Details');
});

it('supports dynamic nodes via override', function () {
    $set = new class extends NodeSet {
        public function nodes(mixed ...$ctx): array {
            return $ctx['items'] ?? [];
        }
    };
    $text = $set->render(items: [
        ['id' => 'd1', 'label' => 'Dynamic', 'content' => 'From context'],
    ]);
    expect($text)->toContain('1. **Dynamic** -- From context');
});

it('returns empty string for empty items', function () {
    $set = new class extends NodeSet {};
    expect($set->render())->toBe('');
});

it('works as element in composed prompt body', function () {
    $criteria = new class extends NodeSet {
        public array $items = [
            ['id' => 'a', 'label' => 'A', 'content' => 'Criterion A'],
        ];
    };

    $composed = new class extends \Cognesy\Xprompt\Prompt {
        public static ?NodeSet $criteria = null;
        public function body(mixed ...$ctx): array {
            return ['## Criteria', static::$criteria];
        }
    };
    $composed::$criteria = $criteria;

    $text = $composed->render();
    expect($text)->toContain('## Criteria');
    expect($text)->toContain('1. **A** -- Criterion A');
});
