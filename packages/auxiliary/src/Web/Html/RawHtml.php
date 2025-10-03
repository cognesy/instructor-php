<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Html;

use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use DOMDocument;
use DOMXPath;
use League\HTMLToMarkdown\HtmlConverter;

class RawHtml
{
    private string $content = '';
    private ?DOMDocument $dom = null;

    public function __construct(string $content) {
        $this->content = $content;
    }

    static public function fromContent(string $content): self {
        return new static($content);
    }

    public function asCleanHtml(): string {
        return Pipeline::builder(ErrorStrategy::FailFast)
            ->throughAll(...[
                $this->normalizeEncoding(...),
                $this->cleanupWhitespace(...),
                $this->removeEmptyElements(...),
                $this->parseDOM(...),
                $this->removeScripts(...),
                $this->removeStyles(...),
                $this->removeComments(...),
                $this->removeSVGs(...),
                $this->cleanupAttributes(...),
                $this->separateTags(...),
                $this->cleanupTags(...),
//                $this->normalizeStructure(...),
//                $this->normalizeUrls(...),
//                //$this->linearizeContent,
//                $this->extractMainContent(...),
            ])
            ->create()
            ->executeWith(ProcessingState::with($this->content))
            ->valueOr('');
    }

    public function asText(): string {
        // Convert to Markdown first to preserve some structure
        $markdown = $this->asMarkdown();

        // Remove Markdown formatting
        $text = $this->pregReplace('/#{1,6}\s+/', '', $markdown); // Remove headers
        $text = $this->pregReplace('/[\*_]{1,2}([^\*_]+)[\*_]{1,2}/', '$1', $text); // Remove emphasis
        $text = $this->pregReplace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text); // Remove links
        $text = $this->pregReplace('/!\[[^\]]*\]\([^\)]+\)/', '', $text); // Remove images
        $text = $this->pregReplace('/^[*+-]\s+/m', '', $text); // Remove list markers
        $text = $this->pregReplace('/^\d+\.\s+/m', '', $text); // Remove numbered list markers

        // Clean up whitespace
        $text = $this->pregReplace('/\n\s*\n\s*\n/', "\n\n", $text);

        return trim($text);
    }

    public function asMarkdown(): string {
        $html = $this->asCleanHtml();
        return (new HtmlConverter)->convert($html);

        // return $this->pipeline()
        //     ->through([
        //         [$this, 'convertHeadings'],
        //         [$this, 'convertLists'],
        //         [$this, 'convertLinks'],
        //         [$this, 'convertImages'],
        //         [$this, 'convertParagraphs'],
        //         [$this, 'cleanupMarkdown'],
        //     ])
        //     ->thenReturn();
    }

    // INTERNAL /////////////////////////////////////////////

    public function normalizeEncoding(string $content): string {
        // Convert to UTF-8 if needed
        if (!mb_check_encoding($content, 'UTF-8')) {
            $from = mb_detect_encoding($content) ?: 'UTF-8';
            $converted = mb_convert_encoding($content, 'UTF-8', $from);
            $content = is_string($converted) ? $converted : $content;
        }

        // Normalize HTML entities
        return html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function parseDOM(string $content): string {
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument('1.0', 'UTF-8');

        // Preserve UTF-8 encoding
        $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');

        // Load potentially malformed HTML
        $this->dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Clear any parsing errors
        libxml_clear_errors();

        return $content;
    }

    public function removeScripts(string $content): string {
        if (!$this->dom) {
            return $content;
        }

        $stores = $this->dom->getElementsByTagName('script');
        while ($stores->length > 0) {
            $store = $stores->item(0);
            $store?->parentNode->removeChild($store);
        }

        // Also remove onclick and other script attributes
        $xpath = new DOMXPath($this->dom);
        $nodes = $xpath->query('//*[@*[starts-with(name(), "on")]]');

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $attributes = $node->attributes;
                if ($attributes === null) {
                    continue;
                }
                foreach ($attributes as $attribute) {
                    if (str_starts_with($attribute->nodeName, 'on')) {
                        $node->removeAttribute($attribute->nodeName);
                    }
                }
            }
        }

        return $this->dom->saveHTML() ?: $content;
    }

    public function removeStyles(string $content): string {
        if (!$this->dom) {
            return $content;
        }

        // Remove style tags
        $styles = $this->dom->getElementsByTagName('style');
        while ($styles->length > 0) {
            $style = $styles->item(0);
            $style?->parentNode->removeChild($style);
        }

        // Remove link[rel=stylesheet]
        $xpath = new DOMXPath($this->dom);
        $styleLinks = $xpath->query('//link[@rel="stylesheet"]');
        if ($styleLinks !== false) {
            foreach ($styleLinks as $link) {
                $link->parentNode?->removeChild($link);
            }
        }

        // Remove style attributes
        $nodes = $xpath->query('//*[@style]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $node->removeAttribute('style');
            }
        }

        // Remove Tailwind and other common CSS framework classes
        $nodes = $xpath->query('//*[@class]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $classes = explode(' ', $node->getAttribute('class'));
                $semanticClasses = array_filter(
                    $classes,
                    fn($class) => !preg_match('/^(tw-|md:|lg:|sm:|hover:|focus:|active:|group-|dark:|light:)/', $class)
                        && !preg_match('/^(mt-|mb-|ml-|mr-|px-|py-|text-|bg-|border-)/', $class),
                );

                if ($semanticClasses === []) {
                    $node->removeAttribute('class');
                } else {
                    $node->setAttribute('class', implode(' ', $semanticClasses));
                }
            }
        }

        return $this->dom->saveHTML() ?: $content;
    }

    public function removeComments(string $content): string {
        if (!$this->dom) {
            return $content;
        }

        $xpath = new DOMXPath($this->dom);
        $comments = $xpath->query('//comment()');
        if ($comments !== false) {
            foreach ($comments as $comment) {
                $comment->parentNode?->removeChild($comment);
            }
        }

        return $this->dom->saveHTML() ?: $content;
    }

    public function cleanupWhitespace(string $content): string {
        // Normalize line endings
        $content = $this->pregReplace('/\R/', "\n", $content);

        // Remove multiple spaces
        $content = $this->pregReplace('/[ \t]+/', ' ', $content);

        // Remove lines with only whitespace
        $content = $this->pregReplace('/^\s+$/m', '', $content);

        // Remove spaces around tags
        $content = $this->pregReplace('/\s*(<[^>]*>)\s*/', '$1', $content);

        // Normalize multiple empty lines
        $content = $this->pregReplace('/\n\s*\n/', "\n\n", $content);

        return trim($content);
    }

    public function cleanupTags(string $content): string {
        $whitelist = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'ul', 'ol', 'li', 'img'];
        // Remove all tags that are not in the whitelist
        return strip_tags($content, $whitelist);
    }

    public function normalizeStructure(string $content): string {
        if (!$this->dom) {
            return $content;
        }

        $xpath = new DOMXPath($this->dom);

        // Convert div with large text to headings
        $divs = $xpath->query('//div[not(ancestor::header) and not(ancestor::nav)]');
        if ($divs !== false) {
            foreach ($divs as $div) {
                if ($div->hasAttribute('style')) {
                    $style = $div->getAttribute('style');
                    if (preg_match('/font-size:\s*(\d+)px/', $style, $matches)) {
                        $size = (int)$matches[1];
                        if ($size >= 24) {
                            $h2 = $this->dom->createElement('h2');
                            $h2->textContent = $div->textContent;
                            $div->parentNode?->replaceChild($h2, $div);
                        }
                    }
                }
            }
        }

        // Convert lists to semantic elements
        $lists = $xpath->query('//div[./div[position() = 1][.//text()[normalize-space()]]]');
        if ($lists !== false) {
            foreach ($lists as $list) {
                $items = $xpath->query('./*[normalize-space()]', $list);
                if ($items !== false && $items->length > 2) {
                    $ul = $this->dom->createElement('ul');
                    foreach ($items as $item) {
                        $li = $this->dom->createElement('li');
                        $li->textContent = $item->textContent;
                        $ul->appendChild($li);
                    }
                    $list->parentNode?->replaceChild($ul, $list);
                }
            }
        }

        return $this->dom->saveHTML() ?: $content;
    }

    public function cleanupAttributes(string $content): string {
        if (!$this->dom) {
            return $content;
        }

        $xpath = new DOMXPath($this->dom);
        $nodes = $xpath->query('//*[@*]');

        // List of attributes to keep
        $keepAttributes = ['href', 'src', 'alt', 'title'];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $attributes = $node->attributes;
                if ($attributes === null) {
                    continue;
                }
                foreach ($attributes as $attribute) {
                    if (!in_array($attribute->nodeName, $keepAttributes, true)) {
                        $node->removeAttribute($attribute->nodeName);
                    }
                }
            }
        }

        return $this->dom->saveHTML() ?: $content;
    }

    public function removeEmptyElements(string $content): string {
        if (!$this->dom) {
            return $content;
        }

        $xpath = new DOMXPath($this->dom);

        do {
            $removed = false;

            // Find elements with no text content and no meaningful children
            $emptyNodes = $xpath->query(
                '//*[not(self::img) and not(self::br) and not(self::hr)][not(normalize-space())]',
            );

            if ($emptyNodes !== false) {
                foreach ($emptyNodes as $node) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                        $removed = true;
                    }
                }
            }
        } while ($removed);

        return $this->dom->saveHTML() ?: $content;
    }

    public function extractMainContent(string $content): string {
        if (!$this->dom) {
            return $content;
        }

        $xpath = new DOMXPath($this->dom);

        // Try to find the main content area using common patterns
        $mainSelectors = [
            '//main',
            '//article',
            '//div[@role="main"]',
            '//div[@id="main"]',
            '//div[@id="content"]',
            '//div[contains(@class, "main-content")]',
        ];

        foreach ($mainSelectors as $selector) {
            $nodeList = $xpath->query($selector);
            if ($nodeList === false) {
                continue;
            }
            $main = $nodeList->item(0);
            if ($main) {
                // Create a new document with just the main content
                $newDoc = new DOMDocument('1.0', 'UTF-8');
                $newMain = $newDoc->importNode($main, true);
                $newDoc->appendChild($newMain);
                return $newDoc->saveHTML() ?: $content;
            }
        }

        // If no main content found, try to identify it heuristically
        $body = $this->dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return $content;
        }

        // Remove obvious non-content areas
        $removeSelectors = [
            '//header',
            '//footer',
            '//nav',
            '//aside',
            '//div[@role="complementary"]',
            '//div[contains(@class, "sidebar")]',
            '//div[contains(@class, "footer")]',
            '//div[contains(@class, "header")]',
            '//div[contains(@class, "nav")]',
        ];

        foreach ($removeSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes === false) {
                continue;
            }
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        return $this->dom->saveHTML() ?: $content;
    }

    public function normalizeUrls(string $content): string {
        if (!$this->dom) {
            return $content;
        }

        $baseUrl = '';

        $xpath = new DOMXPath($this->dom);

        // Find base URL if specified
        $baseList = $xpath->query('//base[@href]');
        $baseNode = $baseList !== false ? $baseList->item(0) : null;
        if ($baseNode instanceof \DOMElement) {
            $baseUrl = $baseNode->getAttribute('href');
        }

        // Process all elements with href or src attributes
        $nodes = $xpath->query('//*[@href or @src]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                if ($node->hasAttribute('href')) {
                    $url = $node->getAttribute('href');
                    $node->setAttribute('href', $this->makeAbsoluteUrl($url, $baseUrl));
                }
                if ($node->hasAttribute('src')) {
                    $url = $node->getAttribute('src');
                    $node->setAttribute('src', $this->makeAbsoluteUrl($url, $baseUrl));
                }
            }
        }

        return $this->dom->saveHTML() ?: $content;
    }

    public function makeAbsoluteUrl(string $url, string $baseUrl): string {
        // Already absolute URL
        if (preg_match('~^(?:https?://|//|data:)~i', $url)) {
            return $url;
        }

        // Remove fragment and query string for processing
        $fragment = parse_url($url, PHP_URL_FRAGMENT) ?? '';
        $query = parse_url($url, PHP_URL_QUERY) ?? '';
        $path = strtok($url, '#?') ?: '';

        if ($baseUrl) {
            // Handle relative URLs
            if (str_starts_with($path, '/')) {
                // Absolute path
                $baseUrlParts = parse_url($baseUrl);
                $scheme = $baseUrlParts['scheme'] ?? 'https';
                $host = $baseUrlParts['host'] ?? '';
                $path = rtrim($host, '/') . '/' . ltrim($path, '/');
            } else {
                // Relative path
                $basePath = dirname($baseUrl);
                $path = rtrim($basePath, '/') . '/' . $path;
            }
        }

        // Clean up path
        $path = $this->pregReplace('~/\./~', '/', $path);
        $path = $this->pregReplace('~/[^/]+/\.\./~', '/', $path);

        // Reconstruct URL
        $url = $path;
        if ($query) {
            $url .= '?' . $query;
        }
        if ($fragment) {
            $url .= '#' . $fragment;
        }

        return $url;
    }

    public function convertHeadings(string $content): string {
        return $this->pregReplaceCallback(
            '/<h([1-6])[^>]*>(.*?)<\/h\1>/si',
            fn($matches) => str_repeat('#', (int)$matches[1]) . ' ' . trim(strip_tags($matches[2])) . "\n\n",
            $content,
        );
    }

    public function convertLists(string $content): string {
        // Convert unordered lists
        $content = $this->pregReplaceCallback(
            '/<ul[^>]*>(.*?)<\/ul>/si',
            function ($matches) {
                return (string) preg_replace_callback(
                        '/<li[^>]*>(.*?)<\/li>/si',
                        fn($m) => "* " . trim(strip_tags($m[1])) . "\n",
                        $matches[1],
                    ) . "\n";
            },
            $content,
        );

        // Convert ordered lists
        $content = $this->pregReplaceCallback(
            '/<ol[^>]*>(.*?)<\/ol>/si',
            function ($matches) {
                $index = 1;
                return (string) preg_replace_callback(
                        '/<li[^>]*>(.*?)<\/li>/si',
                        function ($m) use (&$index) {
                            return $index++ . ". " . trim(strip_tags($m[1])) . "\n";
                        },
                        $matches[1],
                    ) . "\n";
            },
            $content,
        );

        return $content;
    }

    public function convertLinks(string $content): string {
        return $this->pregReplaceCallback(
            '/<a[^>]+href=["\'](.*?)["\'](.*?)>(.*?)<\/a>/si',
            fn($matches) => sprintf('[%s](%s)', trim(strip_tags($matches[3])), $matches[1]),
            $content,
        );
    }

    public function convertImages(string $content): string {
        return $this->pregReplaceCallback(
            '/<img[^>]+src=["\'](.*?)["\'](.*?)alt=["\'](.*?)["\'](.*?)>/si',
            fn($matches) => sprintf('![%s](%s)', $matches[3], $matches[1]),
            $content,
        );
    }

    public function convertParagraphs(string $content): string {
        // Convert <p> tags to double newlines
        $content = $this->pregReplace('/<p[^>]*>(.*?)<\/p>/si', "$1\n\n", $content);

        // Convert <br> tags to single newlines
        $content = $this->pregReplace('/<br[^>]*>/i', "\n", $content);

        return $content;
    }

    public function cleanupMarkdown(string $content): string {
        // Remove any remaining HTML tags
        $content = strip_tags($content);

        // Fix spacing around Markdown elements
        $content = $this->pregReplace('/\n\s*\n\s*\n/', "\n\n", $content);

        // Ensure proper spacing around headings
        $content = $this->pregReplace('/([^\n])(#{1,6}\s)/', "$1\n\n$2", $content);

        // Clean up whitespace
        return trim($content);
    }

    public function removeSVGs(string $content): string {
        return $this->pregReplace('/<svg[^>]*>.*?<\/svg>/si', '', $content);
    }

    public function separateTags(string $content): string {
        return $this->pregReplace('/></', '> <', $content);
    }

    private function pregReplace(string $pattern, string $replacement, string $subject, int $limit = -1): string
    {
        $result = preg_replace($pattern, $replacement, $subject, $limit);
        return is_string($result) ? $result : $subject;
    }

    /**
     * @param callable(array<int|string,string>):string $callback
     */
    private function pregReplaceCallback(string $pattern, callable $callback, string $subject, int $limit = -1): string
    {
        $result = preg_replace_callback($pattern, $callback, $subject, $limit);
        return is_string($result) ? $result : $subject;
    }
}
