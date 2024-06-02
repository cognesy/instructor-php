<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Traits;

use Cognesy\Instructor\Extras\Module\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Cognesy\Instructor\Schema\Utils\PropertyInfo;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Serializer\Attribute\Ignore;

#[Deprecated]
trait ProvidesSignature
{
    private array $internalProperties = [
        'signature',
    ];

    private function makeSignature(string $class) : Signature {
        $classInfo = new ClassInfo($class);
        $description = $classInfo->getClassDescription();

        $inputProperties = $classInfo->getFilteredPropertyNames([
            fn($property) => $property->hasAttribute(InputField::class),
            fn($property) => $this->defaultExclusionsFilter($property), // TODO: is it needed?
        ]);
        $inputSchema = $this->makeSchema($class, $inputProperties);

        $outputProperties = $classInfo->getFilteredPropertyNames([
            fn($property) => $property->hasAttribute(OutputField::class),
            fn($property) => $this->defaultExclusionsFilter($property), // TODO: is it needed?
        ]);
        $outputSchema = $this->makeSchema($class, $outputProperties);

        return new Signature($inputSchema, $outputSchema, $description);
    }

    private function makeSchema(string $class, array $propertyNames): Schema {
        $schema = (new SchemaFactory)->schema($class);
        foreach ($schema->getPropertyNames() as $property) {
            if (!in_array($property, $propertyNames)) {
                $schema->removeProperty($property);
            }
        }
        return $schema;
    }

    private function defaultExclusionsFilter(PropertyInfo $property) : bool {
        return match(true) {
            in_array($property->getName(), $this->internalProperties) => false,
            $property->hasAttribute(Ignore::class) => false,
            $property->isStatic() => false,
            default => true,
        };
    }
}