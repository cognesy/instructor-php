<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Presentation\Console;

use Cognesy\Auxiliary\Beads\Application\Tbd\Action\CloseAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\CommentAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\CompactAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\CreateAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\DepAddAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\DepRemoveAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\DepTreeAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\InitAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\ListAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\ReadyAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\ShowAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\Action\UpdateAction;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdClock;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIdFactory;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdInputMapper;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Services\BeadsJsonlFileService;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\CloseCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\CommentCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\CompactCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\CreateCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\DepAddCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\DepRemoveCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\DepTreeCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\InitCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\ListCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\ReadyCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\ShowCommand;
use Cognesy\Auxiliary\Beads\Presentation\Console\Command\UpdateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

class TbdApplicationFactory
{
    public function create(): Application {
        $app = new Application('tbd', '0.1.0');

        $files = new BeadsJsonlFileService();
        $store = new TbdIssueStore($files);
        $clock = new TbdClock();
        $ids = new TbdIdFactory();
        $map = new TbdInputMapper();

        $commands = [
            new InitCommand(new InitAction($store)),
            new ListCommand(new ListAction($store), $map),
            new ReadyCommand(new ReadyAction($store)),
            new ShowCommand(new ShowAction($store)),
            new CreateCommand(new CreateAction($store, $ids, $clock, $map), $map),
            new UpdateCommand(new UpdateAction($store, $clock, $map), $map),
            new CloseCommand(new CloseAction($store, $clock)),
            new CommentCommand(new CommentAction($store, $clock)),
            new DepAddCommand(new DepAddAction($store, $clock, $map)),
            new DepRemoveCommand(new DepRemoveAction($store, $clock)),
            new DepTreeCommand(new DepTreeAction($store), $map),
            new CompactCommand(new CompactAction($store)),
        ];

        foreach ($commands as $command) {
            $this->registerCommand($app, $command);
        }

        return $app;
    }

    private function registerCommand(Application $app, Command $command): void {
        /** @phpstan-ignore function.alreadyNarrowedType (Symfony version compatibility) */
        if (method_exists($app, 'addCommand')) {
            $app->addCommand($command);
        } else {
            $app->add($command);
        }
    }
}
