<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Drivers\Ooda\Actions;

use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Utils\Json\Json;

/**
 * Builds OODA loop system prompt.
 */
final class MakeOodaPrompt
{
    public function __construct(
        private Tools $tools,
    ) {}

    public function __invoke(): string {
        $rules = $this->makeRules();
        $catalog = $this->makeCatalog();
        return $this->makePrompt($rules, $catalog);
    }

    private function makePrompt(array $rules, array $catalog): string {
        return implode("\n", [
            'INSTRUCTIONS:',
            ...$rules,
            '',
            'TOOL CATALOG (names, descriptions):',
            Json::encode($catalog['tools']),
            '',
            'TOOL SCHEMAS (name, parameters):',
            Json::encode($catalog['schemas']),
        ]);
    }

    private function makeCatalog(): array {
        $toolSchemas = $this->tools->toToolSchema();
        $toolList = $this->tools->descriptions();
        return [
            'tools' => $toolList,
            'schemas' => array_map(
                fn(array $item) => [
                    'name' => $item['function']['name'] ?? '',
                    'parameters' => $item['function']['parameters'] ?? [],
                ],
                $toolSchemas,
            ),
        ];
    }

    private function makeRules(): array {
        return [
            'You are an OODA-loop agent that follows a structured decision-making process.',
            'At each step, you must complete ALL FOUR PHASES in order:',
            '',
            '## OBSERVE - Assess the current situation',
            '- State the goal clearly',
            '- Summarize what you know now (current state)',
            '- Note any results from previous actions',
            '- Identify obstacles or challenges',
            '- Estimate progress toward the goal (0-100%)',
            '',
            '## ORIENT - Analyze and evaluate options',
            '- Analyze the situation based on observations',
            '- List possible next actions (options)',
            '- Consider how each option helps overcome obstacles',
            '- Reason about which approach is best',
            '',
            '## DECIDE - Choose the best action',
            '- Set type=call_tool to use a tool, or type=final_answer when done',
            '- Rate your confidence in this decision (0-100%)',
            '',
            '## ACT - Execute the decision',
            '- If type=call_tool: set "tool" to the tool name (e.g. "search_files") and "args" to a JSON object of parameters (e.g. {"pattern": "*.php"})',
            '- If type=final_answer: set "answer" to the complete answer text',
            '',
            'IMPORTANT:',
            '- Always complete all four phases before acting',
            '- Be thorough in OBSERVE and ORIENT phases - they inform better decisions',
            '- Only set type=final_answer when the goal is fully achieved',
            '- Output strictly valid JSON matching the provided schema',
            '- Use only the available tools listed below',
        ];
    }
}
