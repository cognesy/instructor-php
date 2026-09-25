<?php

declare(strict_types=1);

use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Core\DecisionRequestPreflight;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Models\DecisionCapabilities;
use Cognesy\Polyglot\Decision\Models\DecisionModel;
use Cognesy\Polyglot\Decision\Models\DecisionPrimitiveSupport;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('rejects only explicitly unsupported primitives', function (
    Noul|Choice|Score $question,
    string $unsupported,
): void {
    $request = decisionPreflightRequest(
        question: $question,
        capabilities: new DecisionCapabilities(
            choice: decisionPrimitiveSupport('choice', $unsupported),
            noul: decisionPrimitiveSupport('noul', $unsupported),
            score: decisionPrimitiveSupport('score', $unsupported),
        ),
    );

    expect(fn () => DecisionRequestPreflight::assertSupported($request))
        ->toThrow(InvalidArgumentException::class, 'does not support');
})->with([
    'Noul' => [new Noul('q'), 'noul'],
    'Choice' => [new Choice('q', ChoiceOptions::of(new ChoiceOption('a'))), 'choice'],
    'Score' => [new Score('q', ScoreLevels::of('low', 'high')), 'score'],
]);

it('allows absent profiles unknown capabilities and projected primitives', function (): void {
    $plain = new DecisionRequest('state', Questions::of(new Noul('q')), model: 'model');
    $unknown = decisionPreflightRequest(new Noul('q'), DecisionCapabilities::unknown());
    $projected = decisionPreflightRequest(
        new Noul('q'),
        new DecisionCapabilities(noul: DecisionPrimitiveSupport::Projected),
    );

    DecisionRequestPreflight::assertSupported($plain);
    DecisionRequestPreflight::assertSupported($unknown);
    DecisionRequestPreflight::assertSupported($projected);

    expect(true)->toBeTrue();
});

function decisionPreflightRequest(
    Noul|Choice|Score $question,
    DecisionCapabilities $capabilities,
): DecisionRequest {
    return (new DecisionRequest(
        input: 'state',
        questions: Questions::of($question),
        model: 'model',
    ))->withModelProfile(new DecisionModel(
        driver: 'test',
        model: 'model',
        capabilities: $capabilities,
    ));
}

function decisionPrimitiveSupport(
    string $primitive,
    string $unsupported,
): DecisionPrimitiveSupport {
    return match ($primitive === $unsupported) {
        true => DecisionPrimitiveSupport::Unsupported,
        false => DecisionPrimitiveSupport::Unknown,
    };
}
