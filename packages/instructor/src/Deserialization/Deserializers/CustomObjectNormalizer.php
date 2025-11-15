<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization\Deserializers;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * Converts between objects and arrays using the PropertyAccess component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class CustomObjectNormalizer extends AbstractObjectNormalizer
{
    /** @var array<string, \ReflectionClass<object>> */
    private static array $reflectionCache = [];
    /** @var array<string, bool> */
    private static array $isReadableCache = [];
    /** @var array<string, bool> */
    private static array $isWritableCache = [];

    protected PropertyAccessorInterface $propertyAccessor;
    protected ?PropertyInfoExtractorInterface $propertyInfoExtractor = null;
    private ?ReflectionExtractor $writeInfoExtractor = null;

    /** @var \Closure(object|class-string): class-string */
    private readonly \Closure $objectClassResolver;

    /**
     * @param \Closure(object|class-string): class-string|null $objectClassResolver
     */
    public function __construct(
        ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?NameConverterInterface $nameConverter = null,
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?PropertyTypeExtractorInterface $propertyTypeExtractor = null,
        ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
        ?callable $objectClassResolver = null,
        array $defaultContext = [],
        ?PropertyInfoExtractorInterface $propertyInfoExtractor = null
    ) {
        if (!class_exists(PropertyAccess::class)) {
            throw new LogicException('The ObjectNormalizer class requires the "PropertyAccess" component. Try running "composer require symfony/property-access".');
        }

        parent::__construct($classMetadataFactory, $nameConverter, $propertyTypeExtractor, $classDiscriminatorResolver, $objectClassResolver, $defaultContext);

        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();

        $this->objectClassResolver = ($objectClassResolver ?? static fn($class) => \is_object($class) ? $class::class : $class)(...);
        $this->propertyInfoExtractor = $propertyInfoExtractor ?: new ReflectionExtractor();
        $this->writeInfoExtractor = new ReflectionExtractor();
    }

    /**
     * @return array<string, bool>
     */
    #[\Override]
    public function getSupportedTypes(?string $format): array {
        return ['object' => true];
    }

    #[\Override]
    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array {
        if (\stdClass::class === $object::class) {
            return array_keys((array)$object);
        }

        // If not using groups, detect manually
        $attributes = [];

        // methods
        $class = ($this->objectClassResolver)($object);
        $reflClass = new \ReflectionClass($class);

        foreach ($reflClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflMethod) {
            if (0 !== $reflMethod->getNumberOfRequiredParameters() || $reflMethod->isStatic() || $reflMethod->isConstructor() || $reflMethod->isDestructor()) {
                continue;
            }

            $name = $reflMethod->name;
            $attributeName = null;

            // ctype_lower check to find out if method looks like accessor but actually is not, e.g. hash, cancel
            if (3 < \strlen($name) && !ctype_lower($name[3]) && match ($name[0]) {
                    'g' => str_starts_with($name, 'get'),
                    'h' => str_starts_with($name, 'has'),
                    'c' => str_starts_with($name, 'can'),
                    default => false,
                }) {
                // getters, hassers and canners
                $attributeName = substr($name, 3);

                if (!$reflClass->hasProperty($attributeName)) {
                    $attributeName = lcfirst($attributeName);
                }
            } elseif ('is' !== $name && str_starts_with($name, 'is') && !ctype_lower($name[2])) {
                // issers
                $attributeName = substr($name, 2);

                if (!$reflClass->hasProperty($attributeName)) {
                    $attributeName = lcfirst($attributeName);
                }
            }

            if (null !== $attributeName && $this->isAllowedAttribute($object, $attributeName, $format, $context)) {
                $attributes[$attributeName] = true;
            }
        }

        // properties
        foreach ($reflClass->getProperties() as $reflProperty) {
            if (!$reflProperty->isPublic()) {
                continue;
            }

            if ($reflProperty->isStatic() || !$this->isAllowedAttribute($object, $reflProperty->name, $format, $context)) {
                continue;
            }

            $attributes[$reflProperty->name] = true;
        }

        return array_keys($attributes);
    }

    #[\Override]
    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed {
        $mapping = $this->classDiscriminatorResolver?->getMappingForMappedObject($object);

        return $attribute === $mapping?->getTypeProperty() ? $mapping : $this->propertyAccessor->getValue($object, $attribute);
    }

    #[\Override]
    protected function setAttributeValue(object $object, string $attribute, mixed $value, ?string $format = null, array $context = []): void {
        try {
            $this->propertyAccessor->setValue($object, $attribute, $value);
        } catch (NoSuchPropertyException) {
            // Properties not found are ignored
        }
    }

    #[\Override]
    protected function getAllowedAttributes(string|object $classOrObject, array $context, bool $attributesAsString = false): array|bool {
        if (false === $allowedAttributes = parent::getAllowedAttributes($classOrObject, $context, $attributesAsString)) {
            return false;
        }

        if (null !== $this->classDiscriminatorResolver) {
            $class = \is_object($classOrObject) ? $classOrObject::class : $classOrObject;
            if (null !== $discriminatorMapping = $this->classDiscriminatorResolver->getMappingForMappedObject($classOrObject)) {
                /** @phpstan-ignore-next-line */
                $allowedAttributes[] = $attributesAsString ? $discriminatorMapping->getTypeProperty() : new AttributeMetadata($discriminatorMapping->getTypeProperty());
            }

            if (null !== $discriminatorMapping = $this->classDiscriminatorResolver->getMappingForClass($class)) {
                $attributes = [];
                foreach ($discriminatorMapping->getTypesMapping() as $mappedClass) {
                    $mappedAttributes = parent::getAllowedAttributes($mappedClass, $context, $attributesAsString);
                    if (is_array($mappedAttributes)) {
                        $attributes[] = $mappedAttributes;
                    }
                }
                if (!empty($attributes)) {
                    /** @phpstan-ignore-next-line */
                    $allowedAttributes = array_merge($allowedAttributes, ...$attributes);
                }
            }
        }

        return $allowedAttributes;
    }

    #[\Override]
    protected function isAllowedAttribute(string|object $classOrObject, string $attribute, ?string $format = null, array $context = []): bool {
        if (!parent::isAllowedAttribute($classOrObject, $attribute, $format, $context)) {
            return false;
        }

        $class = \is_object($classOrObject) ? $classOrObject::class : $classOrObject;

        if ($context['_read_attributes'] ?? true) {
            if (!isset(self::$isReadableCache[$class . $attribute])) {
                /** @phpstan-ignore-next-line */
                self::$isReadableCache[$class . $attribute] = ($this->propertyInfoExtractor?->isReadable($class, $attribute) ?? false) || $this->hasAttributeAccessorMethod($class, $attribute) || (\is_object($classOrObject) && $this->propertyAccessor->isReadable($classOrObject, $attribute));
            }

            return self::$isReadableCache[$class . $attribute];
        }

        if (!isset(self::$isWritableCache[$class . $attribute])) {
            if (str_contains($attribute, '.')) {
                self::$isWritableCache[$class . $attribute] = true;
            } else {
                $writeInfo = $this->writeInfoExtractor?->getWriteInfo($class, $attribute);
                self::$isWritableCache[$class . $attribute] = ($this->propertyInfoExtractor?->isWritable($class, $attribute) ?? false) || ($writeInfo !== null && PropertyWriteInfo::TYPE_NONE !== $writeInfo->getType());
            }
        }

        return self::$isWritableCache[$class . $attribute];
    }

    /**
     * @param class-string $class
     */
    private function hasAttributeAccessorMethod(string $class, string $attribute): bool {
        if (!isset(self::$reflectionCache[$class])) {
            /** @var \ReflectionClass<object> $reflection */
            $reflection = new \ReflectionClass($class);
            self::$reflectionCache[$class] = $reflection;
        }

        $reflection = self::$reflectionCache[$class];

        if (!$reflection->hasMethod($attribute)) {
            return false;
        }

        $method = $reflection->getMethod($attribute);

        return !$method->isStatic() && !$method->getAttributes(Ignore::class) && !$method->getNumberOfRequiredParameters();
    }
}
