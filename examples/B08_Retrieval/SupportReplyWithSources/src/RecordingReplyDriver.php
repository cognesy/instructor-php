<?php

declare(strict_types=1);

namespace Examples\Retrieval\SupportReplyWithSources;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Override;

final class RecordingReplyDriver implements CanProcessInferenceRequest
{
    public int $requests = 0;
    public Messages $lastMessages;

    public function __construct(private readonly ReplyDraft $response) {
        $this->lastMessages = Messages::empty();
    }

    #[Override]
    public function makeResponseFor(InferenceRequest $request): InferenceResponse {
        $this->requests++;
        $this->lastMessages = $request->messages();

        return new InferenceResponse(
            message: Message::asAssistant(json_encode($this->response, JSON_THROW_ON_ERROR)),
            finishReason: 'stop',
        );
    }

    /** @return iterable<PartialInferenceDelta> */
    #[Override]
    public function makeStreamDeltasFor(InferenceRequest $request): iterable {
        return [];
    }
}
