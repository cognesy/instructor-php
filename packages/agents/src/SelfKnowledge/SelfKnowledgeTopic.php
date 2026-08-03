<?php declare(strict_types=1);

namespace Cognesy\Agents\SelfKnowledge;

use Cognesy\Agents\Collections\NameList;

final readonly class SelfKnowledgeTopic
{
    public function __construct(
        public string $topic,
        public NameList $files,
    ) {}

    /** @return array{topic: string, files: list<string>} */
    public function toArray(): array {
        return ['topic' => $this->topic, 'files' => $this->files->all()];
    }
}
