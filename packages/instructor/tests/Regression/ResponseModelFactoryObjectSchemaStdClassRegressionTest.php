<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;

it('hydrates dynamic structure when ObjectSchema uses stdClass metadata', function () {
    $schema = SchemaBuilder::define('city')
        ->string('name')
        ->int('population', required: false)
        ->schema();

    $model = makeAnyResponseModel($schema);
    $instance = $model->instance();

    expect($model->instanceClass())->toBe(Structure::class)
        ->and($instance)->toBeInstanceOf(Structure::class)
        ->and($instance->name())->toBe('city')
        ->and($instance->schema()->required)->toContain('name')
        ->and($instance->schema()->required)->not->toContain('population');
});
