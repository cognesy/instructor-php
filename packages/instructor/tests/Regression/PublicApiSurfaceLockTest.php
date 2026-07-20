<?php declare(strict_types=1);

/**
 * Gate 1 (v2-cleanup-plan/01): Tier-1 public API surface lock.
 *
 * The compatibility contract is examples-driven: the classes below are what
 * ./examples/ (and user code shaped like it) calls directly. This test snapshots
 * their public method signatures into a golden fixture and fails on ANY drift —
 * removals, signature changes, and additions alike — so every surface change is
 * an explicit, reviewed fixture diff in the PR, never an accident.
 *
 * Intentional change? Regenerate the fixture and commit the diff:
 *   API_SURFACE_UPDATE=1 vendor/bin/pest packages/instructor/tests/Regression/PublicApiSurfaceLockTest.php
 */

const INSTRUCTOR_TIER1_CLASSES = [
    \Cognesy\Instructor\StructuredOutput::class,
    \Cognesy\Instructor\StructuredOutputRuntime::class,
    \Cognesy\Instructor\PendingStructuredOutput::class,
    \Cognesy\Instructor\StructuredOutputStream::class,
    \Cognesy\Instructor\Config\StructuredOutputConfig::class,
    \Cognesy\Instructor\Extras\Sequence\Sequence::class,
    \Cognesy\Instructor\Extras\Maybe\Maybe::class,
    \Cognesy\Instructor\Extras\Scalar\Scalar::class,
];

// Event FQCNs that examples bind listeners to. Namespace changes are explicit
// compatibility decisions and require updating this lock plus release notes.
const INSTRUCTOR_TIER1_EVENT_FQCNS = [
    \Cognesy\Instructor\Events\Response\ResponseValidated::class,
    \Cognesy\Instructor\Events\Response\ResponseValidationAttempt::class,
    \Cognesy\Instructor\Events\Response\ResponseValidationFailed::class,
    \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseUpdated::class,
    \Cognesy\Instructor\Events\Streaming\PartialResponseGenerated::class,
    \Cognesy\Instructor\Events\Streaming\SequenceUpdated::class,
];

function instructorApiSurface(): array {
    $surface = [];
    foreach (INSTRUCTOR_TIER1_CLASSES as $class) {
        $surface[$class] = publicMethodSignaturesOf($class);
    }
    return $surface;
}

function publicMethodSignaturesOf(string $class): array {
    $reflection = new ReflectionClass($class);
    $signatures = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        // Only Cognesy-declared methods; skip vendor/SPL inherited noise
        if (!str_starts_with($method->getDeclaringClass()->getName(), 'Cognesy\\')) {
            continue;
        }
        $signatures[$method->getName()] = methodSignatureString($method);
    }
    ksort($signatures);
    return $signatures;
}

function methodSignatureString(ReflectionMethod $method): string {
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

function surfaceFixturePath(): string {
    return __DIR__ . '/Fixtures/public-api-surface.json';
}

it('locks the Tier-1 public API surface against the golden fixture', function () {
    $actual = instructorApiSurface();
    $path = surfaceFixturePath();

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
    foreach (INSTRUCTOR_TIER1_EVENT_FQCNS as $fqcn) {
        expect(class_exists($fqcn))->toBeTrue("Event FQCN no longer resolves: {$fqcn}");
    }
});
