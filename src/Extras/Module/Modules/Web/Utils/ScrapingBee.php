<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Web\Utils;

use Cognesy\Instructor\Utils\Env;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;

class ScrapingBee {
    private string $apiKey;
    private string $baseUrl;
    private ClientInterface $client;

    public function __construct() {
        $this->baseUrl = Env::get('SCRAPINGBEE_BASE_URL');
        $this->apiKey = Env::get('SCRAPINGBEE_API_KEY');
        $this->client = new Client();
    }

    public static function fromUrl(string $url, bool $renderJs = true) : string {
        return (new self)->get($url, $renderJs);
    }

    private function get(string $url, bool $renderJs = true): string {
        $apiUrl = $this->makeUrl($url, $renderJs);
        $request = new Request('GET', $apiUrl);
        try {
            $response = $this->client->sendRequest($request);
            return $response->getBody()->getContents();
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    private function makeUrl(string $url, bool $renderJs) : string {
        $fields = [
            'api_key' => $this->apiKey,
            'url' => $url,
            'render_js' => $renderJs ? 'true' : 'false',
        ];
        return $this->baseUrl . '?' . http_build_query($fields);
    }
}
