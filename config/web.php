<?php

use Cognesy\Instructor\Utils\Env;

return [
    'defaultScraper' => 'none', // 'none' uses file_get_contents($url)

    'scrapers' => [
        'jinareader' => [
            'base_uri' => 'https://r.jina.ai/',
            'api_key' => Env::get('JINAREADER_API_KEY', ''),
        ],
        'scrapfly' => [
            'base_uri' => 'https://api.scrapfly.io/scrape',
            'api_key' => Env::get('SCRAPFLY_API_KEY', ''),
        ],
        'scrapingbee' => [
            'base_uri' => 'https://app.scrapingbee.com/api/v1/',
            'api_key' => Env::get('SCRAPINGBEE_API_KEY', ''),
        ],
    ]
];
