<?php
namespace Cognesy\Instructor\Clients\Cohere;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;


class CohereApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesTools;
    use Traits\HandlesResponseFormat;
    use Traits\HandlesScripts;

    protected string $defaultEndpoint = '/chat';


    protected function defaultBody(): array {
        return array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model,
                    'preamble' => $this->preamble(),
                    'chat_history' => $this->chatHistory(),
                    'message' => $this->message(),
                    'tools' => $this->tools(),
                ],
            )
        );
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
//            ->select(['messages', 'data_ack', 'command', 'examples'])
//            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }

    public function message(): string {
        return $this->script
            ->withContext($this->scriptContext)
            ->select(['system', 'messages', 'data_ack', 'command', 'examples'])
            ->toString();
    }
}
