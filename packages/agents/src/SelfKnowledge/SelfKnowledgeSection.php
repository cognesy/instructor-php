<?php declare(strict_types=1);

namespace Cognesy\Agents\SelfKnowledge;

final readonly class SelfKnowledgeSection
{
    public function __construct(
        public string $readmePath,
        public string $docsPath,
        public SelfKnowledgeTopics $topics,
        public ?string $examplesPath = null,
    ) {}

    public static function fromPackageDocs(PackageDocs $docs, SelfKnowledgeTopics $topics): self {
        return new self(
            readmePath: $docs->readmePath(),
            docsPath: $docs->docsPath(),
            topics: $topics,
            examplesPath: $docs->examplesPath(),
        );
    }

    public function render(): string {
        $lines = [
            'Instructor Agents documentation (read only when the user asks about the agent toolkit itself — its capabilities, hooks, templates, sessions, or tool authoring):',
            '- Main documentation: ' . $this->readmePath,
            '- Additional docs: ' . $this->docsPath,
        ];
        if ($this->examplesPath !== null) {
            $lines[] = '- Examples: ' . $this->examplesPath;
        }
        $lines[] = '- Resolve docs/... under Additional docs, not the current working directory';
        foreach ($this->topics->all() as $topic) {
            $lines[] = sprintf(
                '- When asked about %s: %s',
                $topic->topic,
                implode(', ', $topic->files->all()),
            );
        }
        $lines[] = '- Read the referenced .md files completely and follow cross-references before implementing';
        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'readmePath' => $this->readmePath,
            'docsPath' => $this->docsPath,
            'examplesPath' => $this->examplesPath,
            'topics' => $this->topics->toArray(),
        ];
    }
}
