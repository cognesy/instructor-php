<?php
namespace Cognesy\Instructor\Data;

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
        $this->script = $this->makeScript($this->request);

        // Add retry messages if needed
        $this->addRetryMessages();

        // Add meta sections
        return $this->withMetasections($this->script)
            ->select([
                'system',
                'pre-prompt', 'prompt', 'post-prompt',
                'pre-examples',
                    'examples',
                    'pre-input', 'messages', 'input', 'post-input',
                'post-examples',
                'pre-retries', 'retries', 'post-retries'
            ])
            ->toArray(
                context: ['json_schema' => $this->makeJsonSchema() ?? []]
            );
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
        $script->section('system')->appendMessages(
            $this->makeSystem($cachedContext['messages'], $cachedContext['system'])
        );
        $script->section('messages')->appendMessages(
            $this->makeMessages($cachedContext['messages'])
        );
        $script->section('input')->appendMessages(
            $this->makeInput($cachedContext['input'])
        );
        $script->section('prompt')->appendMessage(
            Message::fromString($cachedContext['prompt'])
        );
        $script->section('examples')->appendMessages(
            $this->makeExamples($cachedContext['examples'])
        );
        return $script;
    }

    protected function withMetaSections(Script $script) : Script {
        $script->section('pre-input')->appendMessageIfEmpty([
            'role' => 'user',
            'content' => "INPUT:",
        ]);

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

        $script->section('post-examples')->appendMessageIfEmpty([
            'role' => 'user',
            'content' => "RESPONSE:",
        ]);

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

    protected function makeSystem(array $messages, string $system) : Messages {
        $output = new Messages();

        if (!empty($system)) {
            $output->appendMessage(['role' => 'system', 'content' => $system]);
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

    protected function makeMessages(array $messages) : Messages {
        $output = new Messages();
        if (empty($messages)) {
            return $output;
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