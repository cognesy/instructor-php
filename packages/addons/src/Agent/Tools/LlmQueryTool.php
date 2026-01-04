<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools;

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;

/**
 * LlmQueryTool - Send a query to the LLM and get a response.
 *
 * Use this tool when the agent needs to:
 * - Answer knowledge questions
 * - Perform reasoning or analysis
 * - Generate text or explanations
 * - Think through a problem step by step
 */
class LlmQueryTool extends BaseTool
{
    private LLMProvider $llm;
    private string $model;
    private ?string $systemPrompt;

    public function __construct(
        ?LLMProvider $llm = null,
        string $model = '',
        ?string $systemPrompt = null,
    ) {
        parent::__construct(
            name: 'llm_query',
            description: 'Send a query to the LLM to answer questions, perform reasoning, or generate text. Use for knowledge questions, analysis, or when you need to think through a problem.',
        );
        $this->llm = $llm ?? LLMProvider::new();
        $this->model = $model;
        $this->systemPrompt = $systemPrompt;
    }

    public static function using(string $preset): self {
        return new self(llm: LLMProvider::using($preset));
    }

    public static function withModel(string $model): self {
        return new self(model: $model);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $query = $args['query'] ?? $args[0] ?? '';

        if (empty($query)) {
            return 'Error: query is required';
        }

        $messages = [];
        if ($this->systemPrompt !== null) {
            $messages[] = ['role' => 'system', 'content' => $this->systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $query];

        try {
            $response = (new Inference)
                ->withLLMProvider($this->llm)
                ->withMessages($messages)
                ->withModel($this->model)
                ->create()
                ->response();

            return $response->content() ?: 'No response from LLM';
        } catch (\Throwable $e) {
            return 'Error querying LLM: ' . $e->getMessage();
        }
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The question or task for the LLM to answer or perform',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }
}
