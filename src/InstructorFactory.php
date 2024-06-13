<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Events\EventDispatcher;
//use Cognesy\Instructor\Data\Request;
//use Psr\Log\LoggerInterface;

class InstructorFactory
{
    public static function withClient(CanCallApi $client) : Instructor {
        return self::make()->withClient($client);
    }

    public static function withCache(bool $cache = true) : Instructor {
        return self::make()->withCache($cache);
    }

    public static function withDebug(bool $debug = true, bool $stopOnDebug = false) : Instructor {
        return self::make()->withDebug($debug, $stopOnDebug);
    }

    //public static function withLogger(LoggerInterface $logger) : Instructor {
    //return self::make()->withLogger($logger);
    //}

    public static function withConfig(array $config) : Instructor {
        return self::make()->withConfig($config);
    }

    public static function withEventDispatcher(EventDispatcher $events) : Instructor {
        return self::make()->withEventDispatcher($events);
    }

    //public static function withRequest(Request $request) : Instructor {
    //    return self::make()->withRequest($request);
    //}

    public static function make() : Instructor {
        return new Instructor();
    }
}
