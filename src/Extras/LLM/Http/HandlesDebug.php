<?php
namespace Cognesy\Instructor\Extras\LLM\Http;

use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

trait HandlesDebug
{
    protected function addDebugStack(HandlerStack $stack) : HandlerStack {
        $stack->push(Middleware::tap(
            function (RequestInterface $request, $options) {
                $highlight = [Color::YELLOW];
                Console::println("[REQUEST]", $highlight);
                if ($this->config->debugSection('requestHeaders')) {
                    Console::println("[REQUEST HEADERS]", $highlight);
                    $this->printHeaders($request->getHeaders());
                    Console::println("[/REQUEST HEADERS]", $highlight);
                }
                if ($this->config->debugSection('requestBody')) {
                    Console::println("[REQUEST BODY]", $highlight);
                    dump(json_decode((string) $request->getBody()));
                    Console::println("[/REQUEST BODY]", $highlight);
                }
                Console::println("[/REQUEST]", $highlight);
                Console::println("");
                if ($this->config->debugHttpDetails()) {
                    Console::println("[HTTP DEBUG]", $highlight);
                }
            },
            function ($request, $options, FulfilledPromise|RejectedPromise $response) {
                $response->then(function (ResponseInterface $response) {
                    $highlight = [Color::YELLOW];
                    if ($this->config->debugHttpDetails()) {
                        Console::println("[/HTTP DEBUG]", $highlight);
                        Console::println("");
                    }
                    Console::println("[RESPONSE]", $highlight);
                    if ($this->config->debugSection('responseHeaders')) {
                        Console::println("[RESPONSE HEADERS]", $highlight);
                        $this->printHeaders($response->getHeaders());
                        Console::println("[/RESPONSE HEADERS]", $highlight);
                    }
//                    if ($this->config->debugSection('responseBody')) {
//                        Console::println("[RESPONSE BODY]", $highlight);
//                        dump(json_decode((string) $response->getBody()));
//                        $response->getBody()->seek(0);
//                        Console::println("[/RESPONSE BODY]", $highlight);
//                    }
                    Console::println("[/RESPONSE]", $highlight);
//                    $response->getBody()->seek(0);
                });
            })
        );
        return $stack;
    }

    private function printHeaders(array $headers) {
        foreach ($headers as $name => $values) {
            Console::print("   ".$name, [Color::DARK_GRAY]);
            Console::print(': ', [Color::WHITE]);
            Console::println(implode(' | ', $values), [Color::GRAY]);
        }
    }
}