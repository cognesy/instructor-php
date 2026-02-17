<?php declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\PendingInference;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TellCommand extends Command
{
    protected static string $defaultName = 'tell';

    #[\Override]
    protected function configure() : void {
        $this->setName(self::$defaultName)
            ->setDescription('Prompt AI')
            ->addArgument('prompt', InputArgument::REQUIRED, 'Prompt')
            ->addOption('connection', 'c', InputOption::VALUE_OPTIONAL, 'LLM connection preset', 'openai')
            ->addOption('model', 'm', InputOption::VALUE_OPTIONAL, 'The model option', '')
            ->addOption('dsn', 'd', InputOption::VALUE_OPTIONAL, 'The DSN option', '');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $prompt = $input->getArgument('prompt');

        $dsn = $input->getOption('dsn');
        $preset = $input->getOption('connection');
        $model = $input->getOption('model');

        $response = match(true) {
            empty($dsn) => $this->inferenceUsingPreset($preset, $prompt, $model),
            default => $this->inferenceUsingDSN($dsn, $prompt),
        };

        foreach ($response->stream()->responses() as $partialResponse) {
            $output->write($partialResponse->contentDelta);
        }
        $output->writeln('');

        return Command::SUCCESS;
    }

    protected function inferenceUsingDSN(string $dsn, string $prompt) : PendingInference {
        return InferenceRuntime::fromDsn($dsn)->create(new InferenceRequest(
            messages: $prompt,
            options: ['stream' => true],
        ));
    }

    protected function inferenceUsingPreset(string $preset, string $prompt, string $model = '') : PendingInference {
        return InferenceRuntime::using($preset)->create(new InferenceRequest(
            messages: $prompt,
            model: $model,
            options: ['stream' => true],
        ));
    }
}
