<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Telemetry\PhaseTelemetryContext;
use Cognesy\Utils\Result\Result;

interface CanValidateResponse
{
    /**
     * `$telemetry` is supplied by callers that hold a structured-output execution, so the
     * validation attempt/result events can be stamped as a child span of its root. Validation
     * is otherwise context-free: without it the events still fire, just unstamped.
     */
    public function validate(
        object $response,
        ResponseModel $responseModel,
        ?PhaseTelemetryContext $telemetry = null,
    ) : Result;
}
