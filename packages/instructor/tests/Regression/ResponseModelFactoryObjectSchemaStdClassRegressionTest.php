<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureBuilder;

it('hydrates dynamic structure when ObjectSchema uses stdClass metadata', function () {
    $schema = StructureBuilder::define('city')
        ->string('name')
        ->int('population', required: false)
        ->schema();

    $model = makeAnyResponseModel($schema);
    $instance = $model->instance();

    expect($model->instanceClass())->toBe(Structure::class)
        ->and($instance)->toBeInstanceOf(Structure::class)
        ->and($instance->name())->toBe('city')
        ->and($instance->field('name')->isRequired())->toBeTrue()
        ->and($instance->field('population')->isOptional())->toBeTrue();
});
