<?php

return [
    'http' => [
        'enabled' => false, // enable/disable debug
        'trace' => false, // dump HTTP trace information (available for some clients - e.g. Guzzle)
        'requestUrl' => true, // dump request URL to console
        'requestHeaders' => false, // dump request headers to console
        'requestBody' => false, // dump request body to console
        'responseHeaders' => false, // dump response headers to console
        'responseBody' => false, // dump response body to console
        'responseStream' => false, // dump stream data to console
        'responseStreamByLine' => false, // dump stream data as full lines (true) or as raw received chunks (false)
    ],
];
