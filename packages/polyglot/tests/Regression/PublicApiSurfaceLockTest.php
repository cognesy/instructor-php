<?php

declare(strict_types=1);
use Cognesy\Polyglot\Decision\Answers\ChoiceAnswer;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Answers\ScoreAnswer;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\ChoiceProbabilities;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLegend;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Collections\ScoreProbabilities;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionProviderRequestId;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionRequestId;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\DecisionProvider;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptFailed;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptStarted;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptSucceeded;
use Cognesy\Polyglot\Decision\Events\DecisionCompleted;
use Cognesy\Polyglot\Decision\Events\DecisionFailed;
use Cognesy\Polyglot\Decision\Events\DecisionStarted;
use Cognesy\Polyglot\Decision\PendingDecision;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated;
use Cognesy\Polyglot\Inference\Events\StreamFirstChunkReceived;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;

/**
 * Gate 1 (research/v2-cleanup-plan/01): Tier-1 public API surface lock — polyglot.
 * See the instructor twin for the policy. Regenerate intentionally with:
 *   API_SURFACE_UPDATE=1 vendor/bin/pest packages/polyglot/tests/Regression/PublicApiSurfaceLockTest.php
 */
const POLYGLOT_TIER1_CLASSES = [
    Inference::class,
    InferenceRuntime::class,
    PendingInference::class,
    LLMProvider::class,
    InferenceStream::class,
    Decision::class,
    DecisionRuntime::class,
    PendingDecision::class,
    DecisionProvider::class,
    DecisionConfig::class,
    DecisionRetryPolicy::class,
    DecisionRequest::class,
    DecisionResponse::class,
    DecisionUsage::class,
    DecisionRequestId::class,
    DecisionProviderRequestId::class,
    JsonContent::class,
    NoulCriteria::class,
    ChoiceOption::class,
    Noul::class,
    Choice::class,
    Score::class,
    NoulAnswer::class,
    ChoiceAnswer::class,
    ScoreAnswer::class,
    Questions::class,
    Answers::class,
    ChoiceOptions::class,
    ChoiceProbabilities::class,
    ScoreLevels::class,
    ScoreProbabilities::class,
    ScoreLegend::class,
];

const POLYGLOT_TIER1_EVENT_FQCNS = [
    StreamFirstChunkReceived::class,
    PartialInferenceDeltaCreated::class,
    InferenceCompleted::class,
    InferenceResponseCreated::class,
    DecisionStarted::class,
    DecisionAttemptStarted::class,
    DecisionAttemptSucceeded::class,
    DecisionAttemptFailed::class,
    DecisionCompleted::class,
    DecisionFailed::class,
];

function polyglotApiSurface(): array
{
    $surface = [];
    foreach (POLYGLOT_TIER1_CLASSES as $class) {
        $surface[$class] = polyglotPublicMethodSignaturesOf($class);
    }

    return $surface;
}

function polyglotPublicMethodSignaturesOf(string $class): array
{
    $reflection = new ReflectionClass($class);
    $signatures = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (! str_starts_with($method->getDeclaringClass()->getName(), 'Cognesy\\')) {
            continue;
        }
        $signatures[$method->getName()] = polyglotMethodSignatureString($method);
    }
    ksort($signatures);

    return $signatures;
}

function polyglotMethodSignatureString(ReflectionMethod $method): string
{
    $declaringClass = $method->getDeclaringClass();
    $params = array_map(static function (ReflectionParameter $p) use ($declaringClass): string {
        $type = polyglotCanonicalType($p->getType(), $declaringClass);
        $variadic = $p->isVariadic() ? '...' : '';
        $optional = $p->isOptional() ? '=' : '';

        return "{$type} {$variadic}\${$p->getName()}{$optional}";
    }, $method->getParameters());

    $static = $method->isStatic() ? 'static ' : '';
    $return = polyglotCanonicalType($method->getReturnType(), $declaringClass);

    return $static.$method->getName().'('.implode(', ', $params).'): '.$return;
}

function polyglotCanonicalType(?ReflectionType $type, ReflectionClass $declaringClass): string
{
    if ($type === null) {
        return 'mixed';
    }

    return polyglotCanonicalTypeName((string) $type, $declaringClass);
}

function polyglotCanonicalTypeName(string $type, ReflectionClass $declaringClass): string
{
    $aliases = ['self' => $declaringClass->getName()];
    $parent = $declaringClass->getParentClass();
    if ($parent !== false) {
        $aliases['parent'] = $parent->getName();
    }

    $canonical = preg_replace_callback(
        '/(?<![A-Za-z0-9_\\\\])(self|parent)(?![A-Za-z0-9_\\\\])/',
        static fn (array $matches): string => $aliases[$matches[1]] ?? $matches[1],
        $type,
    );
    assert(is_string($canonical));

    return $canonical;
}

it('locks the Tier-1 polyglot API surface against the golden fixture', function () {
    $actual = polyglotApiSurface();
    $path = __DIR__.'/Fixtures/public-api-surface.json';

    if (getenv('API_SURFACE_UPDATE') === '1' || ! file_exists($path)) {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        expect(file_exists($path))->toBeTrue();

        return;
    }

    $expected = json_decode(file_get_contents($path), true);
    expect($expected)->toBeArray();

    $problems = [];
    foreach ($expected as $class => $methods) {
        if (! isset($actual[$class])) {
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
            if (! isset($methods[$name])) {
                $problems[] = "ADDED method (update fixture to acknowledge): {$class}::{$name}";
            }
        }
    }
    foreach ($actual as $class => $_) {
        if (! isset($expected[$class])) {
            $problems[] = "ADDED class (update fixture to acknowledge): {$class}";
        }
    }

    expect($problems)->toBe([], "Tier-1 API surface drift detected:\n".implode("\n", $problems)
        ."\n\nIf intentional: rerun with API_SURFACE_UPDATE=1 and commit the fixture diff + release note.");
});

it('keeps event FQCNs that examples bind to resolvable', function () {
    foreach (POLYGLOT_TIER1_EVENT_FQCNS as $fqcn) {
        expect(class_exists($fqcn))->toBeTrue("Event FQCN no longer resolves: {$fqcn}");
    }
});

it('normalizes scope-relative API types independently of PHP reflection rendering', function () {
    $class = new ReflectionClass(Decision::class);

    expect(polyglotCanonicalTypeName('self', $class))->toBe(Decision::class)
        ->and(polyglotCanonicalTypeName('?self', $class))->toBe('?'.Decision::class)
        ->and(polyglotCanonicalTypeName('self|static', $class))->toBe(Decision::class.'|static');
});
