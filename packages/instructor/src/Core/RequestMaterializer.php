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
        $store = $this->mergeMessageStores(
            $this->makeMessageStore($request),
            $this->makeCachedMessageStore($request->cachedContext())
        );

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
            ->applyTo('system')->appendMessages($this->makeSystem($messages, $request->system()))
            ->applyTo('messages')->appendMessages($this->makeMessages($messages))
            ->applyTo('prompt')->appendMessages($this->makePrompt($request->prompt()
                ?: $this->config->prompt($request->mode())
                ?? ''
            ))
            ->applyTo('examples')->setMessages($this->makeExamples($request->examples()));
        return $this->removeEmptyMessages($store);
    }

    protected function makeCachedMessageStore(CachedContext $cachedContext) : MessageStore {
        if ($cachedContext->isEmpty()) {
            return new MessageStore();
        }
        $store = new MessageStore();

        // system (cached)
        $store = $store->applyTo('system')->setMessages($this->makeSystem($cachedContext->messages(), $cachedContext->system()));
        if ($store->section('system')->isNotEmpty()) {
            $updated = $store->section('system')->get()->appendContentField('cache_control', ['type' => 'ephemeral']);
            $store = $store->applyTo('system')->setSection($updated);
        }

        // cached chat messages
        $store = $store->applyTo('cached-messages')->setMessages($this->makeMessages($cachedContext->messages()));
        if ($store->section('cached-messages')->isNotEmpty()) {
            $updated = $store->section('cached-messages')->get()->appendContentField('cache_control', ['type' => 'ephemeral']);
            $store = $store->applyTo('cached-messages')->setSection($updated);
        }

        // cached prompt
        if ($cachedContext->prompt() !== '') {
            $store = $store->applyTo('cached-prompt')->setMessages(Messages::fromString($cachedContext->prompt()));
            $updated = $store->section('cached-prompt')->get()->appendContentField('cache_control', ['type' => 'ephemeral']);
            $store = $store->applyTo('cached-prompt')->setSection($updated);
        }

        // cached examples
        $store = $store->applyTo('cached-examples')->setMessages($this->makeExamples($cachedContext->examples()));
        if ($store->section('cached-examples')->isNotEmpty()) {
            $updated = $store->section('cached-examples')->get()->appendContentField('cache_control', ['type' => 'ephemeral']);
            $store = $store->applyTo('cached-examples')->setSection($updated);
        }

        return $this->removeEmptyMessages($store);
    }

    protected function withCacheMetaSections(CachedContext $cachedContext, MessageStore $store) : MessageStore {
        if ($cachedContext->isEmpty()) {
            return $store;
        }

        if ($store->section('cached-prompt')->isNotEmpty()) {
            $store = $store->applyTo('prompt')->remove();
            if ($store->section('pre-cached-prompt')->isEmpty()) {
                $store = $store->applyTo('pre-cached-prompt')->appendMessages([
                    'role' => 'user',
                    'content' => [[
                        'type' => 'text',
                        'text' => "TASK:",
                    ]],
                ]);
            }
        }

        if ($store->section('cached-examples')->isNotEmpty()) {
            if ($store->section('pre-cached-examples')->isEmpty()) {
                $store = $store->applyTo('pre-cached-examples')->appendMessages([
                    'role' => 'user',
                    'content' => [[
                        'type' => 'text',
                        'text' => "EXAMPLES:",
                    ]],
                ]);
            }
        }

        if ($store->section('post-cached')->isEmpty()) {
            $store = $store->applyTo('post-cached')->appendMessages([
                'role' => 'user',
                'content' => [[
                    'type' => 'text',
                    'text' => 'INSTRUCTIONS:',
                ]],
            ]);
        }

        return $store;
    }

    protected function addRetryMessages(StructuredOutputRequest $request, MessageStore $store) : MessageStore {
        $failedResponse = $request->lastFailedResponse();
        if (!$failedResponse || !$request->hasLastResponseFailed()) {
            return $store;
        }

        $messages = [];
        foreach($request->attempts() as $attempt) {
            $messages[] = ['role' => 'assistant', 'content' => $attempt->inferenceResponse()->content()];
            $retryFeedback = $this->config->retryPrompt()
                . Arrays::flattenToString($attempt->errors(), "; ");
            $messages[] = ['role' => 'user', 'content' => $retryFeedback];
        }
        $newMessageStore = $store->applyTo('retries')->setMessages(Messages::fromArray($messages));
        return $newMessageStore;
    }

    protected function withSections(MessageStore $store) : MessageStore {
        if ($store->section('prompt')->isNotEmpty()) {
            if ($store->section('pre-prompt')->isEmpty()) {
                $store = $store->applyTo('pre-prompt')->appendMessages([
                    'role' => 'user',
                    'content' => "TASK:",
                ]);
            }
        }

        if ($store->section('examples')->isNotEmpty()) {
            if ($store->section('pre-examples')->isEmpty()) {
                $store = $store->applyTo('pre-examples')->appendMessages([
                    'role' => 'user',
                    'content' => "EXAMPLES:",
                ]);
            }
        }

        if ($store->section('retries')->isNotEmpty()) {
            if ($store->section('pre-retries')->isEmpty()) {
                $store = $store->applyTo('pre-retries')->appendMessages([
                    'role' => 'user',
                    'content' => "FEEDBACK:",
                ]);
            }
            if ($store->section('post-retries')->isEmpty()) {
                $store = $store->applyTo('post-retries')->appendMessages([
                    'role' => 'user',
                    'content' => "CORRECTED RESPONSE:",
                ]);
            }
        } else {
            if ($store->section('post-retries')->isEmpty()) {
                $store = $store->applyTo('post-retries')->appendMessages([
                    'role' => 'user',
                    'content' => "RESPONSE:",
                ]);
            }
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

    private function mergeMessageStores(MessageStore $baseStore, MessageStore $sourceStore): MessageStore {
        $mergedStore = $baseStore;
        
        // Append messages from each section of the source store to the base store
        foreach ($sourceStore->sections()->each() as $section) {
            $mergedStore = $mergedStore->applyTo($section->name)->appendMessages($section->messages());
        }
        
        // Merge parameters
        return $mergedStore->mergeParameters($sourceStore->parameters());
    }

    private function removeEmptyMessages(MessageStore $store): MessageStore {
        $cleanStore = new MessageStore();
        
        foreach ($store->sections()->each() as $section) {
            $trimmedMessages = $section->messages()->trimmed();
            if (!$trimmedMessages->isEmpty()) {
                $cleanStore = $cleanStore->applyTo($section->name)->setMessages($trimmedMessages);
            }
        }
        
        // Preserve parameters
        return $cleanStore->mergeParameters($store->parameters());
    }
}
