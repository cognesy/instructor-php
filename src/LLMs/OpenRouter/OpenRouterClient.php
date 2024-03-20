<?php
namespace Cognesy\Instructor\LLMs\OpenRouter;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class OpenRouterClient
{
    static public int $connectTimeout = 3;
    static public int $timeout = 30;

    public static function getClient(): ClientInterface
    {
        $modifyResponseMiddleware = function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                $promise = $handler($request, $options);
                $isStreamEnabled = self::isStreamEnabled($options);
                $processor = match($isStreamEnabled) {
                    false => fn(ResponseInterface $response) => self::tryFixResponse($response),
                    default => fn(ResponseInterface $response) => self::passThrough($response),
                };
                return $promise->then($processor);
            };
        };
        $stack = HandlerStack::create();
        $stack->push($modifyResponseMiddleware);
        return new Client([
            'handler' => $stack,
            'connect_timeout' => self::$connectTimeout,
            'timeout' => self::$timeout,
        ]);
    }

    private static function tryFixResponse(ResponseInterface $response) : ResponseInterface {
        if (!self::isStreamResponse($response)) {
            return $response;
        }
        // try patching the response
        $body = trim($response->getBody()->getContents());
        try {
            $body = json_decode($body, true) ?? [];
        } catch (\Exception $e) {
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

    private static function passThrough(ResponseInterface $response) : ResponseInterface {
        // return stream as is
        return $response;
    }

    private static function isStreamEnabled(array $options) : bool {
        return isset($options['stream']) && ($options['stream'] === true);
    }

    private static function isStreamResponse(ResponseInterface $response) : bool {
        $body = $response->getBody();
        return $body instanceof Stream || $body instanceof StreamInterface;
    }
}
