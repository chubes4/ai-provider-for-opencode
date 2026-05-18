<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/autoload.php';

use Chubes4\OpenCodeAiProvider\Provider\OpenCodeProvider;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;

final class CurlTransporter implements HttpTransporterInterface {
    public ?string $lastUri = null;
    public ?string $lastResponseBody = null;

    public function send(Request $request, ?RequestOptions $options = null): Response {
        $this->lastUri = $request->getUri();
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = $name . ': ' . $value;
            }
        }

        $handle = curl_init($request->getUri());
        if ($handle === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $request->getMethod()->value,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $body = $request->getBody();
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);
        if ($raw === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException('cURL request failed: ' . $error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $responseBody = substr($raw, $headerSize);
        $this->lastResponseBody = $responseBody;

        return new Response($status, ['Content-Type' => 'application/json'], $responseBody);
    }
}

$authPath = (getenv('XDG_DATA_HOME') ?: getenv('HOME') . '/.local/share') . '/opencode/auth.json';
$auth = json_decode((string) file_get_contents($authPath), true);
$key = $auth['opencode-go']['key'] ?? '';

if (!is_string($key) || $key === '') {
    fwrite(STDERR, "No opencode-go API key found in OpenCode auth state.\n");
    exit(2);
}

$model = OpenCodeProvider::model(
    'opencode-go/qwen3.5-plus',
    ModelConfig::fromArray([
        ModelConfig::KEY_MAX_TOKENS => 64,
        ModelConfig::KEY_TEMPERATURE => 0.1,
    ])
);

$transporter = new CurlTransporter();
$model->setHttpTransporter($transporter);
$model->setRequestAuthentication(new ApiKeyRequestAuthentication($key));

$result = $model->generateTextResult([
    new Message(
        MessageRoleEnum::user(),
        [new MessagePart('Say: opencode provider live ok')]
    ),
]);

$candidate = $result->getCandidates()[0];
$text = '';
foreach ($candidate->getMessage()->getParts() as $part) {
    $text .= (string) $part->getText();
}

if ($text === '') {
    fwrite(STDERR, "OpenCode Go returned an empty response.\n");
    fwrite(STDERR, (string) $transporter->lastResponseBody . "\n");
    exit(1);
}

fwrite(STDOUT, json_encode([
    'success' => true,
    'provider' => OpenCodeProvider::metadata()->getId(),
    'model' => $result->getModelMetadata()->getId(),
    'uri' => $transporter->lastUri,
    'reply' => $text,
], JSON_PRETTY_PRINT) . "\n");
