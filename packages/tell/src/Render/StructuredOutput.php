<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use HelgeSverre\Toon\Toon;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class StructuredOutput
{
    public function __construct(private OutputInterface $output) {}

    /** @param array<string, mixed> $data */
    public function write(array $data, bool $json = false): void
    {
        $encoded = match ($json) {
            true => $this->json($data),
            false => Toon::encode($data),
        };
        $this->output->writeln($encoded, OutputInterface::VERBOSITY_QUIET);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
