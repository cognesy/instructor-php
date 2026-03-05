<?php declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Config\Dsn;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
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
            ->addOption('connection', 'c', InputOption::VALUE_OPTIONAL, 'LLM connection/driver name', 'openai')
            ->addOption('model', 'm', InputOption::VALUE_OPTIONAL, 'The model option', '')
            ->addOption('dsn', 'd', InputOption::VALUE_OPTIONAL, 'The DSN option', '');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $prompt = $input->getArgument('prompt');

        $dsn = $input->getOption('dsn');
        $connection = $input->getOption('connection');
        $model = $input->getOption('model');

        $response = match(true) {
            empty($dsn) => $this->inferenceUsingConnection($connection, $prompt, $model),
            default => $this->inferenceUsingDSN($dsn, $prompt),
        };

        foreach ($response->stream()->responses() as $partialResponse) {
            $output->write($partialResponse->contentDelta);
        }
        $output->writeln('');

        return Command::SUCCESS;
    }

    protected function inferenceUsingDSN(string $dsn, string $prompt) : PendingInference {
        $config = LLMConfig::fromArray(Dsn::fromString($dsn)->toArray());

        return InferenceRuntime::fromLLMConfig($config)->create(new InferenceRequest(
            messages: $prompt,
            options: ['stream' => true],
        ));
    }

    protected function inferenceUsingConnection(string $connection, string $prompt, string $model = '') : PendingInference {
        $config = LLMConfig::fromArray([
            'driver' => $connection,
            'model' => $model,
        ]);

        return InferenceRuntime::fromLLMConfig($config)->create(new InferenceRequest(
            messages: $prompt,
            options: ['stream' => true],
        ));
    }
}
