<?php

namespace Cognesy\Instructor\Schema\Contracts;

use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\CollectionSchema;
use Cognesy\Instructor\Schema\Data\Schema\ArrayShapeSchema;
use Cognesy\Instructor\Schema\Data\Schema\EnumSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectRefSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;

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
}
