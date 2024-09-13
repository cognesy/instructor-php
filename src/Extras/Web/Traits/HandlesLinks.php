<?php

namespace Cognesy\Instructor\Extras\Web\Traits;

use Cognesy\Instructor\Extras\Web\Link;
use Cognesy\Instructor\Utils\Str;

trait HandlesLinks
{
    private array $blacklist = [
        'www.facebook.com',
        'facebook.com',
        'google.com',
        'app.convertcent.com',
        'twitter.com',
        'calendly.com',
        't.me',
    ];

    public function withDomainBlacklist(array $blacklist): static {
        $this->blacklist = $blacklist;
        return $this;
    }

    public function links() : array {
        return $this->extractLinks($this->content);
    }

    // INTERNAL ///////////////////////////////////////////////////////

    private function extractLinks(string $page, string $baseUrl = '') : array {
        $links = [];
        preg_match_all('/<a[^>]+href\s*=\s*([\'"])(?<href>.+?)\1[^>]*>(?<text>.*?)<\/a>/i', $page, $matches);
        foreach ($matches['href'] as $key => $href) {
            $link = new Link(
                url: $href,
                title: strip_tags($matches['text'][$key]),
                baseUrl: $baseUrl,
            );
            if ($this->skip($link, $links)) {
                continue;
            }
            $links[] = $link;
        }
        return $links;
    }

    private function isLinkInArray(array $links, string $url): bool {
        foreach ($links as $link) {
            if ($link->url === $url) {
                return true;
            }
        }
        return false;
    }

    private function skip(Link $link, array $links = []) : bool {
        return match(true) {
            empty($link->url) => true,
            Str::startsWith($link->url, '#') => true,
            Str::startsWith($link->url, '+') => true,
            Str::startsWith($link->url, '\'') => true,
            Str::startsWith($link->url, 'javascript:') => true,
            Str::startsWith($link->url, 'mailto:') => true,
            Str::startsWith($link->url, '" target=') => true,
            ($link->domain === '') => true,
            in_array($link->domain, $this->blacklist) => true,
            $this->isLinkInArray($links, $link->url) => true,
            default => false
        };
    }
}
