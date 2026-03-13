<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Commands;

use Cognesy\Doctools\Quality\Data\PackageDrift;
use Cognesy\Doctools\Quality\Services\DriftDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'drift',
    description: 'Detect documentation drift — find packages where src/ is newer than docs',
)]
final class DetectDocsDriftCommand extends Command
{
    private const HELP = <<<'HELP'
Compares file modification timestamps between <info>packages/*/src/</info> and
<info>packages/*/docs/</info> + <info>CHEATSHEET.md</info> to identify documentation that may be outdated.

<comment>Columns</comment>
  PACKAGE   Package directory name
  TIER      Classification from packages.json (public, library, addon, dev)
  SCORE     Weighted risk score 0–100 (higher = more likely outdated)
  RISK      Risk category derived from SCORE: HIGH (>=70), MED (>=30), LOW (<30)
  DRIFT     Time since newest doc vs newest src file ("-" = up to date, "no docs" = none exist)
  CHEAT     Whether CHEATSHEET.md exists
  DOCS      Whether docs/ directory exists
  DELTA     Number of src files modified after the newest doc file
  SRC       Total PHP file count under src/
  SPREAD    Time gap between newest and oldest doc file (large = some docs may be stale)

<comment>Risk score factors</comment>
  Drift age       (0–35 pts)  How many days src is ahead of docs (5 pts/day, max 7 days)
  Change ratio    (0–25 pts)  Percentage of src files newer than newest doc
  Docs spread     (0–15 pts)  Gap between newest and oldest doc (0.5 pts/day, max 30 days)
  Missing docs    (0–25 pts)  +15 if no CHEATSHEET.md, +10 if no docs/ and >10 src files

<comment>Examples</comment>
  <info>composer docs drift</info>                            All packages
  <info>composer docs drift --tier=public</info>              Only public-facing packages
  <info>composer docs drift --tier=public,library -r high</info>  High-risk public+library
  <info>composer docs drift -f packages -r medium</info>     Bare package names for scripting
  <info>composer docs drift -f json</info>                   Full detail as JSON
  <info>composer docs drift polyglot sandbox</info>           Specific packages only
HELP;

    public function __construct(
        private readonly DriftDetector $detector = new DriftDetector(),
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setHelp(self::HELP)
            ->addArgument(
                'packages',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Package names to check (default: all with src/)',
            )
            ->addOption(
                'risk',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Filter by risk level: high, medium, low',
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format: table|json|packages',
                'table',
            )
            ->addOption(
                'tier',
                't',
                InputOption::VALUE_OPTIONAL,
                'Filter by tier from packages.json: public, library, addon, dev (comma-separated)',
            )
            ->addOption(
                'repo-root',
                null,
                InputOption::VALUE_OPTIONAL,
                'Repository root (default: cwd)',
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repoRoot = $input->getOption('repo-root')
            ? realpath($input->getOption('repo-root'))
            : getcwd();

        if (!$repoRoot || !is_dir($repoRoot . '/packages')) {
            $output->writeln('<error>Cannot find packages/ directory. Run from repo root or use --repo-root.</error>');
            return Command::FAILURE;
        }

        $packages = $input->getArgument('packages') ?: [];
        $tiers = $this->parseTiers((string)($input->getOption('tier') ?? ''));
        $report = $this->detector->detect($repoRoot, $packages, $tiers);

        $riskFilter = $input->getOption('risk');
        $filtered = $report->packages;
        if ($riskFilter !== null) {
            $riskFilter = strtolower(trim($riskFilter));
            $filtered = array_filter($filtered, fn(PackageDrift $p) => $p->risk === $riskFilter);
            $filtered = array_values($filtered);
        }

        $format = strtolower(trim($input->getOption('format') ?? 'table'));

        return match ($format) {
            'json' => $this->outputJson($output, $filtered),
            'packages' => $this->outputPackageNames($output, $filtered),
            default => $this->outputTable($output, $filtered, $report->packages),
        };
    }

    /**
     * @param list<PackageDrift> $packages
     * @param list<PackageDrift> $all
     */
    private function outputTable(OutputInterface $output, array $packages, array $all): int
    {
        $highCount = count(array_filter($all, fn(PackageDrift $p) => $p->risk === 'high'));
        $medCount = count(array_filter($all, fn(PackageDrift $p) => $p->risk === 'medium'));
        $lowCount = count(array_filter($all, fn(PackageDrift $p) => $p->risk === 'low'));

        $output->writeln(sprintf(
            'Docs drift: <error>%d high</error>, <comment>%d medium</comment>, <info>%d low</info> (%d total)',
            $highCount,
            $medCount,
            $lowCount,
            count($all),
        ));
        $output->writeln('');

        if ($packages === []) {
            $output->writeln('No packages match the filter.');
            return Command::SUCCESS;
        }

        $maxSrcFile = 45;

        // Header
        $output->writeln(sprintf(
            '  %-16s %-8s %5s %-6s %-8s  %-5s %-4s  %5s %5s %-8s  %s',
            'PACKAGE', 'TIER', 'SCORE', 'RISK', 'DRIFT', 'CHEAT', 'DOCS', 'DELTA', 'SRC', 'SPREAD', 'NEWEST SRC FILE',
        ));
        $output->writeln('  ' . str_repeat('-', 85 + $maxSrcFile));

        foreach ($packages as $p) {
            $risk = str_pad(strtoupper($p->risk === 'medium' ? 'med' : $p->risk), 6);
            $risk = match ($p->risk) {
                'high' => "<error>{$risk}</error>",
                'medium' => "<comment>{$risk}</comment>",
                default => "<info>{$risk}</info>",
            };

            $cheat = str_pad($p->hasCheatsheet ? 'yes' : 'NO', 5);
            if (!$p->hasCheatsheet) {
                $cheat = "<comment>{$cheat}</comment>";
            }

            $newestSrc = $p->newestSrcFile;
            if (strlen($newestSrc) > $maxSrcFile) {
                $newestSrc = '...' . substr($newestSrc, -($maxSrcFile - 3));
            }

            $delta = $p->srcChangedSinceNewestDoc;
            $deltaStr = str_pad((string)$delta, 5, ' ', STR_PAD_LEFT);
            if ($delta > 0 && $p->srcFileCount > 0) {
                $pct = round($delta / $p->srcFileCount * 100);
                $deltaStr = str_pad("{$delta}", 5, ' ', STR_PAD_LEFT);
            }

            $output->writeln(sprintf(
                '  %-16s %-8s %5s %s %-8s  %s %-4s  %s %5d %-8s  %s',
                $p->package,
                $p->tier,
                number_format($p->riskScore, 0),
                $risk,
                ($p->docsFileCount === 0) ? 'no docs' : PackageDrift::humanizeDuration($p->driftSeconds),
                $cheat,
                $p->hasDocs ? 'yes' : '-',
                $deltaStr,
                $p->srcFileCount,
                PackageDrift::humanizeDuration($p->docsSpreadSeconds),
                $newestSrc,
            ));
        }

        $output->writeln('');
        $output->writeln('SCORE = weighted risk (0-100)  DELTA = src files newer than newest doc  SPREAD = newest-oldest doc gap');

        return Command::SUCCESS;
    }

    /**
     * @param list<PackageDrift> $packages
     */
    private function outputJson(OutputInterface $output, array $packages): int
    {
        $data = array_map(fn(PackageDrift $p) => $p->toArray(), $packages);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $output->writeln($json ?: '[]');
        return Command::SUCCESS;
    }

    /**
     * @param list<PackageDrift> $packages
     */
    private function outputPackageNames(OutputInterface $output, array $packages): int
    {
        foreach ($packages as $p) {
            $output->writeln($p->package);
        }
        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function parseTiers(string $raw): array
    {
        $tiers = [];
        foreach (explode(',', $raw) as $tier) {
            $normalized = strtolower(trim($tier));
            if ($normalized !== '') {
                $tiers[] = $normalized;
            }
        }
        return $tiers;
    }
}
