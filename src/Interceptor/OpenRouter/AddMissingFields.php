<?php

namespace Cognesy\Instructor\Interceptor\OpenRouter;

use Cognesy\Instructor\Contracts\CanPreprocessResponse;
use Exception;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

class AddMissingFields implements CanPreprocessResponse
{
    public function process(ResponseInterface $response) : ResponseInterface
    {
        // try patching the response
        $body = trim($response->getBody()->getContents());
        try {
            $body = json_decode($body, true) ?? [];
        } catch (Exception $e) {
            return $response;
        }
        if (!isset($body['choices'][0])) {
            return $response;
        }
        if (!isset($body['choices'][0]['index'])) {
            $body['choices'][0]['index'] = 0;
        }
        if (!isset($body['choices'][0]['finish_reason'])) {
            $body['choices'][0]['finish_reason'] = 'stop';
        }
        $modifiedBody = json_encode($body) ?? '';
        $stream = Utils::streamFor($modifiedBody);
        return $response->withBody($stream);
    }
}