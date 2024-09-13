<?php
namespace Cognesy\Instructor\Extras\Web;

use Cognesy\Instructor\Utils\Str;

class Link {
    public string $url;
    public string $title;
    public bool $isInternal;
    public string $domain;

    public function __construct(string $url, string $title, string $baseUrl = '') {
        $this->title = $title;
        $this->isInternal = $this->isInternal($baseUrl, $url);

        $this->url = $url;
        if ($this->isInternal($baseUrl, $url)) {
            $this->url = Str::startsWith($url, '/') ? $baseUrl . $url : $url;
        }
        $this->domain = $this->getDomain($this->url);
    }

    // INTERNAL ///////////////////////////////////////////////////////

    private function isInternal(string $baseUrl, string $url) : bool {
        return match(true) {
            empty($url) => false,
            Str::startsWith($url, '/') => true,
            !empty($baseUrl) && Str::startsWith($url, $baseUrl) => true,
            default => false,
        };
    }

    private function getDomain(string $url): string {
        $urlParts = parse_url($url);
        return $urlParts['host'] ?? '';
    }
}
