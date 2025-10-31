<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Drivers\ReAct\Actions;

use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Utils\Json\Json;

final class MakeReActPrompt
{
    private Tools $tools;

    public function __construct(Tools $tools) {
        $this->tools = $tools;
    }

    public function __invoke() : string {
        $rules = $this->makeRules();
        $catalog = $this->makeCatalog($this->tools);
        return $this->makePrompt($rules, $catalog);
    }

    // INTERNAL /////////////////////////////////////////////////////////

    private function makePrompt(array $rules, array $catalog) : string {
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

    private function makeCatalog(Tools $tools) : array {
        $toolSchemas = $tools->toToolSchema();
        $toolList = $tools->descriptions();
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

    private function makeRules() : array {
         return [
            'You are a ReAct-style agent using Thought/Action/Observation steps.',
            'At each step, output strictly valid JSON that matches the provided schema.',
            'When you act, set type=call_tool and provide tool + args.',
            'When acting, args must be a JSON object whose keys match the selected tool parameters.',
            'Do not perform calculations yourself. Always use the tools to compute results.',
            'Continue calling tools as needed until the task is complete.',
            'Only when no more tool actions are required, set type=final_answer and provide the final answer.',
            'Keep the thought concise and actionable.',
            'Use only the available tools and their parameter schemas.',
        ];
    }
}
