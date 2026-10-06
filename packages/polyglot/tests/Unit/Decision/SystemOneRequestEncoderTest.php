<?php

declare(strict_types=1);

use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Drivers\SystemOne\SystemOneRequestEncoder;
use Cognesy\Polyglot\Decision\Questions\Score;

function systemOneScoreRequest(int $levels): DecisionRequest
{
    return new DecisionRequest(
        input: 'state',
        questions: Questions::of(new Score(
            'scale',
            ScoreLevels::of(...array_map(static fn (int $level): string => "Level {$level}", range(1, $levels))),
        )),
    );
}

it('keeps the ten-level Score limit by default', function () {
    $encoder = new SystemOneRequestEncoder('Provider');

    expect($encoder->encode(systemOneScoreRequest(10), 'model'))->toBeString()
        ->and(fn () => $encoder->encode(systemOneScoreRequest(11), 'model'))
        ->toThrow(InvalidArgumentException::class, 'Provider Score supports between 2 and 10 levels.');
});

it('applies a provider-specific Score level limit', function () {
    $encoder = new SystemOneRequestEncoder('Provider', maxScoreLevels: 255);
    $body = json_decode($encoder->encode(systemOneScoreRequest(255), 'model'), flags: JSON_THROW_ON_ERROR);

    expect($body->questions->scale->criteria)->toHaveCount(255)
        ->and(fn () => $encoder->encode(systemOneScoreRequest(256), 'model'))
        ->toThrow(InvalidArgumentException::class, 'Provider Score supports between 2 and 255 levels.');
});
