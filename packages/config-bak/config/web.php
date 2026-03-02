<?php

use Cognesy\Config\Env;

return [
    'defaultScraper' => 'none', // 'none' uses file_get_contents($url)

    'scrapers' => [
        'firecrawl' => [
            'baseUri' => 'https://api.firecrawl.dev/v1/scrape',
            'apiKey' => Env::get('FIRECRAWL_API_KEY', ''),
        ],
        'jinareader' => [
            'baseUri' => 'https://r.jina.ai/',
            'apiKey' => Env::get('JINAREADER_API_KEY', ''),
        ],
        'scrapfly' => [
            'baseUri' => 'https://api.scrapfly.io/scrape',
            'apiKey' => Env::get('SCRAPFLY_API_KEY', ''),
        ],
        'scrapingbee' => [
            'baseUri' => 'https://app.scrapingbee.com/api/v1/',
            'apiKey' => Env::get('SCRAPINGBEE_API_KEY', ''),
        ],
    ]
];
