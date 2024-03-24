<?php
namespace Cognesy\Instructor\LLMs\Anthropic;

class AnthropicClient
{
    private string $apiKey;
    private string $baseUrl = 'https://api.anthropic.com/v1/messages';
    private string $version = '2023-06-01';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function sendMessage(AnthropicRequest $request): AnthropicResponse
    {
        $headers = new Headers();
        $headers->set('x-api-key', $this->apiKey);
        $headers->set('anthropic-version', $this->version);
        $headers->set('content-type', 'application/json');

        $httpRequest = new HttpRequest(
            HttpMethod::POST,
            $this->baseUrl,
            $headers,
            new RequestBody(json_encode($request))
        );

        $response = $httpRequest->send();

        $data = json_decode($response->getBody()->toJsonString(), true);

        $usage = new AnthropicUsage();
        $usage->input_tokens = $data['usage']['input_tokens'];
        $usage->output_tokens = $data['usage']['output_tokens'];

        $anthropicResponse = new AnthropicResponse();
        $anthropicResponse->content = $data['content'][0]['text'];
        $anthropicResponse->id = $data['id'];
        $anthropicResponse->model = $data['model'];
        $anthropicResponse->role = $data['role'];
        $anthropicResponse->stop_reason = $data['stop_reason'];
        $anthropicResponse->stop_sequence = $data['stop_sequence'];
        $anthropicResponse->type = $data['type'];
        $anthropicResponse->usage = $usage;

        return $anthropicResponse;
    }
}