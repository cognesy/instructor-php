<?php declare(strict_types=1);

use Cognesy\Auxiliary\Web\Config\ScraperConfig;
use Cognesy\Auxiliary\Web\Scraper;
use Cognesy\Auxiliary\Web\Scrapers\BasicReader;
use Cognesy\Auxiliary\Web\Scrapers\FirecrawlDriver;

it('uses injected scraper config default without static settings', function () {
    $config = ScraperConfig::fromArray([
        'defaultScraper' => 'firecrawl',
        'scrapers' => [
            'firecrawl' => [
                'baseUri' => 'https://api.firecrawl.dev/v1/scrape',
                'apiKey' => 'test-key',
            ],
        ],
    ]);

    $driver = Scraper::fromDriver(config: $config);

    expect($driver)->toBeInstanceOf(FirecrawlDriver::class);
});

it('allows explicit scraper selection over injected default', function () {
    $config = ScraperConfig::fromArray([
        'defaultScraper' => 'firecrawl',
        'scrapers' => [
            'firecrawl' => [
                'baseUri' => 'https://api.firecrawl.dev/v1/scrape',
                'apiKey' => 'test-key',
            ],
        ],
    ]);

    $driver = Scraper::fromDriver('none', $config);

    expect($driver)->toBeInstanceOf(BasicReader::class);
});

it('throws on unknown scraper name', function () {
    expect(fn() => Scraper::fromDriver('unknown-driver', new ScraperConfig()))
        ->toThrow(Exception::class, 'Unknown scraper requested: unknown-driver');
});

it('fails fast on invalid typed scraper config data', function () {
    expect(fn() => ScraperConfig::fromArray([
        'defaultScraper' => 'firecrawl',
        'scrapers' => [
            'firecrawl' => 'invalid',
        ],
    ]))
        ->toThrow(\InvalidArgumentException::class);
});
