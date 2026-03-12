<?php

declare(strict_types=1);

use function Cognesy\Xprompt\flatten;

it('returns empty string for null', function () {
    expect(flatten(null))->toBe('');
});

it('passes through strings', function () {
    expect(flatten('hello'))->toBe('hello');
});

it('joins array elements with double newline', function () {
    expect(flatten(['a', 'b']))->toBe("a\n\nb");
});

it('skips null elements in arrays', function () {
    expect(flatten(['a', null, 'b']))->toBe("a\n\nb");
});

it('skips empty string elements in arrays', function () {
    expect(flatten(['a', '', 'b']))->toBe("a\n\nb");
});

it('flattens nested arrays', function () {
    expect(flatten([['a', 'b'], 'c']))->toBe("a\n\nb\n\nc");
});

it('flattens deeply nested arrays', function () {
    expect(flatten([[['a'], 'b'], ['c', [['d']]]]))->toBe("a\n\nb\n\nc\n\nd");
});

it('returns empty string for empty array', function () {
    expect(flatten([]))->toBe('');
});

it('returns empty string for array of nulls', function () {
    expect(flatten([null, null]))->toBe('');
});

it('casts Stringable objects to string', function () {
    $obj = new class implements Stringable {
        public function __toString(): string {
            return 'stringable';
        }
    };
    expect(flatten($obj))->toBe('stringable');
});

it('casts other types to string', function () {
    expect(flatten(42))->toBe('42');
    expect(flatten(3.14))->toBe('3.14');
    expect(flatten(true))->toBe('1');
});

it('handles mixed types in arrays', function () {
    $stringable = new class implements Stringable {
        public function __toString(): string {
            return 'obj';
        }
    };
    expect(flatten(['text', null, $stringable, 42]))->toBe("text\n\nobj\n\n42");
});

it('propagates context to Prompt instances', function () {
    // Create a minimal concrete Prompt for testing
    $prompt = new class extends Cognesy\Xprompt\Prompt {
        public function body(mixed ...$ctx): string|array|null
        {
            return "hello {$ctx['name']}";
        }
    };
    expect(flatten($prompt, ['name' => 'world']))->toBe('hello world');
});

it('propagates context through nested Prompt in array', function () {
    $prompt = new class extends Cognesy\Xprompt\Prompt {
        public function body(mixed ...$ctx): string|array|null
        {
            return "hi {$ctx['who']}";
        }
    };
    expect(flatten(['before', $prompt, 'after'], ['who' => 'there']))
        ->toBe("before\n\nhi there\n\nafter");
});
