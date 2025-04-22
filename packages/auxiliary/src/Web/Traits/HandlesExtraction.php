<?php

namespace Cognesy\Auxiliary\Web\Traits;

use Cognesy\Auxiliary\Web\Webpage;
use Generator;

trait HandlesExtraction
{
    public function get(string $url, array $options = []) : static {
        $this->url = $url;
        $this->content = $this->scraper->getContent($url, $options);
        if ($options['cleanup'] ?? false) {
            $this->content = $this->htmlProcessor->cleanup($this->content);
        }
        return $this;
    }

    public function cleanup() : static {
        $this->content = $this->htmlProcessor->cleanup($this->content);
        return $this;
    }

    public function select(string $selector) : static {
        $this->content = $this->htmlProcessor->select($this->content, $selector);
        return $this;
    }

    /**
     * @param string $selector CSS selector
     * @param callable|null $callback Function to transform the selected item
     * @return Generator<\Cognesy\Auxiliary\Web\Webpage> a generator of Webpage objects
     */
    public function selectMany(string $selector, callable $callback = null, int $limit = 0) : Generator {
        $count = 0;
        foreach ($this->htmlProcessor->selectMany($this->content, $selector) as $html) {
            if ($limit > 0 && $count++ >= $limit) {
                break;
            }
            yield match($callback) {
                null => Webpage::withHtml($html, $this->url),
                default => $callback(Webpage::withHtml($html, $this->url)),
            };
        }
    }
}