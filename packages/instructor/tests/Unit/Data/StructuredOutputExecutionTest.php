<?php declare(strict_types=1);

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputExecutionId;

it('uses typed id and serializes id as string', function () {
    $execution = new StructuredOutputExecution();

    expect($execution->id())->toBeInstanceOf(StructuredOutputExecutionId::class);
    expect($execution->toArray()['id'])->toBe($execution->id()->toString());
});
