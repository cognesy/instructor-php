<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Services;

use Cognesy\Doctools\Quality\Data\DocsQualityConfig;
use InvalidArgumentException;
use Symfony\Component\Filesystem\Path;

final readonly class RulesDiscovery
{
    public function __construct(
        private string $profilesDir = __DIR__ . '/../../../config/quality/profiles',
    ) {}

    /**
     * @return list<string>
     */
    public function discover(DocsQualityConfig $config): array
    {
        $paths = [];

        if (strtolower($config->profile) !== 'none') {
            $paths[] = $this->profilePath($config->profile);
        }

        foreach ($this->localRuleCandidates($config->docsRoot) as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }
            $paths[] = Path::canonicalize($candidate);
        }

        foreach ($config->ruleFiles as $path) {
            if (!is_file($path)) {
                throw new InvalidArgumentException("Rules file not found: {$path}");
            }
            $paths[] = Path::canonicalize($path);
        }

        return $this->unique($paths);
    }

    private function profilePath(string $profile): string
    {
        $normalized = strtolower(trim($profile));
        $file = Path::canonicalize(Path::join($this->profilesDir, "{$normalized}.yaml"));
        if (!is_file($file)) {
            throw new InvalidArgumentException("Unknown docs quality profile: {$profile}");
        }

        return $file;
    }

    /**
     * @return list<string>
     */
    private function localRuleCandidates(string $docsRoot): array
    {
        return [
            Path::join($docsRoot, '.qa', 'rules.yaml'),
            Path::join($docsRoot, '.qa', 'rules.yml'),
            Path::join($docsRoot, '.docs-quality.yaml'),
            Path::join($docsRoot, '.docs-quality.yml'),
            Path::join($docsRoot, '.docs-qa.yaml'),
            Path::join($docsRoot, '.docs-qa.yml'),
        ];
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private function unique(array $paths): array
    {
        $unique = [];
        foreach ($paths as $path) {
            $unique[$path] = true;
        }

        return array_keys($unique);
    }
}

