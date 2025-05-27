<?php
namespace Cognesy\Experimental\Module\Signature\Traits\Factory;

use Cognesy\Experimental\Module\Signature\Attributes\InputField;
use Cognesy\Experimental\Module\Signature\Attributes\OutputField;
use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Utils\ClassInfo;
use Cognesy\Schema\Utils\PropertyInfo;
use Symfony\Component\Serializer\Attribute\Ignore;

trait CreatesFromClassMetadata
{
    private array $internalProperties = [
        'signature',
    ];

    static public function fromClassMetadata(
        string $class,
        string $description = '',
    ): Signature {
        return (new self)->makeSignatureFromClass($class, $description);
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////////

    private function makeSignatureFromClass(
        string $class,
        string $description = '',
    ) : Signature {
        $classInfo = new ClassInfo($class);
        $description = $description ?: $classInfo->getClassDescription();

        $inputProperties = $classInfo->getFilteredPropertyNames([
            fn($property) => $property->hasAttribute(InputField::class),
            fn($property) => $this->defaultExclusionsFilter($property), // TODO: is it needed?
        ]);
        $inputSchema = $this->makeSchemaFromClass($class, $inputProperties);

        $outputProperties = $classInfo->getFilteredPropertyNames([
            fn($property) => $property->hasAttribute(OutputField::class),
            fn($property) => $this->defaultExclusionsFilter($property), // TODO: is it needed?
        ]);
        $outputSchema = $this->makeSchemaFromClass($class, $outputProperties);

        return new Signature($inputSchema, $outputSchema, $description);
    }

    private function makeSchemaFromClass(string $class, array $propertyNames): Schema {
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