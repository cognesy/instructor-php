# Layer/Context: Typed Wiring + PSR Interop

## Overview

- Context/Layer provides a tiny, immutable, strongly-typed wiring mechanism inspired by Effect-TS Layers.
- Keeps core code container-free and explicit; integrates with PSR-11 via thin adapters when needed.

## Key Concepts

- Context: immutable map of class-string<T> to object instances with generics and runtime checks.
- Layer: composable builder that produces a Context; supports factories and composition (dependsOn, merge, referredBy).
- Key<T>: typed token for qualified bindings (multiple implementations of the same interface) without arrays.

Context at a glance
- Holds stable services only (HttpClient, LoggerInterface, ClockInterface, CanProvideConfig, CanHandleEvents).
- No runtime payloads or request-scoped data; pass those as method parameters or DTOs.
- Constructed at composition roots; passed to code that needs to create concrete service instances.

## Type Safety Mechanisms

- Generics: `Context::with/get/has`, `Layer::provides/providesFrom`, and `Key<T>` use PHPDoc generics so static analyzers infer types.
- Runtime checks: `with()` and `withKey()` validate instanceof and throw TypeError on mismatch.
- Monadic lookup: `Context::tryGet(string $class): Result<T, MissingService>` returns `Success(value)` or `Failure(MissingService)` for exception-free control flow.
- `MissingServiceException`: `Context::get` throws a domain-specific `MissingServiceException` for absent bindings.

## API Summary

### Context

  - `with(class-string<T> $class, T $service): self`
  - `get(class-string<T> $class): T`
  - `tryGet(class-string<T> $class): Result<T, MissingService>`
  - `has(class-string<T> $class): bool`
  - `merge(Context $other): self`
  - `withKey(Key<T> $key, T $service): self`
  - `getKey(Key<T> $key): T`

### Layer
  - `provides(class-string<T> $class, T $service): Layer`
  - `providesFrom(class-string<T> $class, callable(Context): T $factory): Layer`
  - `providesKey(Key<T> $key, T $service): Layer`
  - `providesFromKey(Key<T> $key, callable(Context): T $factory): Layer`
  - `dependsOn(Layer $other): Layer`
  - `referredBy(Layer $other): Layer`
  - `merge(Layer $other): Layer` (right-bias)

### Key<T>
  - `properties: id (string), type (class-string<T>)`
  - `static of(string $id, class-string<T> $type): Key<T>`

## Usage Patterns

### Single binding

```php
$ctx = Context::empty()->with(Foo::class, new Foo());
$foo = $ctx->get(Foo::class); // inferred Foo
```

### Qualified bindings

```php
$driverKey = Key::of('http.driver.guzzle', CanHandleHttpRequest::class);
$ctx = Context::empty()->withKey($driverKey, $guzzleDriver);
$driver = $ctx->getKey($driverKey); // inferred CanHandleHttpRequest
```

### Layer composition

```php
$httpLayer = Layer::providesFrom(
    HttpClient::class,
    fn(Context $c): HttpClient => (new HttpClientBuilder(
        events: $c->get(Psr\EventDispatcher\EventDispatcherInterface::class),
        configProvider: $c->get(Cognesy\Config\Contracts\CanProvideConfig::class),
    )
)->create());
$ctx = $httpLayer->applyTo(Context::empty());
```

### Building a Context (Bootstraps)

Minimal standalone bootstrap

```php
use Cognesy\Utils\Context\Context;
use Cognesy\Utils\Context\Layer;
use Cognesy\Config\{ConfigResolver, ConfigPresets};
use Cognesy\Events\Dispatchers\EventDispatcher;

$base = Layer::provides(Psr\EventDispatcher\EventDispatcherInterface::class, new EventDispatcher('app'))
  ->merge(Layer::provides(Cognesy\Config\Contracts\CanProvideConfig::class, ConfigResolver::default()))
  ->merge(Layer::providesFrom(ConfigPresets::class, fn($c) => ConfigPresets::using($c->get(Cognesy\Config\Contracts\CanProvideConfig::class))));

$http = Layer::providesFrom(Cognesy\Http\HttpClientBuilder::class, fn($c) => new Cognesy\Http\HttpClientBuilder(
    events: $c->get(Psr\EventDispatcher\EventDispatcherInterface::class),
    configProvider: $c->get(Cognesy\Config\Contracts\CanProvideConfig::class),
))->merge(Layer::providesFrom(Cognesy\Http\HttpClient::class, fn($c) => $c->get(Cognesy\Http\HttpClientBuilder::class)->create()));

$ctx = $base->merge($http)->applyTo(Context::empty());
```

Per-test context

```php
$testCtx = $base
  ->merge(Layer::provides(Psr\Log\LoggerInterface::class, new Psr\Log\NullLogger()))
  ->merge(Layer::provides(Cognesy\Utils\Time\ClockInterface::class, new Cognesy\Utils\Time\SystemClock()))
  ->applyTo(Context::empty());
```

Framework PSR-11 interop

```php
use Cognesy\Utils\Context\Psr\ContextContainer;

$ctx = /* build Context with layers */;
$psr = new ContextContainer($ctx, [ 'http.client.primary' => Cognesy\Http\HttpClient::class ]);
// Hand $psr to code expecting ContainerInterface
```

### Direct provision with provides()

Use an existing instance without a factory:

```php
$events = new Cognesy\Events\Dispatchers\EventDispatcher();
$layer = Layer::provides(Psr\EventDispatcher\EventDispatcherInterface::class, $events);
$ctx = $layer->applyTo(Context::empty());
$bus = $ctx->get(Psr\EventDispatcher\EventDispatcherInterface::class);
```

### Qualified provision with providesKey()/providesFromKey()

Bind multiple implementations of the same contract safely:

```php
use Cognesy\Utils\Context\Key;
use Cognesy\Http\Contracts\CanHandleHttpRequest;

$guzzleKey  = Key::of('http.driver.guzzle', CanHandleHttpRequest::class);
$symfonyKey = Key::of('http.driver.symfony', CanHandleHttpRequest::class);

$drivers = Layer::providesKey($guzzleKey, $guzzleDriver)
    ->merge(Layer::providesFromKey($symfonyKey, fn(Context $c): CanHandleHttpRequest => $symfonyDriverFactory()));

$ctx = $drivers->applyTo(Context::empty());
$g = $ctx->getKey($guzzleKey);
$s = $ctx->getKey($symfonyKey);
```

### Composition order: dependsOn vs referredBy

Both compose sequentially, but in reverse order:

```php
$base = Layer::provides(ClockInterface::class, new SystemClock());
$derived = Layer::providesFrom(SystemClock::class, fn(Context $c): SystemClock => $c->get(ClockInterface::class));

// other builds first, then this
$ctxA = $derived->dependsOn($base)->applyTo(Context::empty());

// this builds first, then other
$ctxB = $derived->referredBy($base)->applyTo(Context::empty());
```

### Merging and right-bias overrides

Later layers override earlier ones on duplicate keys/classes:

```php
$v1 = Layer::provides(LoggerInterface::class, $loggerA);
$v2 = Layer::provides(LoggerInterface::class, $loggerB);

$ctx = $v1->merge($v2)->applyTo(Context::empty());
// result: LoggerInterface resolves to $loggerB
```

### Exception-free lookup with tryGet()

Prefer Result over exceptions in control flow:

```php
$res = $ctx->tryGet(CacheInterface::class);
if ($res->isSuccess()) {
    $cache = $res->unwrap();
} else {
    // MissingService available via $res->exception()
}
```

### Test-specific contexts

Compose minimal contexts per test without global state:

```php
$testCtx = Layer::provides(ClockInterface::class, new FrozenClock('2025-01-01T00:00:00Z'))
    ->merge(Layer::provides(LoggerInterface::class, new NullLogger()))
    ->applyTo(Context::empty());

$sut = new Service(clock: $testCtx->get(ClockInterface::class), logger: $testCtx->get(LoggerInterface::class));
```

## Integration With PSR-11

### Context → PSR-11 (read-only view)

 - ContextContainer implements Psr\Container\ContainerInterface.
 - Resolves class-string IDs via Context::get.
 - Resolves qualified IDs via a supplied keyTypes map: [keyId => class-string<T>].
 - Throws standard NotFound and ContainerError exceptions.

### PSR-11 → typed access

 - TypedContainer wraps any ContainerInterface and enforces expected types on get(class-string<T>) and getKey(Key<T>).

## Examples

### Expose Context via PSR

```php
$ctx = Context::empty()->with(Foo::class, new Foo());
$psr = new ContextContainer($ctx); // ContainerInterface
$foo = $psr->get(Foo::class);
```

### Expose keyed binding via PSR

```php
$key = Key::of('clock.primary', ClockInterface::class);
$ctx = Context::empty()->withKey($key, new SystemClock());
$psr = new ContextContainer($ctx, [$key->id => $key->type]);
$clock = $psr->get('clock.primary');
```

### Typed facade over an arbitrary PSR container:

```php
$typed = new TypedContainer($psr);
$foo = $typed->get(Foo::class); // verified instance
$driver = $typed->getKey(Key::of('http.driver.guzzle', CanHandleHttpRequest::class));
```

## When To Use What

- Use Context/Layer for composing and testing services in a typed, immutable way.
- Use ContextContainer to hand a Context to code that expects PSR-11.
- Use TypedContainer to safely consume any PSR container while preserving type guarantees.

## Notes

- No autowiring or magic: all registrations are explicit and testable.
- Qualifiers via Key<T> prevent array-based multi-bindings and keep call sites type-safe.
- get() throws MissingService; tryGet() provides Result for alternative control flow without exceptions.

## Why Layer/Context Over External DI

- Strong typing and early feedback
  - Class-string generics and runtime instanceof checks provide compile-time signals and fail-fast errors; PSR-11 alone cannot.
  - Typed qualifiers (Key<T>) keep multiple implementations safe without arrays or stringly-typed lookups.
- Deterministic, explicit wiring
  - No autowiring or reflection — wiring lives in code you can test and version. Composition via `dependsOn/merge` is predictable and diffable.
  - Immutable contexts make it trivial to reason about scopes and isolate tests (each test composes its own Context).
- Smaller surface and faster tests
  - Core packages don’t depend on a container runtime; unit tests don’t boot a framework/container, reducing coupling and flakiness.
- Clear boundaries and portability
  - Works uniformly in scripts/CLI/examples and inside frameworks; you can build once with Layers and expose it as PSR-11 if needed.
- Same DI benefits, different ergonomics
  - Inversion of control: interfaces bound to implementations at composition time (Layers/Context).
  - Swappability: override via `merge` (right-bias) or additional Layers; mirrors container overrides.
  - Scoping: create per-request/per-test Contexts instead of global singletons.

### Interop with PSR DI containers

- Keep using your app container for everything else; integrate this library by:
  - Exposing the composed Context as a PSR-11 container via `ContextContainer` when the host expects PSR.
  - Consuming an existing PSR-11 container safely via `TypedContainer` (adds type checks on top of PSR get/has).
  - Mapping qualified bindings: use `Key::of($id, $type)` and provide `[$id => $type]` to `ContextContainer` so keys are available under stable PSR IDs.

This approach preserves DI’s core advantages (IoC, replaceability, scoping) while adding strong typing, immutability, and test-friendly composition — without forcing a specific container or framework into core packages.

## Codebase Integration Recipes (Instructor PHP)

The following blueprints map common patterns in this repo to Layer/Context usage. They are pragmatic and align with current package APIs.

### Factories and Builders

Prefer exposing typed Layers that assemble factory/builder instances, then pass those into services explicitly.

- HttpClientBuilder

```php
use Cognesy\Utils\Context\{Context, Layer};
use Cognesy\Config\Contracts\CanProvideConfig;
use Psr\EventDispatcher\EventDispatcherInterface;

$httpLayer = Layer::providesFrom(
    Cognesy\Http\HttpClientBuilder::class,
    fn(Context $c) => new Cognesy\Http\HttpClientBuilder(
        events: $c->get(EventDispatcherInterface::class),
        configProvider: $c->get(CanProvideConfig::class),
    )
);

$ctx = $httpLayer->applyTo(Context::empty());
$builder = $ctx->get(Cognesy\Http\HttpClientBuilder::class);
$client = $builder->create();
```

- HttpClientDriverFactory

```php
use Psr\EventDispatcher\EventDispatcherInterface;

$layer = Layer::providesFrom(
    Cognesy\Http\HttpClientDriverFactory::class,
    fn(Context $c) => new Cognesy\Http\HttpClientDriverFactory(
        $c->get(EventDispatcherInterface::class)
    )
);
```

Guideline: A “Factory” class should never reach into a global container; obtain collaborators via constructor (wired by Layer) or method parameters.

### ConfigResolver and CanProvideConfig

Many entry points accept `CanProvideConfig` and internally use `ConfigPresets::using($provider)`.

Blueprint layer:

```php
use Cognesy\Config\{ConfigResolver, ConfigPresets};
use Cognesy\Config\Contracts\CanProvideConfig;

$configLayer = Layer::provides(CanProvideConfig::class, ConfigResolver::default())
  ->merge(Layer::providesFrom(ConfigPresets::class, fn(Context $c) =>
      ConfigPresets::using($c->get(CanProvideConfig::class))
  ));
```

Usage in code:
- Prefer accepting `CanProvideConfig` in constructors of services that resolve presets.
- For classes that already accept a provider (e.g., HttpClientBuilder), rely on the layer to inject it.
- For scripts/examples, compose `ConfigResolver::default()` unless you need custom providers.

### EventBusResolver and CanHandleEvents

Code typically accepts `CanHandleEvents|EventDispatcherInterface` and normalizes via `EventBusResolver::using()`.

Blueprint layer:

```php
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Contracts\CanHandleEvents;
use Psr\EventDispatcher\EventDispatcherInterface;

$eventsLayer = Layer::provides(EventDispatcher::class, new EventDispatcher())
  ->merge(Layer::provides(EventDispatcherInterface::class, new EventDispatcher()))
  ->merge(Layer::provides(CanHandleEvents::class, new EventDispatcher()));
```

Guidelines:
- If your class raises or reacts to domain/infra events, accept `CanHandleEvents` (preferred) or `EventDispatcherInterface`.
- Don’t construct dispatchers inside services; wire them in via Layer for testability.

### HttpClient vs CanHandleHttpRequest (drivers)

HttpClient (facade) orchestrates driver + middleware + events; drivers implement `CanHandleHttpRequest` and execute requests.

When to depend on which:
- High-level services: depend on `Cognesy\Http\HttpClient` for a unified interface and middleware support.
- Low-level integrations or middleware internals: depend on `CanHandleHttpRequest` only if you need raw driver access (rare outside internals).

Blueprint: default client from layers

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientBuilder;

$clientLayer = Layer::providesFrom(HttpClient::class, fn(Context $c) =>
    $c->get(HttpClientBuilder::class)->create()
);

$ctx = $eventsLayer
    ->merge($configLayer)
    ->merge($httpLayer) // provides HttpClientBuilder
    ->merge($clientLayer)
    ->applyTo(Context::empty());

$http = $ctx->get(HttpClient::class);
```

Switching drivers via config presets

```php
// config group: http.defaultPreset / http.presets.{name}.driver = 'guzzle' | 'symfony' | 'laravel'
$client = $ctx->get(HttpClientBuilder::class)
    ->using('my-preset') // optional preset name
    ->create();
```

Supplying a pre-built client instance

```php
$client = $ctx->get(HttpClientBuilder::class)
    ->withClientInstance('symfony', \Symfony\Component\HttpClient\HttpClient::create())
    ->create();
```

Injecting a custom or mock driver

```php
// Custom driver instance
$client = $ctx->get(HttpClientBuilder::class)
    ->withDriver(new Cognesy\Http\Drivers\Mock\MockHttpDriver($ctx->get(Psr\EventDispatcher\EventDispatcherInterface::class)))
    ->create();

// Convenience: mock with expectations
$client = $ctx->get(HttpClientBuilder::class)
    ->withMock(function($mock) {
        $mock->expect('GET', 'https://example.com')->andReturn(200, 'ok');
    })
    ->create();
```

Multiple named clients (qualified bindings)

```php
use Cognesy\Utils\Context\Key;
use Cognesy\Http\HttpClient;

$primaryKey = Key::of('http.client.primary', HttpClient::class);
$backupKey  = Key::of('http.client.backup', HttpClient::class);

$clients = Layer::providesFromKey($primaryKey, fn(Context $c) =>
    $c->get(HttpClientBuilder::class)->using('primary')->create()
)->merge(Layer::providesFromKey($backupKey, fn(Context $c) =>
    $c->get(HttpClientBuilder::class)->using('backup')->create()
));

$ctx = $eventsLayer->merge($configLayer)->merge($httpLayer)->merge($clients)->applyTo(Context::empty());
$primary = $ctx->getKey($primaryKey);
$backup  = $ctx->getKey($backupKey);
```

Pooling

```php
$factory = $ctx->get(Cognesy\Http\HttpClientDriverFactory::class);
$pool = $factory->makePoolHandler(); // uses config-selected driver
```

### Tests: minimal and replaceable wiring

```php
use Psr\Log\NullLogger;

$testCtx = $eventsLayer
  ->merge($configLayer)
  ->merge(Layer::provides(Psr\Log\LoggerInterface::class, new NullLogger()))
  ->applyTo(Context::empty());

// Use mock driver
$http = $testCtx->get(Cognesy\Http\HttpClientBuilder::class)
    ->withMock(fn($m) => $m->expect('GET', '/ping')->andReturn(200, 'ok'))
    ->create();
```

### Config Presets & Settings (Root config/*.php)

Root `config/*.php` drive defaults for http, llm, embeddings, debug, prompt, structured. The `SettingsConfigProvider` used by `ConfigResolver::default()` reads these files.

- Layer wiring

```php
use Cognesy\Config\{ConfigResolver, ConfigPresets};
use Cognesy\Config\Contracts\CanProvideConfig;

$configLayer = Layer::provides(CanProvideConfig::class, ConfigResolver::default())
  ->merge(Layer::providesFrom(ConfigPresets::class, fn(Context $c) =>
      ConfigPresets::using($c->get(CanProvideConfig::class))
  ));
```

- Usage
  - Always inject `CanProvideConfig` into services that resolve presets.
  - Avoid calling `ConfigResolver::using()` inside consumers; compose in layer instead.
  - Use `ConfigPresets::using($cfg)->for('llm')->getOrDefault('openai')` to resolve preset data when needed in factories.

### Providers: LLMProvider/EmbeddingsProvider

Builder classes used to resolve configs and produce drivers.

- Layer wiring for LLMProvider

```php
use Cognesy\Polyglot\Inference\LLMProvider;

$llmProviderLayer = Layer::providesFrom(LLMProvider::class, fn(Context $c) =>
    LLMProvider::new(
        events: $c->get(Psr\EventDispatcher\EventDispatcherInterface::class),
        configProvider: $c->get(CanProvideConfig::class)
    )
);
```

- Layer wiring for EmbeddingsProvider

```php
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;

$embedProviderLayer = Layer::providesFrom(EmbeddingsProvider::class, fn(Context $c) =>
    EmbeddingsProvider::new(
        events: $c->get(Psr\EventDispatcher\EventDispatcherInterface::class) ?? null,
        configProvider: $c->get(CanProvideConfig::class)
    )
);
```

- Usage patterns
  - Prefer injecting providers (or fully built drivers) into higher-level services; avoid creating providers ad‑hoc.
  - For multiple named providers, add Keys: `Key::of('llm.provider.primary', LLMProvider::class)`; set presets via provider API before driver construction.

### Inference: InferenceDriverFactory, BaseInferenceDriver, CanHandleInference

Refactor wiring so Inference receives ready collaborators from layers.

- Layers

```php
use Cognesy\Http\HttpClient;use Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory;

$inferenceLayers = $eventsLayer
  ->merge($configLayer)
  ->merge($httpLayer) // provides HttpClientBuilder
  ->merge(Layer::providesFrom(HttpClient::class, fn(Context $c) => $c->get(Cognesy\Http\HttpClientBuilder::class)->create()))
  ->merge(Layer::providesFrom(InferenceDriverFactory::class, fn(Context $c) => new InferenceDriverFactory($c->get(Psr\EventDispatcher\EventDispatcherInterface::class))))
  ->merge($llmProviderLayer);
```

- Driver creation

```php
$provider = $ctx->get(Cognesy\Polyglot\Inference\LLMProvider::class)
    ->using('openai'); // or withDsn/withConfig
$config = $provider->resolveConfig();
$http = $ctx->get(Cognesy\Http\HttpClient::class);
$driver = $ctx->get(InferenceDriverFactory::class)->makeDriver($config, $http);
```

- Guidelines
  - Higher-level services should depend on `HttpClient` or a fully built `CanHandleInference`, not on `HttpClientBuilder`.
  - Keep `BaseInferenceDriver` internals unchanged; just ensure HttpClient/events are injected from Context.

### Templates: Template/TemplateProvider, CanHandleTemplate, CanRenderMessages

TemplateProvider resolves engine config (twig/blade/arrowpipe) and provides a driver implementing `CanHandleTemplate`.

- Layer wiring

```php
use Cognesy\Template\TemplateProvider;

$templateLayer = $configLayer->merge(Layer::providesFrom(TemplateProvider::class, fn(Context $c) =>
    new TemplateProvider(
        preset: '',
        config: null,
        driver: null,
        configProvider: $c->get(CanProvideConfig::class),
    )
));
```

- Using Template with Context

```php
$provider = $ctx->get(TemplateProvider::class);
$engine = $provider->withConfig($provider->config()); // or ->get('twig'|'blade' preset)
$text = $engine->renderFile('welcome.twig', ['name' => 'Ada']);
```

- Rendering messages

Template itself converts XML-like structures to `Messages`. If you have multiple renderers for `CanRenderMessages`, bind qualified keys:

```php
use Cognesy\Utils\Context\Key;
use Cognesy\Messages\Contracts\CanRenderMessages;

$roleRendererKey = Key::of('messages.renderer.role', CanRenderMessages::class);
$arrowpipeRendererKey = Key::of('messages.renderer.arrowpipe', CanRenderMessages::class);

$renderers = Layer::providesKey($roleRendererKey, new Cognesy\Template\Rendering\MessageToRoleStringRenderer())
  ->merge(Layer::providesKey($arrowpipeRendererKey, new Cognesy\Template\Rendering\ArrowpipeMessagesRenderer()));

$ctx = $templateLayer->merge($renderers)->applyTo(Context::empty());
```

## Design Guidance: Use at the Edges, Keep Classes Explicit

Layers/Context are composition tools — not runtime locators. Keep your classes free of Context/Layer and wire dependencies at the edges (bootstrap, adapters, tests).

- Do
  - Keep constructors explicit (accept concrete abstractions you need: `HttpClient`, `CanHandleEvents`, `CanProvideConfig`, etc.).
  - Compose dependencies via Layers once, and pass them into services; use Keys for multiple named instances.
  - Use in:
    - Composition roots (CLI, examples, app bootstrap),
    - Framework adapters (Laravel/Symfony service providers/bundles),
    - Tests (swap drivers, clocks, loggers quickly).
- Don’t
  - Don’t inject `Context`/`Layer` into services.
  - Don’t call `Context::get()` inside domain/service methods.
  - Don’t use Layers to hide poor cohesion (constructor "explosion").

### Anti-pattern vs Pattern

Anti-pattern (typed service locator):

```php
final class Foo {
    public function __construct(private Cognesy\Utils\Context\Context $ctx) {}
    public function doWork(): void {
        $http = $this->ctx->get(Cognesy\Http\HttpClient::class);
        // ...
    }
}
```

Pattern (explicit dependencies, wired externally):

```php
final class Foo {
    public function __construct(private Cognesy\Http\HttpClient $http) {}
    public function doWork(): void {
        $this->http->request('GET', '/health');
    }
}
// Wiring at the edge
$ctx = /* compose layers */;
$foo = new Foo($ctx->get(Cognesy\Http\HttpClient::class));
```

### Constructor Size: Alternatives Before Context

If a constructor grows too large, prefer design refactoring over hiding dependencies in a context:

- Cohesive ports/facades: group related behavior behind a small interface (e.g., `ModuleInfra` owning specific methods you need, backed by `Logger` + `Events`).
- Typed config objects: pass immutable options DTOs for optional parameters.
- Dedicated factories/builders: construct complex graphs outside the class; the class receives the ready collaborator.

### Effect-TS Intent (Mapped to PHP)

Layers in Effect-TS compose environments for effects; they reduce dependency threading without introducing globals. The consumer still declares required services in types; wiring stays external. In PHP, mirror this by:

- Keeping class dependencies explicit and typed.
- Using Layer/Context to assemble those dependencies once.
- Avoiding global lookups; prefer passing collaborators explicitly.

## Where To Draw The Line (Composition Roots)

Keep classes explicit; compose implementations at the edges. Use Context/Layer only in composition roots for a given scope:

- CLI/examples: a small bootstrap building layers and the service graph.
- Framework adapters: ServiceProvider/Bundle wires PSR DI; adapt to/from Context with the PSR bridges.
- Tests: compose minimal contexts per test; override using provides/merge and Keys.

Runtime data vs services:
- Pass runtime/ephemeral values (requests, DSNs, payloads) as method parameters or immutable config DTOs.
- Keep Context for stable services (HttpClient, LoggerInterface, ClockInterface, CanProvideConfig, CanHandleEvents).

### What Feeds the Layers (Three Patterns)

- Upstream via Context
  - Compose foundational layers first (events, config, clock, logger), then module layers that read them via `Context::get()` inside layer factories.
  - Order with `dependsOn/merge` so prerequisites exist before modules.
- Parameterized layer factories (preferred for clarity)
  - Expose functions that take a few explicit ports and return a Layer, e.g. `HttpClientLayer::default(CanProvideConfig $cfg, EventDispatcherInterface $events): Layer`.
  - Keeps inputs visible and testable; avoids hidden coupling.
- PSR bridge
  - If a host app has PSR DI, read needed ports via `TypedContainer` and build Context, or expose Context as PSR via `ContextContainer`.

### Do / Don’t

- Do
  - Keep constructors explicit with cohesive dependencies (interfaces/ports).
  - Compose once at the edge and pass dependencies in; use Key<T> for multiple named instances.
  - Use typed config DTOs for options; use factories/builders for complex graphs.
- Don’t
  - Don’t inject Context/Layer into services.
  - Don’t call `Context::get()` inside domain/service methods.
  - Don’t stash runtime payloads in Context.

### Checklist: Adding a Module

- Define contracts your services need (ports) and their minimal upstream dependencies (events, config, logger, clock).
- Provide a parameterized module Layer that:
  - Accepts those ports (or reads them if already composed),
  - Binds contracts → implementations for the module,
  - Exposes optional qualified bindings via Key<T> where useful.
- Document module “requires” and “provides” in README/CHEATSHEET.

### Recommended Defaults (This Repo)

- Foundational layers
  - events: `EventDispatcherInterface` → `EventDispatcher`
  - config: `CanProvideConfig` → `ConfigResolver::default`, plus `ConfigPresets::using()`
  - utils: `ClockInterface` → `SystemClock`, optional `LoggerInterface` → `NullLogger`
- HTTP client
  - Provide `HttpClientBuilder` (requires events + config) and optionally `HttpClient`.
  - Use Keys for named clients: `http.client.primary`, `http.client.backup`.
- Experimental pipeline
  - Wire `ExecutionPolicies`, `DefaultExecutor`, `EventDispatcherInterface`, and operator collaborators via layers; keep the core loop free of context lookups.

## Externalizing Resolution (Replacing “Resolver” Paradigms)

Layer/Context lets you move normalization/selection work to composition time so consumers receive a single, stable contract rather than unions/nulls.

### Events: EventBusResolver → Layer wiring

- Purpose today: accept `null|CanHandleEvents|EventDispatcherInterface` and normalize.
- With Layers: bind a concrete dispatcher in a layer and inject `CanHandleEvents` directly.
- Guidance:
  - New/refactored classes: accept `CanHandleEvents` (or `EventDispatcherInterface`) only.
  - Keep `EventBusResolver` as a boundary/legacy adapter in public factories for BC, but avoid using it inside internals when layering is available.

### Config: Keep ConfigResolver, externalize its assembly

- `ConfigResolver` is a real service (provider chain, caching, fallback). Do not remove it.
- With Layers: decide which providers compose the resolver, bind `CanProvideConfig`, and inject that into consumers.
- Guidance:
  - New/refactored classes: accept `CanProvideConfig`; avoid calling `ConfigResolver::using()` inside consumers.
  - Compose `ConfigResolver::default()` (or a custom chain) in a layer; provide `ConfigPresets::using($cfg)` there as well.

### Practical migration

1) Stage 1: Introduce layers that provide `CanHandleEvents` and `CanProvideConfig`; wire consumers through layers (resolvers become no-ops in practice).
2) Stage 2: For new entry points, drop union/nullable constructor params; accept a single contract and document the layer recipe.
3) Stage 3: Optionally deprecate `EventBusResolver::using` in internals; keep `ConfigResolver` as the injected provider chain.

### Before/After (conceptual)

- Before (normalizing inside constructor):
  - `new HttpClientBuilder(events: null|CanHandleEvents|EventDispatcherInterface, configProvider: ?CanProvideConfig)` → calls resolvers internally.
- After (normalized by layer):
  - Layer binds `EventDispatcherInterface` and `CanProvideConfig` → `new HttpClientBuilder(events: $events, configProvider: $cfg)`.

## Applying Layer/Context to Experimental Pipeline

The experimental pipeline engine (packages/pipeline/src/Experimental) is orthogonal to DI — it defines execution semantics (policies, operators, events). Layer/Context complements it by wiring policies, executors, event bus, and operator dependencies in a typed, testable way.

### What to wire with Layers

- Policies
  - ContinuationPolicy<TState>, OutcomePolicy<TState>, ErrorHandlingPolicy<TState>
  - Bundle via ExecutionPolicies<TState> for your state
- Executor
  - DefaultExecutor (or a custom Executor)
- Events
  - PSR-14 dispatcher (from events package)
- Operator dependencies
  - Operators should accept collaborators in constructors (e.g., HttpClient, LoggerInterface, ClockInterface); assemble via Layers

### Policy wiring blueprint

```php
use Cognesy\Pipeline\Experimental\Core\ExecutionPolicies;
use Cognesy\Pipeline\Experimental\Policies\Continuation\AlwaysContinuePolicy;
use Cognesy\Pipeline\Experimental\Policies\Outcome\OutcomeIdentityPolicy;
use Cognesy\Pipeline\Experimental\Policies\ExecutionError\FailFastErrorHandling;
use Cognesy\Utils\Context\{Context, Layer};

/** @var Layer $policyLayer */
$policyLayer = Layer::providesFrom(
    ExecutionPolicies::class,
    fn(Context $c) => new ExecutionPolicies(
        continue: new AlwaysContinuePolicy(),
        outcome: new OutcomeIdentityPolicy(),
        errors: new FailFastErrorHandling(), // or SimpleRetryErrorHandling(...)
    )
);
```

For multiple named policies, use Key<T>:

```php
use Cognesy\Utils\Context\Key;

$fastFailKey = Key::of('pipeline.policies.fastfail', ExecutionPolicies::class);
$retryKey    = Key::of('pipeline.policies.retry', ExecutionPolicies::class);

$policies = Layer::providesFromKey($fastFailKey, fn(Context $c) => new ExecutionPolicies(/* fail-fast */))
  ->merge(Layer::providesFromKey($retryKey, fn(Context $c) => new ExecutionPolicies(/* retry */)));
```

### Executor and events wiring

```php
use Cognesy\Pipeline\Experimental\Executors\DefaultExecutor;
use Psr\EventDispatcher\EventDispatcherInterface;

$execLayer = Layer::provides(DefaultExecutor::class, new DefaultExecutor());
$eventsLayer = Layer::provides(EventDispatcherInterface::class, new Cognesy\Events\Dispatchers\EventDispatcher('pipeline'));
```

### Operators: compose with explicit dependencies

Operators should declare their collaborators:

```php
final class FetchRemoteOp implements Cognesy\Pipeline\Experimental\Contracts\Operator {
    public function __construct(private Cognesy\Http\HttpClient $http) {}
    public function process(mixed $state): mixed { /* use $this->http */ }
}

$opsLayer = Layer::providesFrom(Cognesy\Pipeline\Experimental\Core\OperatorStack::class,
    fn(Context $c) => new Cognesy\Pipeline\Experimental\Core\OperatorStack(
        new FetchRemoteOp($c->get(Cognesy\Http\HttpClient::class)),
        new Cognesy\Pipeline\Experimental\Operators\Tap(fn($s) => $c->get(Psr\Log\LoggerInterface::class)->info('step')),
    )
);
```

### Building a Pipeline via Layers

```php
use Cognesy\Pipeline\Experimental\Pipeline;

$ctx = $eventsLayer
  ->merge($execLayer)
  ->merge($policyLayer)
  ->merge($opsLayer)
  ->applyTo(Context::empty());

$pipeline = new Pipeline(
  $ctx->get(Cognesy\Pipeline\Experimental\Core\OperatorStack::class),
  $ctx->get(ExecutionPolicies::class),
  $ctx->get(Psr\EventDispatcher\EventDispatcherInterface::class),
  $ctx->get(Cognesy\Pipeline\Experimental\Executors\DefaultExecutor::class)
);

$result = $pipeline->run($initialState);
```

### Configuration-driven policies

Use `CanProvideConfig` to parameterize policies without hardcoding:

```php
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Pipeline\Experimental\Policies\ExecutionError\SimpleRetryErrorHandling;

$retryPolicies = Layer::providesFrom(ExecutionPolicies::class, function (Context $c) {
    $cfg = $c->get(CanProvideConfig::class);
    $attempts = (int) ($cfg->get('pipeline.retry.maxAttempts') ?? 3);
    $delayMs  = (int) ($cfg->get('pipeline.retry.initialDelayMs') ?? 100);
    return new ExecutionPolicies(
        continue: new AlwaysContinuePolicy(),
        errors: new SimpleRetryErrorHandling(maxAttempts: $attempts, initialDelayMs: $delayMs, exponential: true),
    );
});
```

### Testing

- Provide a FrozenClock and NullLogger via Layers for deterministic tests.
- Swap `ErrorHandlingPolicy` to `FailFastErrorHandling` or a test `SimpleRetryErrorHandling` with short delays.
- Avoid `usleep` in tests by abstracting sleep (see below).

### Small improvement suggestion

Avoid `usleep` in DefaultExecutor; introduce a tiny `SleeperInterface { public function sleepMs(int $ms): void; }` in the pipeline experimental package. Wire a `SystemSleeper` in Layers and use a no-op or fast-sleeper in tests. This keeps policies/executor test-friendly and aligns with our design principles (inject, don’t hardcode globals).

### When Layer/Context doesn’t apply

The pipeline’s core algorithm (policy decisions, operator loop) is not a DI concern. Layer/Context should not leak into that logic. Its role is wiring: choosing policies, providing operators’ dependencies, and exposing the event bus/clock/logger.
