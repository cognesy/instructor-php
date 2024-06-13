<?php
namespace PHPSTORM_META
{
    override(\Psr\Container\ContainerInterface::get(0), map([
        '' => '@',
    ]));
    override(\Cognesy\Instructor\Configuration\Configuration::get(0), map([
        '' => '@',
    ]));
    override(\Cognesy\Instructor\Instructor::__callStatic(), map([
        'withClient' => \Cognesy\Instructor\Instructor::class,
    ]));
}
