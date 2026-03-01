<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature\Factories;

use Cognesy\Experimental\Signature\Attributes\InputField;
use Cognesy\Experimental\Signature\Attributes\OutputField;
use Cognesy\Experimental\Signature\Signature;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Serializer\Attribute\Ignore;

class SignatureFromClassMetadata
{
    private array $internalProperties = [
        'signature',
    ];

    public function make(
        string $class,
        string $description = '',
    ) : Signature {
        $description = $description ?: (new SchemaFactory())->schema($class)->description();

        $inputProperties = $this->propertyDescriptionsWithAttribute($class, InputField::class);
        $inputSchema = $this->makeSchemaFromClass($class, $inputProperties);

        $outputProperties = $this->propertyDescriptionsWithAttribute($class, OutputField::class);
        $outputSchema = $this->makeSchemaFromClass($class, $outputProperties);

        return new Signature($inputSchema, $outputSchema, $description);
    }

    /**
     * @param array<string, string> $propertyDescriptions
     */
    private function makeSchemaFromClass(string $class, array $propertyDescriptions): Schema {
        $schema = (new SchemaFactory)->schema($class);
        if (!$schema instanceof ObjectSchema) {
            return $schema;
        }

        $selectedProperties = [];
        foreach ($propertyDescriptions as $propertyName => $propertyDescription) {
            if (!$schema->hasProperty($propertyName)) {
                continue;
            }

            $propertySchema = $schema->getPropertySchema($propertyName);
            $selectedProperties[$propertyName] = match (true) {
                $propertyDescription === '' => $propertySchema,
                default => SchemaFactory::withMetadata($propertySchema, description: $propertyDescription),
            };
        }

        $required = array_values(array_filter(
            $schema->required,
            static fn(string $name) : bool => isset($selectedProperties[$name]),
        ));

        return new ObjectSchema(
            type: $schema->type,
            name: $schema->name(),
            description: $schema->description(),
            properties: $selectedProperties,
            required: $required,
        );
    }

    /**
     * @param class-string $attributeClass
     * @return array<string, string>
     */
    private function propertyDescriptionsWithAttribute(string $class, string $attributeClass) : array {
        $properties = [];
        $reflectionClass = new ReflectionClass($class);
        foreach ($reflectionClass->getProperties() as $property) {
            if (!$this->defaultExclusionsFilter($property)) {
                continue;
            }
            $attributes = $property->getAttributes($attributeClass);
            if ($attributes === []) {
                continue;
            }
            $instance = $attributes[0]->newInstance();
            $properties[$property->getName()] = trim((string) ($instance->description ?? ''));
        }
        return $properties;
    }

    private function defaultExclusionsFilter(ReflectionProperty $property) : bool {
        return match(true) {
            in_array($property->getName(), $this->internalProperties) => false,
            $property->getAttributes(Ignore::class) !== [] => false,
            $property->isStatic() => false,
            default => true,
        };
    }
}
