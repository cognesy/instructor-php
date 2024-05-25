<?php
namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Utils\ClassInfo;

trait CreatesFromClasses
{
    static public function fromClass(
        string $class,
        string $name = null,
        string $description = null
    ) : static {
        $classInfo = new ClassInfo($class);
        return self::fromClassInfo($classInfo, $name, $description);
    }

    static private function fromClassInfo(
        ClassInfo $classInfo,
        string $name = null,
        string $description = null
    ) : static {
        $className = $name ?? $classInfo->getShortName();
        $classDescription = $description ?? $classInfo->getClassDescription();
        $arguments = self::makePropertyFields($classInfo);
        return Structure::define($className, $arguments, $classDescription);
    }

    static private function makePropertyFields(ClassInfo $classInfo) : array {
        $arguments = [];
        $typeDetailsFactory = new TypeDetailsFactory;
        foreach ($classInfo->getProperties() as $propertyName => $propertyInfo) {
            $propertyDescription = $propertyInfo->getDescription();
            $isOptional = $propertyInfo->isNullable();
            $typeDetails = $typeDetailsFactory->fromTypeName($propertyInfo->getTypeName());
            $arguments[] = Field::fromTypeDetails($propertyName, $typeDetails, $propertyDescription)->optional($isOptional);
        }
        return $arguments;
    }
}