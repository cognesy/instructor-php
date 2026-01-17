<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Data;

use Cognesy\Config\BasePath;
use Symfony\Component\Yaml\Yaml;

readonly class DocsConfig
{
    public function __construct(
        // Main section
        public string $mainTitle,
        public string $mainSource,
        public array $mainPages,
        // Packages section
        public string $packagesSourcePattern,
        public array $packageDescriptions,
        public array $packageTargetDirs,
        public array $packageOrder,
        public array $packageInternal,
        // Examples section
        public string $examplesSource,
        public array $examplesIntroPages,
        // Changelog section
        public string $changelogSource,
        // Output targets
        public string $mintlifyTarget,
        public string $mintlifySourceIndex,
        public string $mkdocsTarget,
        public string $mkdocsTemplate,
        // LLMs documentation
        public bool $llmsEnabled = true,
        public string $llmsIndexFile = 'llms.txt',
        public string $llmsFullFile = 'llms-full.txt',
        public array $llmsExcludeSections = ['release-notes/'],
        public string $llmsDeployTarget = '',
        public string $llmsDeployDocsFolder = 'docs',
        public string $llmsProjectDescription = 'Structured data extraction in PHP, powered by LLMs. Define a PHP class, get a validated object back.',
    ) {}

    /**
     * Load configuration from YAML file
     */
    public static function fromFile(string $path = 'config/docs.yaml'): self
    {
        $fullPath = BasePath::get($path);

        if (!file_exists($fullPath)) {
            return self::defaults();
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            return self::defaults();
        }

        $config = Yaml::parse($content);
        if (!is_array($config)) {
            return self::defaults();
        }

        return new self(
            // Main section
            mainTitle: $config['main']['title'] ?? 'Instructor for PHP',
            mainSource: $config['main']['source'] ?? './docs',
            mainPages: $config['main']['pages'] ?? ['index.md'],
            // Packages section
            packagesSourcePattern: $config['packages']['source_pattern'] ?? './packages/*/docs',
            packageDescriptions: $config['packages']['descriptions'] ?? [],
            packageTargetDirs: $config['packages']['target_dirs'] ?? [],
            packageOrder: $config['packages']['order'] ?? [],
            packageInternal: $config['packages']['internal'] ?? [],
            // Examples section
            examplesSource: $config['examples']['source'] ?? './examples',
            examplesIntroPages: $config['examples']['intro_pages'] ?? [],
            // Changelog section
            changelogSource: $config['changelog']['source'] ?? './docs/release-notes',
            // Output targets
            mintlifyTarget: $config['output']['mintlify']['target'] ?? './docs-build',
            mintlifySourceIndex: $config['output']['mintlify']['source_index'] ?? './docs/mint.json',
            mkdocsTarget: $config['output']['mkdocs']['target'] ?? './docs-mkdocs',
            mkdocsTemplate: $config['output']['mkdocs']['template'] ?? './docs/mkdocs.yml.template',
            // LLMs documentation
            llmsEnabled: $config['llms']['enabled'] ?? true,
            llmsIndexFile: $config['llms']['index_file'] ?? 'llms.txt',
            llmsFullFile: $config['llms']['full_file'] ?? 'llms-full.txt',
            llmsExcludeSections: $config['llms']['exclude_sections'] ?? ['release-notes/'],
            llmsDeployTarget: $config['llms']['deploy']['target'] ?? '',
            llmsDeployDocsFolder: $config['llms']['deploy']['docs_folder'] ?? 'docs',
            llmsProjectDescription: $config['llms']['project_description'] ?? 'Structured data extraction in PHP, powered by LLMs. Define a PHP class, get a validated object back.',
        );
    }

    /**
     * Default configuration
     */
    public static function defaults(): self
    {
        return new self(
            mainTitle: 'Instructor for PHP',
            mainSource: './docs',
            mainPages: ['index.md'],
            packagesSourcePattern: './packages/*/docs',
            packageDescriptions: [],
            packageTargetDirs: [],
            packageOrder: [],
            packageInternal: [],
            examplesSource: './examples',
            examplesIntroPages: [],
            changelogSource: './docs/release-notes',
            mintlifyTarget: './docs-build',
            mintlifySourceIndex: './docs/mint.json',
            mkdocsTarget: './docs-mkdocs',
            mkdocsTemplate: './docs/mkdocs.yml.template',
            llmsEnabled: true,
            llmsIndexFile: 'llms.txt',
            llmsFullFile: 'llms-full.txt',
            llmsExcludeSections: ['release-notes/'],
            llmsDeployTarget: '',
            llmsDeployDocsFolder: 'docs',
            llmsProjectDescription: 'Structured data extraction in PHP, powered by LLMs. Define a PHP class, get a validated object back.',
        );
    }
}
