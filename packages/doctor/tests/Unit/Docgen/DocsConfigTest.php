<?php declare(strict_types=1);

namespace Cognesy\Doctor\Tests\Unit\Docgen;

use Cognesy\Doctor\Docgen\Data\DocsConfig;

describe('DocsConfig', function () {

    describe('defaults', function () {

        it('returns default values', function () {
            $config = DocsConfig::defaults();

            expect($config->mainTitle)->toBe('Instructor for PHP');
            expect($config->mkdocsTarget)->toBe('./docs-mkdocs');
        });

        it('includes llms defaults', function () {
            $config = DocsConfig::defaults();

            expect($config->llmsEnabled)->toBeTrue();
            expect($config->llmsIndexFile)->toBe('llms.txt');
            expect($config->llmsFullFile)->toBe('llms-full.txt');
            expect($config->llmsExcludeSections)->toBe(['release-notes/']);
            expect($config->llmsDeployTarget)->toBe('');
            expect($config->llmsDeployDocsFolder)->toBe('docs');
        });

    });

    describe('fromFile', function () {

        it('loads llms configuration from yaml', function () {
            // This test uses the actual config file
            $config = DocsConfig::fromFile('config/docs.yaml');

            expect($config->llmsEnabled)->toBeTrue();
            expect($config->llmsIndexFile)->toBe('llms.txt');
            expect($config->llmsFullFile)->toBe('llms-full.txt');
            expect($config->llmsExcludeSections)->toContain('release-notes/');
            expect($config->llmsDeployTarget)->toBe('../instructor-www/public');
            expect($config->llmsDeployDocsFolder)->toBe('docs');
        });

        it('falls back to defaults for missing llms config', function () {
            // Create a temporary config file without llms section
            $tempDir = sys_get_temp_dir() . '/docs-config-test-' . uniqid();
            mkdir($tempDir, 0755, true);

            $configContent = <<<YAML
main:
  title: 'Test Project'
  source: './docs'
  pages:
    - index.md
YAML;
            file_put_contents($tempDir . '/test-config.yaml', $configContent);

            // Override BasePath for this test
            $originalCwd = getcwd();
            chdir($tempDir);

            try {
                $config = DocsConfig::fromFile('test-config.yaml');

                // Should use defaults for llms
                expect($config->llmsEnabled)->toBeTrue();
                expect($config->llmsIndexFile)->toBe('llms.txt');
                expect($config->llmsFullFile)->toBe('llms-full.txt');
            } finally {
                chdir($originalCwd);
                unlink($tempDir . '/test-config.yaml');
                rmdir($tempDir);
            }
        });

    });

});
