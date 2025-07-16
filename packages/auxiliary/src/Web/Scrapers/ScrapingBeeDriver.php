<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Scrapers;

use Cognesy\Auxiliary\Web\Contracts\CanGetUrlContent;
use Cognesy\Config\Env;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;

class ScrapingBeeDriver implements CanGetUrlContent{
    private string $apiKey;
    private string $baseUrl;
    private ClientInterface $client;

    public function __construct(string $baseUrl = '', string $apiKey = '') {
        $this->baseUrl = $baseUrl ?: Env::get('SCRAPINGBEE_BASE_URI');
        $this->apiKey = $apiKey ?: Env::get('SCRAPINGBEE_API_KEY');
        $this->client = new Client();
    }

    public function getContent(string $url, array $options = []): string {
        $renderJs = $options['render_js'] ?? true;
        $apiUrl = $this->makeUrl($url, $renderJs);
        $request = new Request('GET', $apiUrl);
        try {
            $response = $this->client->sendRequest($request);
            return $response->getBody()->getContents();
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    // INTERNAL ///////////////////////////////////////////

    private function makeUrl(string $url, bool $renderJs) : string {
        $fields = [
            'api_key' => $this->apiKey,
            'url' => $url,
            'render_js' => $renderJs ? 'true' : 'false',
        ];
        return $this->baseUrl . '?' . http_build_query($fields);
    }
}
