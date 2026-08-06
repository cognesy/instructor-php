<?php declare(strict_types=1);

namespace Cognesy\Agents\SelfKnowledge;

use Cognesy\Agents\Collections\NameList;

final readonly class SelfKnowledgeTopics
{
    /** @var list<SelfKnowledgeTopic> */
    private array $topics;

    public function __construct(SelfKnowledgeTopic ...$topics) {
        $this->topics = $topics;
    }

    public static function agents(): self {
        return new self(
            new SelfKnowledgeTopic('package overview', new NameList('01-introduction.md')),
            new SelfKnowledgeTopic('getting started or a first agent', new NameList('02-basic-agent.md')),
            new SelfKnowledgeTopic('core concepts, state, or steps', new NameList('03-basic-concepts.md')),
            new SelfKnowledgeTopic('loop control, continuation, or stop conditions', new NameList('04-controlling-the-loop.md', '09-stop-conditions.md')),
            new SelfKnowledgeTopic('using tools', new NameList('05-tools.md')),
            new SelfKnowledgeTopic('writing tools', new NameList('06-building-tools.md', '17-building-tools-advanced.md')),
            new SelfKnowledgeTopic('context or compilers', new NameList('07-context-and-compilers.md')),
            new SelfKnowledgeTopic('hooks or interception', new NameList('08-hooks.md')),
            new SelfKnowledgeTopic('testing agents', new NameList('10-testing.md', 'testing-doubles.md')),
            new SelfKnowledgeTopic('state internals', new NameList('11-state-internals.md')),
            new SelfKnowledgeTopic('tool-calling internals', new NameList('12-tool-calling-internals.md')),
            new SelfKnowledgeTopic('builder or capabilities', new NameList('13-agent-builder.md')),
            new SelfKnowledgeTopic('agent templates or definitions', new NameList('14-agent-templates.md')),
            new SelfKnowledgeTopic('subagents', new NameList('15-subagents.md')),
            new SelfKnowledgeTopic('sessions or persistence', new NameList('16-session-runtime.md')),
            new SelfKnowledgeTopic('events or observability', new NameList('18-observing-agent-execution.md')),
            new SelfKnowledgeTopic('skills', new NameList('19-skills.md')),
            new SelfKnowledgeTopic('self-knowledge or installed package documentation', new NameList('20-self-knowledge.md')),
            new SelfKnowledgeTopic('evaluating agent behavior or model-graded testing', new NameList('21-evals.md')),
            new SelfKnowledgeTopic('deterministic eval assertions or trajectory checks', new NameList('22-eval-assertions.md')),
            new SelfKnowledgeTopic('semantic judging of agent behavior or LLM-as-judge evals', new NameList('23-eval-judges.md')),
            new SelfKnowledgeTopic('eval trace policy, artifacts on disk, or provenance and cost reporting', new NameList('24-eval-traces-and-artifacts.md')),
            new SelfKnowledgeTopic('running evals from the CLI, repetition and pass rates, eval exit codes, or PHPUnit and Pest integration', new NameList('25-running-evals.md')),
        );
    }

    /** @return list<SelfKnowledgeTopic> */
    public function all(): array {
        return $this->topics;
    }

    /** @return list<string> */
    public function files(): array {
        $files = [];
        foreach ($this->topics as $topic) {
            $files = [...$files, ...$topic->files->all()];
        }
        return array_values(array_unique($files));
    }

    /** @return list<array{topic: string, files: list<string>}> */
    public function toArray(): array {
        return array_map(
            static fn (SelfKnowledgeTopic $topic): array => $topic->toArray(),
            $this->topics,
        );
    }
}
