<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Utils\Metadata;

it('does not allow direct mutation of updatedAt and metadata', function () {
    $request = new HttpRequest('https://api.example.com', 'GET', [], '', []);

    expect(fn() => $request->updatedAt = new DateTimeImmutable('+1 minute'))
        ->toThrow(Error::class);

    expect(fn() => $request->metadata = Metadata::fromArray(['traceId' => '123']))
        ->toThrow(Error::class);
});
