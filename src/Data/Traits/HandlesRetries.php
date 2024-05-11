<?php

namespace Cognesy\Instructor\Data\Traits;

use Cognesy\Instructor\Data\Request;
use DeepCopy\DeepCopy;
use DeepCopy\Filter\SetNullFilter;
use DeepCopy\Matcher\PropertyNameMatcher;

trait HandlesRetries
{
    private string $defaultRetryPrompt = "JSON generated incorrectly, fix following errors: ";
    private string $retryPrompt;

    private int $maxRetries;

    /** @var Request[] */
    private array $failedAttempts = [];

    public function maxRetries() : int {
        return $this->maxRetries;
    }

    public function retryPrompt() : string {
        return $this->retryPrompt;
    }

    // TODO: not used currently - will keep the track of failed attempts
    public function newFailedAttempt(Request $request) : void {
        $copier = new DeepCopy();
        $copy = $copier->copy($request);
        $copier->addFilter(new SetNullFilter(), new PropertyNameMatcher('failedAttempts'));
        $copier->addFilter(new SetNullFilter(), new PropertyNameMatcher('client'));
        $copier->addFilter(new SetNullFilter(), new PropertyNameMatcher('modelFactory'));
        $copier->addFilter(new SetNullFilter(), new PropertyNameMatcher('toolCallBuilder'));
        $this->failedAttempts[] = $copy;
    }

    public function makeRetryMessages(
        array $messages, Request $request, string $jsonData, array $errors
    ) : array {
        $messages[] = ['role' => 'assistant', 'content' => $jsonData];
        $messages[] = ['role' => 'user', 'content' => $request->retryPrompt() . implode(", ", $errors)];
        return $messages;
    }
}