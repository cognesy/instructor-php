<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Step\Traits;

use Cognesy\Addons\Chat\Exceptions\ChatException;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Throwable;

trait HandlesStepChatCompletion
{
    protected readonly string $participantName;
    protected readonly ?InferenceResponse $inferenceResponse;
    protected readonly ?InferenceFinishReason $finishReason;

    public function participantName(): string {
        return $this->participantName;
    }

    public function inferenceResponse(): ?InferenceResponse {
        return $this->inferenceResponse;
    }

    public function finishReason(): ?InferenceFinishReason {
        return $this->finishReason;
    }

    public static function failure(
        string $participantName,
        Messages $inputMessages,
        Throwable $error,
        ?Messages $outputMessages = null,
        ?array $meta = null,
    ): self {
        $baseError = $error instanceof ChatException ? $error : ChatException::fromThrowable($error);
        return new self(
            participantName: $participantName,
            inputMessages: $inputMessages,
            outputMessages: $outputMessages,
            usage: Usage::none(),
            inferenceResponse: null,
            finishReason: InferenceFinishReason::Other,
            metadata: ($meta ?? []) + ['errorType' => get_class($baseError)],
            errors: [$baseError],
        );
    }
}