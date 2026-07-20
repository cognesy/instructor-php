<?php declare(strict_types=1);

use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Schema\SchemaBuilder;

it('resolves ObjectSchema stdClass metadata directly', function () {
    $schema = SchemaBuilder::define('city')
        ->string('name')
        ->int('population', required: false)
        ->schema();

    $model = makeAnyResponseModel($schema);

    expect($model->outputFormat())->toEqual(OutputFormat::stdClass())
        ->and($model->schema()->required)->toContain('name')
        ->and($model->schema()->required)->not->toContain('population');
});
