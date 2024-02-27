<?php
namespace Tests;

use Cognesy\Instructor\Schema\FunctionCallSchema;
use Tests\Examples\Event;
use Tests\Examples\Events;

/**
 * Function creates project event
 * @param string $title Title of the event
 * @param string $date Date of the event
 * @param \Tests\Examples\Stakeholder[] $stakeholders Stakeholders involved in the event
 * @return \Tests\Examples\Event
 */
function createEvent(string $title, string $date, array $stakeholders): Event {
    return new Event();
}

it('creates function call - function', function () {
    $array = (new FunctionCallSchema)->withFunction(createEvent(...));
    // dump($array);
    expect($array)->toBeArray();
    expect($array['type'])->toEqual('function');
    expect($array['function']['name'])->toEqual('Tests\createEvent');
    expect($array['function']['description'])->toEqual('Function creates project event');
    expect($array['function']['parameters']['type'])->toEqual('object');
    expect($array['function']['parameters']['properties']['title']['type'])->toEqual('string');
    expect($array['function']['parameters']['properties']['date']['type'])->toEqual('string');
    expect($array['function']['parameters']['properties']['stakeholders']['type'])->toEqual('array');
    // ...
    expect($array)->toMatchSnapshot();
});

it('creates function call - method', function () {
    $array = (new FunctionCallSchema)->withMethod((new Events)->createEvent(...));
    // dump($array);
    expect($array)->toBeArray();
    expect($array['type'])->toEqual('function');
    expect($array['function']['name'])->toEqual('createEvent');
    expect($array['function']['description'])->toEqual('Method creates project event');
    expect($array['function']['parameters']['type'])->toEqual('object');
    expect($array['function']['parameters']['properties']['title']['type'])->toEqual('string');
    expect($array['function']['parameters']['properties']['date']['type'])->toEqual('string');
    expect($array['function']['parameters']['properties']['stakeholders']['type'])->toEqual('array');
    // ...
    expect($array)->toMatchSnapshot();
});

it('creates function call - object', function () {
    $array = (new FunctionCallSchema)->withClass(Events::class, 'createEvent', 'Extract object from provided content');
    // dump($array);
    expect($array)->toBeArray();
    expect($array['type'])->toEqual('function');
    expect($array['function']['name'])->toEqual('createEvent');
    expect($array['function']['description'])->toEqual('Extract object from provided content');
    expect($array['function']['parameters']['type'])->toEqual('object');
    expect($array['function']['parameters']['properties']['events']['type'])->toEqual('array');
    expect($array['function']['parameters']['properties']['events']['items']['type'])->toEqual('object');
    // ...
    expect($array)->toMatchSnapshot();
});
