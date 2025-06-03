<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Template\Script\Script;
use Cognesy\Template\Template;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
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
        $script = $this
            ->makeScript($request)
            ->mergeScript($this->makeCachedScript($request->cachedContext()));

        // Add retry messages if needed
        $script = $this->addRetryMessages($request, $script);

        // Add meta sections
        $output = $this
            ->withCacheMetaSections($request->cachedContext(), $this->withSections($script))
            ->select($this->config->chatStructure());

        $rendered = Template::arrowpipe()
            ->with(['json_schema' => json_encode($this->makeJsonSchema($request->responseModel()))])
            ->renderMessages($output->toMessages());

        return $rendered->toArray();
    }

    protected function makeScript(StructuredOutputRequest $request) : Script {
        if ($this->isRequestEmpty($request)) {
            throw new Exception('Request cannot be empty - you have to provide content for processing.');
        }

        $script = new Script();

        // GET DATA
        $messages = $request->messages();

        // SYSTEM SECTION
        $script->section('system')->appendMessages(
            $this->makeSystem($messages, $request->system())
        );
        $script->section('messages')->appendMessages(
            $this->makeMessages($messages)
        );
        $script->section('prompt')->appendMessage(
            $this->makePrompt($request->prompt()
                ?: $this->config->prompt($request->mode())
                ?? ''
            )
        );
        $script->section('examples')->appendMessages(
            $this->makeExamples($request->examples())
        );
        return $script->trimmed();
    }

    protected function makeCachedScript(CachedContext $cachedContext) : Script {
        if ($cachedContext->isEmpty()) {
            return new Script();
        }

        $script = new Script();

        $script->section('system')
            ->prependMessages($this->makeSystem($cachedContext->messages(), $cachedContext->system()))
            ->appendContentField('cache_control', ['type' => 'ephemeral']);

        $script->section('cached-messages')
            ->appendMessages($this->makeMessages($cachedContext->messages()))
            ->appendContentField('cache_control', ['type' => 'ephemeral']);

        $script->section('cached-prompt')
            ->appendMessage(Message::fromString($cachedContext->prompt()))
            ->appendContentField('cache_control', ['type' => 'ephemeral']);

        $script->section('cached-examples')
            ->appendMessages($this->makeExamples($cachedContext->examples()))
            ->appendContentField('cache_control', ['type' => 'ephemeral']);

        return $script->trimmed();
    }

    protected function withCacheMetaSections(CachedContext $cachedContext, Script $script) : Script {
        if ($cachedContext->isEmpty()) {
            return $script;
        }

        if ($script->section('cached-prompt')->notEmpty()) {
            $script->removeSection('prompt');
            $script->section('pre-cached-prompt')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => [[
                    'type' => 'text',
                    'text' => "TASK:",
                ]],
            ]);
        }

        if ($script->section('cached-examples')->notEmpty()) {
            $script->section('pre-cached-examples')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => [[
                    'type' => 'text',
                    'text' => "EXAMPLES:",
                ]],
            ]);
        }

        $script->section('post-cached')->appendMessageIfEmpty([
            'role' => 'user',
            'content' => [[
                'type' => 'text',
                'text' => 'INSTRUCTIONS:',
            ]],
        ]);

        return $script;
    }

    protected function addRetryMessages(StructuredOutputRequest $request, Script $script) : Script {
        $failedResponse = $request->lastFailedResponse();
        if (!$failedResponse || !$request->hasLastResponseFailed()) {
            return $script;
        }

        $newScript = $script->clone();
        $messages = [];
        foreach($request->attempts() as $attempt) {
            $messages[] = ['role' => 'assistant', 'content' => $attempt->llmResponse()->content()];
            $retryFeedback = $this->config->retryPrompt()
                . Arrays::flatten($attempt->errors(), "; ");
            $messages[] = ['role' => 'user', 'content' => $retryFeedback];
        }
        $newScript->section('retries')->appendMessages($messages);
        return $newScript;
    }

    protected function withSections(Script $script) : Script {
        if ($script->section('prompt')->notEmpty()) {
            $script->section('pre-prompt')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "TASK:",
            ]);
        }

        if ($script->section('examples')->notEmpty()) {
            $script->section('pre-examples')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "EXAMPLES:",
            ]);
        }

        if ($script->section('retries')->notEmpty()) {
            $script->section('pre-retries')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "FEEDBACK:",
            ]);
            $script->section('post-retries')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "CORRECTED RESPONSE:",
            ]);
        } else {
            $script->section('post-retries')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "RESPONSE:",
            ]);
        }

        return $script;
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
        $output = new Messages();
        if (!empty($system)) {
            $output->appendMessage(new Message(role: 'system', content: $system));
        }
        $output->appendMessages(
            $messages->headWithRoles(['system', 'developer'])
        );
        return $output;
    }

    protected function makeMessages(Messages $messages) : Messages {
        $output = new Messages();
        $output->appendMessages(
            $messages->tailAfterRoles(['developer', 'system'])
        );
        return $output;
    }

    protected function makeExamples(array $examples) : Messages {
        $output = new Messages();
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
            $output->appendMessages($example->toMessages());
        }
        return $output;
    }

    protected function makePrompt(string $prompt) : Message {
        return new Message(role: 'user', content: $prompt);
    }

    protected function makeJsonSchema(?ResponseModel $responseModel) : array {
        return $responseModel?->toJsonSchema() ?? [];
    }
}