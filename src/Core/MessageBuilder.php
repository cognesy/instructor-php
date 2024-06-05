<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
use Cognesy\Instructor\Clients\Anyscale\AnyscaleClient;
use Cognesy\Instructor\Clients\Azure\AzureClient;
use Cognesy\Instructor\Clients\Cohere\CohereClient;
use Cognesy\Instructor\Clients\FireworksAI\FireworksAIClient;
use Cognesy\Instructor\Clients\Groq\GroqClient;
use Cognesy\Instructor\Clients\Mistral\MistralClient;
use Cognesy\Instructor\Clients\Ollama\OllamaClient;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Clients\OpenRouter\OpenRouterClient;
use Cognesy\Instructor\Clients\TogetherAI\TogetherAIClient;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Template;
use Exception;

class MessageBuilder
{
    private string $clientClass = '';
    private Mode $mode = Mode::Tools;
    private array $messages = [];
    private ?ResponseModel $responseModel = null;
    private ?string $dataAcknowledgedPrompt = null;
    private ?string $prompt = null;
    private ?array $examples = null;

    public function __construct() {}

    static public function requestBody(
        string $clientClass,
        Mode $mode = Mode::Tools,
        array $messages = [],
        ?ResponseModel $responseModel = null,
        ?string $dataAcknowledgedPrompt = null,
        ?string $prompt = null,
        ?array $examples = null,
    ) : array {
        $instance = new self();
        $instance->clientClass = $clientClass;
        $instance->mode = $mode;
        $instance->messages = $messages;
        $instance->responseModel = $responseModel;
        $instance->dataAcknowledgedPrompt = $dataAcknowledgedPrompt;
        $instance->prompt = $prompt;
        $instance->examples = $examples;
        return $instance->makeExtractionRequest();
    }

    // INTERNAL TOOLS ///////////////////////////////////////////////////////////////////

    private function makeExtractionRequest() : array {
        // get body creation method based on client
        $builder = $this->getBuilder($this->clientClass);
        // get the parts of body specific to the client
        $body = $builder();
        // filter out empty values
        $body = array_filter($body);
        return $body;
    }

    private function getBuilder(string $clientClass) : callable {
        return match($clientClass) {
            AnthropicClient::class => fn() => $this->anthropic(),
            CohereClient::class => fn() => $this->cohere(),
            MistralClient::class => fn() => $this->mistral(),
            OpenAIClient::class,
            AzureClient::class => fn() => $this->openAI(),
            AnyscaleClient::class,
            FireworksAIClient::class,
            GroqClient::class,
            OllamaClient::class,
            OpenRouterClient::class,
            TogetherAIClient::class => fn() => $this->openAILike(),
            default => fn() => [],
        };
    }

    private function anthropic() : array {
        $body = [];
        $messages = $this->makeMessages();
        $body['system'] = $messages->system();
        $body['messages'] = $this->mapToTargetAPI(
            type: ClientType::Anthropic->value,
            messages: array_filter(array_merge(
                $messages->history(),
                $messages->command()
            )),
        );
        return $body;
    }

    private function cohere() : array {
        $body = [];
        $messages = $this->makeMessages();
        $body['preamble'] = $messages->system();
        $body['chat_history'] = $this->mapToTargetAPI(
            type: ClientType::Cohere->value,
            messages: $messages->history()
        );
        $body['message'] = $messages->command(true)[0]['content'] ?? '';
        return array_filter($body);
    }

    private function mistral() : array {
        $body = [];
        $messages = $this->makeMessages();
        $body['messages'] = $this->mapToTargetAPI(
            type: ClientType::Mistral->value,
            messages: array_filter(array_merge(
                empty($messages->system()) ? [] : ['role' => 'system', 'content' => $messages->system()],
                $messages->history(),
                $messages->command(withSchema: true)
            )),
        );
        return $body;
    }

    private function openAI() : array {
        $body = [];
        $messages = $this->makeMessages();
        $body['messages'] = $this->mapToTargetAPI(
            type: ClientType::OpenAI->value,
            messages: array_filter(array_merge(
                empty($messages->system()) ? [] : ['role' => 'system', 'content' => $messages->system()],
                $messages->history(),
                $messages->command(withSchema: true)
            )),
        );
        return $body;
    }

    private function openAILike() : array {
        $body = [];
        $messages = $this->makeMessages();
        $body['messages'] = $this->mapToTargetAPI(
            type: ClientType::OpenAICompatible->value,
            messages: array_filter(array_merge(
                empty($messages->system()) ? [] : ['role' => 'system', 'content' => $messages->system()],
                $messages->history(),
                $messages->command()
            )),
        );
        return $body;
    }

    /**
     * @return object
     * @throws Exception
     */
    private function makeMessages() : object {
        if (empty($this->messages)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }

        return new class(
            $this->clientClass,
            $this->mode,
            $this->messages,
            $this->prompt,
            $this->examples,
            $this->dataAcknowledgedPrompt,
            $this->responseModel,
        ) {
            private array $system = [];
            private array $history = [];

            public function __construct(
                private string $clientClass,
                private Mode $mode,
                private array $messages,
                private string $prompt,
                private array $examples,
                private string $dataAcknowledgedPrompt,
                private ResponseModel $responseModel,
            ) {
                $index = 0;
                // extract initial system messages
                foreach ($this->messages as $message) {
                    $role = $message['role'];
                    $content = $message['content'];
                    if ($role === 'system') {
                        $this->system[] = $content;
                        $index++;
                    } else {
                        break;
                    }
                }
                // extract history
                $this->history = array_slice($this->messages, $index);
            }

            // BEGINNING MESSAGE
            public function system() : string {
                return implode("\n", $this->system);
            }

            // ORIGINAL MESSAGES PASSED FOR PROCESSING - DATA
            public function history() : array {
                return array_merge(
                    $this->ensureProperSequence(
                        $this->normalize($this->history)
                    ),
                );
            }

            // PROMPT AND EXAMPLES
            public function command(bool $withSchema = false) : array {
                $content = '';
                // PROMPT SECTION
                if (!empty($this->prompt)) {
                    $content = match($withSchema) {
                        true => Template::render(
                            $this->prompt,
                            ['json_schema' => $this->responseModel->toJsonSchema()]
                        ),
                        default => str_replace('{json_schema}', '', $this->prompt),
                    };
                }
                // EXAMPLES SECTION
                $content .= "\n\n";
                if (!empty($this->examples)) {
                    foreach ($this->examples as $example) {
                        $content .= $example->toString() . "\n\n";
                    }
                }
                // MERGE PROMPT AND EXAMPLES INTO SINGLE MESSAGE ENTRY
                return [['role' => 'user', 'content' => $content]];
            }

            private function normalize(string|array $messages): array {
                if (!is_array($messages)) {
                    return [['role' => 'user', 'content' => $messages]];
                }
                return $messages;
            }

            private function ensureProperSequence(string|array $messages): array {
                // add user turn if assistant was the first to speak
                if ($messages[0]['role'] === 'assistant') {
                    $messages = array_merge([['role' => 'user', 'content' => 'Continue']], $messages);
                }
                // add assistant turn if user was the last to speak
                if ($messages[count($messages)-1]['role'] === 'user') {
                    $messages = array_merge($messages, [['role' => 'assistant', 'content' => $this->dataAcknowledgedPrompt]]);
                }
                return $messages;
            }
        };
    }

    private function mapToTargetAPI(string $type, array $messages) : array {
        if (empty($messages)) {
            return [];
        }
        $roleMap = [
            ClientType::Anthropic->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'assistant', 'tool' => 'user'],
            ClientType::Cohere->value => ['user' => 'USER', 'assistant' => 'CHATBOT', 'system' => 'CHATBOT', 'tool' => 'USER'],
            ClientType::Mistral->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            ClientType::OpenAI->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
            ClientType::OpenAICompatible->value => ['user' => 'user', 'assistant' => 'assistant', 'system' => 'system', 'tool' => 'tool'],
        ];
        $keyMap = [
            ClientType::Anthropic->value => 'content',
            ClientType::Cohere->value => 'message',
            ClientType::Mistral->value => 'content',
            ClientType::OpenAICompatible->value => 'content',
            ClientType::OpenAI->value => 'content',
        ];
        $roles = $roleMap[$type];
        $key = $keyMap[$type];
        $normalized = [];
        foreach ($messages as $message) {
            $normalized[] = ['role' => $roles[$message['role']], $key => $message['content']];
        }
        return $normalized;
    }
}
