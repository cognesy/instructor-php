<?php declare(strict_types=1);

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\MockHttp;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

it('returns array output for dynamic schema when intoArray is used', function () {
    $city = Structure::define('city', [
        Field::string('name', 'City name')->required(),
        Field::int('population', 'City population')->required(),
        Field::int('founded', 'Founding year')->required(),
    ]);

    $mockHttp = MockHttp::get([
        '{"name":"Paris","population":2148000,"founded":-52}'
    ]);

    $data = (new StructuredOutput)
        ->withHttpClient($mockHttp)
        ->intoArray()
        ->withMessages([['role' => 'user', 'content' => 'What is the capital of France?']])
        ->withResponseJsonSchema($city->toJsonSchema())
        ->withOutputMode(OutputMode::JsonSchema)
        ->get();

    expect($data)->toBeArray();
    expect($data['name'])->toContain('Paris');
    expect($data['population'])->toBe(2148000);
    expect($data['founded'])->toBe(-52);
});
