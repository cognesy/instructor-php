<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Data;

use Cognesy\Instructor\Extraction\ResponseContent;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

final readonly class ExtractionInput
{
    public function __construct(
        public string $content,
        public OutputMode $mode,
        public ?InferenceResponse $response = null,
    ) {}

    public static function fromResponse(InferenceResponse $response, OutputMode $mode): self
    {
        return new self(
            content: ResponseContent::fromResponse($response, $mode),
            mode: $mode,
            response: $response,
        );
    }

    public static function fromContent(
        string $content,
        OutputMode $mode,
        ?InferenceResponse $response = null,
    ): self {
        return new self(
            content: $content,
            mode: $mode,
            response: $response,
        );
    }
}
