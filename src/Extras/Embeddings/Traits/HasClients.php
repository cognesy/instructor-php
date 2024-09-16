<?php

namespace Cognesy\Instructor\Extras\Embeddings\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Utils\Settings;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

trait HasClients
{
    public function make(string|array $input, array $options = []) : array {
        if (is_string($input)) {
            $input = [$input];
        }
        if (count($input) > $this->maxInputs) {
            throw new InvalidArgumentException("Number of inputs exceeds the limit of {$this->maxInputs}");
        }
        return match ($this->clientType) {
            ClientType::Azure => $this->getAzureOpenAIEmbedding($input, $options),
            ClientType::Cohere => $this->getCohereEmbedding($input, $options),
            ClientType::Gemini => $this->getGeminiEmbedding($input, $options),
            ClientType::Mistral => $this->getMistralEmbedding($input, $options),
            ClientType::OpenAI => $this->getOpenAIEmbedding($input, $options),
            ClientType::Ollama => $this->getOllamaEmbedding($input, $options),
            ClientType::Jina => $this->getJinaEmbedding($input, $options),
            default => throw new InvalidArgumentException("Unknown client: {$this->client}"),
        };
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function loadConfig(string $client) : void {
        if (!Settings::has('embed', "connections.$client")) {
            throw new InvalidArgumentException("Unknown client: $client");
        }
        $this->clientType = ClientType::from(Settings::get('embed', "connections.$client.clientType"));
        $this->apiKey = Settings::get('embed', "connections.$client.apiKey", '');
        $this->model = Settings::get('embed', "connections.$client.defaultModel", '');
        $this->dimensions = Settings::get('embed', "connections.$client.defaultDimensions", 0);
        $this->maxInputs = Settings::get('embed', "connections.$client.maxInputs", 1);
        $this->apiUrl = Settings::get('embed', "connections.$client.apiUrl");
        $this->endpoint = Settings::get('embed', "connections.$client.endpoint");
    }

    protected function getOpenAIEmbedding(array $input, array $options = []): array {
        $url = "{$this->apiUrl}{$this->endpoint}";
        $request = [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => array_filter(array_merge([
                'input' => $input,
                'model' => $this->model,
                'encoding_format' => 'float',
            ], $options)),
        ];
        $response = $this->client->post($url, $request);
        $result = json_decode($response->getBody()->getContents(), true);
        return array_map(fn($item) => $item['embedding'], $result['data']);
    }

    protected function getAzureOpenAIEmbedding(array $input, array $options = []): array {
        $url = "{$this->apiUrl}{$this->endpoint}";
        $request = [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => array_filter(array_merge([
                'input' => $input,
                'model' => $this->model,
                'encoding_format' => 'float',
            ], $options)),
        ];
        $response = $this->client->post($url, $request);
        $result = json_decode($response->getBody()->getContents(), true);
        return array_map(fn($item) => $item['embedding'], $result['data']);
    }

    /**
     * @throws GuzzleException
     */
    protected function getCohereEmbedding(array $input, array $options = []): array {
        $url = "{$this->apiUrl}{$this->endpoint}";
        $options['input_type'] = $options['input_type'] ?? 'search_document';
        $request = [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => array_filter(array_merge([
                'texts' => $input,
                'model' => $this->model,
                'embedding_types' => ['float'],
                'truncate' => 'END',
            ], $options)),
        ];
        $response = $this->client->post($url, $request);
        $result = json_decode($response->getBody()->getContents(), true);
        return $result['embeddings']['float'];
    }

    /**
     * @throws GuzzleException
     */
    protected function getGeminiEmbedding(array $input, array $options = []): array {
        $url = str_replace("{model}", $this->model, "{$this->apiUrl}{$this->endpoint}?key={$this->apiKey}");
        $request = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'requests' => array_map(fn($item) => ['model' => $this->model, 'content' => ['parts' => [['text' => $item]]]], $input),
            ],
            ['debug' => true]
        ];
        $response = $this->client->post($url, $request);
        $result = json_decode($response->getBody()->getContents(), true);
        return array_map(fn($item) => $item['values'], $result['embeddings']);
    }

    /**
     * @throws GuzzleException
     */
    protected function getMistralEmbedding(array $input, array $options = []): array {
        $url = "{$this->apiUrl}{$this->endpoint}";
        $request = [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => array_filter(array_merge([
                'input' => $input,
                'model' => $this->model,
                'encoding_format' => 'float',
            ], $options)),
        ];
        $response = $this->client->post($url, $request);
        $result = json_decode($response->getBody()->getContents(), true);
        return array_map(fn($item) => $item['embedding'], $result['data']);
    }

    /**
     * @throws GuzzleException
     */
    protected function getOllamaEmbedding(array $input, array $options = []): array {
        $url = "{$this->apiUrl}{$this->endpoint}";
        $request = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => array_filter(array_merge([
                'input' => $input,
                'model' => $this->model,
            ], $options)),
        ];
        $response = $this->client->post($url, $request);
        $result = json_decode($response->getBody()->getContents(), true);
        return array_map(fn($item) => $item['embedding'], $result['data']);
    }

    /**
     * @throws GuzzleException
     */
    protected function getJinaEmbedding(array $input, array $options = []): array {
        $url = "{$this->apiUrl}{$this->endpoint}";
        $dimensions = $options['dimensions'] ?? 128;
        $inputType = $options['input_type'] ?? 'document';
        $request = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->apiKey}",
            ],
            'json' => array_filter(array_merge([
                'model' => $this->model,
                'normalized' => true,
                'embedding_type' => 'float',
                'input' => $input,
            ], $options)),
        ];
        if ($this->model === 'jina-colbert-v2') {
            $request['json']['input_type'] = $inputType;
            $request['json']['dimensions'] = $dimensions;
        }
        $response = $this->client->post($url, $request);
        $result = json_decode($response->getBody()->getContents(), true);
        return array_map(fn($item) => $item['embedding'], $result['data']);
    }
}