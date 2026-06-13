<?php

declare(strict_types=1);

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value)
    {
        return $value;
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/autoload.php';

use Chubes4\OpenCodeAiProvider\Models\OpenCodeTextGenerationModel;
use Chubes4\OpenCodeAiProvider\Metadata\OpenCodeModelMetadataDirectory;
use Chubes4\OpenCodeAiProvider\Provider\OpenCodeProvider;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
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

putenv('OPENCODE_CONFIG=' . $configPath);
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
                : [['id' => 'kimi-k2.6'], ['id' => 'qwen3.6-plus']];

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
assert($metadata->getId() === 'opencode');

$directory = OpenCodeProvider::modelMetadataDirectory();
$directory->setHttpTransporter(new CapturingTransporter());
$directory->setRequestAuthentication(new ApiKeyRequestAuthentication('test-key'));
$models    = $directory->listModelMetadata();
assert(count($models) === 7);
assert($directory->hasModelMetadata('opencode/kimi-k2.6'));
assert($directory->hasModelMetadata('opencode-go/kimi-k2.6'));
assert($directory->hasModelMetadata('opencode-go/deepseek-v4-flash'));
assert($directory->hasModelMetadata('opencode/deterministic-provider/deterministic-v2'));
assert($directory->hasModelMetadata('opencode-go/local-go-model'));
assert($directory->hasModelMetadata('opencode/anthropic/claude-local'));

assert(OpenCodeProvider::baseUrlForModel('opencode/kimi-k2.6') === OpenCodeProvider::ZEN_BASE_URL);
assert(OpenCodeProvider::baseUrlForModel('opencode-go/kimi-k2.6') === OpenCodeProvider::GO_BASE_URL);

$zenModel = OpenCodeProvider::model('opencode/kimi-k2.6');
$goModel  = OpenCodeProvider::model('opencode-go/kimi-k2.6');
assert($zenModel instanceof OpenCodeTextGenerationModel);
assert($goModel instanceof OpenCodeTextGenerationModel);

$freshDirectory = new OpenCodeModelMetadataDirectory();
assert(count($freshDirectory->listModelMetadata()) === 17);
assert($freshDirectory->hasModelMetadata('opencode/kimi-k2.6'));
assert($freshDirectory->hasModelMetadata('opencode-go/kimi-k2.6'));
assert($freshDirectory->hasModelMetadata('opencode-go/local-go-model'));
assert(OpenCodeProvider::model('opencode-go/local-go-model') instanceof OpenCodeTextGenerationModel);

putenv('OPENCODE_CONFIG=' . $emptyRoot . '/missing-opencode.json');
putenv('XDG_CONFIG_HOME=' . $emptyRoot . '/config');
putenv('XDG_DATA_HOME=' . $emptyRoot . '/data');
putenv('XDG_STATE_HOME=' . $emptyRoot . '/state');

$fallbackDirectory = new OpenCodeModelMetadataDirectory();
assert(count($fallbackDirectory->listModelMetadata()) === 14);
assert($fallbackDirectory->hasModelMetadata('opencode/kimi-k2.6'));
assert($fallbackDirectory->hasModelMetadata('opencode-go/deepseek-v4-flash'));

putenv('OPENCODE_CONFIG=' . $configPath);
putenv('XDG_DATA_HOME=' . $dataHome);
putenv('XDG_STATE_HOME=' . $stateHome);

$transport = new CapturingTransporter();
$goModel->setHttpTransporter($transport);
$goModel->setRequestAuthentication(new ApiKeyRequestAuthentication('test-key'));
$goModel->generateTextResult([
    new Message(MessageRoleEnum::user(), [new MessagePart('hello')]),
]);

assert($transport->request instanceof Request);
assert($transport->request->getUri() === 'https://opencode.ai/zen/go/v1/chat/completions');
assert($transport->request->getData()['model'] === 'kimi-k2.6');
assert($transport->request->getHeaderAsString('Authorization') === 'Bearer test-key');

$localAuthModel = OpenCodeProvider::model('opencode-go/local-go-model');
$localAuthTransport = new CapturingTransporter();
$localAuthModel->setHttpTransporter($localAuthTransport);
$localAuthModel->generateTextResult([
    new Message(MessageRoleEnum::user(), [new MessagePart('hello')]),
]);

assert($localAuthTransport->request instanceof Request);
$authFixture = json_decode(file_get_contents($dataHome . '/opencode/auth.json'), true, 512, JSON_THROW_ON_ERROR);
$authorizationHash = hash('sha256', $localAuthTransport->request->getHeaderAsString('Authorization'));
$expectedAuthHash  = hash('sha256', 'Bearer ' . $authFixture['opencode-go']['key']);
assert($authorizationHash === $expectedAuthHash);

fwrite(STDOUT, "OpenCode provider smoke passed.\n");
