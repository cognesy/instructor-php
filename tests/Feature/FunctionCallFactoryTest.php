<?php
namespace Tests;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Schema\Factories\FunctionCallBuilder;
use Tests\Examples\Complex\ProjectEvent;
use Tests\Examples\Complex\ProjectEvents;

if (!function_exists('addEvent')) {
    /**
     * Function creates project event
     * @param string $title Title of the event
     * @param string $date Date of the event
     * @param \Tests\Examples\Complex\Stakeholder[] $stakeholders Stakeholders involved in the event
     * @return \Tests\Examples\Complex\ProjectEvent
     */
    function addEvent(string $title, string $date, array $stakeholders): ProjectEvent {
        return new ProjectEvent();
    }
}


it('creates function call', function () {
    $array = Configuration::fresh()
        ->get(FunctionCallBuilder::class)
        ->fromClass(ProjectEvents::class, 'addEvent', 'Extract object from provided content');
    expect($array)->toBeArray();
    expect($array['type'])->toEqual('function');
    expect($array['function']['name'])->toEqual('addEvent');
    expect($array['function']['description'])->toEqual('Extract object from provided content');
    expect($array['function']['parameters']['type'])->toEqual('object');
    expect($array['function']['parameters']['properties']['events']['type'])->toEqual('array');
    expect($array['function']['parameters']['properties']['events']['items']['type'])->toEqual('object');
    // ...
    expect($array)->toMatchSnapshot();
})->skip('Modified implementation of FunctionCallBuilder.php - fix it!');
