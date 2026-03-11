<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Core\InferenceExecutionSession;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Pricing;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Public lazy handle for one raw inference operation.
 *
 * Responsibilities:
 * - trigger provider execution only when response data is requested
 * - coordinate one-shot access across `get()`, `response()`, and `stream()`
 * - expose raw convenience accessors over the finalized response
 *
 * Non-responsibilities:
 * - it does not own the mutable raw execution lifecycle directly
 * - it does not perform structured-output extraction or validation
 * - it is not a generic pending base for higher layers
 */
class PendingInference
{
    private readonly InferenceExecutionSession $session;

    public function __construct(
        InferenceExecution         $execution,
        CanProcessInferenceRequest $driver,
        EventDispatcherInterface   $eventDispatcher,
        ?Pricing                   $pricing = null,
    ) {
        $this->session = new InferenceExecutionSession(
            execution: $execution,
            driver: $driver,
            events: $eventDispatcher,
            pricing: $pricing,
        );
    }

    public function isStreamed() : bool {
        return $this->session->isStreamed();
    }

    public function get() : string {
        return $this->response()->content();
    }

    public function stream() : InferenceStream {
        return $this->session->stream();
    }

    public function executionId() : string {
        return $this->session->executionId();
    }

    public function asJson() : string {
        return $this->response()
            ->findJsonData()
            ->toString();
    }

    public function asJsonData() : array {
        return $this->response()
            ->findJsonData()
            ->toArray();
    }

    public function asToolCallJson() : string {
        return $this->response()
            ->findToolCallJsonData()
            ->toString();
    }

    public function asToolCallJsonData() : array {
        return $this->response()
            ->findToolCallJsonData()
            ->toArray();
    }

    public function response() : InferenceResponse {
        return $this->session->response();
    }
}
