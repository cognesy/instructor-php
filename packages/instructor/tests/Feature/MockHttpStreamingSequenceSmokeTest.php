<?php declare(strict_types=1);

use Cognesy\Http\HttpClientBuilder;
use Cognesy\Instructor\Extras\Sequence\Sequence as SequenceModel;
use Cognesy\Instructor\StructuredOutput;
use PHPUnit\Framework\TestCase;

final class MockHttpStreamingSequenceSmokeTest extends TestCase
{
    public function testStreamingSequenceAggregation(): void
    {
        // Item class for sequence elements
        if (!class_exists('SeqItem')) {
            eval('class SeqItem { public int $x; }');
        }

        // SSE payloads produce JSON: {"list":[{"x":1},{"x":2}]}
        $payloads = [
            ['choices' => [['delta' => ['content' => '{"list":[{"x":1}']]]],
            ['choices' => [['delta' => ['content' => ',{"x":2}]']]]],
        ];

        $http = (new HttpClientBuilder())
            ->withMock(function ($mock) use ($payloads) {
                $mock->on()
                    ->post('https://api.openai.com/v1/chat/completions')
                    ->withStream(true)
                    ->withJsonSubset(['model' => 'gpt-4o-mini', 'stream' => true])
                    ->replySSEFromJson($payloads);
            })
            ->create();

        $sequenceModel = SequenceModel::of('SeqItem');

        $stream = (new StructuredOutput)
            ->withHttpClient($http)
            ->using('openai')
            ->with(
                messages: 'Extract list of items',
                responseModel: $sequenceModel,
                model: 'gpt-4o-mini',
            )
            ->withStreaming(true)
            ->stream();

        // Collect sequence updates; should yield progressively
        $updates = iterator_to_array($stream->sequence());
        $this->assertNotEmpty($updates);
        $last = end($updates);
        $this->assertInstanceOf(SequenceModel::class, $last);
        $this->assertSame(2, $last->count());
        $this->assertSame(1, $last->get(0)->x);
        $this->assertSame(2, $last->get(1)->x);
    }
}
