<?php declare(strict_types=1);

namespace Cognesy\Schema\Utils;

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\InputField;
use Cognesy\Schema\Attributes\Instructions;
use Cognesy\Schema\Attributes\OutputField;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

class Descriptions
{
    private function __construct() {}

    /**
     * @param class-string $class
     */
    public static function forClass(
        string $class,
    ): string {
        return (new self)->makeClassDescription($class);
    }

    /**
     * @param class-string $class
     */
    public static function forProperty(
        string $class,
        string $propertyName,
    ): string {
        return (new self)->makePropertyDescriptions($class, $propertyName);
    }

    public static function forFunction(
        string $functionName,
    ): string {
        return (new self)->makeFunctionDescription($functionName);
    }

    /**
     * @param class-string $class
     */
    public static function forMethod(
        string $class,
        string $methodName,
    ): string {
        return (new self)->makeMethodDescriptions($class, $methodName);
    }

    /**
     * @param class-string $class
     */
    public static function forMethodParameter(
        string $class,
        string $methodName,
        string $parameterName,
    ): string {
        $parameterReflection = new ReflectionParameter(
            [$class, $methodName],
            $parameterName
        );
        return (new self)->makeParameterDescriptions($parameterName, $parameterReflection);
    }

    public static function forFunctionParameter(
        string $functionName,
        string $parameterName,
    ): string {
        $parameterReflection = new ReflectionParameter(
            $functionName,
            $parameterName
        );
        return (new self)->makeParameterDescriptions($parameterName, $parameterReflection);
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    /**
     * @param class-string $class
     */
    private function makeClassDescription(string $class) : string {
        /** @var class-string $class */
        $reflection = new ReflectionClass($class);

        // get #[Description] attributes
        $descriptions = array_merge(
            AttributeUtils::getValues($reflection, Description::class, 'text'),
            AttributeUtils::getValues($reflection, Instructions::class, 'text'),
        );

        // get class description from PHPDoc
        $phpDocDescription = DocstringUtils::descriptionsOnly($reflection->getDocComment() ?: '');
        if ($phpDocDescription) {
            $descriptions[] = $phpDocDescription;
        }

        return trim(implode('\n', array_filter($descriptions)));
    }

    /**
     * @param class-string $class
     */
    private function makePropertyDescriptions(string $class, string $propertyName): string {
        $reflection = new ReflectionProperty($class, $propertyName);
        $extractor = $this->makeExtractor();

        $descriptions = array_merge(
            AttributeUtils::getValues($reflection, Description::class, 'text'),
            AttributeUtils::getValues($reflection, Instructions::class, 'text'),
            AttributeUtils::getValues($reflection, InputField::class, 'description'),
            AttributeUtils::getValues($reflection, OutputField::class, 'description'),
        );
        // get property description from PHPDoc
        $descriptions[] = $extractor->getShortDescription($class, $propertyName);
        $descriptions[] = $extractor->getLongDescription($class, $propertyName);

        $list = array_filter($descriptions, fn($desc) => !empty($desc));
        return trim(implode('\n', $list));
    }

    private function makeParameterDescriptions(
        string              $parameterName,
        ReflectionParameter $parameterReflection,
    ): string {
        $function = $parameterReflection->getDeclaringFunction();

        // get #[Description] attributes
        $descriptions = array_merge(
            AttributeUtils::getValues($parameterReflection, Description::class, 'text'),
            AttributeUtils::getValues($parameterReflection, Instructions::class, 'text'),
        );

        // get parameter description from PHPDoc
        $methodDescription = $function->getDocComment() ?: '';
        $docDescription = DocstringUtils::getParameterDescription($parameterName, $methodDescription);
        if ($docDescription) {
            $descriptions[] = $docDescription;
        }

        return trim(implode('\n', array_filter($descriptions)));
    }

    private function makeFunctionDescription(string $functionName): string {
        $reflection = new \ReflectionFunction($functionName);

        // get #[Description] attributes
        $descriptions = array_merge(
            AttributeUtils::getValues($reflection, Description::class, 'text'),
            AttributeUtils::getValues($reflection, Instructions::class, 'text'),
        );

        // get function description from PHPDoc
        $phpDocDescription = DocstringUtils::descriptionsOnly($reflection->getDocComment() ?: '');
        if ($phpDocDescription) {
            $descriptions[] = $phpDocDescription;
        }

        return trim(implode('\n', array_filter($descriptions)));
    }

    /**
     * @param class-string $class
     */
    private function makeMethodDescriptions(
        string $class,
        string $methodName,
    ): string {
        $reflection = new ReflectionMethod($class, $methodName);

        // get #[Description] attributes
        $descriptions = array_merge(
            AttributeUtils::getValues($reflection, Description::class, 'text'),
            AttributeUtils::getValues($reflection, Instructions::class, 'text'),
        );

        // get function description from PHPDoc
        $phpDocDescription = DocstringUtils::descriptionsOnly($reflection->getDocComment() ?: '');
        if ($phpDocDescription) {
            $descriptions[] = $phpDocDescription;
        }

        return trim(implode('\n', array_filter($descriptions)));
    }

    private function makeExtractor() : PropertyInfoExtractor {
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        return new PropertyInfoExtractor(
            [$reflectionExtractor],
            [new PhpStanExtractor(), $phpDocExtractor, $reflectionExtractor],
            [$phpDocExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor]
        );
    }
}