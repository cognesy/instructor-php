<?php declare(strict_types=1);

/**
 * Gate 1 (research/v2-cleanup-plan/01): Tier-1 public API surface lock — polyglot.
 * See the instructor twin for the policy. Regenerate intentionally with:
 *   API_SURFACE_UPDATE=1 vendor/bin/pest packages/polyglot/tests/Regression/PublicApiSurfaceLockTest.php
 */

const POLYGLOT_TIER1_CLASSES = [
    \Cognesy\Polyglot\Inference\Inference::class,
    \Cognesy\Polyglot\Inference\InferenceRuntime::class,
    \Cognesy\Polyglot\Inference\PendingInference::class,
    \Cognesy\Polyglot\Inference\LLMProvider::class,
    \Cognesy\Polyglot\Inference\Streaming\InferenceStream::class,
];

const POLYGLOT_TIER1_EVENT_FQCNS = [
    \Cognesy\Polyglot\Inference\Events\StreamFirstChunkReceived::class,
    \Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated::class,
    \Cognesy\Polyglot\Inference\Events\InferenceCompleted::class,
    \Cognesy\Polyglot\Inference\Events\InferenceResponseCreated::class,
];

function polyglotApiSurface(): array {
    $surface = [];
    foreach (POLYGLOT_TIER1_CLASSES as $class) {
        $surface[$class] = polyglotPublicMethodSignaturesOf($class);
    }
    return $surface;
}

function polyglotPublicMethodSignaturesOf(string $class): array {
    $reflection = new ReflectionClass($class);
    $signatures = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getDeclaringClass()->getName(), 'Cognesy\\')) {
            continue;
        }
        $signatures[$method->getName()] = polyglotMethodSignatureString($method);
    }
    ksort($signatures);
    return $signatures;
}

function polyglotMethodSignatureString(ReflectionMethod $method): string {
    $params = array_map(static function (ReflectionParameter $p): string {
        $type = $p->hasType() ? (string) $p->getType() : 'mixed';
        $variadic = $p->isVariadic() ? '...' : '';
        $optional = $p->isOptional() ? '=' : '';
        return "{$type} {$variadic}\${$p->getName()}{$optional}";
    }, $method->getParameters());

    $static = $method->isStatic() ? 'static ' : '';
    $return = $method->hasReturnType() ? (string) $method->getReturnType() : 'mixed';

    return $static . $method->getName() . '(' . implode(', ', $params) . '): ' . $return;
}

it('locks the Tier-1 polyglot API surface against the golden fixture', function () {
    $actual = polyglotApiSurface();
    $path = __DIR__ . '/Fixtures/public-api-surface.json';

    if (getenv('API_SURFACE_UPDATE') === '1' || !file_exists($path)) {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        expect(file_exists($path))->toBeTrue();
        return;
    }

    $expected = json_decode(file_get_contents($path), true);
    expect($expected)->toBeArray();

    $problems = [];
    foreach ($expected as $class => $methods) {
        if (!isset($actual[$class])) {
            $problems[] = "REMOVED class: {$class}";
            continue;
        }
        foreach ($methods as $name => $signature) {
            $current = $actual[$class][$name] ?? null;
            if ($current === null) {
                $problems[] = "REMOVED method: {$class}::{$name}";
            } elseif ($current !== $signature) {
                $problems[] = "CHANGED signature: {$class}::{$name}\n    was: {$signature}\n    now: {$current}";
            }
        }
        foreach ($actual[$class] as $name => $signature) {
            if (!isset($methods[$name])) {
                $problems[] = "ADDED method (update fixture to acknowledge): {$class}::{$name}";
            }
        }
    }
    foreach ($actual as $class => $_) {
        if (!isset($expected[$class])) {
            $problems[] = "ADDED class (update fixture to acknowledge): {$class}";
        }
    }

    expect($problems)->toBe([], "Tier-1 API surface drift detected:\n" . implode("\n", $problems)
        . "\n\nIf intentional: rerun with API_SURFACE_UPDATE=1 and commit the fixture diff + release note.");
});

it('keeps event FQCNs that examples bind to resolvable', function () {
    foreach (POLYGLOT_TIER1_EVENT_FQCNS as $fqcn) {
        expect(class_exists($fqcn))->toBeTrue("Event FQCN no longer resolves: {$fqcn}");
    }
});
