<?php
namespace Cognesy\Instructor\Features\Core\Data\Traits\ChatTemplate;

use Cognesy\Utils\Arrays;

trait HandlesRetries
{
    protected function addRetryMessages() : void {
        $failedResponse = $this->request->lastFailedResponse();
        if (!$failedResponse || !$this->request->hasLastResponseFailed()) {
            return;
        }
        foreach($this->request->attempts() as $attempt) {
            $messages = $this->makeRetryMessages(
                [], $attempt->llmResponse()->content(), $attempt->errors()
            );
            $this->script->section('retries')->appendMessages($messages);
        }
    }

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
}