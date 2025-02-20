<?php

namespace Cognesy\Aux\Web\Scrapers;

use Cognesy\Aux\Web\Contracts\CanGetUrlContent;
use Cognesy\Utils\Env;

class JinaReaderDriver implements CanGetUrlContent {
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl = '', string $apiKey = '') {
        $this->baseUrl = $baseUrl ?: Env::get('JINA_READER_BASE_URI', '');
        $this->apiKey = $apiKey ?: Env::get('JINA_READER_API_KEY', '');
    }

    public function getContent(string $url, array $options = []) : string {
        $url = $this->baseUrl . $url . '&api_key=' . $this->apiKey;
        return file_get_contents($url);
    }
}

//curl https://r.jina.ai/https://example.com \
//	-H "X-Return-Format: html"
