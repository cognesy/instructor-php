<?php

declare(strict_types=1);

use Cognesy\Polyglot\Decision\Answers\ChoiceAnswer;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Answers\ScoreAnswer;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\ChoiceProbabilities;
use Cognesy\Polyglot\Decision\Collections\ScoreLegend;
use Cognesy\Polyglot\Decision\Collections\ScoreProbabilities;
use Cognesy\Polyglot\Decision\Data\JsonContent;

it('round-trips mixed answers and exposes exact typed accessors', function () {
    $answers = Answers::of(
        new NoulAnswer('urgent', 0.92),
        new ChoiceAnswer(
            questionId: 'target',
            value: '2',
            confidence: 0.5,
            probabilities: ChoiceProbabilities::of(['1', 0.5], ['2', 0.5]),
        ),
        new ScoreAnswer(
            questionId: 'impact',
            value: 1.6,
            confidence: 0.78,
            probabilities: ScoreProbabilities::of(0.05, 0.3, 0.65),
            legend: ScoreLegend::of(
                'Calm',
                JsonContent::list(['Frustrated', 'workaround exists']),
                JsonContent::object(['label' => 'Very angry', 'blocked' => true]),
            ),
        ),
    );

    $roundTrip = Answers::fromArray($answers->toArray());

    expect($roundTrip->count())->toBe(3)
        ->and($roundTrip->noul('urgent'))->toBeInstanceOf(NoulAnswer::class)
        ->and($roundTrip->noul('urgent')->probability())->toBe(0.92)
        ->and($roundTrip->choice('target'))->toBeInstanceOf(ChoiceAnswer::class)
        ->and($roundTrip->choice('target')->value())->toBe('2')
        ->and($roundTrip->choice('target')->probabilities()->ids())->toBe(['1', '2'])
        ->and($roundTrip->score('impact'))->toBeInstanceOf(ScoreAnswer::class)
        ->and($roundTrip->score('impact')->value())->toBe(1.6)
        ->and($roundTrip->score('impact')->legend()->at(2)->isObject())->toBeTrue()
        ->and($roundTrip->toArray())->toEqual($answers->toArray());
});

it('accepts rounded distributions and probability-weighted score rounding', function () {
    $choice = new ChoiceAnswer(
        'route',
        'a',
        0.0,
        ChoiceProbabilities::of(['a', 0.3333], ['b', 0.3333], ['c', 0.3333]),
    );
    $score = new ScoreAnswer(
        'severity',
        1.0,
        0.0,
        ScoreProbabilities::of(0.3333, 0.3333, 0.3333),
        ScoreLegend::of('Low', 'Medium', 'High'),
    );

    expect($choice->value())->toBe('a')
        ->and($score->value())->toBe(1.0)
        ->and($score->probabilities()->expectedValue())->toBeGreaterThan(0.999);
});

it('throws for missing answer IDs and wrong-kind access', function () {
    $answers = Answers::of(new NoulAnswer('urgent', 0.7));

    expect(fn () => $answers->answer('missing'))
        ->toThrow(InvalidArgumentException::class, "Answer for question 'missing' does not exist.")
        ->and(fn () => $answers->choice('urgent'))
        ->toThrow(InvalidArgumentException::class, "Answer for question 'urgent' is not a Choice answer.");
});

it('rejects malformed ranges distributions and answer semantics', function () {
    expect(fn () => new NoulAnswer('q', NAN))
        ->toThrow(InvalidArgumentException::class, 'finite number')
        ->and(fn () => new NoulAnswer('q', 1.01))
        ->toThrow(InvalidArgumentException::class, 'between 0 and 1')
        ->and(fn () => ChoiceProbabilities::of(['a', 0.2], ['b', 0.2]))
        ->toThrow(InvalidArgumentException::class, 'sum to 1')
        ->and(fn () => new ChoiceAnswer(
            'route',
            'b',
            0.6,
            ChoiceProbabilities::of(['a', 0.8], ['b', 0.2]),
        ))->toThrow(InvalidArgumentException::class, 'highest probability')
        ->and(fn () => new ScoreAnswer(
            'severity',
            0.0,
            0.5,
            ScoreProbabilities::of(0.1, 0.9),
            ScoreLegend::of('Low', 'High'),
        ))->toThrow(InvalidArgumentException::class, 'probability-weighted level');
});

it('rejects booleans numeric strings unknown fields and duplicate IDs', function () {
    expect(fn () => NoulAnswer::fromArray(['id' => 'q', 'type' => 'noul', 'probability' => true]))
        ->toThrow(InvalidArgumentException::class, 'finite number')
        ->and(fn () => NoulAnswer::fromArray(['id' => 'q', 'type' => 'noul', 'probability' => '0.8']))
        ->toThrow(InvalidArgumentException::class, 'finite number')
        ->and(fn () => NoulAnswer::fromArray([
            'id' => 'q',
            'type' => 'noul',
            'probability' => 0.8,
            'extra' => true,
        ]))->toThrow(InvalidArgumentException::class, 'Unknown Noul answer fields: extra')
        ->and(fn () => Answers::of(new NoulAnswer('same', 0.1), new NoulAnswer('same', 0.2)))
        ->toThrow(InvalidArgumentException::class, 'Answer question IDs must be unique');
});
