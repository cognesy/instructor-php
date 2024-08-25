<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Script;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Arrays;
use Exception;

class ChatTemplate
{
    private string $defaultRetryPrompt = "JSON generated incorrectly, fix following errors:\n";
    private array $defaultPrompts = [
        Mode::MdJson->value => "Respond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n<|json_schema|>\n",
        Mode::Json->value => "Respond correctly with strict JSON object. Response must follow JSONSchema:\n<|json_schema|>\n",
        Mode::Tools->value => "Extract correct and accurate data from the input using provided tools.\n",
        //Mode::Tools->value => "Extract correct and accurate data from the input using provided tools. Response must be JSON object following provided tool schema.\n<|json_schema|>\n",
    ];

    private ?Request $request;
    private Script $script;

    public function __construct(
        Request $request = null
    ) {
        $this->request = $request;
    }

    public static function fromRequest(Request $request) : static {
        return new self($request);
    }

    public function toMessages() : array {
        $this->script = $this->makeScript($this->request)->mergeScript(
            $this->makeCachedScript($this->request->cachedContext())
        );

        // Add retry messages if needed
        $this->addRetryMessages();

        // Add meta sections
        $output = $this
            ->withCacheMetaSections($this->withMetaSections($this->script))
            ->select([
                // potentially cached - predefined sections used to construct the script
                'system',
                'pre-cached',
                    'pre-cached-prompt', 'cached-prompt', 'post-cached-prompt',
                    'pre-cached-examples', 'cached-examples', 'post-cached-examples',
                    'pre-cached-input', 'cached-input', 'post-cached-input',
                    'cached-messages',
                'post-cached',
                // never cached
                'pre-prompt', 'prompt', 'post-prompt',
                'pre-examples', 'examples', 'post-examples',
                'pre-input', 'input', 'post-input',
                'messages',
                'pre-retries', 'retries', 'post-retries'
            ])
            ->toArray(
                context: ['json_schema' => $this->makeJsonSchema() ?? []],
            );

        return $output;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function makeScript(Request $request) : Script {
        if ($this->isRequestEmpty($request)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }

        $script = new Script();

        // GET DATA
        $messages = $this->normalizeMessages($request->messages());

        // SYSTEM SECTION
        $script->section('system')->appendMessages(
            $this->makeSystem($messages, $request->system())
        );
        $script->section('messages')->appendMessages(
            $this->makeMessages($messages)
        );
        $script->section('input')->appendMessages(
            $this->makeInput($request->input())
        );
        $script->section('prompt')->appendMessage(
            $this->makePrompt($this->request->prompt())
        );
        $script->section('examples')->appendMessages(
            $this->makeExamples($request->examples())
        );

        return $this->filterEmptySections($script);
    }

    protected function makeCachedScript(array $cachedContext) : Script {
        if (empty($cachedContext)) {
            return new Script();
        }

        $script = new Script();

        $script->section('system')->prependMessages(
            $this->makeSystem($cachedContext['messages'], $cachedContext['system'])
        );
        $script->section('cached-messages')->appendMessages(
            $this->makeMessages($cachedContext['messages'])
        );
        $script->section('cached-input')->appendMessages(
            $this->makeInput($cachedContext['input'])
        );
        $script->section('cached-prompt')->appendMessage(
            Message::fromString($cachedContext['prompt'])
        );
        $script->section('cached-examples')->appendMessages(
            $this->makeExamples($cachedContext['examples'])
        );

        return $script;
    }

    protected function withMetaSections(Script $script) : Script {
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

        if ($script->section('input')->notEmpty()) {
            $script->section('pre-input')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "INPUT:",
            ]);
            $script->section('post-input')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "RESPONSE:",
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
        }

        return $script;
    }

    protected function withCacheMetaSections(Script $script) : Script {
        if (empty($this->request->cachedContext())) {
            return $script;
        }

        if ($script->section('cached-prompt')->notEmpty()) {
            $script->removeSection('prompt');
            $script->section('pre-cached-prompt')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "TASK:",
            ]);
        }

        if ($script->section('cached-examples')->notEmpty()) {
            $script->section('pre-cached-examples')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "EXAMPLES:",
            ]);
        }

        if ($script->section('cached-input')->notEmpty()) {
            $script->section('pre-cached-input')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "INPUT:",
            ]);
        }

        $script->section('post-cached')->appendMessageIfEmpty([
            'role' => 'user',
            'content' => [[
                'type' => 'text',
                'text' => 'INSTRUCTIONS:',
                'cache_control' => ["type" => "ephemeral"],
            ]],
        ]);

        return $script;
    }

    private function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }

    private function filterEmptySections(Script $script) : Script {
        foreach ($script->sections() as $section) {
            if ($this->isSectionEmpty($section->messages())) {
                $script->removeSection($section->name());
            }
        }
        return $script;
    }

    private function isSectionEmpty(Message|Messages $content) : bool {
        return match(true) {
            $content instanceof Messages => $content->isEmpty(),
            $content instanceof Message => $content->isEmpty() || $content->isNull(),
            default => true,
        };
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

    protected function makeSystem(string|array $messages, string $system) : Messages {
        $output = new Messages();

        if (!empty($system)) {
            $output->appendMessage(['role' => 'system', 'content' => $system]);
        }

        if (!is_array($messages)) {
            $messages = [['role' => 'user', 'content' => $messages]];
        }

        // EXTRACT SYSTEM ROLE FROM MESSAGES - until first non-system message
        foreach ($messages as $message) {
            if ($message['role'] !== 'system') {
                break;
            }
            $output->appendMessage(['role' => 'system', 'content' => $message['content']]);
        }

        return $output;
    }

    protected function makeMessages(string|array $messages) : Messages {
        $output = new Messages();
        if (empty($messages)) {
            return $output;
        }
        if (!is_array($messages)) {
            $messages = [['role' => 'user', 'content' => $messages]];
        }
        // skip system messages
        $index = 0;
        foreach ($messages as $message) {
            if ($message['role'] !== 'system') {
                break;
            }
            $index++;
        }
        $output->appendMessages(array_slice($messages, $index));
        return $output;
    }

    protected function makeExamples(array $examples) : Messages {
        $messages = new Messages();
        if (empty($examples)) {
            return $messages;
        }
        foreach ($examples as $item) {
            $example = match(true) {
                is_array($item) => Example::fromArray($item),
                is_string($item) => Example::fromJson($item),
                $item instanceof Example => $item,
                default => throw new Exception('Invalid example type'),
            };
            $messages->appendMessages($example->toMessages());
        }
        return $messages;
    }

    protected function makePrompt(string $prompt) : Message {
        return new Message(
            role: 'user',
            content: $prompt
                ?: $this->defaultPrompts[$this->request->mode()->value]
                ?? ''
        );
    }

    protected function makeRetryPrompt() : string {
        return $this->request->retryPrompt() ?: $this->defaultRetryPrompt;
    }

    protected function makeInput(array|object|string $input) : Messages {
        if (empty($input)) {
            return new Messages();
        }
        return Messages::fromInput($input);
    }

    protected function makeJsonSchema() : array {
        return $this->request->responseModel()?->toJsonSchema();
    }

    private function isRequestEmpty(Request $request) : bool {
        return match(true) {
            !empty($request->messages()) => false,
            !empty($request->input()) => false,
            !empty($request->prompt()) => false,
            !empty($request->system()) => false, // ?
            !empty($request->examples()) => false, // ?
            default => true,
        };
    }

    private function addRetryMessages() {
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