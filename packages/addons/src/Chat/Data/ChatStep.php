<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

final class ChatStep
{
    private string $participantId;
    private ?Messages $messages;
    private ?Usage $usage;
    private ?InferenceResponse $inferenceResponse;
    private ?string $finishReason;
    private array $meta;

    public function __construct(
        string $participantId,
        ?Messages $messages = null,
        ?Usage $usage = null,
        ?InferenceResponse $inferenceResponse = null,
        ?string $finishReason = null,
        array $meta = [],
    ) {
        $this->participantId = $participantId;
        $this->messages = $messages;
        $this->usage = $usage;
        $this->inferenceResponse = $inferenceResponse;
        $this->finishReason = $finishReason;
        $this->meta = $meta;
    }

    public function participantId() : string { return $this->participantId; }
    public function messages() : Messages { return $this->messages ?? Messages::empty(); }
    public function usage() : Usage { return $this->usage ?? new Usage(); }
    public function inferenceResponse() : ?InferenceResponse { return $this->inferenceResponse; }
    public function finishReason() : ?string { return $this->finishReason; }
    public function meta() : array { return $this->meta; }
}

