<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Streaming;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\Usage;

final class InferenceStreamState
{
    private string $content = '';
    private int $contentLength = 0;
    private string $reasoningContent = '';
    private int $reasoningContentLength = 0;
    private string $finishReason = '';
    private ?HttpResponse $responseData = null;
    private mixed $value = null;

    private string $contentDelta = '';
    private string $reasoningContentDelta = '';
    private string $toolId = '';
    private string $toolName = '';
    private string $toolArgs = '';

    /** @var array<string,array{id:string,name:string,args:string}> */
    private array $tools = [];
    private int $toolsCount = 0;
    private string $lastToolKey = '';
    private string $pendingToolArgs = '';
    private int $toolMutationCount = 0;
    private int $valueRevision = 0;
    private bool $hasValue = false;

    private readonly StreamingUsageState $usage;

    public function __construct()
    {
        $this->usage = new StreamingUsageState();
    }

    public function applyDelta(PartialInferenceDelta $delta): void
    {
        $this->contentDelta = $delta->contentDelta;
        $this->reasoningContentDelta = $delta->reasoningContentDelta;
        $this->toolId = match (true) {
            is_string($delta->toolId) => $delta->toolId,
            $delta->toolId !== null => $delta->toolId->toString(),
            default => '',
        };
        $this->toolName = $delta->toolName;
        $this->toolArgs = $delta->toolArgs;
        $this->applyValue($delta->value);

        $this->content .= $this->contentDelta;
        $this->contentLength += strlen($this->contentDelta);
        $this->reasoningContent .= $this->reasoningContentDelta;
        $this->reasoningContentLength += strlen($this->reasoningContentDelta);
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
        $this->accumulateToolDelta();
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
        return $this->toolMutationCount;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function valueRevision(): int
    {
        return $this->valueRevision;
    }

    public function usage(): Usage
    {
        return $this->usage->toUsage();
    }

    public function toolArgsSnapshot(): string
    {
        if ($this->lastToolKey === '' || !isset($this->tools[$this->lastToolKey])) {
            return '';
        }

        return (string) ($this->tools[$this->lastToolKey]['args'] ?? '');
    }

    public function toolCalls(): ToolCalls
    {
        if (empty($this->tools)) {
            return ToolCalls::empty();
        }
        $items = [];
        foreach ($this->tools as $entry) {
            $items[] = [
                'id' => $entry['id'] ?? '',
                'name' => $entry['name'] ?? '',
                'arguments' => $entry['args'] ?? '',
            ];
        }
        return ToolCalls::fromArray($items);
    }

    public function finalResponse(): InferenceResponse
    {
        return new InferenceResponse(
            content: $this->content,
            finishReason: $this->finishReason,
            toolCalls: $this->toolCalls(),
            reasoningContent: $this->reasoningContent,
            usage: $this->usage->toUsage(),
            responseData: $this->responseData ?? HttpResponse::empty(),
            isPartial: false,
        );
    }

    private function accumulateToolDelta(): void
    {
        if (!$this->hasToolDelta()) {
            return;
        }

        $key = $this->resolveToolKey($this->toolId, $this->toolName);

        if ($this->shouldStartToolById($key)) {
            $this->toolsCount += 1;
            $this->tools[$key] = [
                'id' => $this->toolId,
                'name' => $this->toolName,
                'args' => '',
            ];
            $this->recordToolEvent('start', $key, $this->toolId, $this->toolName, '');
        }

        if ($this->shouldUpdateToolName($key)) {
            $this->tools[$key]['name'] = $this->toolName;
            $this->recordToolEvent('rename', $key, '', $this->toolName, '');
        }

        if ($this->shouldStartToolByName($key)) {
            $this->toolsCount += 1;
            $this->tools[$key] = [
                'id' => '',
                'name' => $this->toolName,
                'args' => '',
            ];
            $this->recordToolEvent('start', $key, '', $this->toolName, '');
        }

        $this->lastToolKey = $key;

        if ($key === '') {
            $this->pendingToolArgs .= $this->toolArgs;
            return;
        }

        if (!isset($this->tools[$key])) {
            return;
        }

        $toolArgs = $this->pendingToolArgs . $this->toolArgs;
        $this->pendingToolArgs = '';

        if ($toolArgs === '') {
            return;
        }

        $this->tools[$key]['args'] .= $toolArgs;
        $this->recordToolEvent('args', $key, '', '', $toolArgs);
    }

    private function hasToolDelta(): bool
    {
        return $this->toolId !== '' || $this->toolName !== '' || $this->toolArgs !== '';
    }

    private function resolveToolKey(string $toolId, string $toolName): string
    {
        if ($toolId !== '') {
            return 'id:' . $toolId;
        }

        if ($toolName !== '') {
            if ($this->lastToolKey !== '' && isset($this->tools[$this->lastToolKey])) {
                $activeName = (string) ($this->tools[$this->lastToolKey]['name'] ?? '');
                if ($activeName === $toolName) {
                    return $this->lastToolKey;
                }
            }

            return 'name:' . $toolName . '#' . ($this->toolsCount + 1);
        }

        if ($this->lastToolKey !== '' && isset($this->tools[$this->lastToolKey])) {
            return $this->lastToolKey;
        }

        return '';
    }

    private function shouldStartToolById(string $key): bool
    {
        return $this->toolId !== '' && ($key !== $this->lastToolKey || !isset($this->tools[$key]));
    }

    private function shouldUpdateToolName(string $key): bool
    {
        return $this->toolId !== '' && $this->toolName !== '' && isset($this->tools[$key]);
    }

    private function shouldStartToolByName(string $key): bool
    {
        return $this->toolId === '' && $this->toolName !== '' && !isset($this->tools[$key]);
    }

    private function recordToolEvent(
        string $event,
        string $key,
        string $toolId,
        string $toolName,
        string $toolArgs,
    ): void {
        $this->toolMutationCount += 1;
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
