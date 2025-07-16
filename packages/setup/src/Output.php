<?php declare(strict_types=1);

namespace Cognesy\Setup;

use Psr\Log\NullLogger;
use Cognesy\Setup\Loggers\FileLogger;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Output
{
    private SymfonyStyle $io;
    private LoggerInterface $logger;

    public function __construct(InputInterface $input, OutputInterface $output) {
        $this->io = new SymfonyStyle($input, $output);
        $this->setupOutputStyles($output);
        $logFile = $input->getOption('log-file');
        $this->logger = $logFile
            ? new FileLogger(Path::resolve($logFile))
            : new NullLogger();
    }

    public function out(string $message, string $level = 'info'): void {
        $this->io->writeln($message);
        $this->logger->log($level, strip_tags($message));
    }

    private function setupOutputStyles(OutputInterface $output): void {
        $styles = [
            'blue' => ['blue', null, ['bold']],
            'gray' => ['gray', null, ['bold']],
            'green' => ['green', null, ['bold']],
            'red' => ['red', null, ['bold']],
            'white' => ['white', null, ['bold']],
            'yellow' => ['yellow', null, ['bold']],
            'dark-gray' => ['black', null, ['bold']],
        ];

        foreach ($styles as $name => [$color, $background, $options]) {
            $output->getFormatter()->setStyle($name, new OutputFormatterStyle($color, $background, $options));
        }
    }
}
