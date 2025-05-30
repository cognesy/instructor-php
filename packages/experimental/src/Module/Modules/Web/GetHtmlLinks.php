<?php
namespace Cognesy\Experimental\Module\Modules\Web;

use Cognesy\Experimental\Module\Core\Module;
use Cognesy\Experimental\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Experimental\Module\Signature\Attributes\ModuleSignature;
use Cognesy\Utils\Str;

#[ModuleSignature('html:string -> links:Link[]')]
#[ModuleDescription('Extract links from HTML')]
class GetHtmlLinks extends Module
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

    public function __construct(?array $blacklist = null) {
        $this->blacklist = $blacklist ?? $this->blacklist;
    }

    public function for(string $html): array {
        return ($this)(html: $html)->get('links');
    }

    /**
     * @return \Cognesy\Auxiliary\Web\Link[]
     */
    protected function forward(mixed ...$callArgs): array {
        $html = $callArgs['html'];
        $links = $this->extractLinks($html);
        return [
            'links' => $links
        ];
    }

    private function extractLinks(string $page, string $baseUrl = '') : array {
        $links = [];
        preg_match_all('/<a[^>]+href\s*=\s*([\'"])(?<href>.+?)\1[^>]*>(?<text>.*?)<\/a>/i', $page, $matches);
        foreach ($matches['href'] as $key => $href) {
            $link = new \Cognesy\Auxiliary\Web\Link(
                url: $href,
                title: strip_tags($matches['text'][$key]),
            );
            if ($this->skip($link, $links)) {
                continue;
            }
            $links[] = $link;
        }
        return $links;
    }

    private function getDomain(string $url): string {
        $urlParts = parse_url($url);
        return $urlParts['host'] ?? '';
    }

    private function isLinkInArray(array $links, string $url): bool {
        foreach ($links as $link) {
            if ($link->url === $url) {
                return true;
            }
        }
        return false;
    }

    private function skip(\Cognesy\Auxiliary\Web\Link $link, array $links = []) : bool {
        return match(true) {
            empty($link->url) => true,
            Str::startsWith($link->url, '#') => true,
            Str::startsWith($link->url, '+') => true,
            Str::startsWith($link->url, '\'') => true,
            Str::startsWith($link->url, 'javascript:') => true,
            Str::startsWith($link->url, 'mailto:') => true,
            Str::startsWith($link->url, '" target=') => true,
            ($this->getDomain($link->url) === '') => true,
            in_array($this->getDomain($link->url), $this->blacklist) => true,
            $this->isLinkInArray($links, $link->url) => true,
            default => false
        };
    }
}
