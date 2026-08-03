<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Data;

final readonly class ValidationProblem
{
    public function __construct(
        public string $field,
        public string $message,
    ) {}

    /** @return array{field: string, message: string} */
    public function toArray(): array {
        return ['field' => $this->field, 'message' => $this->message];
    }
}
