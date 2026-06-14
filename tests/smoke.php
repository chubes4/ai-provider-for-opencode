<?php

declare(strict_types=1);

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value)
    {
        return $value;
    }
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/autoload.php';

use Chubes4\OpenCodeAiProvider\Models\OpenCodeTextGenerationModel;
use Chubes4\OpenCodeAiProvider\Metadata\OpenCodeModelMetadataDirectory;
use Chubes4\OpenCodeAiProvider\Provider\OpenCodeProvider;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;

$fixtureRoot = __DIR__ . '/fixtures/sandbox-state';
$configPath  = $fixtureRoot . '/mounted-opencode.json';
$dataHome    = $fixtureRoot . '/xdg-data-home';
$stateHome   = $fixtureRoot . '/xdg-state-home';
$emptyRoot   = sys_get_temp_dir() . '/ai-provider-for-opencode-empty-' . getmypid();
mkdir($emptyRoot . '/config', 0777, true);
mkdir($emptyRoot . '/data', 0777, true);
mkdir($emptyRoot . '/state', 0777, true);
mkdir($emptyRoot . '/home', 0777, true);
mkdir($emptyRoot . '/jsonc-config/opencode', 0777, true);

putenv('OPENCODE_CONFIG=' . $configPath);
putenv('HOME=' . $emptyRoot . '/home');
putenv('XDG_CONFIG_HOME=' . $emptyRoot . '/config');
putenv('XDG_DATA_HOME=' . $dataHome);
putenv('XDG_STATE_HOME=' . $stateHome);

final class CapturingTransporter implements HttpTransporterInterface
{
    public ?Request $request = null;
    /** @var list<string> */
    public array $uris = [];

    public function send(Request $request, ?RequestOptions $options = null): Response
    {
        $this->request = $request;
        $this->uris[] = $request->getUri();

        if (substr($request->getUri(), -7) === '/models') {
            $models = strpos($request->getUri(), '/zen/go/') !== false
                ? [['id' => 'kimi-k2.6'], ['id' => 'deepseek-v4-flash']]
                : [['id' => 'kimi-k2.6'], ['id' => 'qwen3.6-plus'], ['id' => 'gpt-5.5']];

            return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'object' => 'list',
                'data' => $models,
            ], JSON_THROW_ON_ERROR));
        }

        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'test-response',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'ok',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 1,
                'completion_tokens' => 1,
                'total_tokens' => 2,
            ],
        ], JSON_THROW_ON_ERROR));
    }
}

$metadata = OpenCodeProvider::metadata();
expect($metadata->getId() === 'opencode', 'Provider metadata ID should be opencode.');

$directory = OpenCodeProvider::modelMetadataDirectory();
$directory->setHttpTransporter(new CapturingTransporter());
$directory->setRequestAuthentication(new ApiKeyRequestAuthentication('test-key'));
$models    = $directory->listModelMetadata();
expect(count($models) === 9, 'Authenticated model listing should include live and local configured models.');
expect($directory->hasModelMetadata('opencode/kimi-k2.6'), 'Zen model metadata should be present.');
expect($directory->hasModelMetadata('opencode-go/kimi-k2.6'), 'Go model metadata should be present.');
expect($directory->hasModelMetadata('opencode-go/deepseek-v4-flash'), 'Go live model metadata should be present.');
expect($directory->hasModelMetadata('opencode/deterministic-provider/deterministic-v2'), 'Configured provider model metadata should be present.');
expect($directory->hasModelMetadata('opencode-go/local-go-model'), 'Configured Go model metadata should be present.');
expect($directory->hasModelMetadata('opencode/anthropic/claude-local'), 'Configured Anthropic model metadata should be present.');
expect($directory->hasModelMetadata('opencode/oauth-provider/oauth-model'), 'Configured OAuth provider model metadata should be present.');

expect(OpenCodeProvider::baseUrlForModel('opencode/kimi-k2.6') === OpenCodeProvider::ZEN_BASE_URL, 'Zen model should use Zen base URL.');
expect(OpenCodeProvider::baseUrlForModel('opencode-go/kimi-k2.6') === OpenCodeProvider::GO_BASE_URL, 'Go model should use Go base URL.');
expect(OpenCodeProvider::authSurfaceForModel('opencode/kimi-k2.6') === 'opencode', 'Bare Zen model should use OpenCode auth.');
expect(OpenCodeProvider::authSurfacesForModel('opencode/kimi-k2.6') === ['opencode', 'openai'], 'Bare Zen model should fall back to OpenAI auth.');
expect(OpenCodeProvider::authSurfaceForModel('opencode/deterministic-provider/deterministic-v2') === 'deterministic-provider', 'Configured provider model should use its provider auth.');
expect(OpenCodeProvider::authSurfaceForModel('opencode-go/kimi-k2.6') === 'opencode-go', 'Go model should use Go auth.');

$zenModel = OpenCodeProvider::model('opencode/kimi-k2.6');
$goModel  = OpenCodeProvider::model('opencode-go/kimi-k2.6');
expect($zenModel instanceof OpenCodeTextGenerationModel, 'Zen provider model should be an OpenCode text model.');
expect($goModel instanceof OpenCodeTextGenerationModel, 'Go provider model should be an OpenCode text model.');

$freshDirectory = new OpenCodeModelMetadataDirectory();
expect(count($freshDirectory->listModelMetadata()) === 4, 'Fresh directory should expose local configured models without static fallback models.');
expect($freshDirectory->hasModelMetadata('opencode-go/local-go-model'), 'Fresh directory should include local Go model metadata.');
expect(OpenCodeProvider::model('opencode-go/local-go-model') instanceof OpenCodeTextGenerationModel, 'Provider should create configured Go text model.');

putenv('OPENCODE_CONFIG=' . $emptyRoot . '/missing-opencode.json');
putenv('XDG_CONFIG_HOME=' . $emptyRoot . '/config');
putenv('XDG_DATA_HOME=' . $emptyRoot . '/data');
putenv('XDG_STATE_HOME=' . $emptyRoot . '/state');

$fallbackDirectory = new OpenCodeModelMetadataDirectory();
expect(count($fallbackDirectory->listModelMetadata()) === 0, 'Empty state should not expose static fallback models.');

file_put_contents($emptyRoot . '/jsonc-config/opencode/opencode.jsonc', <<<'JSONC'
{
    // OpenCode accepts provider/model pairs from configured providers.
    "provider": {
        "dynamic-provider": {
            "name": "Dynamic Provider",
            "models": {
                "dynamic-model": {
                    "name": "Dynamic Model",
                },
            },
        },
    },
    "model": "second-provider/second-model",
}
JSONC);
putenv('OPENCODE_CONFIG');
putenv('XDG_CONFIG_HOME=' . $emptyRoot . '/jsonc-config');

$jsoncDirectory = new OpenCodeModelMetadataDirectory();
expect(count($jsoncDirectory->listModelMetadata()) === 2, 'JSONC config should expose configured models dynamically.');
expect($jsoncDirectory->hasModelMetadata('opencode/dynamic-provider/dynamic-model'), 'JSONC provider model metadata should be present.');
expect($jsoncDirectory->hasModelMetadata('opencode/second-provider/second-model'), 'JSONC selected model metadata should be present.');

putenv('OPENCODE_CONFIG=' . $configPath);
putenv('XDG_CONFIG_HOME=' . $emptyRoot . '/config');
putenv('XDG_DATA_HOME=' . $dataHome);
putenv('XDG_STATE_HOME=' . $stateHome);

$transport = new CapturingTransporter();
$goModel->setHttpTransporter($transport);
$goModel->setRequestAuthentication(new ApiKeyRequestAuthentication('test-key'));
$goModel->generateTextResult([
    new Message(MessageRoleEnum::user(), [new MessagePart('hello')]),
]);

expect($transport->request instanceof Request, 'Explicit-auth model execution should send a request.');
expect($transport->request->getUri() === 'https://opencode.ai/zen/go/v1/chat/completions', 'Go model should use Go completions endpoint.');
expect($transport->request->getData()['model'] === 'kimi-k2.6', 'Go model request should use bare API model ID.');
expect($transport->request->getHeaderAsString('Authorization') === 'Bearer test-key', 'Explicit auth should be preserved.');

$localAuthModel = OpenCodeProvider::model('opencode-go/local-go-model');
$localAuthTransport = new CapturingTransporter();
$localAuthModel->setHttpTransporter($localAuthTransport);
$localAuthModel->generateTextResult([
    new Message(MessageRoleEnum::user(), [new MessagePart('hello')]),
]);

expect($localAuthTransport->request instanceof Request, 'Local-auth direct provider model execution should send a request.');
$authFixture = json_decode(file_get_contents($dataHome . '/opencode/auth.json'), true, 512, JSON_THROW_ON_ERROR);
$authorizationHash = hash('sha256', $localAuthTransport->request->getHeaderAsString('Authorization'));
$expectedAuthHash  = hash('sha256', 'Bearer ' . $authFixture['opencode-go']['key']);
expect($authorizationHash === $expectedAuthHash, 'Local-auth direct provider model execution should use mounted OpenCode auth state.');

$configuredAuthModel = OpenCodeProvider::model('opencode/deterministic-provider/deterministic-v2');
$configuredAuthTransport = new CapturingTransporter();
$configuredAuthModel->setHttpTransporter($configuredAuthTransport);
$configuredAuthModel->generateTextResult([
    new Message(MessageRoleEnum::user(), [new MessagePart('hello')]),
]);

expect($configuredAuthTransport->request instanceof Request, 'Configured provider model execution should send a request.');
$configuredAuthorizationHash = hash('sha256', $configuredAuthTransport->request->getHeaderAsString('Authorization'));
$expectedConfiguredAuthHash  = hash('sha256', 'Bearer ' . $authFixture['deterministic-provider']['key']);
expect($configuredAuthorizationHash === $expectedConfiguredAuthHash, 'Configured provider model execution should use provider-specific mounted auth state.');
expect($configuredAuthTransport->request->getData()['model'] === 'deterministic-provider/deterministic-v2', 'Configured provider model request should preserve provider-qualified API model ID.');

$oauthAuthModel = OpenCodeProvider::model('opencode/oauth-provider/oauth-model');
$oauthAuthTransport = new CapturingTransporter();
$oauthAuthModel->setHttpTransporter($oauthAuthTransport);
$oauthAuthModel->generateTextResult([
    new Message(MessageRoleEnum::user(), [new MessagePart('hello')]),
]);

expect($oauthAuthTransport->request instanceof Request, 'Configured OAuth provider model execution should send a request.');
$oauthAuthorizationHash = hash('sha256', $oauthAuthTransport->request->getHeaderAsString('Authorization'));
$expectedOauthAuthHash = hash('sha256', 'Bearer ' . $authFixture['oauth-provider']['access']);
expect($oauthAuthorizationHash === $expectedOauthAuthHash, 'Configured OAuth provider model execution should use mounted OpenCode access state.');

$zenFallbackModel = OpenCodeProvider::model('opencode/gpt-5.5');
$zenFallbackTransport = new CapturingTransporter();
$zenFallbackModel->setHttpTransporter($zenFallbackTransport);
$zenFallbackModel->generateTextResult([
    new Message(MessageRoleEnum::user(), [new MessagePart('hello')]),
]);

expect($zenFallbackTransport->request instanceof Request, 'Bare Zen model execution should send a request.');
$zenFallbackAuthorizationHash = hash('sha256', $zenFallbackTransport->request->getHeaderAsString('Authorization'));
$expectedZenFallbackAuthHash = hash('sha256', 'Bearer ' . $authFixture['openai']['access']);
expect($zenFallbackAuthorizationHash === $expectedZenFallbackAuthHash, 'Bare Zen model execution should fall back to mounted OpenAI access state.');
expect($zenFallbackTransport->request->getData()['model'] === 'gpt-5.5', 'Bare Zen model request should send the OpenCode API model ID.');

$registryTransport = new CapturingTransporter();
$registry = new ProviderRegistry();
$registry->setHttpTransporter($registryTransport);
$registry->registerProvider(OpenCodeProvider::class);
$registryModel = $registry->getProviderModel('opencode', 'opencode-go/local-go-model');
$registryModel->generateTextResult([
    new Message(MessageRoleEnum::user(), [new MessagePart('hello')]),
]);

expect($registryTransport->request instanceof Request, 'Registry-created provider model execution should send a request.');
$registryAuthorizationHash = hash('sha256', $registryTransport->request->getHeaderAsString('Authorization'));
expect($registryAuthorizationHash === $expectedAuthHash, 'Registry-created provider model execution should use mounted OpenCode auth state.');

$aiClientTransport = new CapturingTransporter();
$aiClientRegistry = new ProviderRegistry();
$aiClientRegistry->setHttpTransporter($aiClientTransport);
$aiClientRegistry->registerProvider(OpenCodeProvider::class);
AiClient::generateTextResult(
    [new Message(MessageRoleEnum::user(), [new MessagePart('hello')])],
    OpenCodeProvider::model('opencode-go/local-go-model'),
    $aiClientRegistry
);

expect($aiClientTransport->request instanceof Request, 'AiClient provider model execution should send a request.');
$aiClientAuthorizationHash = hash('sha256', $aiClientTransport->request->getHeaderAsString('Authorization'));
expect($aiClientAuthorizationHash === $expectedAuthHash, 'AiClient provider model execution should use mounted OpenCode auth state.');

fwrite(STDOUT, "OpenCode provider smoke passed.\n");
