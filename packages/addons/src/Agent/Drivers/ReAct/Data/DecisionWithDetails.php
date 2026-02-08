<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Drivers\ReAct\Data;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;

final readonly class DecisionWithDetails
{
    public function __construct(
        private ReActDecision $decision,
        private InferenceResponse $response,
    ) {}

    public function decision(): ReActDecision { return $this->decision; }
    public function response(): InferenceResponse { return $this->response; }
}

