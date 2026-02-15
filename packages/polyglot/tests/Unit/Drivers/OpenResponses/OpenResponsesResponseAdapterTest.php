<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Unit\Drivers\OpenResponses;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesUsageFormat;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use PHPUnit\Framework\TestCase;

class OpenResponsesResponseAdapterTest extends TestCase
{
    private OpenResponsesResponseAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new OpenResponsesResponseAdapter(
            new OpenResponsesUsageFormat()
        );
    }

    private function makeHttpResponse(array $data): HttpResponse
    {
        return HttpResponse::sync(200, [], json_encode($data));
    }

    /**
     * Helper: send a single event body through fromStreamResponses and return the first result.
     */
    private function streamOne(string $eventBody): ?PartialInferenceResponse
    {
        foreach ($this->adapter->fromStreamResponses([$eventBody]) as $partial) {
            return $partial;
        }
        return null;
    }

    /**
     * Helper: send multiple event bodies through fromStreamResponses and return all results.
     * @param string[] $eventBodies
     * @return PartialInferenceResponse[]
     */
    private function streamAll(array $eventBodies): array
    {
        return iterator_to_array($this->adapter->fromStreamResponses($eventBodies), false);
    }

    public function test_parses_basic_response(): void
    {
        $responseData = [
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Hello! How can I help you?',
                        ],
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 8,
            ],
        ];

        $httpResponse = $this->makeHttpResponse($responseData);

        $result = $this->adapter->fromResponse($httpResponse);

        $this->assertEquals('Hello! How can I help you?', $result->content());
        $this->assertEquals('stop', $result->finishReason()->value);
        $this->assertEquals(10, $result->usage()->inputTokens);
        $this->assertEquals(8, $result->usage()->outputTokens);
    }

    public function test_parses_function_call_response(): void
    {
        $responseData = [
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'function_call',
                    'call_id' => 'call_abc',
                    'name' => 'get_weather',
                    'arguments' => '{"location": "NYC"}',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 20,
                'completion_tokens' => 15,
            ],
        ];

        $httpResponse = $this->makeHttpResponse($responseData);

        $result = $this->adapter->fromResponse($httpResponse);

        $this->assertTrue($result->hasToolCalls());
        $toolCalls = $result->toolCalls();
        $this->assertCount(1, $toolCalls->all());

        $toolCall = $toolCalls->first();
        $this->assertEquals('get_weather', $toolCall->name());
        $this->assertEquals('call_abc', $toolCall->id());
        $this->assertEquals('NYC', $toolCall->args()['location']);
    }

    public function test_parses_multiple_function_calls(): void
    {
        $responseData = [
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'function_call',
                    'call_id' => 'call_1',
                    'name' => 'get_weather',
                    'arguments' => '{"location": "NYC"}',
                ],
                [
                    'type' => 'function_call',
                    'call_id' => 'call_2',
                    'name' => 'get_time',
                    'arguments' => '{"timezone": "EST"}',
                ],
            ],
        ];

        $httpResponse = $this->makeHttpResponse($responseData);

        $result = $this->adapter->fromResponse($httpResponse);

        $toolCalls = $result->toolCalls();
        $this->assertCount(2, $toolCalls->all());
    }

    public function test_parses_reasoning_content(): void
    {
        $responseData = [
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'reasoning',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Let me think about this...',
                        ],
                    ],
                ],
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'The answer is 42.',
                        ],
                    ],
                ],
            ],
        ];

        $httpResponse = $this->makeHttpResponse($responseData);

        $result = $this->adapter->fromResponse($httpResponse);

        $this->assertEquals('The answer is 42.', $result->content());
        $this->assertEquals('Let me think about this...', $result->reasoningContent());
    }

    public function test_parses_reasoning_text_part_type(): void
    {
        $responseData = [
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'reasoning',
                    'content' => [
                        [
                            'type' => 'reasoning_text',
                            'text' => 'Reasoning trace',
                        ],
                    ],
                ],
            ],
        ];

        $httpResponse = $this->makeHttpResponse($responseData);

        $result = $this->adapter->fromResponse($httpResponse);

        $this->assertEquals('Reasoning trace', $result->reasoningContent());
    }

    public function test_maps_completed_status_to_stop(): void
    {
        $responseData = [
            'status' => 'completed',
            'output' => [],
        ];

        $httpResponse = $this->makeHttpResponse($responseData);

        $result = $this->adapter->fromResponse($httpResponse);
        $this->assertEquals('stop', $result->finishReason()->value);
    }

    public function test_maps_incomplete_status_to_length(): void
    {
        $responseData = [
            'status' => 'incomplete',
            'output' => [],
        ];

        $httpResponse = $this->makeHttpResponse($responseData);

        $result = $this->adapter->fromResponse($httpResponse);
        $this->assertEquals('length', $result->finishReason()->value);
    }

    public function test_maps_incomplete_content_filter_to_content_filter(): void
    {
        $responseData = [
            'status' => 'incomplete',
            'incomplete_details' => [
                'reason' => 'content_filter',
            ],
            'output' => [],
        ];

        $httpResponse = $this->makeHttpResponse($responseData);

        $result = $this->adapter->fromResponse($httpResponse);
        $this->assertEquals('content_filter', $result->finishReason()->value);
    }

    public function test_maps_failed_status_to_error(): void
    {
        $responseData = [
            'status' => 'failed',
            'output' => [],
        ];

        $httpResponse = $this->makeHttpResponse($responseData);

        $result = $this->adapter->fromResponse($httpResponse);
        $this->assertEquals('error', $result->finishReason()->value);
    }

    public function test_to_event_body_parses_data_prefix(): void
    {
        $data = 'data: {"type": "response.output_text.delta", "delta": "Hello"}';

        $result = $this->adapter->toEventBody($data);

        $this->assertEquals('{"type": "response.output_text.delta", "delta": "Hello"}', $result);
    }

    public function test_to_event_body_handles_done(): void
    {
        $data = 'data: [DONE]';

        $result = $this->adapter->toEventBody($data);

        $this->assertFalse($result);
    }

    public function test_to_event_body_skips_event_lines(): void
    {
        $data = 'event: response.output_text.delta';

        $result = $this->adapter->toEventBody($data);

        $this->assertEquals('', $result);
    }

    public function test_parses_stream_content_delta(): void
    {
        $eventBody = json_encode([
            'type' => 'response.output_text.delta',
            'delta' => 'Hello',
        ]);

        $result = $this->streamOne($eventBody);

        $this->assertEquals('Hello', $result->contentDelta);
    }

    public function test_parses_stream_reasoning_delta(): void
    {
        $eventBody = json_encode([
            'type' => 'response.reasoning_text.delta',
            'delta' => 'Thinking...',
        ]);

        $result = $this->streamOne($eventBody);

        $this->assertEquals('Thinking...', $result->reasoningContentDelta);
    }

    public function test_parses_stream_function_call_added(): void
    {
        $eventBody = json_encode([
            'type' => 'response.output_item.added',
            'item' => [
                'type' => 'function_call',
                'call_id' => 'call_xyz',
                'name' => 'get_weather',
            ],
        ]);

        $result = $this->streamOne($eventBody);

        $this->assertEquals('call_xyz', $result->toolId);
        $this->assertEquals('get_weather', $result->toolName);
    }

    public function test_parses_stream_function_call_args_delta(): void
    {
        $eventBody = json_encode([
            'type' => 'response.function_call_arguments.delta',
            'call_id' => 'call_xyz',
            'delta' => '{"loc',
        ]);

        $result = $this->streamOne($eventBody);

        $this->assertEquals('{"loc', $result->toolArgs);
    }

    public function test_parses_stream_completed_event(): void
    {
        $eventBody = json_encode([
            'type' => 'response.completed',
        ]);

        $result = $this->streamOne($eventBody);

        $this->assertEquals('stop', $result->finishReason());
    }

    public function test_parses_stream_failed_event(): void
    {
        $eventBody = json_encode([
            'type' => 'response.failed',
        ]);

        $result = $this->streamOne($eventBody);

        $this->assertEquals('error', $result->finishReason());
    }

    public function test_handles_null_event_body(): void
    {
        $result = $this->streamOne('');

        $this->assertNull($result);
    }

    public function test_handles_invalid_json(): void
    {
        $result = $this->streamOne('not json');

        $this->assertNull($result);
    }

    public function test_stream_function_call_done_with_item_id_preserves_call_id(): void
    {
        $eventBodies = [
            (string) json_encode([
                'type' => 'response.output_item.added',
                'item' => [
                    'type' => 'function_call',
                    'id' => 'item_1',
                    'call_id' => 'call_1',
                    'name' => 'get_weather',
                ],
            ]),
            (string) json_encode([
                'type' => 'response.function_call_arguments.delta',
                'item_id' => 'item_1',
                'delta' => '{"city":"Par',
            ]),
            (string) json_encode([
                'type' => 'response.function_call_arguments.done',
                'item_id' => 'item_1',
                'name' => 'get_weather',
                'arguments' => '{"city":"Paris"}',
            ]),
            (string) json_encode([
                'type' => 'response.completed',
                'response' => [
                    'status' => 'completed',
                ],
            ]),
        ];

        $prior = PartialInferenceResponse::empty();
        $last = null;

        foreach ($this->adapter->fromStreamResponses($eventBodies) as $partial) {
            $last = $partial->withAccumulatedContent($prior);
            $prior = $last;
        }

        $this->assertNotNull($last);
        $toolCalls = $last->toolCalls();
        $this->assertTrue($toolCalls->hasAny());
        $toolCall = $toolCalls->first();
        $this->assertEquals('call_1', $toolCall->id());
        $this->assertEquals('get_weather', $toolCall->name());
        $this->assertEquals('Paris', $toolCall->args()['city']);
    }

    public function test_stream_usage_is_extracted_from_nested_response(): void
    {
        $eventBody = (string) json_encode([
            'type' => 'response.completed',
            'response' => [
                'status' => 'completed',
                'usage' => [
                    'input_tokens' => 21,
                    'output_tokens' => 9,
                ],
            ],
        ]);

        $result = $this->streamOne($eventBody);
        $this->assertNotNull($result);
        $accumulated = $result->withAccumulatedContent(PartialInferenceResponse::empty());

        $this->assertEquals(21, $accumulated->usage()->inputTokens);
        $this->assertEquals(9, $accumulated->usage()->outputTokens);
    }

    public function test_parses_response_with_mixed_output_items(): void
    {
        $responseData = [
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'I will check the weather.',
                        ],
                    ],
                ],
                [
                    'type' => 'function_call',
                    'call_id' => 'call_abc',
                    'name' => 'get_weather',
                    'arguments' => '{"location": "NYC"}',
                ],
            ],
        ];

        $httpResponse = $this->makeHttpResponse($responseData);

        $result = $this->adapter->fromResponse($httpResponse);

        $this->assertEquals('I will check the weather.', $result->content());
        $this->assertTrue($result->hasToolCalls());
    }
}
