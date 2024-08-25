<?php
namespace Cognesy\Instructor\Data\Traits\ChatTemplate;

use Cognesy\Instructor\Utils\Arrays;

trait HandlesRetries
{
    protected function makeRetryMessages(
        array $messages,
        string $jsonData,
        array $errors
    ) : array {
        $retryFeedback = $this->makeRetryPrompt() . Arrays::flatten($errors, "; ");
        $messages[] = ['role' => 'assistant', 'content' => $jsonData];
        $messages[] = ['role' => 'user', 'content' => $retryFeedback];
        return $messages;
    }

    protected function makeRetryPrompt() : string {
        return $this->request->retryPrompt() ?: $this->defaultRetryPrompt;
    }

    protected function addRetryMessages() {
        $failedResponse = $this->request->lastFailedResponse();
        if (!$failedResponse || !$this->request->hasLastResponseFailed()) {
            return;
        }
        $this->script->section('retries')->appendMessages(
            $this->makeRetryMessages(
                [], $failedResponse->apiResponse()->content, $failedResponse->errors()
            )
        );
    }
}