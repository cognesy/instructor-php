<?php

return [
    'defaultPreset' => 'off',

    'presets' => [
        'on' => [
            'http_enabled' => true, // enable/disable debug
            'http_trace' => false, // dump HTTP trace information (available for some clients - e.g. Guzzle)
            'http_requestUrl' => true, // dump request URL to console
            'http_requestHeaders' => false, // dump request headers to console
            'http_requestBody' => true, // dump request body to console
            'http_responseHeaders' => false, // dump response headers to console
            'http_responseBody' => true, // dump response body to console
            'http_responseStream' => true, // dump stream data to console
            'http_responseStreamByLine' => true, // dump stream data as full lines (true) or as raw received chunks (false)
        ],

        'detailed' => [
            'http_enabled' => true, // enable/disable debug
            'http_trace' => true, // dump HTTP trace information (available for some clients - e.g. Guzzle)
            'http_requestUrl' => true, // dump request URL to console
            'http_requestHeaders' => true, // dump request headers to console
            'http_requestBody' => true, // dump request body to console
            'http_responseHeaders' => true, // dump response headers to console
            'http_responseBody' => true, // dump response body to console
            'http_responseStream' => true, // dump stream data to console
            'http_responseStreamByLine' => false, // dump stream data as full lines (true) or as raw received chunks (false)
        ],

        'off' => [
            'http_enabled' => false, // enable/disable debug
            'http_trace' => false, // dump HTTP trace information (available for some clients - e.g. Guzzle)
            'http_requestUrl' => true, // dump request URL to console
            'http_requestHeaders' => true, // dump request headers to console
            'http_requestBody' => true, // dump request body to console
            'http_responseHeaders' => true, // dump response headers to console
            'http_responseBody' => true, // dump response body to console
            'http_responseStream' => true, // dump stream data to console
            'http_responseStreamByLine' => true, // dump stream data as full lines (true) or as raw received chunks (false)
        ],
    ],
];
