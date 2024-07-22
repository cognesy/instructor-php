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
        Mode::Tools->value => "Extract correct and accurate data from the input using provided tools. Response must be JSON object following provided tool schema.\n<|json_schema|>\n",
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
                'pre-input', 'messages', 'input', 'post-input',
                'pre-prompt', 'prompt', 'post-prompt',
                'pre-examples', 'examples', 'post-examples',
                'pre-retries', 'retries', 'post-retries'
            ])
            ->toArray(
                context: ['json_schema' => $this->jsonSchema() ?? []]
            );
    }

    public function prompt() : string {
        return $this->request->prompt() ?: $this->defaultPrompts[$this->request->mode()->value] ?? '';
    }

    public function retryPrompt() : string {
        return $this->request->retryPrompt() ?: $this->defaultRetryPrompt;
    }

    public function system() : string {
        return $this->request->system(); // ?: 'You are executor of complex language programs. Analyze input instructions, extract key information from provided inputs, and always generate a JSON output adhering to the provided schema.';
    }

    public function jsonSchema() : array {
        return $this->request->responseModel()?->toJsonSchema();
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function makeScript(Request $request) : Script {
        if ($this->isRequestEmpty($request)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }

        $script = new Script();

        // GET DATA
        $messages = $this->normalizeMessages($request->messages());
        $input = $request->input();
        $examples = $request->examples();
        $prompt = $this->prompt();

        // SYSTEM SECTION
        $index = 0;
        $script->section('system')->appendMessage(['role' => 'system', 'content' => $request->system()]);
        foreach ($messages as $message) {
            if ($message['role'] !== 'system') {
                break;
            }
            $script->section('system')->appendMessage(['role' => 'system', 'content' => $message['content']]);
            $index++;
        }

        // MESSAGES SECTION
        $messagesSection = array_slice($messages, $index);
        if (!empty($messagesSection)) {
            $script->section('messages')->appendMessages($messagesSection);
        }

        // INPUT DATA SECTION
        $inputMessages = Messages::fromInput($input);
        if (!$this->isInputEmpty($inputMessages)) {
            $script->section('input')->appendMessages($inputMessages);
        }

        // PROMPT SECTION
        if (!empty($prompt)) {
            $script->section('prompt')->appendMessage([
                'role' => 'user',
                'content' => $prompt
            ]);
        }

        // EXAMPLES SECTION
        if (!empty($examples)) {
            foreach ($examples as $item) {
                $example = match(true) {
                    is_array($item) => Example::fromArray($item),
                    is_string($item) => Example::fromJson($item),
                    $item instanceof Example => $item,
                    default => throw new Exception('Invalid example type'),
                };
                $script->section('examples')->appendMessage(Message::fromString($example->toString()));
            }
        }
        return $script;
    }

    protected function withMetaSections(Script $script) : Script {
        $script->section('pre-input')->appendMessageIfEmpty([
            'role' => 'user',
            'content' => "INPUT:",
        ]);

        $script->section('pre-prompt')->appendMessageIfEmpty([
            'role' => 'user',
            'content' => "TASK:",
        ]);

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

    private function isInputEmpty(Message|Messages $inputMessages) : bool {
        return match(true) {
            $inputMessages instanceof Messages => $inputMessages->isEmpty(),
            $inputMessages instanceof Message => $inputMessages->isEmpty() || $inputMessages->isNull(),
            default => true,
        };
    }

    private function isRequestEmpty(Request $request) : bool {
        return match(true) {
            !empty($request->messages()) => false,
            !empty($request->input()) => false,
            !empty($request->examples()) => false,
            !empty($request->prompt()) => false,
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

    protected function makeRetryMessages(
        array $messages,
        string $jsonData,
        array $errors
    ) : array {
        $retryFeedback = $this->retryPrompt() . Arrays::flatten($errors, "; ");
        $messages[] = ['role' => 'assistant', 'content' => $jsonData];
        $messages[] = ['role' => 'user', 'content' => $retryFeedback];
        return $messages;
    }
}