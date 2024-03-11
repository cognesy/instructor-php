<?php
namespace Cognesy\Instructor\Schema\Data;

class TypeDetails
{
    public function __construct(
        public string       $type, // object, enum, array, int, string, bool, float
        public ?string      $class = null, // for objects and enums OR null
        public ?TypeDetails $nestedType = null, // for arrays OR null
        public ?string      $enumType = null, // for enums OR null
        public ?array       $enumValues = null, // for enums OR null
    ) {}

    public function __toString() : string
    {
        return match ($this->type) {
            'object' => $this->class,
            'enum' => $this->class,
            'array' => $this->nestedType->__toString().'[]',
            default => $this->type,
        };
    }

    public function jsonType() : string
    {
        return match ($this->type) {
            'object' => 'object',
            'enum' => $this->enumType ?? 'string',
            'array' => 'array',
            'int' => 'integer',
            'string' => 'string',
            'bool' => 'boolean',
            'float' => 'number',
            default => throw new \Exception('Unknown type: '.$this->type),
        };
    }

    public function shortName() : string {
        return match ($this->type) {
            'object' => $this->classOnly(),
            'enum' => "one of ".implode(', ', $this->enumValues),
            'array' => $this->nestedType->shortName().'[]',
            default => $this->type,
        };
    }

    public function classOnly() : string {
        if (!in_array($this->type, ['object', 'enum'])) {
            throw new \Exception('Trying to get class name for type that is not an object or enum');
        }
        $segments = explode('\\', $this->class);
        return array_pop($segments);
    }

    static public function fromJsonType(string $jsonType) : string {
        return match ($jsonType) {
            'object' => 'object',
            'array' => 'array',
            'integer' => 'int',
            'number' => 'float',
            'string' => 'string',
            'boolean' => 'bool',
            default => throw new \Exception('Unknown type: '.$jsonType),
        };
    }
}
