<?php
namespace Cognesy\Instructor\Extras\Structure\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\FieldFactory;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Symfony\Component\Serializer\Attribute\Ignore;

trait CreatesStructureFromClasses
{
    static public function fromClass(
        string $class,
        string $name = null,
        string $description = null
    ) : Structure {
        $classInfo = new ClassInfo($class);
        return self::fromClassInfo($classInfo, $name, $description);
    }

    static private function fromClassInfo(
        ClassInfo $classInfo,
        string $name = null,
        string $description = null
    ) : Structure {
        $className = $name ?? $classInfo->getShortName();
        $classDescription = $description ?? $classInfo->getClassDescription();
        $arguments = self::makePropertyFields($classInfo);
        return Structure::define($className, $arguments, $classDescription);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    /**
     * @return \Cognesy\Instructor\Extras\Structure\Field[]
     */
    static private function makePropertyFields(ClassInfo $classInfo) : array {
        $arguments = [];
        $typeDetailsFactory = new TypeDetailsFactory;
        foreach ($classInfo->getProperties() as $propertyName => $propertyInfo) {
            switch (true) {
                case $propertyInfo->isStatic():
                case !$propertyInfo->isPublic():
                case $propertyInfo->isReadOnly():
                case $propertyInfo->hasAttribute(Ignore::class):
                    continue 2;
            }
            $arguments[] = FieldFactory::fromTypeDetails(
                $propertyName,
                $typeDetailsFactory->fromTypeName($propertyInfo->getTypeName()),
                $propertyInfo->getDescription()
            )->optional($propertyInfo->isNullable());
        }
        return $arguments;
    }
}