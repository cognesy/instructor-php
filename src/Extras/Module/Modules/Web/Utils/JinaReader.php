<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Web\Utils;

use Cognesy\Instructor\Utils\Env;

class JinaReader {
    private string $baseUrl;
    private string $apiKey;

    public function __construct() {
        $this->baseUrl = Env::get('JINA_READER_BASE_URL', '');
        $this->apiKey = Env::get('JINA_READER_API_KEY', '');
    }

    public static function fromUrl(string $url) : string {
        return (new self)->get($url);
    }

    public function get(string $url) : string {
        $url = $this->baseUrl . $url . '&api_key=' . $this->apiKey;
        return file_get_contents($url);
    }
}
//curl https://r.jina.ai/https://example.com \
//	-H "X-Return-Format: html"
