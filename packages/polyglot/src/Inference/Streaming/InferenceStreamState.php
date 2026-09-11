<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Streaming;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Assembly\AssistantMessageAssembler;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

final class InferenceStreamState
{
    private int $contentLength = 0;
    private int $reasoningContentLength = 0;
    private string $finishReason = '';
    private ?HttpResponse $responseData = null;
    private mixed $value = null;

    private int $valueRevision = 0;
    private bool $hasValue = false;

    private readonly StreamingUsageState $usage;
    private readonly AssistantMessageAssembler $messageAssembler;

    public function __construct()
    {
        $this->usage = new StreamingUsageState();
        $this->messageAssembler = new AssistantMessageAssembler();
    }

    public function applyDelta(PartialInferenceDelta $delta): void
    {
        $contentDelta = $delta->messageChunks->textDelta();
        $reasoningContentDelta = $delta->messageChunks->reasoningDelta();
        $this->applyValue($delta->value);

        $this->contentLength += strlen($contentDelta);
        $this->reasoningContentLength += strlen($reasoningContentDelta);
        $this->finishReason = match ($delta->finishReason) {
            '' => $this->finishReason,
            default => $delta->finishReason,
        };

        if ($delta->responseData instanceof HttpResponse) {
            $this->responseData = match (true) {
                $this->responseData instanceof HttpResponse && $this->responseData->statusCode() > 0 => $this->responseData,
                default => $delta->responseData,
            };
        }

        $this->usage->apply($delta->usage, $delta->usageIsCumulative);
        $this->messageAssembler->applyAll($delta->messageChunks);
        if ($delta->replay !== null) {
            $this->messageAssembler->applyReplay($delta->replay);
        }
    }

    public function content(): string
    {
        return $this->messageAssembler->content();
    }

    public function reasoningContent(): string
    {
        return $this->messageAssembler->reasoningContent();
    }

    /**
     * Key of the tool call currently receiving deltas ('' when none).
     */
    public function toolKey(): string
    {
        return $this->messageAssembler->toolKey();
    }

    public function contentLength(): int
    {
        return $this->contentLength;
    }

    public function reasoningContentLength(): int
    {
        return $this->reasoningContentLength;
    }

    public function finishReason(): string
    {
        return $this->finishReason;
    }

    public function toolMutationCount(): int
    {
        return $this->messageAssembler->toolMutationCount();
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function valueRevision(): int
    {
        return $this->valueRevision;
    }

    public function usage(): InferenceUsage
    {
        return $this->usage->toUsage();
    }

    public function toolArgsSnapshot(): string
    {
        return $this->messageAssembler->toolArgsSnapshot();
    }

    public function toolCalls(): ToolCalls
    {
        return $this->messageAssembler->toolCalls();
    }

    public function currentToolCall(): ?ToolCall
    {
        return $this->messageAssembler->currentToolCall();
    }

    public function finalResponse(): InferenceResponse
    {
        return $this->response(isPartial: false);
    }

    public function partialResponse(): InferenceResponse
    {
        return $this->response(isPartial: true);
    }

    private function response(bool $isPartial): InferenceResponse
    {
        return new InferenceResponse(
            finishReason: $this->finishReason,
            usage: $this->usage->toUsage(),
            responseData: $this->responseData ?? HttpResponse::empty(),
            isPartial: $isPartial,
            message: $this->messageAssembler->message(),
        );
    }

    private function applyValue(mixed $value): void
    {
        $hasVisibleChange = match (true) {
            !$this->hasValue => $value !== null,
            is_scalar($value) || $value === null => $value !== $this->value,
            is_object($value) => $value !== $this->value,
            default => true,
        };

        $this->value = $value;
        $this->hasValue = true;

        if ($hasVisibleChange) {
            $this->valueRevision += 1;
        }
    }

}
