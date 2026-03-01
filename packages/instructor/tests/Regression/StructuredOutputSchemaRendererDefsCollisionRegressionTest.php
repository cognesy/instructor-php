<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Schema\Tests\Examples\RefsCollision\NA\User as NAUser;
use Cognesy\Schema\Tests\Examples\RefsCollision\NB\User as NBUser;
use Cognesy\Schema\Tests\Examples\RefsCollision\Root;

it('keeps distinct tool-call $defs keys for classes sharing basename', function () {
    $renderer = new StructuredOutputSchemaRenderer(
        new StructuredOutputConfig(useObjectReferences: true),
    );

    $schema = $renderer->schemaFactory()->schema(Root::class);
    $rendering = $renderer->renderFromSchema($schema);
    $parameters = $rendering->toolCallSchema()[0]['function']['parameters'];
    $defs = $parameters['$defs'] ?? [];

    expect($defs)->toHaveCount(2);
    expect($parameters['properties']['naUser']['$ref'] ?? null)->not->toBe($parameters['properties']['nbUser']['$ref'] ?? null);

    $defsByClass = [];
    foreach ($defs as $key => $def) {
        $defsByClass[$def['x-php-class'] ?? ''] = $key;
    }

    expect($defsByClass)->toHaveKey(NAUser::class);
    expect($defsByClass)->toHaveKey(NBUser::class);
    expect($parameters['properties']['naUser']['$ref'] ?? null)->toBe('#/$defs/' . $defsByClass[NAUser::class]);
    expect($parameters['properties']['nbUser']['$ref'] ?? null)->toBe('#/$defs/' . $defsByClass[NBUser::class]);
});

