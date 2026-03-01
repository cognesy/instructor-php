<?php declare(strict_types=1);

use Cognesy\Schema\Data\ScalarSchema;
use Cognesy\Schema\SchemaFactory;
use Symfony\Component\TypeInfo\TypeIdentifier;

it('maps scalar value input in schema() without raw TypeError', function () {
    $schema = SchemaFactory::default()->schema(123);

    expect($schema)->toBeInstanceOf(ScalarSchema::class)
        ->and($schema->type()->isIdentifiedBy(TypeIdentifier::INT))->toBeTrue();
});
