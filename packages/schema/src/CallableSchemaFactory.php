<?php declare(strict_types=1);

namespace Cognesy\Schema;

use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

final class CallableSchemaFactory
{
    private TypeResolver $resolver;

    public function __construct(
        private readonly ?SchemaFactory $schemaFactory = null,
        ?TypeResolver $resolver = null,
    ) {
        $this->resolver = $resolver ?? TypeResolver::create();
    }

    /** @param callable(mixed...):mixed $callable */
    public function fromCallable(callable $callable, ?string $name = null, ?string $description = null) : Schema {
        return $this->fromReflection($this->reflectCallable($callable), $name, $description);
    }

    public function fromFunctionName(string $function, ?string $name = null, ?string $description = null) : Schema {
        return $this->fromReflection(new ReflectionFunction($function), $name, $description);
    }

    public function fromMethodName(string $class, string $method, ?string $name = null, ?string $description = null) : Schema {
        return $this->fromReflection(new ReflectionMethod($class, $method), $name, $description);
    }

    private function fromReflection(ReflectionFunctionAbstract $function, ?string $name, ?string $description) : Schema {
        $properties = [];
        $required = [];

        foreach ($function->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $hasDefaultValue = $parameter->isDefaultValueAvailable();
            $defaultValue = $hasDefaultValue ? $parameter->getDefaultValue() : null;
            $parameterSchema = $this->schemaFactory()->fromType(
                type: $this->parameterType($parameter),
                name: $parameterName,
                description: self::parameterDescription($function->getDocComment() ?: '', $parameterName),
                hasDefaultValue: $hasDefaultValue,
                defaultValue: $defaultValue,
            );

            $properties[$parameterName] = $parameterSchema;

            if (!$parameter->isOptional()) {
                $required[] = $parameterName;
            }
        }

        return new ObjectSchema(
            type: Type::object(\stdClass::class),
            name: $name ?? $function->getShortName(),
            description: $description ?? self::summary($function->getDocComment() ?: ''),
            properties: $properties,
            required: $required,
        );
    }

    private function schemaFactory() : SchemaFactory {
        return $this->schemaFactory ?? SchemaFactory::default();
    }

    private function parameterType(ReflectionParameter $parameter) : Type {
        $resolved = $this->resolver->resolve($parameter);
        if (!$parameter->isVariadic()) {
            return $resolved;
        }

        return match (true) {
            TypeInfo::isCollection($resolved) || TypeInfo::isArray($resolved) => $resolved,
            default => Type::list($resolved),
        };
    }

    /** @param callable(mixed...):mixed $callable */
    private function reflectCallable(callable $callable) : ReflectionFunctionAbstract {
        $closure = match (true) {
            $callable instanceof \Closure => $callable,
            default => \Closure::fromCallable($callable),
        };

        $reflection = new ReflectionFunction($closure);
        $scopeClass = $reflection->getClosureScopeClass()?->getName();
        $functionName = $reflection->getName();

        if ($scopeClass === null || str_contains($functionName, '{closure')) {
            return $reflection;
        }

        return new ReflectionMethod($scopeClass, $functionName);
    }

    private static function summary(string $docComment): string {
        if ($docComment === '') {
            return '';
        }

        $lines = preg_split('/\R/', $docComment) ?: [];
        $descriptionLines = [];

        foreach ($lines as $line) {
            $cleanLine = trim($line);
            $cleanLine = preg_replace('/^\/\*\*?/', '', $cleanLine) ?? '';
            $cleanLine = preg_replace('/\*\/$/', '', $cleanLine) ?? '';
            $cleanLine = preg_replace('/^\*/', '', $cleanLine) ?? '';
            $cleanLine = trim($cleanLine);

            if ($cleanLine === '' || str_starts_with($cleanLine, '@')) {
                continue;
            }

            $descriptionLines[] = $cleanLine;
        }

        return trim(implode("\n", $descriptionLines));
    }

    private static function parameterDescription(string $docComment, string $parameterName): string {
        if ($docComment === '') {
            return '';
        }

        if (!preg_match_all('/@param\s+[^$]*\$(\w+)\s*(.*)$/m', $docComment, $matches, PREG_SET_ORDER)) {
            return '';
        }

        foreach ($matches as $match) {
            if ($match[1] !== $parameterName) {
                continue;
            }

            return trim($match[2]);
        }

        return '';
    }
}
