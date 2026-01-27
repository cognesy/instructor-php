<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Unit\Drivers\OpenResponses;

use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesUsageFormat;
use PHPUnit\Framework\TestCase;

class OpenResponsesUsageFormatTest extends TestCase
{
    private OpenResponsesUsageFormat $usageFormat;

    protected function setUp(): void
    {
        $this->usageFormat = new OpenResponsesUsageFormat();
    }

    public function test_parses_standard_usage_format(): void
    {
        $data = [
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
            ],
        ];

        $usage = $this->usageFormat->fromData($data);

        $this->assertEquals(100, $usage->inputTokens);
        $this->assertEquals(50, $usage->outputTokens);
    }

    public function test_parses_alternative_token_names(): void
    {
        $data = [
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ];

        $usage = $this->usageFormat->fromData($data);

        $this->assertEquals(100, $usage->inputTokens);
        $this->assertEquals(50, $usage->outputTokens);
    }

    public function test_parses_cached_tokens(): void
    {
        $data = [
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'prompt_tokens_details' => [
                    'cached_tokens' => 30,
                ],
            ],
        ];

        $usage = $this->usageFormat->fromData($data);

        $this->assertEquals(30, $usage->cacheReadTokens);
    }

    public function test_parses_reasoning_tokens(): void
    {
        $data = [
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'completion_tokens_details' => [
                    'reasoning_tokens' => 20,
                ],
            ],
        ];

        $usage = $this->usageFormat->fromData($data);

        $this->assertEquals(20, $usage->reasoningTokens);
    }

    public function test_parses_nested_response_usage(): void
    {
        $data = [
            'response' => [
                'usage' => [
                    'input_tokens' => 12,
                    'output_tokens' => 7,
                ],
            ],
        ];

        $usage = $this->usageFormat->fromData($data);

        $this->assertEquals(12, $usage->inputTokens);
        $this->assertEquals(7, $usage->outputTokens);
    }

    public function test_parses_new_detail_token_names(): void
    {
        $data = [
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 40,
                'input_tokens_details' => [
                    'cached_tokens' => 25,
                ],
                'output_tokens_details' => [
                    'reasoning_tokens' => 15,
                ],
            ],
        ];

        $usage = $this->usageFormat->fromData($data);

        $this->assertEquals(25, $usage->cacheReadTokens);
        $this->assertEquals(15, $usage->reasoningTokens);
    }

    public function test_handles_missing_usage(): void
    {
        $data = [];

        $usage = $this->usageFormat->fromData($data);

        $this->assertEquals(0, $usage->inputTokens);
        $this->assertEquals(0, $usage->outputTokens);
    }

    public function test_handles_partial_usage(): void
    {
        $data = [
            'usage' => [
                'prompt_tokens' => 100,
            ],
        ];

        $usage = $this->usageFormat->fromData($data);

        $this->assertEquals(100, $usage->inputTokens);
        $this->assertEquals(0, $usage->outputTokens);
    }

    public function test_prefers_standard_names_over_alternative(): void
    {
        $data = [
            'usage' => [
                'prompt_tokens' => 100,
                'input_tokens' => 50, // Should be ignored
                'completion_tokens' => 75,
                'output_tokens' => 25, // Should be ignored
            ],
        ];

        $usage = $this->usageFormat->fromData($data);

        $this->assertEquals(100, $usage->inputTokens);
        $this->assertEquals(75, $usage->outputTokens);
    }
}
