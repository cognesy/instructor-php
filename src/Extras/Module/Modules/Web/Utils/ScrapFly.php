<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Web\Utils;

use Cognesy\Instructor\Utils\Env;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;

class ScrapFly {
    private string $baseUrl;
    private string $apiKey;
    private ClientInterface $client;

    public function __construct() {
        $this->baseUrl = Env::get('SCRAPFLY_BASE_URL');
        $this->apiKey = Env::get('SCRAPFLY_API_KEY');
        $this->client = new Client();
    }

    public static function fromUrl(string $url) : string {
        return (new self)->get($url);
    }

    private function get(string $url): string {
        $apiUrl = $this->makeUrl($url);
        $request = new Request('GET', $apiUrl);

        try {
            $response = $this->client->sendRequest($request);
            $content = $response->getBody()->getContents();
            $json = json_decode($content, true);
            return $json['result']['content'];
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    private function makeUrl(string $url) : string {
        $fields = [
            'key' => $this->apiKey,
            'url' => $url,
            'render_js' => 'false',
            'asp' => 'false',
            'format' => 'raw',
        ];
        return $this->baseUrl . '?' . http_build_query($fields);
    }
}
