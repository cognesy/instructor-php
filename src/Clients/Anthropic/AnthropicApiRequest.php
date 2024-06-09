<?php
namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;


class AnthropicApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesResponseFormat;
    use Traits\HandlesTools;

    protected string $defaultEndpoint = '/messages';

    protected function defaultBody(): array {
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'system' => $this->system(),
                    'messages' => $this->messages(),
                    'tools' => $this->tools(),
                    'tool_choice' => $this->getToolChoice(),
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }

    public function messages(): array {
        if ($this->noScript()) {
            return $this->messages;
        }

        if ($this->script->section('examples')->notEmpty()) {
            $this->script->section('pre-examples')->appendMessage([
                'role' => 'assistant',
                'content' => 'Provide examples.',
            ]);
        }
        $this->script->section('pre-input')->appendMessage([
            'role' => 'assistant',
            'content' => "Provide input.",
        ]);

        if($this->mode->is(Mode::Tools)) {
            unset($this->scriptContext['json_schema']);
        }

        $x = $this->script
            ->withContext($this->scriptContext)
            ->select(['prompt', 'pre-examples', 'examples', 'pre-input', 'messages', 'input', 'retries'])
            ->toNativeArray(ClientType::fromRequestClass(static::class));
        dd($this->script);
    }

    public function system(): array {
        return $this->script
            ->withContext($this->scriptContext)
            ->select(['system'])
            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }
}
