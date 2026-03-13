<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Services;

use Cognesy\Doctools\Quality\Data\DriftReport;
use Cognesy\Doctools\Quality\Data\PackageDrift;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DriftDetector
{
    /**
     * @param list<string> $packages  Package names to scan (empty = all with src/)
     * @param list<string> $tiers     Tier filter (empty = all tiers)
     */
    public function detect(string $repoRoot, array $packages = [], array $tiers = []): DriftReport
    {
        $packagesDir = rtrim($repoRoot, '/') . '/packages';
        $tierMap = $this->loadTierMap($repoRoot);

        if ($packages === []) {
            $packages = $this->discoverPackages($packagesDir);
        }

        if ($tiers !== []) {
            $packages = array_values(array_filter(
                $packages,
                fn(string $pkg) => in_array($tierMap[$pkg] ?? 'unknown', $tiers, true),
            ));
        }

        sort($packages);

        $results = [];
        foreach ($packages as $package) {
            $packageDir = $packagesDir . '/' . $package;
            if (!is_dir($packageDir . '/src')) {
                continue;
            }
            $results[] = $this->analyzePackage($package, $packageDir, $tierMap[$package] ?? 'unknown');
        }

        // Sort by risk score descending
        usort($results, fn(PackageDrift $a, PackageDrift $b) => $b->riskScore <=> $a->riskScore);

        return new DriftReport($results);
    }

    private function analyzePackage(string $package, string $packageDir, string $tier): PackageDrift
    {
        $srcDir = $packageDir . '/src';
        $docsDir = $packageDir . '/docs';
        $cheatsheet = $packageDir . '/CHEATSHEET.md';

        $hasCheatsheet = file_exists($cheatsheet);
        $hasDocs = is_dir($docsDir);

        // Collect src file mtimes
        $srcFiles = $this->collectFiles($srcDir, 'php');
        $srcMtimes = array_map(fn(SplFileInfo $f) => $f->getMTime(), $srcFiles);
        $srcNewest = $srcMtimes !== [] ? max($srcMtimes) : 0;
        $srcOldest = $srcMtimes !== [] ? min($srcMtimes) : 0;

        // Find newest src file path
        $newestSrcFile = '';
        foreach ($srcFiles as $file) {
            if ($file->getMTime() === $srcNewest) {
                $newestSrcFile = $this->relativePath($packageDir, $file->getPathname());
                break;
            }
        }

        // Collect docs file mtimes (CHEATSHEET.md + docs/*.md)
        $docsMtimes = [];
        $docsFileCount = 0;
        if ($hasCheatsheet) {
            $docsMtimes[] = filemtime($cheatsheet);
            $docsFileCount++;
        }
        if ($hasDocs) {
            foreach ($this->collectFiles($docsDir, 'md') as $file) {
                $docsMtimes[] = $file->getMTime();
                $docsFileCount++;
            }
        }
        $docsNewest = $docsMtimes !== [] ? max($docsMtimes) : 0;
        $docsOldest = $docsMtimes !== [] ? min($docsMtimes) : 0;
        $docsSpread = $docsNewest > 0 ? ($docsNewest - $docsOldest) : 0;

        // Drift = how far behind newest doc is from newest src (0 when no docs exist)
        $driftSeconds = ($docsNewest > 0) ? max(0, $srcNewest - $docsNewest) : 0;

        // Count src files newer than newest doc and oldest doc
        $changedSinceNewest = 0;
        $changedSinceOldest = 0;
        foreach ($srcMtimes as $mtime) {
            if ($docsNewest > 0 && $mtime > $docsNewest) {
                $changedSinceNewest++;
            }
            if ($docsOldest > 0 && $mtime > $docsOldest) {
                $changedSinceOldest++;
            }
        }

        $srcCount = count($srcFiles);
        $riskScore = $this->calculateRiskScore(
            driftSeconds: $driftSeconds,
            srcCount: $srcCount,
            changedSinceNewest: $changedSinceNewest,
            changedSinceOldest: $changedSinceOldest,
            docsSpread: $docsSpread,
            hasCheatsheet: $hasCheatsheet,
            hasDocs: $hasDocs,
        );

        $risk = match (true) {
            $riskScore >= 70 => 'high',
            $riskScore >= 30 => 'medium',
            default => 'low',
        };

        return new PackageDrift(
            package: $package,
            tier: $tier,
            risk: $risk,
            riskScore: $riskScore,
            srcFileCount: $srcCount,
            srcNewest: $srcNewest,
            srcOldest: $srcOldest,
            newestSrcFile: $newestSrcFile,
            docsFileCount: $docsFileCount,
            docsNewest: $docsNewest,
            docsOldest: $docsOldest,
            hasCheatsheet: $hasCheatsheet,
            hasDocs: $hasDocs,
            driftSeconds: $driftSeconds,
            srcChangedSinceNewestDoc: $changedSinceNewest,
            srcChangedSinceOldestDoc: $changedSinceOldest,
            docsSpreadSeconds: $docsSpread,
        );
    }

    /**
     * Risk score 0–100 based on weighted factors:
     *
     * - drift age         (0–35): how long since docs were touched vs src
     * - change ratio      (0–25): % of src files newer than newest doc
     * - docs spread       (0–15): large gap between newest and oldest doc = stale corners
     * - no docs penalty   (0–25): missing cheatsheet or docs/ dir
     */
    private function calculateRiskScore(
        int $driftSeconds,
        int $srcCount,
        int $changedSinceNewest,
        int $changedSinceOldest,
        int $docsSpread,
        bool $hasCheatsheet,
        bool $hasDocs,
    ): float {
        // Factor 1: drift age (0–35)
        // 0 drift = 0, >=7 days = 35
        $driftDays = $driftSeconds / 86400;
        $ageFactor = min(35.0, $driftDays * 5);

        // Factor 2: change ratio (0–25)
        // what % of src files are newer than the newest doc
        $changePct = $srcCount > 0 ? ($changedSinceNewest / $srcCount) : 0;
        $changeFactor = min(25.0, $changePct * 50); // 50% changed = max score

        // Factor 3: docs spread (0–15)
        // large spread means some docs are very stale even if newest is recent
        $spreadDays = $docsSpread / 86400;
        $spreadFactor = min(15.0, $spreadDays * 0.5); // 30 days spread = max

        // Factor 4: no docs penalty (0–25)
        $missingPenalty = 0.0;
        if (!$hasCheatsheet) {
            $missingPenalty += 15.0;
        }
        if (!$hasDocs && $srcCount > 10) {
            $missingPenalty += 10.0;
        }

        return round(min(100.0, $ageFactor + $changeFactor + $spreadFactor + $missingPenalty), 1);
    }

    /**
     * @return list<SplFileInfo>
     */
    private function collectFiles(string $dir, string $extension): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === $extension) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @return array<string, string>  package name => tier
     */
    private function loadTierMap(string $repoRoot): array
    {
        $path = rtrim($repoRoot, '/') . '/packages.json';
        if (!file_exists($path)) {
            return [];
        }

        $json = json_decode(file_get_contents($path), true);
        if (!is_array($json) || !isset($json['packages'])) {
            return [];
        }

        $map = [];
        foreach ($json['packages'] as $entry) {
            $local = $entry['local'] ?? '';
            $tier = $entry['tier'] ?? 'unknown';
            $name = basename($local);
            if ($name !== '') {
                $map[$name] = $tier;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function discoverPackages(string $packagesDir): array
    {
        $packages = [];
        foreach (scandir($packagesDir) ?: [] as $entry) {
            if ($entry[0] === '.') {
                continue;
            }
            if (is_dir($packagesDir . '/' . $entry . '/src')) {
                $packages[] = $entry;
            }
        }
        return $packages;
    }

    private function relativePath(string $base, string $path): string
    {
        $base = rtrim($base, '/') . '/';
        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base));
        }
        return $path;
    }
}
