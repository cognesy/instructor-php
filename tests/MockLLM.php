<?php
namespace Tests;

use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Data\LLMApiResponse;
use Cognesy\Instructor\Extras\LLM\Drivers\OpenAIDriver;
use Mockery;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class MockLLM
{
    static public function get(array $args) : CanHandleInference {
        $mockLLM = Mockery::mock(OpenAIDriver::class);
        $mockResponse = Mockery::mock(ResponseInterface::class, StreamInterface::class, MessageInterface::class);
        $list = [];
        foreach ($args as $arg) {
            $list[] = self::makeFunc($arg);
        }
        //$mockLLM->shouldReceive('handle')->andReturnUsing(fn() => new OpenAIApiRequest());
        $mockLLM->shouldReceive('getData')->andReturn('');
        $mockLLM->shouldReceive('handle')->andReturn($mockResponse);
        $mockLLM->shouldReceive('getEndpointUrl')->andReturn('');
        $mockLLM->shouldReceive('getRequestHeaders')->andReturn([]);
        $mockLLM->shouldReceive('getRequestBody')->andReturnUsing([]);
        $mockLLM->shouldReceive('toApiResponse')->andReturnUsing(...$list);
        $mockLLM->shouldReceive('toPartialApiResponse')->andReturn($mockLLM);


        $mockResponse->shouldReceive('__toString')->andReturn('');
        $mockResponse->shouldReceive('close')->andReturn(null);
        $mockResponse->shouldReceive('detach')->andReturn(null);
        $mockResponse->shouldReceive('eof')->andReturn(true);
        $mockResponse->shouldReceive('getBody')->andReturn($mockResponse);
        $mockResponse->shouldReceive('getContents')->andReturn('');
        $mockResponse->shouldReceive('getHeader')->andReturn([]);
        $mockResponse->shouldReceive('getHeaderLine')->andReturn('');
        $mockResponse->shouldReceive('getHeaders')->andReturn([]);
        $mockResponse->shouldReceive('getMetadata')->andReturn([]);
        $mockResponse->shouldReceive('getProtocolVersion')->andReturn('1.1');
        $mockResponse->shouldReceive('getReasonPhrase')->andReturn('');
        $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockResponse->shouldReceive('getSize')->andReturn(0);
        $mockResponse->shouldReceive('hasHeader')->andReturn(false);
        $mockResponse->shouldReceive('isReadable')->andReturn(true);
        $mockResponse->shouldReceive('isSeekable')->andReturn(false);
        $mockResponse->shouldReceive('isWritable')->andReturn(false);
        $mockResponse->shouldReceive('read')->andReturn('');
        $mockResponse->shouldReceive('rewind')->andReturn(null);
        $mockResponse->shouldReceive('seek')->andReturn(null);
        $mockResponse->shouldReceive('tell')->andReturn(0);
        $mockResponse->shouldReceive('withAddedHeader')->andReturn($mockResponse);
        $mockResponse->shouldReceive('withBody')->andReturn($mockResponse);
        $mockResponse->shouldReceive('withHeader')->andReturn($mockResponse);
        $mockResponse->shouldReceive('withProtocolVersion')->andReturn($mockResponse);
        $mockResponse->shouldReceive('withStatus')->andReturn($mockResponse);
        $mockResponse->shouldReceive('withoutHeader')->andReturn($mockResponse);
        $mockResponse->shouldReceive('write')->andReturn(0);

        return $mockLLM;
    }

    static private function makeFunc(string $json) {
        return fn() => new LLMApiResponse(
            content: $json,
        );
    }
}
