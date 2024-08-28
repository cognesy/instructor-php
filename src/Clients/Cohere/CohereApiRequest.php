<?php
namespace Cognesy\Instructor\Clients\Cohere;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;


class CohereApiRequest extends ApiRequest
{
    protected string $defaultEndpoint = '/chat';

    protected function defaultBody(): array {
        $system = '';
        $chatHistory = [];
        $messages = Messages::asString($this->messages());
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'preamble' => $system,
                    'chat_history' => $chatHistory,
                    'message' => $messages,
                    'tools' => $this->tools(),
                    'response_format' => $this->getResponseFormat(),
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }

    public function tools(): array {
        if (empty($this->tools)) {
            return [];
        }
        $cohereFormat = [];
        foreach ($this->tools as $tool) {
            $parameters = [];
            foreach ($tool['function']['parameters']['properties'] as $name => $param) {
                $parameters[] = array_filter([
                    'name' => $name,
                    'description' => $param['description'] ?? '',
                    'type' => $this->toCohereType($param),
                    'required' => in_array($name, $this->tools['function']['parameters']['required']??[]),
                ]);
            }
            $cohereFormat[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'parameters_definitions' => $parameters,
            ];
        }
        return $cohereFormat;
    }

    public function getToolChoice(): string|array {
        return '';
    }

    protected function getResponseFormat(): array {
        return [
            'type' => 'json_object',
            'schema' => $this->getResponseSchema(),
        ];
    }

    protected function getResponseSchema(): array {
        return $this->jsonSchema ?? [];
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    private function toCohereType(array $param) : string {
        $type = $param['type'] ?? 'string';
        return match($type) {
            'string' => 'str',
            'number' => 'float',
            'integer' => 'int',
            'boolean' => 'bool',
            'array' => throw new \Exception('Array type not supported by Cohere'),
            'object' => throw new \Exception('Object type not supported by Cohere'),
            default => throw new \Exception('Unknown type'),
        };
    }
}
