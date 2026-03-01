<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Data;

final readonly class DocsQualityConfig
{
    /**
     * @param list<string> $extensions
     * @param list<string> $ruleFiles
     */
    public function __construct(
        public string $docsRoot,
        public string $repoRoot,
        public string $profile,
        public array $extensions,
        public array $ruleFiles = [],
        public ?string $astGrepBin = null,
        public bool $strict = true,
        public string $format = 'text',
    ) {}
}
