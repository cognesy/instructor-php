<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Web;

use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;
use Cognesy\Instructor\Utils\Str;
use Cognesy\Instructor\Utils\Web\Link;

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

    public function __construct(array $blacklist = null) {
        $this->blacklist = $blacklist ?? $this->blacklist;
    }

    public function for(string $html): array {
        return ($this)(html: $html)->get('links');
    }

    /**
     * @return Link[]
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
            $link = new \Cognesy\Instructor\Utils\Web\Link();
            $link->url = $href;
            $link->title = strip_tags($matches['text'][$key]);
            $link->isInternal = $this->isInternal($href, $baseUrl);
            if ($link->isInternal) {
                $link->url = Str::startsWith($href, '/') ? $baseUrl . $href : $href;
            }
            $link->domain = $this->getDomain($link->url);
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

    private function isInternal($url, $baseUrl) : bool {
        return match(true) {
            empty($url) => false,
            Str::startsWith($url, '/') => true,
            !empty($baseUrl) && Str::startsWith($url, $baseUrl) => true,
            default => false,
        };
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
            ($this->getDomain($link->url) === '') => true,
            in_array($this->getDomain($link->url), $this->blacklist) => true,
            $this->isLinkInArray($links, $link->url) => true,
            default => false
        };
    }
}
