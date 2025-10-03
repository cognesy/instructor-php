<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Scrapers;

use Cognesy\Auxiliary\Web\Contracts\CanGetUrlContent;
use Cognesy\Config\Env;

class JinaReaderDriver implements CanGetUrlContent {
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl = '', string $apiKey = '') {
        $this->baseUrl = $baseUrl ?: Env::get('JINA_READER_BASE_URI', '');
        $this->apiKey = $apiKey ?: Env::get('JINA_READER_API_KEY', '');
    }

    #[\Override]
    public function getContent(string $url, array $options = []) : string {
        $url = $this->baseUrl . $url . '&api_key=' . $this->apiKey;
        $content = file_get_contents($url);
        return is_string($content) ? $content : '';
    }
}

//curl https://r.jina.ai/https://example.com \
//	-H "X-Return-Format: html"
