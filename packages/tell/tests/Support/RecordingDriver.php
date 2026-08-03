<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tests\Support;

use Cognesy\Agents\Context\CanAcceptMessageCompiler;
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Context\Compilers\SelectedSections;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Override;

final readonly class RecordingDriver implements CanAcceptMessageCompiler, CanUseTools
{
    public function __construct(
        private RequestRecorder $recorder,
        private string $response = 'recorded answer',
        private CanCompileMessages $compiler = new SelectedSections,
    ) {}

    #[Override]
    public function useTools(AgentState $state): AgentState
    {
        $messages = $this->compiler->compile($state);
        $this->recorder->requests[] = $messages->toArray();

        return $state->withCurrentStep(new AgentStep(
            inputMessages: $messages,
            outputMessages: Messages::fromString($this->response, 'assistant'),
            inferenceResponse: new InferenceResponse(content: $this->response),
        ));
    }

    #[Override]
    public function messageCompiler(): CanCompileMessages
    {
        return $this->compiler;
    }

    #[Override]
    public function withMessageCompiler(CanCompileMessages $compiler): static
    {
        return new self($this->recorder, $this->response, $compiler);
    }
}
