<?php declare(strict_types=1);

namespace Cognesy\Schema\Contracts;

use Cognesy\Schema\Data\Schema\ArraySchema;
use Cognesy\Schema\Data\Schema\ArrayShapeSchema;
use Cognesy\Schema\Data\Schema\CollectionSchema;
use Cognesy\Schema\Data\Schema\EnumSchema;
use Cognesy\Schema\Data\Schema\MixedSchema;
use Cognesy\Schema\Data\Schema\ObjectRefSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\OptionSchema;
use Cognesy\Schema\Data\Schema\ScalarSchema;
use Cognesy\Schema\Data\Schema\Schema;

interface CanVisitSchema
{
    public function visitSchema(Schema $schema): void;
    public function visitCollectionSchema(CollectionSchema $schema): void;

    public function visitArraySchema(ArraySchema $schema): void;
    public function visitArrayShapeSchema(ArrayShapeSchema $schema): void;
    public function visitObjectSchema(ObjectSchema $schema): void;
    public function visitEnumSchema(EnumSchema $schema): void;
    public function visitScalarSchema(ScalarSchema $schema): void;
    public function visitObjectRefSchema(ObjectRefSchema $schema): void;
    public function visitOptionSchema(OptionSchema $schema) : void;
    public function visitMixedSchema(MixedSchema $schema): void;
}
