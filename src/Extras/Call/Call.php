<?php

namespace Cognesy\Instructor\Extras\Call;

use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Utils\FunctionInfo;
use Cognesy\Instructor\Validation\ValidationResult;
use ReflectionFunction;

class Call implements CanDeserializeSelf, CanTransformSelf, CanProvideSchema, CanValidateSelf
{
    private string $name;
    private string $description;
    private Structure $arguments;

    public function __construct(
        string $name,
        string $description,
        Structure $arguments
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->arguments = $arguments;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): string {
        return $this->description;
    }

    /** @return string[] returns array argument names */
    public function getArgumentNames() : array {
        $arguments = [];
        foreach ($this->arguments->fields() as $field) {
            $arguments[] = $field->name();
        }
        return $arguments;
    }

    static public function fromFunctionName(string $name) : static {
        return self::fromFunctionInfo(FunctionInfo::fromFunctionName($name));
    }

    static public function fromMethodName(string $class, string $method) : static {
        return self::fromFunctionInfo(FunctionInfo::fromMethodName($class, $method));
    }

    static public function fromCallable(callable $callable) : static {
        return self::fromFunctionInfo(new FunctionInfo(new ReflectionFunction($callable)));
    }

    public function fromJson(string $jsonData): static {
        $this->arguments = $this->arguments->fromJson($jsonData);
        return $this;
    }

    public function transform(): mixed {
        return $this->toArgs();
    }

    public function toArgs(): array {
        $arguments = [];
        foreach ($this->arguments->fields() as $field) {
            $arguments[$field->name()] = $field->get();
        }
        return $arguments;
    }

    public function toSchema(): Schema {
        return $this->arguments->toSchema();
    }

    public function validate(): ValidationResult {
        return $this->arguments->validate();
    }

    public function toJsonSchema(): array {
        return $this->arguments->toJsonSchema();
    }

    public function toToolCall() : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->toJsonSchema(),
            ],
        ];
    }

    static private function fromFunctionInfo(FunctionInfo $functionInfo) : static {
        $functionName = $functionInfo->getShortName();
        $functionDescription = $functionInfo->getDescription();
        $arguments = self::getArgumentFields($functionInfo);
        return new static($functionName, $functionDescription, Structure::define('', $arguments, ''));
    }

    static private function getArgumentFields(FunctionInfo $functionInfo) : array {
        $arguments = [];
        $typeDetailsFactory = new TypeDetailsFactory;
        foreach ($functionInfo->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $parameterDescription = $functionInfo->getParameterDescription($parameterName);
            $typeDetails = $typeDetailsFactory->fromTypeName($parameter->getType());
            $arguments[] = Field::fromType($parameterName, $typeDetails, $parameterDescription);
        }
        return $arguments;
    }
}
