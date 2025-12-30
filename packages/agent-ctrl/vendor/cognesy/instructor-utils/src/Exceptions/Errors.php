<?php declare(strict_types=1);

namespace Cognesy\Utils\Exceptions;

use Cognesy\Utils\Collection\ArrayList;
use Throwable;

class Errors
{
    /** @param ArrayList<Throwable> $errors */
    private ArrayList $errors;

    private function __construct(?ArrayList $errors = null) {
        $this->errors = $errors ?? ArrayList::empty();
    }

    public static function of(Throwable ...$errors): self {
        return new self(ArrayList::of(...$errors));
    }

    public static function empty(): self {
        return new self();
    }

    // ACCESSORS /////////////////////////////////////////////

    public function count(): int {
        return $this->errors->count();
    }

    public function isEmpty(): bool {
        return $this->errors->isEmpty();
    }

    public function hasAny(): bool {
        return !$this->isEmpty();
    }

    /** @return list<Throwable> */
    public function all(): array {
        return $this->errors->all();
    }

    public function first(): ?Throwable {
        return $this->errors->first();
    }

    public function last(): ?Throwable {
        return $this->errors->last();
    }

    // MUTATORS /////////////////////////////////////////////

    public function with(Throwable ...$errors): self {
        return new self($this->errors->withAppended(ArrayList::of(...$errors)));
    }

    // SERIALIZATION ////////////////////////////////////////

    public function toArray(): array {
        return array_map(
            fn (Throwable $error) => $this->mapTo($error),
            $this->errors->all()
        );
    }

    public static function fromArray(array $data): self {
        $errors = array_map(
            fn (array $errorData) => self::mapFrom($errorData),
            $data
        );
        return new self(ArrayList::of(...$errors));
    }

    // INTERNAL //////////////////////////////////////////////

    private static function mapFrom(array $data): Throwable {
        $type = $data['type'] ?? throw new \InvalidArgumentException('Missing error type for deserialization');
        return match(true) {
            $type === 'exception' => DeserializedException::fromArray($data),
            $type === 'error' => DeserializedError::fromArray($data),
            default => throw new \InvalidArgumentException('Invalid error data for deserialization'),
        };
    }

    private function mapTo(Throwable $error): array {
        return match(true) {
            $error instanceof \Exception => [
                'type' => 'exception',
                'class' => get_class($error),
                'message' => $error->getMessage(),
                'code' => $error->getCode(),
            ],
            $error instanceof \Error => [
                'type' => 'error',
                'class' => get_class($error),
                'message' => $error->getMessage(),
                'code' => $error->getCode(),
            ],
            default => throw new \InvalidArgumentException('Unsupported Throwable type: ' . get_class($error)),
        };
    }
}