<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Data;

readonly class DocumentationConfig
{
    public function __construct(
        public string $docsSourceDir,
        public string $docsTargetDir,
        public string $cookbookTargetDir,
        public string $mintlifySourceIndexFile,
        public string $mintlifyTargetIndexFile,
        public string $codeblocksDir,
        public array $dynamicGroups,
    ) {}

    public static function create(
        string $docsSourceDir,
        string $docsTargetDir,
        string $cookbookTargetDir,
        string $mintlifySourceIndexFile,
        string $mintlifyTargetIndexFile,
        string $codeblocksDir,
        array $dynamicGroups = [],
    ): self {
        return new self(
            docsSourceDir: $docsSourceDir,
            docsTargetDir: $docsTargetDir,
            cookbookTargetDir: $cookbookTargetDir,
            mintlifySourceIndexFile: $mintlifySourceIndexFile,
            mintlifyTargetIndexFile: $mintlifyTargetIndexFile,
            codeblocksDir: $codeblocksDir,
            dynamicGroups: $dynamicGroups,
        );
    }
}