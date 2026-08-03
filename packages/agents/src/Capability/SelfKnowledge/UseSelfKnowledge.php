<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\SelfKnowledge;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Profile\AgentProfile;
use Cognesy\Agents\Profile\Contracts\CanContributeToAgentProfile;
use Cognesy\Agents\Profile\ToolProfile;
use Cognesy\Agents\SelfKnowledge\PackageDocs;
use Cognesy\Agents\SelfKnowledge\SelfKnowledgeSection;
use Cognesy\Agents\SelfKnowledge\SelfKnowledgeTopics;
use Override;

final readonly class UseSelfKnowledge implements CanProvideAgentCapability, CanContributeToAgentProfile
{
    private const SECTION_PREFIX = 'Instructor Agents documentation (';

    public function __construct(
        private bool $requireReadTool = true,
        private ?PackageDocs $docs = null,
    ) {}

    #[Override]
    public static function capabilityName(): string {
        return 'use_self_knowledge';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent;
    }

    #[Override]
    public function contributeToAgentProfile(AgentProfile $profile): AgentProfile {
        $profile = $this->withoutSelfKnowledge($profile);
        $docs = $this->docs ?? PackageDocs::installed();
        $topics = SelfKnowledgeTopics::agents();
        if (!$this->canContribute($profile, $docs, $topics)) {
            return $profile;
        }

        $section = SelfKnowledgeSection::fromPackageDocs($docs, $topics);
        $metadata = $profile->metadata
            ->withKeyValue('selfKnowledge', $section->toArray())
            ->withKeyValue('prompt_sections', [
                ...$this->promptSections($profile),
                $section->render(),
            ]);
        return $profile->withMetadata($metadata);
    }

    private function canContribute(
        AgentProfile $profile,
        PackageDocs $docs,
        SelfKnowledgeTopics $topics,
    ): bool {
        if (!$docs->exists() || !$this->allTopicsExist($docs, $topics)) {
            return false;
        }
        return !$this->requireReadTool || $this->hasReadTool($profile);
    }

    private function hasReadTool(AgentProfile $profile): bool {
        foreach ($profile->tools->all() as $tool) {
            if ($this->isReadTool($tool)) {
                return true;
            }
        }
        return false;
    }

    private function isReadTool(ToolProfile $tool): bool {
        if (in_array($tool->name, ['read', 'read_file'], true)) {
            return true;
        }
        $tags = $tool->metadata['tags'] ?? [];
        return is_array($tags)
            && in_array('file', $tags, true)
            && in_array('read', $tags, true);
    }

    private function allTopicsExist(PackageDocs $docs, SelfKnowledgeTopics $topics): bool {
        foreach ($topics->files() as $file) {
            if (!is_file($docs->docsPath() . '/' . $file)) {
                return false;
            }
        }
        return true;
    }

    private function withoutSelfKnowledge(AgentProfile $profile): AgentProfile {
        $sections = $this->promptSections($profile);
        $metadata = $profile->metadata
            ->withoutKey('selfKnowledge')
            ->withoutKey('prompt_sections');
        if ($sections !== []) {
            $metadata = $metadata->withKeyValue('prompt_sections', $sections);
        }
        return $profile->withMetadata($metadata);
    }

    /** @return list<string> */
    private function promptSections(AgentProfile $profile): array {
        $sections = $profile->metadata->get('prompt_sections', []);
        if (!is_array($sections)) {
            return [];
        }
        return array_values(array_filter(
            $sections,
            static fn (mixed $section): bool => is_string($section)
                && !str_starts_with($section, self::SECTION_PREFIX),
        ));
    }
}
