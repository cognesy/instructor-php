<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Contracts;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Throwable;

interface HasStepChatCompletion
{
    public function participantName(): string;

    public function inferenceResponse(): ?InferenceResponse;

    public function finishReason(): ?InferenceFinishReason;

    public static function failure(
        Throwable $error,
        string $participantName,
        Messages $inputMessages,
        ?Messages $outputMessages = null,
        ?array $metadata = null,
    );
}