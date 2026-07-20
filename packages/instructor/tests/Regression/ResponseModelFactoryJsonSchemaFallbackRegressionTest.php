<?php declare(strict_types=1);

use Cognesy\Instructor\Data\OutputFormat;

final class JsonSchemaFallbackDto
{
    public string $name;
}

it('defaults a json schema without x-php-class to an array target', function () {
    $model = makeAnyResponseModel([
        'type' => 'object',
        'name' => 'city',
        'properties' => [
            'name' => ['type' => 'string'],
            'population' => ['type' => 'integer'],
        ],
        'required' => ['name'],
    ]);

    expect($model->outputFormat())->toEqual(OutputFormat::array());
});

it('normalizes leading backslash in x-php-class for json schema', function () {
    $model = makeAnyResponseModel([
        'x-php-class' => '\\' . JsonSchemaFallbackDto::class,
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
        'required' => ['name'],
    ]);

    expect($model->outputFormat())->toEqual(OutputFormat::instanceOf(JsonSchemaFallbackDto::class));
});

it('resolves json schema x-php-class stdClass directly', function () {
    $model = makeAnyResponseModel([
        'x-php-class' => stdClass::class,
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
        'required' => ['name'],
    ]);

    expect($model->outputFormat())->toEqual(OutputFormat::stdClass());
});
