<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Evals;

use Cognesy\Agents\Evals\EvalTracePolicy;

it('renders a card number payload as a shape-only preview, per the approved fix', function (): void {
    $digest = EvalTracePolicy::safe()->digest(['card' => 'SECRET-4111111111111111']);

    expect($digest['preview'])->toBe('{"card":"<string:23>"}')
        ->and($digest['preview'])->not->toContain('SECRET')
        ->and($digest['preview'])->not->toContain('4111111111111111')
        ->and($digest['hash'])->toStartWith('sha256:')
        ->and($digest['bytes'])->toBe(strlen(json_encode(['card' => 'SECRET-4111111111111111'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
});

it('renders keys and elided types for a mixed map/list payload, without any of the values', function (): void {
    $payload = ['user' => ['id' => 7, 'ssn' => '123-45-6789'], 'rows' => [1, 2, 3]];

    $digest = EvalTracePolicy::safe()->digest($payload);

    expect($digest['preview'])->toBe('{"user":{"id":"<int>","ssn":"<string:11>"},"rows":"<array:3>"}')
        ->and($digest['preview'])->not->toContain('7')
        ->and($digest['preview'])->not->toContain('123-45-6789');
});

it('renders every scalar type as a shape placeholder that carries no value', function (): void {
    $payload = [
        'name' => 'Alice',
        'age' => 30,
        'score' => 12.5,
        'active' => true,
        'inactive' => false,
        'note' => null,
    ];

    $digest = EvalTracePolicy::safe()->digest($payload);

    expect($digest['preview'])->toBe(
        '{"name":"<string:5>","age":"<int>","score":"<float>","active":"<bool>","inactive":"<bool>","note":"<null>"}',
    );
});

it('bounds the preview of a very wide payload to previewBytes', function (): void {
    $wide = [];
    for ($i = 0; $i < 500; $i++) {
        $wide["key{$i}"] = "value-{$i}";
    }

    $digest = EvalTracePolicy::safe()->digest($wide);

    expect(strlen($digest['preview']))->toBeLessThanOrEqual(120)
        ->and($digest['preview'])->not->toContain('value-');
});

it('bounds the preview of a deeply nested payload to previewBytes', function (): void {
    $deep = 'bottom-secret';
    for ($i = 0; $i < 50; $i++) {
        $deep = ['nested' => $deep];
    }

    $digest = EvalTracePolicy::safe()->digest($deep);

    expect(strlen($digest['preview']))->toBeLessThanOrEqual(120)
        ->and($digest['preview'])->not->toContain('bottom-secret');
});

it('collapses a map past the recursion depth cap to an object placeholder instead of expanding further', function (): void {
    $deep = ['secret' => 'leaf-value'];
    for ($i = 0; $i < 10; $i++) {
        $deep = ['nested' => $deep];
    }

    $digest = EvalTracePolicy::safe()->withPreviewBytes(4096)->digest($deep);

    expect($digest['preview'])->not->toContain('leaf-value')
        ->and($digest['preview'])->toContain('<object:1>');
});

it('emits verbatim payloads under an explicitly constructed full() policy', function (): void {
    $payload = ['card' => 'SECRET-4111111111111111'];

    // full() is not a digest - it is the payload itself, passed straight through by
    // the caller (EvalStep::digestOrPassthrough). digest() is never invoked under
    // full(); this test documents that full() and safe() are policy branches, not
    // two renderings of digest().
    expect(EvalTracePolicy::full()->isFull())->toBeTrue()
        ->and(EvalTracePolicy::safe()->isFull())->toBeFalse();
});

it('recognizes an already-digested value and never digests it a second time', function (): void {
    $digest = EvalTracePolicy::safe()->digest(['card' => 'SECRET-4111111111111111']);

    expect(EvalTracePolicy::isDigest($digest))->toBeTrue()
        ->and(EvalTracePolicy::isDigest(['card' => 'SECRET-4111111111111111']))->toBeFalse()
        ->and(EvalTracePolicy::isDigest('not-a-digest'))->toBeFalse();
});

it('keeps hash and bytes stable and independent of the shape-only preview change', function (): void {
    $payload = ['card' => 'SECRET-4111111111111111'];
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $digest = EvalTracePolicy::safe()->digest($payload);

    expect($digest['hash'])->toBe('sha256:' . hash('sha256', $encoded))
        ->and($digest['bytes'])->toBe(strlen($encoded));
});
