<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Html;

use Cognesy\Auxiliary\Web\Contracts\CanConvertToMarkdown;
use Cognesy\Auxiliary\Web\Contracts\CanProcessHtml;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\DomCrawler\Crawler;

class HtmlProcessor implements CanProcessHtml, CanConvertToMarkdown {
    #[\Override]
    public function getMetadata(string $html, array $attributes = []): array {
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

    #[\Override]
    public function getTitle(string $html) : string {
        $crawler = new Crawler($html);
        $node = $crawler->filter('title');
        if ($node->count() === 0) {
            return '';
        }
        return $node->text();
    }

    #[\Override]
    public function getBody(string $html) : string {
        $body = $this->cleanBodyTag($html);
        $parts = explode('<body>', $body);
        $body = $parts[1] ?? '';
        $parts = explode('</body>', $body);
        $body = $parts[0] ?? '';
        return $body;
    }

    #[\Override]
    public function toMarkdown(string $html) : string {
        return (new HtmlConverter)->convert($html);
    }

    public function select(string $html, string $selector) : string {
        return (new Crawler($html))->filter($selector)->html();
    }

    public function selectMany(string $html, string $selector) : array {
        return (new Crawler($html))->filter($selector)->each(function (Crawler $node) {
            return $node->html();
        });
    }

    public function cleanup(
        string $html,
        array $removeTags = ['a', 'b', 'i', 'strong', 'pre', 'p', 'img', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'tbody', 'thead', 'tfoot', 'caption'],
        array $removeTagsWithContent = ['script', 'noscript', 'style', 'iframe', 'aside']
    ) : string {
        $body = $html;
//        $body = match(true) {
//            default => $this->extractContentByTier($body, $this->selectorTiers())
//        };
        $body = $this->removeTagsWithContent($body, $removeTagsWithContent);
        $body = str_replace(array('</div>'), array(" </div><br>\n\n "), $body);
        $body = $this->addSpaces($body);
        // remove any non-basic html tags, leave only raw text
        $body = $this->stripHtmlTags($body, $removeTags);
        $body = $this->removeWhitespaceBeforeEndOfLine($body);
        $body = $this->removeWhitespaceBeforeBr($body);
        $body = $this->consolidateBrNewlines($body);
        $body = $this->consolidateBrs($body);
        $body = $this->replaceMultipleNewlines($body);
        $body = $this->replaceMultipleSpacesPreservePre($body);

        return $body;
    }

    // INTERNAL /////////////////////////////////////////////////////////

    // removed unused selector tier helpers to satisfy static analysis

    private function stripHtmlTags(string $html, array $allowed = []) : string {
        return strip_tags($html, $allowed);
    }

    private function removeTagsWithContent(string $html, array $tags) : string {
        foreach ($tags as $tag) {
            $html = $this->removeTagWithContent($html, $tag);
        }
        return $html;
    }

    private function removeTagWithContent(string $html, string $tag) : string {
        $pattern = '/<'. $tag . '\b[^>]*>(.*?)<\/' .$tag . '>/is';
        $result = preg_replace($pattern, '', $html);
        return is_string($result) ? $result : $html;
    }

    // removed unused removeTagKeepContent

    private function cleanBodyTag(string $html) : string {
        $result = preg_replace('/<body[^>]*>/', '<body>', $html);
        return is_string($result) ? $result : $html;
    }

    // removed unused replaceMultipleSpaces

    private function replaceMultipleNewlines(string $str) : string {
        $result = preg_replace('/\n{2,}/', "\n\n", $str);
        return is_string($result) ? $result : $str;
    }

    // removed unused removeDuplicateEmptyLines

    private function removeWhitespaceBeforeEndOfLine(string $str) : string {
        $result = preg_replace('/[\t ]+$/m', '', $str);
        return is_string($result) ? $result : $str;
    }

    protected function removeWhitespaceBeforeBr(string $str) : string {
        $result = preg_replace('/[\t\n ]*<br>\n/s', "<br>\n", $str);
        return is_string($result) ? $result : $str;
    }

    protected function consolidateBrs(string $str) : string {
        $result = preg_replace('/(\s*<br>\s*)+/', "<br>\n", $str);
        return is_string($result) ? $result : $str;
    }

    protected function consolidateBrNewlines(string $str) : string {
        $result = preg_replace('/(\s*<br>\n\s*\n)+/', "<br>\n", $str);
        return is_string($result) ? $result : $str;
    }

    private function replaceMultipleSpacesPreservePre(string $str) : string {
        // Step 1: Find all <pre></pre> blocks
        preg_match_all('/<pre\b[^>]*>(.*?)<\/pre>/is', $str, $preMatches);

        // Step 2: Replace all <pre></pre> blocks with placeholders
        $strWithPlaceholders = preg_replace('/<pre\b[^>]*>(.*?)<\/pre>/is', 'PRE_PLACEHOLDER', $str);
        if (!is_string($strWithPlaceholders)) {
            return $str;
        }

        // Step 3: Replace multiple spaces with a single one
        $strWithPlaceholders = preg_replace('/[ \t]{2,}/', ' ', $strWithPlaceholders);
        if (!is_string($strWithPlaceholders)) {
            return $str;
        }

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
        $result = preg_replace_callback('/<[^>]+>/', function ($matches) {
            $tag = $matches[0];
            if (strpos($tag, '<span') === 0 || strpos($tag, '</span') === 0) {
                // If it's a span tag, return it unchanged
                return $tag;
            }
            // Otherwise, add spaces around the tag
            return ' ' . $tag . ' ';
        }, $body);
        return is_string($result) ? $result : $body;
    }

    // removed unused content extraction helpers

    // removed unused extractContentByTier

    // removed unused hasIdApp

    // removed unused getAppContent

    // removed unused removeHtmlTags
}
