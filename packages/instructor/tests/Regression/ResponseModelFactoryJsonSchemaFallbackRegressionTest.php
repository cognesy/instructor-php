<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;

it('uses Structure fallback when json schema has no x-php-class', function () {
    $model = makeAnyResponseModel([
        'type' => 'object',
        'name' => 'city',
        'properties' => [
            'name' => ['type' => 'string'],
            'population' => ['type' => 'integer'],
        ],
        'required' => ['name'],
    ]);

    $instance = $model->instance();

    expect($model->instanceClass())->toBe(Structure::class)
        ->and($instance)->toBeInstanceOf(Structure::class)
        ->and($instance->name())->toBe('city');
});

it('normalizes leading backslash in x-php-class for json schema', function () {
    $model = makeAnyResponseModel([
        'x-php-class' => '\\' . Structure::class,
        'type' => 'object',
        'name' => 'city',
        'properties' => [
            'name' => ['type' => 'string'],
        ],
        'required' => ['name'],
    ]);

    expect($model->instanceClass())->toBe(Structure::class)
        ->and($model->instance())->toBeInstanceOf(Structure::class);
});
