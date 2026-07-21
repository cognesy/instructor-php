<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Events;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\DefaultRequestRedactor;

final readonly class HttpInteractionSummary
{
    public function __construct(
        public string $method,
        public string $url,
        public string $outcome,
        public ?int $statusCode = null,
        public ?int $sequence = null,
    ) {
    }

    public static function fromRequest(
        HttpRequest $request,
        string $outcome,
        ?int $statusCode = null,
        ?int $sequence = null,
    ): self {
        return new self(
            method: $request->method(),
            url: DefaultRequestRedactor::redactUrl($request->url()),
            outcome: $outcome,
            statusCode: $statusCode,
            sequence: $sequence,
        );
    }
}
