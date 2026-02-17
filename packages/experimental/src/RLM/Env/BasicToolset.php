<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Env;

use Cognesy\Experimental\RLM\Contracts\Toolset;
use Cognesy\Experimental\RLM\Data\Handles\ResultHandle;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class BasicToolset implements Toolset
{
    private readonly CanCreateInference $inference;

    public function __construct(
        CanCreateInference $inference,
    ) {
        $this->inference = $inference;
    }

    /**
     * Supports: llm_call with args { messages?: string|array, model?: string, options?: array }
     * Returns an artifact handle reference (no large content in transcripts).
     *
     * @param array<string,mixed> $args
     */
    public function call(string $name, array $args): ResultHandle {
        return match ($name) {
            'llm_call' => $this->llmCall($args),
            default => ResultHandle::from('artifact://rlm/unsupported_tool/' . rawurlencode($name)),
        };
    }

    /**
     * @param array<string,mixed> $args
     */
    private function llmCall(array $args): ResultHandle {
        $messages = match (true) {
            isset($args['messages']) && is_string($args['messages']) => Messages::fromString($args['messages']),
            isset($args['messages']) && is_array($args['messages']) => Messages::fromArray($args['messages']),
            default => Messages::empty(),
        };
        $model = is_string($args['model'] ?? null) ? (string)$args['model'] : '';
        $options = is_array($args['options'] ?? null) ? $args['options'] : ['temperature' => 0.0, 'max_tokens' => 128];

        $response = $this->inference->create(new InferenceRequest(
            messages: $messages,
            model: $model,
            options: $options,
        ))->response();
        $content = $response->content();
        $id = substr(sha1($content), 0, 12);
        // v1: return an artifact handle keyed by content hash (content is not in transcript)
        return ResultHandle::from('artifact://rlm/llm_call/' . $id);
    }
}
