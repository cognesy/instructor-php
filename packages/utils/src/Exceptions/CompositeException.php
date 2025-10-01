<?php declare(strict_types=1);

namespace Cognesy\Utils\Exceptions;

use RuntimeException;
use Throwable;

class CompositeException extends RuntimeException
{
    /** @var array<Throwable> */
    protected array $errors;

    public function __construct(array $errors) {
        $message = $this->makeMessage($errors);
        parent::__construct($message);
        $this->errors = $this->makeErrors($errors);
    }

    public static function of(Throwable ...$errors): self {
        return new self($errors);
    }

    public function withNewError(Throwable $error): self {
        $newErrors = $this->errors;
        $newErrors[] = $error;
        return new self($newErrors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    // SERIALIZATION ////////////////////////////////////////////

    public function toArray(): array {
        return array_map(fn($e) => $e->getMessage(), $this->errors);
    }

    public static function fromArray(array $data): self {
        return new self(array_map(fn($msg) => new RuntimeException($msg), $data));
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeMessage(array $errors) : string {
        return 'CompositeException: '
            . count($errors)
            . ' errors occurred: '
            . implode(', ', array_map(fn($e) => $e->getMessage(), $errors));
    }

    private function makeErrors(array $errors) : array {
        return array_map(fn($e) => $e instanceof Throwable ? $e : new RuntimeException((string) $e), $errors);
    }
}