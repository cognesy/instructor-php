<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Web\Utils;

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\DomCrawler\Crawler;

class HtmlProcessor {
    public function toMarkdown(string $page) : string {
        $page = $this->cleanup($page);
        return (new HtmlConverter)->convert($page);
    }

    public function getTitle(string $html) : string {
        $crawler = new Crawler($html);
        $node = $crawler->filter('title');
        if ($node->count() === 0) {
            return '';
        }
        return $node->text();
    }

    public function getMetadata(string $html, array $attributes): array {
        // use Crawler to extract metadata
        $crawler = new Crawler($html);
        $metadata = [];
        $crawler->filter('meta')->each(function (Crawler $node) use (&$metadata) {
            $name = $node->attr('name');
            $property = $node->attr('property');
            $content = $node->attr('content');
            if ($name) {
                $metadata[$name] = $content;
            } elseif ($property) {
                $metadata[$property] = $content;
            }
        });

        // Get title, description, keywords
        $filtered = [];
        foreach ($attributes as $attribute) {
            if (isset($metadata[$attribute])) {
                $filtered[$attribute] = $metadata[$attribute];
            }
        }
        return $filtered;
    }

    // INTERNAL /////////////////////////////////////////////////////////

    protected function cleanup(string $page) : string {
        $body = $this->getBody($page);
//        $body = match(true) {
//            default => $this->extractContentByTier($body, $this->selectorTiers())
//        };
        $body = $this->removeTagsWithContent($body, ['script', 'noscript', 'style', 'iframe', 'aside']);
        $body = str_replace(array('</div>'), array(" </div><br>\n\n "), $body);
        $body = $this->addSpaces($body);
        // remove any non-basic html tags, leave only raw text
        $body = $this->stripHtmlTags($body, ['a', 'b', 'i', 'strong', 'pre', 'p', 'img', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'tbody', 'thead', 'tfoot', 'caption']);
        $body = $this->removeWhitespaceBeforeEndOfLine($body);
        $body = $this->removeWhitespaceBeforeBr($body);
        $body = $this->consolidateBrNewlines($body);
        $body = $this->consolidateBrs($body);
        $body = $this->replaceMultipleNewlines($body);
        $body = $this->replaceMultipleSpacesPreservePre($body);
        return $body;
    }

    private function selectorTiers() : array {
        return [
            ['article'],
            ['div.prose'],
            ['div.content', 'div[role="content"]'],
            ['main', 'div[role="main"]', 'div.main'],
        ];
    }

    private function stripHtmlTags(string $html, array $allowed = []) : string {
        return strip_tags($html, $allowed);
    }

    private function getBody(string $page) : string {
        $body = $this->cleanBodyTag($page);
        $body = explode('<body>', $body)[1];
        $body = explode('</body>', $body)[0];
        return $body;
    }

    private function removeTagsWithContent(string $html, array $tags) : string {
        foreach ($tags as $tag) {
            $html = $this->removeTagWithContent($html, $tag);
        }
        return $html;
    }

    private function removeTagWithContent(string $html, string $tag) : string {
        $pattern = '/<'. $tag . '\b[^>]*>(.*?)<\/' .$tag . '>/is';
        return preg_replace($pattern, '', $html);
    }

    private function removeTagKeepContent(string $html, string $tag) : string {
        // remove opening tag entry
        $html = preg_replace('/<' . $tag . '\b[^>]*>/', '', $html);
        // remove closing tag entry
        $html = preg_replace('/<\/' . $tag . '>/is', '', $html);
        return $html;
    }

    private function cleanBodyTag(string $html) : string {
        return preg_replace('/<body[^>]*>/', '<body>', $html);
    }

    private function replaceMultipleSpaces(string $str) : string {
        return preg_replace('/ {2,}/', ' ', $str);
    }

    private function replaceMultipleNewlines(string $str) : string {
        return preg_replace('/\n{2,}/', "\n\n", $str);
    }

    private function removeDuplicateEmptyLines(string $str) : string {
        return preg_replace('/\n[ \t]*\n/', "\n\n", $str);
    }

    private function removeWhitespaceBeforeEndOfLine(string $str) : string {
        return preg_replace('/[\t ]+$/m', '', $str);
    }

    protected function removeWhitespaceBeforeBr(string $str) : string {
        return preg_replace('/[\t\n ]*<br>\n/s', "<br>\n", $str);
    }

    protected function consolidateBrs(string $str) : string {
        return preg_replace('/(\s*<br>\s*)+/', "<br>\n", $str);
    }

    protected function consolidateBrNewlines(string $str) : string {
        return preg_replace('/(\s*<br>\n\s*\n)+/', "<br>\n", $str);
    }

    private function replaceMultipleSpacesPreservePre(string $str) : string {
        // Step 1: Find all <pre></pre> blocks
        preg_match_all('/<pre\b[^>]*>(.*?)<\/pre>/is', $str, $preMatches);

        // Step 2: Replace all <pre></pre> blocks with placeholders
        $strWithPlaceholders = preg_replace('/<pre\b[^>]*>(.*?)<\/pre>/is', 'PRE_PLACEHOLDER', $str);

        // Step 3: Replace multiple spaces with a single one
        $strWithPlaceholders = preg_replace('/[ \t]{2,}/', ' ', $strWithPlaceholders);

        // Step 4: Replace placeholders with original <pre></pre> blocks
        foreach ($preMatches[0] as $preMatch) {
            $pos = strpos($strWithPlaceholders, 'PRE_PLACEHOLDER');
            if ($pos !== false) {
                $strWithPlaceholders = substr_replace($strWithPlaceholders, $preMatch, $pos, strlen('PRE_PLACEHOLDER'));
            }
        }

        return $strWithPlaceholders;
    }

    private function addSpaces(string $body) : string {
        return preg_replace_callback('/<[^>]+>/', function ($matches) {
            $tag = $matches[0];
            if (strpos($tag, '<span') === 0 || strpos($tag, '</span') === 0) {
                // If it's a span tag, return it unchanged
                return $tag;
            } else {
                // Otherwise, add spaces around the tag
                return ' ' . $tag . ' ';
            }
        }, $body);
    }

    private function containsAny(string $html, array $selectors) : bool {
        $crawler = new Crawler($html);
        $filtered = $crawler->filter(implode(', ', $selectors));
        return $filtered->count() > 0;
    }

    private function containsSingle(string $html, mixed $selectors) : bool {
        $crawler = new Crawler($html);
        $filtered = $crawler->filter(implode(', ', $selectors));
        return $filtered->count() === 1;
    }

    private function extractContent(string $html, array $selectors) : string {
        $crawler = new Crawler($html);
        $items = $crawler->filter(implode(', ', $selectors))->each(function (Crawler $node) {
            return $node->html();
        });
        $content = '';
        foreach ($items as $item) {
            $content .= $item . "\n\n";
        }
        return $content;
    }

    private function extractContentByTier(string $html, array $selectorTiers) : string {
        foreach ($selectorTiers as $selectors) {
            if ($this->containsSingle($html, $selectors)) {
                return $this->extractContent($html, $selectors);
            }
            $selectorTiers = array_reverse($selectorTiers);
            if ($this->containsAny($html, $selectors)) {
                return $this->extractContent($html, $selectors);
            }
        }
        return $html;
    }

    private function hasIdApp(string $body) : bool {
        $crawler = new Crawler($body);
        $node = $crawler->filter('#app');
        return $node->count() > 0;
    }

    private function getAppContent(string $body) : string {
        $crawler = new Crawler($body);
        return $crawler->filter('div#app')->attr('data-page');
    }

    private function removeHtmlTags(string $body, array $excluded) : string {
        foreach ($excluded as $tag) {
            $body = $this->removeTagKeepContent($body, $tag);
        }
        return $body;
    }
}
