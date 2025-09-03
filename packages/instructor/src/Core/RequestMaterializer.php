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
use Cognesy\Template\Script\Script;
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

        // Temporary safeguard to keep messages present; isolated for easy removal.
        $final = $this->ensureNonEmptyMessages($rendered, $request);
        return $final->toArray();
    }

    protected function makeScript(StructuredOutputRequest $request) : Script {
        if ($this->isRequestEmpty($request)) {
            throw new Exception('Request cannot be empty - you have to provide content for processing.');
        }
        $messages = $request->messages();
        $script = (new Script())
            ->withSectionMessages('system', $this->makeSystem($messages, $request->system()))
            ->withSectionMessages('messages', $this->makeMessages($messages))
            ->withSectionMessage('prompt', $this->makePrompt($request->prompt()
                ?: $this->config->prompt($request->mode())
                ?? ''
            ))
            ->withSectionMessages('examples', $this->makeExamples($request->examples()));
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
            $messages[] = ['role' => 'assistant', 'content' => $attempt->inferenceResponse()->content()];
            $retryFeedback = $this->config->retryPrompt()
                . Arrays::flattenToString($attempt->errors(), "; ");
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
        $output = Messages::empty();
        if (!empty($system)) {
            $output->appendMessage(new Message(role: 'system', content: $system));
        }
        $output->appendMessages(
            $messages->headWithRoles(['system', 'developer'])
        );
        return $output;
    }

    protected function makeMessages(Messages $messages) : Messages {
        $output = Messages::empty();
        $output->appendMessages(
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
