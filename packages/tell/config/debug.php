<?php

return [
    'defaultPreset' => 'off',

    'presets' => [
        'on' => [
            'httpEnabled' => true, // enable/disable debug
            'httpTrace' => false, // dump HTTP trace information (available for some clients - e.g. Guzzle)
            'httpRequestUrl' => true, // dump request URL to console
            'httpRequestHeaders' => false, // dump request headers to console
            'httpRequestBody' => true, // dump request body to console
            'httpResponseHeaders' => false, // dump response headers to console
            'httpResponseBody' => true, // dump response body to console
            'httpResponseStream' => true, // dump stream data to console
            'httpResponseStreamByLine' => true, // dump stream data as full lines (true) or as raw received chunks (false)
        ],

        'detailed' => [
            'httpEnabled' => true, // enable/disable debug
            'httpTrace' => true, // dump HTTP trace information (available for some clients - e.g. Guzzle)
            'httpRequestUrl' => true, // dump request URL to console
            'httpRequestHeaders' => true, // dump request headers to console
            'httpRequestBody' => true, // dump request body to console
            'httpResponseHeaders' => true, // dump response headers to console
            'httpResponseBody' => true, // dump response body to console
            'httpResponseStream' => true, // dump stream data to console
            'httpResponseStreamByLine' => false, // dump stream data as full lines (true) or as raw received chunks (false)
        ],

        'url-only' => [
            'httpEnabled' => true, // enable/disable debug
            'httpTrace' => false, // dump HTTP trace information (available for some clients - e.g. Guzzle)
            'httpRequestUrl' => true, // dump request URL to console
            'httpRequestHeaders' => false, // dump request headers to console
            'httpRequestBody' => false, // dump request body to console
            'httpResponseHeaders' => false, // dump response headers to console
            'httpResponseBody' => false, // dump response body to console
            'httpResponseStream' => false, // dump stream data to console
            'httpResponseStreamByLine' => false, // dump stream data as full lines (true) or as raw received chunks (false)
        ],

        'off' => [
            'httpEnabled' => false, // enable/disable debug
            'httpTrace' => false, // dump HTTP trace information (available for some clients - e.g. Guzzle)
            'httpRequestUrl' => false, // dump request URL to console
            'httpRequestHeaders' => false, // dump request headers to console
            'httpRequestBody' => false, // dump request body to console
            'httpResponseHeaders' => false, // dump response headers to console
            'httpResponseBody' => false, // dump response body to console
            'httpResponseStream' => false, // dump stream data to console
            'httpResponseStreamByLine' => false, // dump stream data as full lines (true) or as raw received chunks (false)
        ],
    ],
];
