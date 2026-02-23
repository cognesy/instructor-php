<?php declare(strict_types=1);

use Cognesy\Utils\Exceptions\DeserializedException;
use Cognesy\Utils\Exceptions\ErrorList;

it('creates immutable error lists and merges them', function () {
    $first = new RuntimeException('first');
    $second = new InvalidArgumentException('second');

    $errors = ErrorList::with($first)->withAppendedExceptions($second);
    $merged = ErrorList::empty()->withMergedErrorList($errors);

    expect($errors->count())->toBe(2)
        ->and($merged->count())->toBe(2)
        ->and($errors->first())->toBe($first)
        ->and($errors->hasError(RuntimeException::class))->toBeTrue()
        ->and($errors->hasError(DomainException::class))->toBeFalse()
        ->and($errors->toMessagesString())->toBe("first\nsecond");
});

it('hydrates from arrays and falls back to DeserializedException', function () {
    $errors = ErrorList::fromArray([
        ['class' => RuntimeException::class, 'message' => 'runtime'],
        ['class' => 'Not\\A\\Throwable', 'message' => 'fallback'],
        ['message' => 'default-class'],
        ['class' => RuntimeException::class],
        'invalid entry',
    ]);

    expect($errors->count())->toBe(4)
        ->and($errors->all()[0])->toBeInstanceOf(RuntimeException::class)
        ->and($errors->all()[1])->toBeInstanceOf(DeserializedException::class)
        ->and($errors->all()[2])->toBeInstanceOf(DeserializedException::class)
        ->and($errors->all()[3])->toBeInstanceOf(RuntimeException::class)
        ->and($errors->all()[0]->getMessage())->toBe('runtime')
        ->and($errors->all()[1]->getMessage())->toBe('fallback')
        ->and($errors->all()[2]->getMessage())->toBe('default-class')
        ->and($errors->all()[3]->getMessage())->toBe('Unknown execution error');
});
