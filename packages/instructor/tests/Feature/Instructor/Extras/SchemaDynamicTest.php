<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\MockHttp;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Schema\SchemaBuilder;

it('returns array output for dynamic schema when intoArray is used', function () {
    $city = Structure::fromSchema(
        SchemaBuilder::define('city')
            ->string('name', 'City name')
            ->int('population', 'City population')
            ->int('founded', 'Founding year')
            ->schema(),
    );

    $mockHttp = MockHttp::get([
        '{"name":"Paris","population":2148000,"founded":-52}'
    ]);

    $data = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(httpClient: $mockHttp, outputMode: OutputMode::JsonSchema))
        ->intoArray()
        ->withMessages([['role' => 'user', 'content' => 'What is the capital of France?']])
        ->withResponseJsonSchema($city->toJsonSchema())
        ->get();

    expect($data)->toBeArray();
    expect($data['name'])->toContain('Paris');
    expect($data['population'])->toBe(2148000);
    expect($data['founded'])->toBe(-52);
});
