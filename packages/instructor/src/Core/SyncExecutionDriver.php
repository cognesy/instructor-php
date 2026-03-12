<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Contracts\CanEmitStreamingUpdates;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Json\JsonExtractor;

final class SyncExecutionDriver implements CanEmitStreamingUpdates
{
    private ExecutionLoop $loop;
    private readonly AttemptProcessor $attemptProcessor;

    public function __construct(
        StructuredOutputExecution $execution,
        private readonly InferenceProvider $inferenceProvider,
        CanGenerateResponse $responseGenerator,
        CanDetermineRetry $retryPolicy,
    ) {
        $this->loop = new ExecutionLoop($execution);
        $this->attemptProcessor = new AttemptProcessor(
            responseGenerator: $responseGenerator,
            retryPolicy: $retryPolicy,
        );
    }

    #[\Override]
    public function hasNextEmission(): bool {
        return $this->loop->hasNextEmission($this->advance(...));
    }

    #[\Override]
    public function nextEmission(): ?StructuredOutputResponse {
        return $this->loop->nextEmission($this->advance(...));
    }

    #[\Override]
    public function execution(): StructuredOutputExecution {
        return $this->loop->execution();
    }

    private function advance(ExecutionLoop $loop): void {
        if ($loop->shouldStopAttempts()) {
            $loop->terminate();
            return;
        }

        $execution = $loop->execution()->withStartedAttempt();
        $loop->replaceExecution($execution);

        $inference = $this->normalizeContent(
            $this->inferenceProvider->getInference($execution)->response(),
            $execution->outputMode(),
        );

        $result = $this->attemptProcessor->process(
            execution: $execution,
            inferenceResponse: $inference,
        );
        $loop->applyAttemptResult($result);
    }

    private function normalizeContent(InferenceResponse $response, OutputMode $mode): InferenceResponse {
        return $response->withContent(match ($mode) {
            OutputMode::Text => $response->content(),
            OutputMode::Tools => $response->toolCalls()->first()?->argsAsJson()
                ?: $response->content()
                    ?: '',
            default => ($extracted = JsonExtractor::first($response->content())) !== null
                ? Json::encode($extracted)
                : $response->content(),
        });
    }
}
