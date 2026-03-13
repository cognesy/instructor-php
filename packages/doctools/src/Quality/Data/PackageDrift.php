<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Data;

final class PackageDrift
{
    public function __construct(
        public readonly string $package,
        public readonly string $tier,
        public readonly string $risk,
        public readonly float $riskScore,
        // src stats
        public readonly int $srcFileCount,
        public readonly int $srcNewest,
        public readonly int $srcOldest,
        public readonly string $newestSrcFile,
        // docs stats
        public readonly int $docsFileCount,
        public readonly int $docsNewest,
        public readonly int $docsOldest,
        public readonly bool $hasCheatsheet,
        public readonly bool $hasDocs,
        // drift metrics
        public readonly int $driftSeconds,
        public readonly int $srcChangedSinceNewestDoc,
        public readonly int $srcChangedSinceOldestDoc,
        public readonly int $docsSpreadSeconds,
    ) {}

    public function srcChangedPct(): float
    {
        return $this->srcFileCount > 0
            ? round($this->srcChangedSinceNewestDoc / $this->srcFileCount * 100, 1)
            : 0.0;
    }

    public function toArray(): array
    {
        return [
            'package' => $this->package,
            'tier' => $this->tier,
            'risk' => $this->risk,
            'risk_score' => $this->riskScore,
            'src_file_count' => $this->srcFileCount,
            'src_newest' => self::ts($this->srcNewest),
            'src_oldest' => self::ts($this->srcOldest),
            'newest_src_file' => $this->newestSrcFile,
            'docs_file_count' => $this->docsFileCount,
            'docs_newest' => self::ts($this->docsNewest),
            'docs_oldest' => self::ts($this->docsOldest),
            'has_cheatsheet' => $this->hasCheatsheet,
            'has_docs' => $this->hasDocs,
            'drift_seconds' => $this->driftSeconds,
            'drift_human' => self::humanizeDuration($this->driftSeconds),
            'src_changed_since_newest_doc' => $this->srcChangedSinceNewestDoc,
            'src_changed_since_oldest_doc' => $this->srcChangedSinceOldestDoc,
            'src_changed_pct' => $this->srcChangedPct(),
            'docs_spread_seconds' => $this->docsSpreadSeconds,
            'docs_spread_human' => self::humanizeDuration($this->docsSpreadSeconds),
        ];
    }

    public static function humanizeDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '-';
        }
        if ($seconds < 3600) {
            return sprintf('%dm', max(1, intdiv($seconds, 60)));
        }
        if ($seconds < 86400) {
            return sprintf('%dh', intdiv($seconds, 3600));
        }
        return sprintf('%dd', intdiv($seconds, 86400));
    }

    private static function ts(int $timestamp): string
    {
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : 'n/a';
    }
}
