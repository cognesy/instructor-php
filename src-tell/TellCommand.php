<?php

namespace Cognesy\Tell;

use Cognesy\LLM\LLM\Inference;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TellCommand extends Command
{
    protected static $defaultName = 'tell';

    protected function configure() : void {
        $this->setName(self::$defaultName)
            ->setDescription('Prompt AI')
            ->addArgument('prompt', InputArgument::REQUIRED, 'Prompt')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The connection option', 'openai');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $prompt = $input->getArgument('prompt');
        $connection = $input->getOption('connection');

        $response = (new Inference)
            ->withConnection($connection)
            ->create(
                messages: $prompt,
                options: ['stream' => true]
            );

        foreach ($response->stream()->responses() as $response) {
            $output->write($response->contentDelta);
        }
        $output->writeln('');

        return Command::SUCCESS;
    }
}