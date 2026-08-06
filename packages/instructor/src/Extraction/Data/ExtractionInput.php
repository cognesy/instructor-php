<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Data;

use Cognesy\Instructor\Extraction\ResponseContent;
use Cognesy\Instructor\Telemetry\PhaseTelemetryContext;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Utils\Profiler\TracksObjectCreation;

final readonly class ExtractionInput
{
    use TracksObjectCreation;

    /**
     * @param PhaseTelemetryContext|null $telemetry Set by the caller that holds the
     *        structured-output execution. Null for standalone extraction, which has no
     *        execution to be a child of and therefore emits unstamped events.
     */
    public function __construct(
        public string $content,
        public OutputMode $mode,
        public ?InferenceResponse $response = null,
        public ?PhaseTelemetryContext $telemetry = null,
    ) {
        $this->trackObjectCreation();
    }

    public static function fromResponse(
        InferenceResponse $response,
        OutputMode $mode,
        ?PhaseTelemetryContext $telemetry = null,
    ): self {
        return new self(
            content: ResponseContent::fromResponse($response, $mode),
            mode: $mode,
            response: $response,
            telemetry: $telemetry,
        );
    }

    public static function fromContent(
        string $content,
        OutputMode $mode,
        ?InferenceResponse $response = null,
        ?PhaseTelemetryContext $telemetry = null,
    ): self {
        return new self(
            content: $content,
            mode: $mode,
            response: $response,
            telemetry: $telemetry,
        );
    }
}
