<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Step;

use Cognesy\Addons\Collaboration\Exceptions\CollaborationException;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Throwable;

trait HandlesCollaborationCompletion
{
    protected readonly string $collaboratorName;
    protected readonly ?InferenceResponse $inferenceResponse;
    protected readonly ?InferenceFinishReason $finishReason;

    public function collaboratorName(): string {
        return $this->collaboratorName;
    }

    public function inferenceResponse(): ?InferenceResponse {
        return $this->inferenceResponse;
    }

    public function finishReason(): ?InferenceFinishReason {
        return $this->finishReason;
    }

    public static function failure(
        Throwable $error,
        string    $collaboratorName,
        Messages  $inputMessages,
        ?Messages $outputMessages = null,
        ?array    $metadata = null,
    ): self {
        $baseError = $error instanceof CollaborationException ? $error : CollaborationException::fromThrowable($error);
        return new self(
            collaboratorName: $collaboratorName,
            inputMessages: $inputMessages,
            outputMessages: $outputMessages,
            usage: Usage::none(),
            inferenceResponse: null,
            finishReason: InferenceFinishReason::Other,
            metadata: ($metadata ?? []) + ['errorType' => get_class($baseError)],
            errors: [$baseError],
        );
    }
}