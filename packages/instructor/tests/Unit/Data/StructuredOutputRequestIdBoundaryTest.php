<?php declare(strict_types=1);

use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Data\StructuredOutputRequestId;
use Cognesy\Messages\Messages;

it('round-trips request id through array boundary', function () {
    $request = new StructuredOutputRequest(
        messages: Messages::fromString('Extract fields'),
        requestedSchema: ['type' => 'object'],
    );

    $serialized = $request->toArray();
    $restored = StructuredOutputRequest::fromArray($serialized);

    expect($serialized['id'])->toBeString()->not->toBeEmpty()
        ->and($restored->id())->toBeInstanceOf(StructuredOutputRequestId::class)
        ->and($restored->id()->toString())->toBe($serialized['id']);
});
