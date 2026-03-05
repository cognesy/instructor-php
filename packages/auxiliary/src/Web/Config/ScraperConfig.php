<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Config;

use InvalidArgumentException;

final readonly class ScraperConfig
{
    /**
     * @param array<string, array{baseUri?: string, apiKey?: string}> $scrapers
     */
    public function __construct(
        public string $defaultScraper = 'none',
        public array $scrapers = [],
    ) {}

    public static function fromArray(array $data): self {
        $defaultScraper = $data['defaultScraper'] ?? 'none';
        if (!is_string($defaultScraper) || $defaultScraper === '') {
            throw new InvalidArgumentException('Invalid defaultScraper value in ScraperConfig');
        }

        $scrapers = $data['scrapers'] ?? [];
        if (!is_array($scrapers)) {
            throw new InvalidArgumentException('Invalid scrapers value in ScraperConfig');
        }

        $normalized = [];
        foreach ($scrapers as $name => $node) {
            if (!is_string($name) || $name === '' || !is_array($node)) {
                throw new InvalidArgumentException('Invalid scraper node in ScraperConfig');
            }
            $baseUri = $node['baseUri'] ?? '';
            $apiKey = $node['apiKey'] ?? '';
            if (!is_string($baseUri) || !is_string($apiKey)) {
                throw new InvalidArgumentException("Invalid scraper credentials for '{$name}' in ScraperConfig");
            }
            $normalized[$name] = [
                'baseUri' => $baseUri,
                'apiKey' => $apiKey,
            ];
        }

        return new self(
            defaultScraper: $defaultScraper,
            scrapers: $normalized,
        );
    }

    public function selectedScraper(string $requested = ''): string {
        return $requested !== '' ? $requested : $this->defaultScraper;
    }

    public function baseUriFor(string $scraper): string {
        return $this->scrapers[$scraper]['baseUri'] ?? '';
    }

    public function apiKeyFor(string $scraper): string {
        return $this->scrapers[$scraper]['apiKey'] ?? '';
    }
}

