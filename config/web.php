<?php

use Cognesy\Instructor\Utils\Env;

return [
    'defaultScraper' => 'none',

    'scrapers' => [
        'jinareader' => [
            'api_key' => Env::get('JINAREADER_API_KEY', ''),
            'base_uri' => Env::get('JINAREADER_BASE_URI', ''),
        ],
        'scrapfly' => [
            'api_key' => Env::get('SCRAPFLY_API_KEY', ''),
            'base_uri' => Env::get('SCRAPFLY_BASE_URI', ''),
        ],
        'scrapingbee' => [
            'api_key' => Env::get('SCRAPINGBEE_API_KEY', ''),
            'base_uri' => Env::get('SCRAPINGBEE_BASE_URI', ''),
        ],
    ]
];