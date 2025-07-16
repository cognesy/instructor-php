<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web;

use Cognesy\Utils\Str;

class Link {
    readonly public string $url;
    readonly public string $title;
    readonly public bool $isInternal;
    readonly public string $domain;

    public function __construct(
        string $url = '',
        string $title = '',
        string $baseUrl = '',
        ?bool $isInternal = null,
        ?string $domain = null,
    ) {
        $this->title = $title;
        $this->isInternal = $isInternal ?? $this->isInternal($baseUrl, $url);
        $this->url = match(true) {
            $this->isInternal => Str::startsWith($url, '/') ? $baseUrl . $url : $url,
            default => $url,
        };
        $this->domain = $domain ?? $this->getDomain($this->url);
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
