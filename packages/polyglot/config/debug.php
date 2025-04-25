<?php
return [
    'http' => [
        'enabled' => false, // enable/disable debug
        'trace' => false, // dump HTTP trace information (available for some clients - e.g. Guzzle)
        'requestUrl' => true, // dump request URL to console
        'requestHeaders' => true, // dump request headers to console
        'requestBody' => true, // dump request body to console
        'responseHeaders' => true, // dump response headers to console
        'responseBody' => true, // dump response body to console
        'responseStream' => true, // dump stream data to console
        'responseStreamByLine' => true, // dump stream data as full lines (true) or as raw received chunks (false)
    ],
];
