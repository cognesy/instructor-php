<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

final readonly class SystemPromptComposer
{
    /** @param list<string> $extraGuidelines */
    public function __construct(
        private ?string $preamble = null,
        private array $extraGuidelines = [],
        private ?string $append = null,
    ) {}

    public function compose(AgentProfile $profile): string {
        $sections = [];
        $this->appendText($sections, $this->preamble);
        $sections[] = $this->toolsSection($profile->tools);
        $sections[] = $this->guidelinesSection($profile->tools);

        foreach ($this->profileSections($profile) as $section) {
            $this->appendText($sections, $section);
        }
        $this->appendText($sections, $this->append);

        return implode("\n\n", $sections);
    }

    private function toolsSection(ToolProfileList $tools): string {
        $lines = ['Available tools:'];
        $visible = $tools->visible();
        if ($visible === []) {
            $lines[] = '(none)';
        }
        foreach ($visible as $tool) {
            $lines[] = sprintf('- %s: %s', $tool->name, $tool->promptSnippet);
        }
        $lines[] = '';
        $lines[] = 'In addition to the tools above, you may have access to other custom tools depending on the project.';
        return implode("\n", $lines);
    }

    private function guidelinesSection(ToolProfileList $tools): string {
        $lines = ['Guidelines:'];
        foreach (PromptGuidelines::collect($tools, $this->extraGuidelines) as $guideline) {
            $lines[] = '- ' . $guideline;
        }
        return implode("\n", $lines);
    }

    /** @return list<string> */
    private function profileSections(AgentProfile $profile): array {
        $sections = $profile->metadata->get('prompt_sections', []);
        if (!is_array($sections)) {
            return [];
        }
        return array_values(array_filter($sections, 'is_string'));
    }

    /** @param list<string> $sections */
    private function appendText(array &$sections, ?string $text): void {
        $trimmed = trim($text ?? '');
        if ($trimmed === '') {
            return;
        }
        $sections[] = $trimmed;
    }
}
