<?php declare(strict_types=1);
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Template\Template;
use Cognesy\Utils\Arrays;
use Exception;

class RequestMaterializer implements CanMaterializeRequest
{
    private StructuredOutputConfig $config;

    public function __construct(
        StructuredOutputConfig $config,
    ) {
        $this->config = $config;
    }

    public function toMessages(StructuredOutputRequest $request) : array {
        $store = $this
            ->makeMessageStore($request)
            ->mergeMessageStore($this->makeCachedMessageStore($request->cachedContext()));

        // Add retry messages if needed
        $store = $this->addRetryMessages($request, $store);

        // Add meta sections
        $output = $this
            ->withCacheMetaSections($request->cachedContext(), $this->withSections($store))
            ->select($this->config->chatStructure());

        $rendered = Template::arrowpipe()
            ->with(['json_schema' => json_encode($this->makeJsonSchema($request->responseModel()))])
            ->renderMessages($output->toMessages());

        // Temporary safeguard to keep messages present; isolated for easy removal.
        $final = $this->ensureNonEmptyMessages($rendered, $request);
        return $final->toArray();
    }

    protected function makeMessageStore(StructuredOutputRequest $request) : MessageStore {
        if ($this->isRequestEmpty($request)) {
            throw new Exception('Request cannot be empty - you have to provide content for processing.');
        }
        $messages = $request->messages();
        $store = (new MessageStore())
            ->withSectionMessages('system', $this->makeSystem($messages, $request->system()))
            ->withSectionMessages('messages', $this->makeMessages($messages))
            ->withSectionMessage('prompt', $this->makePrompt($request->prompt()
                ?: $this->config->prompt($request->mode())
                ?? ''
            ))
            ->withSectionMessages('examples', $this->makeExamples($request->examples()));
        return $store->trimmed();
    }

    protected function makeCachedMessageStore(CachedContext $cachedContext) : MessageStore {
        if ($cachedContext->isEmpty()) {
            return new MessageStore();
        }
        $store = new MessageStore();

        // system (cached)
        $store = $store->withSectionMessages('system', $this->makeSystem($cachedContext->messages(), $cachedContext->system()));
        if ($store->getSection('system')->notEmpty()) {
            $updated = $store->getSection('system')->appendContentField('cache_control', ['type' => 'ephemeral']);
            $store = $store->replaceSection('system', $updated);
        }

        // cached chat messages
        $store = $store->withSectionMessages('cached-messages', $this->makeMessages($cachedContext->messages()));
        if ($store->getSection('cached-messages')->notEmpty()) {
            $updated = $store->getSection('cached-messages')->appendContentField('cache_control', ['type' => 'ephemeral']);
            $store = $store->replaceSection('cached-messages', $updated);
        }

        // cached prompt
        if ($cachedContext->prompt() !== '') {
            $store = $store->withSectionMessage('cached-prompt', Message::fromString($cachedContext->prompt()));
            $updated = $store->getSection('cached-prompt')->appendContentField('cache_control', ['type' => 'ephemeral']);
            $store = $store->replaceSection('cached-prompt', $updated);
        }

        // cached examples
        $store = $store->withSectionMessages('cached-examples', $this->makeExamples($cachedContext->examples()));
        if ($store->getSection('cached-examples')->notEmpty()) {
            $updated = $store->getSection('cached-examples')->appendContentField('cache_control', ['type' => 'ephemeral']);
            $store = $store->replaceSection('cached-examples', $updated);
        }

        return $store->trimmed();
    }

    protected function withCacheMetaSections(CachedContext $cachedContext, MessageStore $store) : MessageStore {
        if ($cachedContext->isEmpty()) {
            return $store;
        }

        if ($store->section('cached-prompt')->notEmpty()) {
            $store = $store->removeSection('prompt');
            $store = $store->withSectionMessageIfEmpty('pre-cached-prompt', [
                'role' => 'user',
                'content' => [[
                    'type' => 'text',
                    'text' => "TASK:",
                ]],
            ]);
        }

        if ($store->section('cached-examples')->notEmpty()) {
            $store = $store->withSectionMessageIfEmpty('pre-cached-examples', [
                'role' => 'user',
                'content' => [[
                    'type' => 'text',
                    'text' => "EXAMPLES:",
                ]],
            ]);
        }

        $store = $store->withSectionMessageIfEmpty('post-cached', [
            'role' => 'user',
            'content' => [[
                'type' => 'text',
                'text' => 'INSTRUCTIONS:',
            ]],
        ]);

        return $store;
    }

    protected function addRetryMessages(StructuredOutputRequest $request, MessageStore $store) : MessageStore {
        $failedResponse = $request->lastFailedResponse();
        if (!$failedResponse || !$request->hasLastResponseFailed()) {
            return $store;
        }

        $newMessageStore = $store->clone();
        $messages = [];
        foreach($request->attempts() as $attempt) {
            $messages[] = ['role' => 'assistant', 'content' => $attempt->inferenceResponse()->content()];
            $retryFeedback = $this->config->retryPrompt()
                . Arrays::flattenToString($attempt->errors(), "; ");
            $messages[] = ['role' => 'user', 'content' => $retryFeedback];
        }
        $newMessageStore = $newMessageStore->withSectionMessages('retries', Messages::fromArray($messages));
        return $newMessageStore;
    }

    protected function withSections(MessageStore $store) : MessageStore {
        if ($store->section('prompt')->notEmpty()) {
            $store = $store->withSectionMessageIfEmpty('pre-prompt', [
                'role' => 'user',
                'content' => "TASK:",
            ]);
        }

        if ($store->section('examples')->notEmpty()) {
            $store = $store->withSectionMessageIfEmpty('pre-examples', [
                'role' => 'user',
                'content' => "EXAMPLES:",
            ]);
        }

        if ($store->section('retries')->notEmpty()) {
            $store = $store->withSectionMessageIfEmpty('pre-retries', [
                'role' => 'user',
                'content' => "FEEDBACK:",
            ]);
            $store = $store->withSectionMessageIfEmpty('post-retries', [
                'role' => 'user',
                'content' => "CORRECTED RESPONSE:",
            ]);
        } else {
            $store = $store->withSectionMessageIfEmpty('post-retries', [
                'role' => 'user',
                'content' => "RESPONSE:",
            ]);
        }

        return $store;
    }

    protected function isRequestEmpty(StructuredOutputRequest $request) : bool {
        return match(true) {
            !empty($request->messages()) => false,
            !empty($request->prompt()) => false,
            !empty($request->system()) => false, // ?
            !empty($request->examples()) => false, // ?
            default => true,
        };
    }

    protected function makeSystem(Messages $messages, string $system) : Messages {
        $output = Messages::empty();
        if (!empty($system)) {
            $output = $output->appendMessage(new Message(role: 'system', content: $system));
        }
        $output = $output->appendMessages(
            $messages->headWithRoles(['system', 'developer'])
        );
        return $output;
    }

    protected function makeMessages(Messages $messages) : Messages {
        $output = Messages::empty();
        $output = $output->appendMessages(
            $messages->tailAfterRoles(['developer', 'system'])
        );
        return $output;
    }

    protected function makeExamples(array $examples) : Messages {
        $output = Messages::empty();
        if (empty($examples)) {
            return $output;
        }
        foreach ($examples as $item) {
            $example = match(true) {
                is_array($item) => Example::fromArray($item),
                is_string($item) => Example::fromJson($item),
                $item instanceof Example => $item,
                default => throw new Exception('Invalid example type'),
            };
            $output = $output->appendMessages($example->toMessages());
        }
        return $output;
    }

    protected function makePrompt(string $prompt) : Message {
        return new Message(role: 'user', content: $prompt);
    }

    protected function makeJsonSchema(?ResponseModel $responseModel) : array {
        return $responseModel?->toJsonSchema() ?? [];
    }

    /**
     * TEMP: Ensure we always provide a non-empty messages array to the driver.
     * If rendering unexpectedly results in no messages, fall back to original
     * request messages or synthesize minimal content from prompt/system.
     * This helper is isolated for easy removal once the root cause is fixed.
     */
    private function ensureNonEmptyMessages(Messages $rendered, StructuredOutputRequest $request) : Messages {
        if (!$rendered->isEmpty()) {
            return $rendered;
        }

        $fallback = Messages::empty();

        if (!$request->messages()->isEmpty()) {
            $fallback = $fallback->appendMessages($request->messages());
        }

        if ($fallback->isEmpty() && !empty($request->prompt())) {
            $fallback = $fallback->appendMessage(new Message(role: 'user', content: $request->prompt()));
        }

        if ($fallback->isEmpty() && !empty($request->system())) {
            $fallback = $fallback->appendMessage(new Message(role: 'system', content: $request->system()));
        }

        return $fallback;
    }
}
