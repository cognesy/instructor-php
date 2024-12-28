<?php

namespace Cognesy\Instructor\Utils\Web\Scrapers;

namespace Cognesy\Instructor\Utils\Web\Scrapers;

use Cognesy\Instructor\Utils\Env;
use Cognesy\Instructor\Utils\Web\Contracts\CanGetUrlContent;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;

class FirecrawlDriver implements CanGetUrlContent
{
    private string $baseUrl;
    private string $apiKey;
    private ClientInterface $client;

    public function __construct(string $baseUrl = '', string $apiKey = '') {
        $this->baseUrl = $baseUrl ?: Env::get('FIRECRAWL_BASE_URI', '');
        $this->apiKey = $apiKey ?: Env::get('FIRECRAWL_API_KEY', '');
        $this->client = new Client();
    }

    public function getContent(string $url, array $options = []): string {
        $body = [
            'url' => $url,
            'formats' => ['html', 'markdown'],
            'onlyMainContent' => true,
            'timeout' => 30000,
        ];
        if (isset($options['render_js'])) {
            $body['waitFor'] = $options['render_js'] ? 1000 : 0;
        }
        $requestData = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $body,
        ];
        try {
            $response = $this->client->request('POST', $this->baseUrl, $requestData);
            $content = $response->getBody()->getContents();
            $json = json_decode($content, true);
            return $json['data']['html'];
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }
}
