<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Step;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Throwable;

interface HasCollaborationCompletion
{
    public function collaboratorName(): string;

    public function inferenceResponse(): ?InferenceResponse;

    public function finishReason(): ?InferenceFinishReason;

    public static function failure(
        Throwable $error,
        string    $collaboratorName,
        Messages  $inputMessages,
        ?Messages $outputMessages = null,
        ?array    $metadata = null,
    );
}