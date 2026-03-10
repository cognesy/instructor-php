<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Event emitted when an error occurs
 */
final readonly class ErrorEvent extends StreamEvent
{
    public function __construct(
        array $rawData,
        public string $message,
        public ?string $code = null,
    ) {
        parent::__construct($rawData);
    }

    #[\Override]
    public function type(): string
    {
        return 'error';
    }

    public static function fromArray(array $data): self
    {
        $error = $data['error'] ?? [];
        $errorData = is_array($error) ? $error : [];

        return new self(
            rawData: $data,
            message: is_string($error)
                ? $error
                : Normalize::toString($errorData['message'] ?? $data['message'] ?? 'Unknown error', 'Unknown error'),
            code: Normalize::toNullableString($errorData['code'] ?? $data['code'] ?? null),
        );
    }
}
