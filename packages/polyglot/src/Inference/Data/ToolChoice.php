<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use InvalidArgumentException;

readonly final class ToolChoice
{
    private function __construct(
        private string $mode = '',
        private ?string $functionName = null,
    ) {}

    public static function auto() : self {
        return new self(mode: 'auto');
    }

    public static function required() : self {
        return new self(mode: 'required');
    }

    public static function none() : self {
        return new self(mode: 'none');
    }

    public static function specific(string $name) : self {
        return new self(mode: 'specific', functionName: $name);
    }

    public static function empty() : self {
        return new self();
    }

    public static function fromAny(string|array|self $choice) : self {
        return match (true) {
            $choice instanceof self => $choice,
            is_string($choice) => self::fromString($choice),
            default => self::fromArray($choice),
        };
    }

    public function mode() : string {
        return $this->mode;
    }

    public function isAuto() : bool {
        return $this->mode === 'auto';
    }

    public function isRequired() : bool {
        return $this->mode === 'required';
    }

    public function isNone() : bool {
        return $this->mode === 'none';
    }

    public function isSpecific() : bool {
        return $this->functionName !== null;
    }

    public function isEmpty() : bool {
        return $this->mode === '' && $this->functionName === null;
    }

    public function functionName() : ?string {
        return $this->functionName;
    }

    public function toArray() : string|array {
        return match (true) {
            $this->isEmpty() => [],
            $this->isSpecific() => [
                'type' => 'function',
                'function' => ['name' => $this->functionName],
            ],
            default => $this->mode,
        };
    }

    private static function fromString(string $choice) : self {
        return match ($choice) {
            '' => self::empty(),
            'auto' => self::auto(),
            'required' => self::required(),
            'none' => self::none(),
            default => throw new InvalidArgumentException("Unsupported tool choice mode [$choice]."),
        };
    }

    private static function fromArray(array $choice) : self {
        $functionName = self::functionNameFromArray($choice);
        $type = self::typeFromArray($choice);

        return match (true) {
            $choice === [] => self::empty(),
            $functionName !== null => self::specific($functionName),
            $type !== null => self::fromString($type),
            default => throw new InvalidArgumentException('Unsupported tool choice array format.'),
        };
    }

    private static function functionNameFromArray(array $choice) : ?string {
        $function = $choice['function'] ?? null;
        if (! is_array($function)) {
            return null;
        }

        $name = $function['name'] ?? null;

        return match (true) {
            is_string($name) && $name !== '' => $name,
            default => null,
        };
    }

    private static function typeFromArray(array $choice) : ?string {
        $type = $choice['type'] ?? null;

        return match (true) {
            is_string($type) => $type,
            default => null,
        };
    }
}
