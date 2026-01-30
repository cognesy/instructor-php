<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

/**
 * Observer interface for inference lifecycle hooks.
 *
 * Allows hooks to modify inference messages and responses.
 */
interface CanObserveInference
{
    public function onBeforeInference(AgentState $state, Messages $messages): AgentState;

    public function onAfterInference(AgentState $state, InferenceResponse $response): AgentState;
}
