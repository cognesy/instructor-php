<?php declare(strict_types=1);

namespace Cognesy\Agents\Hook\Hooks;

use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;

final readonly class ApplyContextConfigHook implements HookInterface
{
    public function __construct(
        private string $systemPrompt,
        private ?ResponseFormat $responseFormat = null,
    ) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $agentContext = $context->state()->context();

        if ($this->systemPrompt !== '') {
            $agentContext = $agentContext->withSystemPrompt($this->systemPrompt);
        }

        if ($this->responseFormat !== null && !$this->responseFormat->isEmpty()) {
            $agentContext = $agentContext->withResponseFormat($this->responseFormat);
        }

        return $context->withState(
            $context->state()->with(context: $agentContext)
        );
    }
}
