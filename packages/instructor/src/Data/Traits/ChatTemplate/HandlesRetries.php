<?php
namespace Cognesy\Instructor\Data\Traits\ChatTemplate;

use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Template\Script\Script;
use Cognesy\Utils\Arrays;

trait HandlesRetries
{
    protected function addRetryMessages(StructuredOutputRequest $request, Script $script) : Script {
        $failedResponse = $request->lastFailedResponse();
        if (!$failedResponse || !$request->hasLastResponseFailed()) {
            return $script;
        }

        $newScript = $script->clone();
        $messages = [];
        foreach($request->attempts() as $attempt) {
            $messages[] = ['role' => 'assistant', 'content' => $attempt->llmResponse()->content()];
            $retryFeedback = ($request->retryPrompt() ?: $this->config->retryPrompt())
                . Arrays::flatten($attempt->errors(), "; ");
            $messages[] = ['role' => 'user', 'content' => $retryFeedback];
        }
        $newScript->section('retries')->appendMessages($messages);
        return $newScript;
    }
}