<?php declare(strict_types=1);

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Middleware\EventSource\Listeners\PrintToConsole;

it('prints JSON body using Console output without dump helper', function () {
    $listener = new class(new DebugConfig()) extends PrintToConsole {
        public function printForTest(string $body) : void {
            $this->printBody($body);
        }
    };

    ob_start();
    $listener->printForTest('{"message":"ok","count":2}');
    $output = ob_get_clean();

    expect($output)->toContain('"message": "ok"');
    expect($output)->toContain('"count": 2');
    expect($output)->not->toContain('dump(');
});

it('prints raw body when payload is not valid JSON', function () {
    $listener = new class(new DebugConfig()) extends PrintToConsole {
        public function printForTest(string $body) : void {
            $this->printBody($body);
        }
    };

    ob_start();
    $listener->printForTest('not-json-body');
    $output = ob_get_clean();

    expect($output)->toContain('not-json-body');
});
