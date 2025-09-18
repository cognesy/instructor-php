<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Services;

use Cognesy\Doctor\Doctest\DoctestFile;
use Cognesy\Doctor\Markdown\MarkdownFile;
use Symfony\Component\Filesystem\Path;
use Cognesy\Doctor\Doctest\Data\PlannedCase;
use Cognesy\Doctor\Doctest\Data\PlannedRegion;

/**
 * Produces a simple extraction plan for a Markdown document using existing Doctest primitives.
 * This class is pure and does not perform any I/O.
 */
final class DoctestPlanner
{
    /**
     * Build extraction plan for a given Markdown file.
     *
     * @return array<int,PlannedCase>
     */
    public function planForMarkdown(MarkdownFile $markdown, ?string $targetDir = null): array {
        $plan = [];

        $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdown));
        foreach ($doctests as $doctest) {
            $path = $this->determineOutputPath($doctest->sourceMarkdown->path, $doctest->sourceMarkdown->caseDir, $doctest->codePath, $targetDir);

            $regions = [];
            if ($doctest->hasRegions()) {
                $regionNames = $this->findRegionNames($doctest->code);
                if (empty($regionNames)) {
                    $regionNames = $doctest->getAvailableRegions();
                }
                $regions = array_map(function (string $regionName) use ($doctest, $targetDir) {
                    return new PlannedRegion(
                        name: $regionName,
                        path: $this->determineRegionOutputPath(
                            $doctest->sourceMarkdown->path,
                            $doctest->sourceMarkdown->caseDir,
                            $doctest->codePath,
                            $regionName,
                            $targetDir,
                        ),
                    );
                }, $regionNames);
            }

            $plan[] = new PlannedCase(
                id: (string)$doctest->id,
                language: (string)$doctest->language,
                path: $path,
                regions: $regions,
            );
        }

        return $plan;
    }

    // INTERNAL (mirrors ExtractCodeBlocks logic) ///////////////////////

    private function determineOutputPath(string $markdownPath, string $caseDir, string $codePath, ?string $targetDir): string {
        if ($targetDir) {
            $filename = basename($codePath);
            return Path::join($targetDir, $caseDir, $filename);
        }

        $markdownDir = dirname($markdownPath);
        return Path::join($markdownDir, ltrim($codePath, './'));
    }

    private function determineRegionOutputPath(string $markdownPath, string $caseDir, string $codePath, string $regionName, ?string $targetDir): string {
        $pathInfo = pathinfo($codePath);
        $baseFilename = $pathInfo['filename'] ?? '';
        $extension = $pathInfo['extension'] ?? '';
        $regionFilename = $baseFilename . '_' . $regionName . '.' . $extension;

        if ($targetDir) {
            return Path::join($targetDir, $caseDir, $regionFilename);
        }

        $markdownDir = dirname($markdownPath);
        $resolvedCaseDir = Path::join($markdownDir, ltrim($caseDir, './'));
        return Path::join($resolvedCaseDir, $regionFilename);
    }


    /**
     * Extract region names directly from code as a fallback (language-agnostic).
     *
     * @return array<int,string>
     */
    private function findRegionNames(string $code): array {
        $names = [];
        if (preg_match_all('/@doctest-region-start\s+name[=:]\s*["\']?([^"\'\s<>]+)["\']?/m', $code, $m)) {
            $names = $m[1];
        }
        return array_values(array_unique(array_filter(array_map('strval', $names))));
    }
}
