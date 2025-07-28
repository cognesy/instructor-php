<?php declare(strict_types=1);

namespace Cognesy\Utils\Exceptions;

use RuntimeException;

class CompositeException extends RuntimeException
{
    protected array $errors;

    public function __construct(array $errors) {
        $message = 'CompositeException: ' . count($errors) . ' errors occurred: '
            . implode(', ', array_map(fn($e) => $e->getMessage(), $errors));
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array {
        return $this->errors;
    }
}