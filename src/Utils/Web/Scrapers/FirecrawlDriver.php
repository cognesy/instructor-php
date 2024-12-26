<?php

namespace Cognesy\Instructor\Utils\Web\Scrapers;

namespace Cognesy\Instructor\Utils\Web\Scrapers;

use Cognesy\Instructor\Utils\Env;
use Cognesy\Instructor\Utils\Web\Contracts\CanGetUrlContent;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;

class FirecrawlDriver implements CanGetUrlContent
{
    private string $baseUrl;
    private string $apiKey;
    private ClientInterface $client;

    public function __construct(string $baseUrl = '', string $apiKey = '') {
        $this->baseUrl = $baseUrl ?: Env::get('FIRECRAWL_BASE_URI', '');
        $this->apiKey = $apiKey ?: Env::get('FIRECRAWL_API_KEY', '');
    }

    public function getContent(string $url, array $options = []): string {
        $request = new Request('GET', $this->baseUrl);

        try {
            $response = $this->client->sendRequest($request);
            $content = $response->getBody()->getContents();
            $json = json_decode($content, true);
            return $json['result']['content'];
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }
}
