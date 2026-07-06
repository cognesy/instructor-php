window.ARCH = window.ARCH || {};
window.ARCH.pr = {
  "label": "commit 7ed4a53",
  "title": "feat: expand symfony support and restore qa coverage",
  "add": 8014,
  "del": 908,
  "files": 104,
  "nodes": {
    "root-docs": {
      "files": 3,
      "add": 71,
      "del": 1,
      "paths": [
        "docs/mint.json",
        "docs/mkdocs.yml.template",
        "docs/packages.md"
      ]
    },
    "pkg-addons": {
      "files": 1,
      "add": 37,
      "del": 18,
      "paths": [
        "packages/addons/docs/agent_hooks.md"
      ]
    },
    "pkg-hub": {
      "files": 1,
      "add": 2,
      "del": 2,
      "paths": [
        "packages/hub/docs/adding-examples.md"
      ]
    },
    "pkg-laravel": {
      "files": 7,
      "add": 522,
      "del": 431,
      "paths": [
        "packages/laravel/docs/agents.md",
        "packages/laravel/docs/configuration.md",
        "packages/laravel/docs/events.md",
        "packages/laravel/docs/native-agents.md",
        "packages/laravel/docs/response-models.md",
        "packages/laravel/docs/testing.md",
        "packages/laravel/docs/troubleshooting.md"
      ]
    },
    "pkg-logging": {
      "files": 7,
      "add": 201,
      "del": 12,
      "paths": [
        "packages/logging/README.md",
        "packages/logging/src/Enrichers/TelemetryCorrelationEnricher.php",
        "packages/logging/src/Factories/SymfonyLoggingFactory.php",
        "packages/logging/src/Integrations/Symfony/DependencyInjection/Configuration.php",
        "packages/logging/src/Integrations/Symfony/DependencyInjection/InstructorLoggingExtension.php",
        "packages/logging/tests/Regression/SymfonyLoggingFactoryRegressionTest.php",
        "packages/logging/tests/Unit/Integrations/Symfony/InstructorLoggingExtensionWiringTest.php"
      ]
    },
    "pkg-sandbox": {
      "files": 2,
      "add": 35,
      "del": 29,
      "paths": [
        "packages/sandbox/docs/3-execution-policy.md",
        "packages/sandbox/docs/7-troubleshooting.md"
      ]
    },
    "pkg-symfony": {
      "files": 56,
      "add": 5148,
      "del": 401,
      "paths": [
        "packages/symfony/CHEATSHEET.md",
        "packages/symfony/README.md",
        "packages/symfony/composer.json",
        "packages/symfony/docs/_meta.yaml",
        "packages/symfony/docs/configuration.md",
        "packages/symfony/docs/delivery.md",
        "packages/symfony/docs/logging.md",
        "packages/symfony/docs/migration.md",
        "packages/symfony/docs/operations.md",
        "packages/symfony/docs/overview.md",
        "packages/symfony/docs/quickstart.md",
        "packages/symfony/docs/runtime-surfaces.md",
        "packages/symfony/docs/sessions.md",
        "packages/symfony/docs/telemetry.md",
        "packages/symfony/docs/testing.md",
        "packages/symfony/phpunit.xml",
        "packages/symfony/resources/config/agents.yaml",
        "packages/symfony/resources/config/delivery.yaml",
        "packages/symfony/resources/config/logging.yaml",
        "packages/symfony/resources/config/messenger.yaml",
        "packages/symfony/resources/config/sessions.yaml",
        "packages/symfony/resources/config/telemetry.yaml",
        "packages/symfony/src/Agents/AgentRegistryTags.php",
        "packages/symfony/src/Agents/SchemaRegistration.php",
        "packages/symfony/src/InstructorSymfonyBundle.php",
        "packages/symfony/tests/Feature/AdvancedRuntimeTestingHelpersTest.php",
        "packages/symfony/tests/Feature/AgentCtrlBindingsTest.php",
        "packages/symfony/tests/Feature/AgentCtrlConfigTreeTest.php",
        "packages/symfony/tests/Feature/AgentCtrlContinuationBindingsTest.php",
        "packages/symfony/tests/Feature/AgentCtrlRuntimeBindingsTest.php",
        "packages/symfony/tests/Feature/BundleSurfaceTest.php",
        "packages/symfony/tests/Feature/ConfigProviderTest.php",
        "packages/symfony/tests/Feature/CoreBindingsTest.php",
        "packages/symfony/tests/Feature/CoreServiceFakesTest.php",
        "packages/symfony/tests/Feature/DeliveryConfigTreeTest.php",
        "packages/symfony/tests/Feature/LoggingBindingsTest.php",
        "packages/symfony/tests/Feature/MessengerBindingsTest.php",
        "packages/symfony/tests/Feature/NativeAgentBindingsTest.php",
        "packages/symfony/tests/Feature/ProgressBindingsTest.php",
        "packages/symfony/tests/Feature/SessionPersistenceBindingsTest.php",
        "packages/symfony/tests/Feature/TelemetryBindingsTest.php",
        "packages/symfony/tests/Feature/TelemetryConfigTreeTest.php",
        "packages/symfony/tests/Pest.php",
        "packages/symfony/tests/Support/EmbeddingsFakeRuntime.php",
        "packages/symfony/tests/Support/InferenceFakeRuntime.php",
        "packages/symfony/tests/Support/MockHttpClientFactory.php",
        "packages/symfony/tests/Support/RecordingTelemetryExporter.php",
        "packages/symfony/tests/Support/ScriptedAgentLoopFactory.php",
        "packages/symfony/tests/Support/StructuredOutputFakeRuntime.php",
        "packages/symfony/tests/Support/SymfonyCoreServiceOverrides.php",
        "packages/symfony/tests/Support/SymfonyNativeAgentOverrides.php",
        "packages/symfony/tests/Support/SymfonyTelemetryServiceOverrides.php",
        "packages/symfony/tests/Support/SymfonyTestApp.php",
        "packages/symfony/tests/Support/SymfonyTestLogger.php",
        "packages/symfony/tests/Support/SymfonyTestServiceRegistry.php",
        "packages/symfony/tests/Support/TestKernel.php"
      ]
    },
    "mod-symfony-src-delivery": {
      "files": 14,
      "add": 495,
      "del": 0,
      "paths": [
        "packages/symfony/src/Delivery/Cli/SymfonyCliObservationFormatter.php",
        "packages/symfony/src/Delivery/Cli/SymfonyCliObservationPrinter.php",
        "packages/symfony/src/Delivery/Messenger/ExecuteAgentCtrlPromptMessage.php",
        "packages/symfony/src/Delivery/Messenger/ExecuteAgentCtrlPromptMessageHandler.php",
        "packages/symfony/src/Delivery/Messenger/ExecuteNativeAgentPromptMessage.php",
        "packages/symfony/src/Delivery/Messenger/ExecuteNativeAgentPromptMessageHandler.php",
        "packages/symfony/src/Delivery/Messenger/MessengerObservationBridge.php",
        "packages/symfony/src/Delivery/Messenger/RuntimeObservationMessage.php",
        "packages/symfony/src/Delivery/Progress/Contracts/CanHandleProgressUpdates.php",
        "packages/symfony/src/Delivery/Progress/ProgressEventDispatcher.php",
        "packages/symfony/src/Delivery/Progress/RuntimeProgressBridge.php",
        "packages/symfony/src/Delivery/Progress/RuntimeProgressProjector.php",
        "packages/symfony/src/Delivery/Progress/RuntimeProgressStatus.php",
        "packages/symfony/src/Delivery/Progress/RuntimeProgressUpdate.php"
      ]
    },
    "mod-symfony-src-dependencyinjection": {
      "files": 8,
      "add": 1024,
      "del": 14,
      "paths": [
        "packages/symfony/src/DependencyInjection/Compiler/RegisterNativeAgentContributionsPass.php",
        "packages/symfony/src/DependencyInjection/Compiler/WiretapCliObservationPass.php",
        "packages/symfony/src/DependencyInjection/Compiler/WiretapLoggingEventBusPass.php",
        "packages/symfony/src/DependencyInjection/Compiler/WiretapMessengerObservationPass.php",
        "packages/symfony/src/DependencyInjection/Compiler/WiretapProgressEventBusPass.php",
        "packages/symfony/src/DependencyInjection/Compiler/WiretapTelemetryEventBusPass.php",
        "packages/symfony/src/DependencyInjection/Configuration.php",
        "packages/symfony/src/DependencyInjection/InstructorSymfonyExtension.php"
      ]
    },
    "mod-symfony-src-support": {
      "files": 1,
      "add": 131,
      "del": 0,
      "paths": [
        "packages/symfony/src/Support/SymfonyLoggingFactory.php"
      ]
    },
    "mod-symfony-src-telemetry": {
      "files": 4,
      "add": 348,
      "del": 0,
      "paths": [
        "packages/symfony/src/Telemetry/NullTelemetryExporter.php",
        "packages/symfony/src/Telemetry/SymfonyTelemetryFactory.php",
        "packages/symfony/src/Telemetry/TelemetryLifecycleSubscriber.php",
        "packages/symfony/src/Telemetry/TelemetryObservationBridge.php"
      ]
    }
  },
  "contains": [
    "pkg-symfony"
  ]
};
