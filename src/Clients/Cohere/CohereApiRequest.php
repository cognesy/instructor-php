<?php
namespace Cognesy\Instructor\Clients\Cohere;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;


class CohereApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesTools;
    use Traits\HandlesResponseFormat;

    protected string $defaultEndpoint = '/chat';


    protected function defaultBody(): array {
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'preamble' => $this->preamble(),
                    'chat_history' => $this->chatHistory(),
                    'message' => $this->message(),
                    'tools' => $this->tools(),
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }

    public function message(): string {
        if ($this->noScript()) {
            return Messages::fromArray($this->messages)->toString();
        }

        if ($this->script->section('examples')->notEmpty()) {
            $this->script->section('pre-examples')->appendMessage([
                'role' => 'assistant',
                'content' => 'Examples:',
            ]);
            $this->script->section('pre-input')->appendMessage([
                'role' => 'user',
                'content' => 'INPUT:',
            ]);
        }
        return $this->script
            ->withContext($this->scriptContext)
            ->select(['system', 'prompt', 'pre-examples', 'examples', 'pre-input', 'messages', 'input', 'retries'])
            ->toString();
    }

    public function preamble(): string {
        return '';
//        return $this->script
//            ->withContext($this->scriptContext)
//            ->select(['system'])
//            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }

    public function chatHistory(): array {
        return [];
//        return $this->script
//            ->withContext($this->scriptContext)
//            ->select(['messages', 'data_ack', 'prompt', 'examples'])
//            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }
}
