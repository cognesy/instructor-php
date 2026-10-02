<?php

declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Drivers\Clef\ClefRequestAdapter;
use Cognesy\Polyglot\Decision\Drivers\Clef\ClefResponseAdapter;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

const CLEF_LIVE_RESPONSE = __DIR__.'/../../Fixtures/Decision/clef-flash-live-response.json';

it('renders the Clef System One body against the Workers AI model route', function (): void {
    $http = clefRequestAdapter()->toHttpClientRequest(clefRequest());
    $body = $http->body()->toArray();

    expect($http->url())->toBe('https://api.cloudflare.com/client/v4/accounts/acct/ai/run/@cf/cloudflare/clef-flash')
        ->and($http->method())->toBe('POST')
        ->and($http->headers())->toBe([
            'Authorization' => 'Bearer synthetic-test-key',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
        ->and($body['model'])->toBe('clef-flash')
        ->and($body['state'])->toBe('Checkout has been failing for every customer for the last hour.')
        ->and(array_keys($body['questions']))->toBe(['urgent', 'team', 'severity'])
        ->and($body['questions']['severity']['criteria'])->toBe(['No impact', 'Minor', 'Major', 'Critical']);
});

it('routes a request model override into both the URL and the body', function (): void {
    $request = new DecisionRequest(
        input: 'state',
        questions: Questions::of(new Noul('urgent', 'Is it urgent?')),
        model: 'clef',
    );
    $http = clefRequestAdapter()->toHttpClientRequest($request);

    expect($http->url())->toEndWith('/ai/run/@cf/cloudflare/clef')
        ->and($http->body()->toArray()['model'])->toBe('clef');
});

it('rejects Clef questions without instructions before producing an HTTP request', function (): void {
    $request = new DecisionRequest(
        input: 'state',
        questions: Questions::of(new Noul('urgent')),
    );

    expect(fn () => clefRequestAdapter()->toHttpClientRequest($request))
        ->toThrow(InvalidArgumentException::class, "Clef requires instructions for question 'urgent'");
});

it('requires a Cloudflare API token', function (): void {
    $adapter = new ClefRequestAdapter(new DecisionConfig(
        driver: 'clef',
        apiUrl: 'https://api.cloudflare.com/client/v4/accounts/acct/ai',
        endpoint: '/run/@cf/cloudflare/{model}',
        model: 'clef-flash',
    ));

    expect(fn () => $adapter->toHttpClientRequest(clefRequest()))
        ->toThrow(InvalidArgumentException::class, "field 'apiKey' is missing or empty");
});

it('decodes a recorded live Clef response from the Cloudflare envelope', function (): void {
    $response = (new ClefResponseAdapter)->fromHttpResponse(clefHttpResponse(clefLiveBody()), clefRequest());

    expect($response->model())->toBe('clef-flash')
        ->and($response->answers()->noul('urgent')->probability())->toBe(0.9539)
        ->and($response->answers()->choice('team')->value())->toBe('technical')
        ->and($response->answers()->choice('team')->confidence())->toBe(0.8724)
        ->and($response->answers()->score('severity')->value())->toBe(2.7227)
        ->and($response->usage()->toArray())->toBe(['input' => 336, 'output' => 0])
        ->and($response->providerRequestId()->toString())->toBe('ray-1');
});

it('decodes an unwrapped System One payload', function (): void {
    $response = (new ClefResponseAdapter)->fromHttpResponse(
        clefHttpResponse(json_encode(json_decode(clefLiveBody())->result, JSON_THROW_ON_ERROR)),
        clefRequest(),
    );

    expect($response->answers()->choice('team')->value())->toBe('technical');
});

it('fails closed on an unsuccessful Cloudflare envelope', function (): void {
    $body = '{"result":{},"success":false,"errors":[{"code":5006,"message":"Bad input"}],"messages":[]}';

    expect(fn () => (new ClefResponseAdapter)->fromHttpResponse(clefHttpResponse($body), clefRequest()))
        ->toThrow(DecisionResponseException::class, 'unsuccessful request');
});

it('fails closed when Clef omits usage token counts', function (): void {
    $body = json_decode(clefLiveBody());
    unset($body->result->usage->output_tokens);

    expect(fn () => (new ClefResponseAdapter)->fromHttpResponse(
        clefHttpResponse(json_encode($body, JSON_THROW_ON_ERROR)),
        clefRequest(),
    ))->toThrow(DecisionResponseException::class, "usage field 'output_tokens'");
});

function clefRequestAdapter(): ClefRequestAdapter
{
    return new ClefRequestAdapter(new DecisionConfig(
        driver: 'clef',
        apiUrl: 'https://api.cloudflare.com/client/v4/accounts/acct/ai/',
        apiKey: 'synthetic-test-key',
        endpoint: '/run/@cf/cloudflare/{model}',
        model: 'clef-flash',
    ));
}

function clefRequest(): DecisionRequest
{
    return new DecisionRequest(
        input: 'Checkout has been failing for every customer for the last hour.',
        questions: Questions::of(
            new Noul('urgent', 'Is this support request urgent?'),
            new Choice(
                id: 'team',
                options: ChoiceOptions::of(
                    new ChoiceOption('billing', 'Payments'),
                    new ChoiceOption('technical', 'Outages and errors'),
                    new ChoiceOption('sales', 'Plans'),
                ),
                instructions: 'Which team should handle this request?',
            ),
            new Score(
                id: 'severity',
                levels: ScoreLevels::of('No impact', 'Minor', 'Major', 'Critical'),
                instructions: 'How severe is the customer impact?',
            ),
        ),
    );
}

function clefLiveBody(): string
{
    $json = file_get_contents(CLEF_LIVE_RESPONSE);
    if (! is_string($json)) {
        throw new RuntimeException('Clef live response fixture cannot be read.');
    }

    return $json;
}

function clefHttpResponse(string $body): HttpResponse
{
    return HttpResponse::sync(200, ['content-type' => 'application/json', 'cf-ray' => 'ray-1'], $body);
}
