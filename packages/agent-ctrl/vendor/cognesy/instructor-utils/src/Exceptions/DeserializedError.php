<?php declare(strict_types=1);

namespace Cognesy\Utils\Exceptions;

use Error;

class DeserializedError extends Error
{
    public function toArray(): array {
        return [
            'class' => get_class($this),
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];
    }

    public static function fromArray(array $data): self {
        $message = $data['message'] ?? '';
        $code = $data['code'] ?? 0;
        return new self($message, $code);
    }
}