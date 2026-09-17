<?php

declare(strict_types=1);

use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('round-trips mixed concrete question types and structured content', function () {
    $questions = Questions::of(
        new Noul(
            id: 'urgent',
            instructions: 'Does this need immediate attention?',
            criteria: new NoulCriteria(
                true: JsonContent::object(['meaning' => 'time-sensitive']),
                false: 'No urgency expressed',
            ),
        ),
        new Choice(
            id: 'target',
            instructions: JsonContent::object([
                'task' => 'Select the matching browser element',
                'elements' => (object) ['1' => ['label' => 'Submit'], '2' => ['label' => 'Cancel']],
            ]),
            options: ChoiceOptions::of(
                new ChoiceOption('1', JsonContent::object(['role' => 'button'])),
                new ChoiceOption('2'),
            ),
        ),
        new Score(
            id: 'impact',
            instructions: null,
            levels: ScoreLevels::of(
                'Normal work continues',
                JsonContent::list(['Work is impaired', 'A workaround exists']),
                JsonContent::object(['blocked' => true, 'workaround' => null]),
            ),
        ),
    );

    $roundTrip = Questions::fromArray($questions->toArray());

    expect($roundTrip->count())->toBe(3)
        ->and($roundTrip->question('urgent'))->toBeInstanceOf(Noul::class)
        ->and($roundTrip->question('target'))->toBeInstanceOf(Choice::class)
        ->and($roundTrip->question('impact'))->toBeInstanceOf(Score::class)
        ->and($roundTrip->toArray())->toEqual($questions->toArray())
        ->and($roundTrip->question('impact')->instructions())->toBeNull();
});

it('supports empty question definitions but validates collection lookups', function () {
    $questions = Questions::empty();

    expect($questions->isEmpty())->toBeTrue()
        ->and($questions->count())->toBe(0)
        ->and($questions->toArray())->toBe([])
        ->and(fn () => $questions->question('missing'))
        ->toThrow(InvalidArgumentException::class, "Question 'missing' does not exist.");
});

it('rejects duplicate question and choice option IDs', function () {
    $duplicate = new Noul('duplicate');

    expect(fn () => Questions::of($duplicate, $duplicate))
        ->toThrow(InvalidArgumentException::class, 'Question IDs must be unique')
        ->and(fn () => ChoiceOptions::of(
            new ChoiceOption('same'),
            new ChoiceOption('same'),
        ))
        ->toThrow(InvalidArgumentException::class, 'Choice option IDs must be unique');
});

it('rejects malformed question definitions before execution', function () {
    expect(fn () => new Noul(''))
        ->toThrow(InvalidArgumentException::class, 'non-empty string')
        ->and(fn () => ChoiceOptions::fromArray([]))
        ->toThrow(InvalidArgumentException::class, 'at least one option')
        ->and(fn () => ScoreLevels::of('only one'))
        ->toThrow(InvalidArgumentException::class, 'at least two levels')
        ->and(fn () => Questions::fromArray([['id' => 'x', 'type' => 'unknown']]))
        ->toThrow(InvalidArgumentException::class, 'Unknown question type')
        ->and(fn () => Questions::fromArray([['id' => 'x', 'type' => 'noul', 'extra' => true]]))
        ->toThrow(InvalidArgumentException::class, 'Unknown Noul question fields: extra');
});

it('rejects malformed nested content', function () {
    expect(fn () => ChoiceOption::fromArray(['id' => 'x', 'description' => 123]))
        ->toThrow(InvalidArgumentException::class, 'description must be text')
        ->and(fn () => ScoreLevels::fromArray(['valid', null]))
        ->toThrow(InvalidArgumentException::class, 'Each Score level')
        ->and(fn () => NoulCriteria::fromArray(['true' => 'yes', 'other' => 'no']))
        ->toThrow(InvalidArgumentException::class, 'Unknown Noul criteria fields: other');
});
