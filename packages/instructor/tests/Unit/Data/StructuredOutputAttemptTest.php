<?php declare(strict_types=1);

use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputAttemptId;

it('uses typed id and serializes id as string', function () {
    $attempt = new StructuredOutputAttempt();

    expect($attempt->id())->toBeInstanceOf(StructuredOutputAttemptId::class);
    expect($attempt->toArray()['id'])->toBe($attempt->id()->toString());
});
