<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpRequestBody;

it('throws explicit exception when array body cannot be json encoded', function () {
    $resource = fopen('php://memory', 'rb');

    expect(fn() => new HttpRequestBody(['stream' => $resource]))
        ->toThrow(InvalidArgumentException::class, 'Failed to encode request body as JSON');

    if (is_resource($resource)) {
        fclose($resource);
    }
});
