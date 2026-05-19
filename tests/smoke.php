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
use Chubes4\OpenCodeAiProvider\Provider\OpenCodeProvider;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;

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
assert(count($models) === 4);
assert($directory->hasModelMetadata('opencode/kimi-k2.6'));
assert($directory->hasModelMetadata('opencode-go/kimi-k2.6'));
assert($directory->hasModelMetadata('opencode-go/deepseek-v4-flash'));

assert(OpenCodeProvider::baseUrlForModel('opencode/kimi-k2.6') === OpenCodeProvider::ZEN_BASE_URL);
assert(OpenCodeProvider::baseUrlForModel('opencode-go/kimi-k2.6') === OpenCodeProvider::GO_BASE_URL);

$zenModel = OpenCodeProvider::model('opencode/kimi-k2.6');
$goModel  = OpenCodeProvider::model('opencode-go/kimi-k2.6');
assert($zenModel instanceof OpenCodeTextGenerationModel);
assert($goModel instanceof OpenCodeTextGenerationModel);

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

fwrite(STDOUT, "OpenCode provider smoke passed.\n");
