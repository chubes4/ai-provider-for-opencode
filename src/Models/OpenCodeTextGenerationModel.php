<?php

declare(strict_types=1);

namespace Chubes4\OpenCodeAiProvider\Models;

use Chubes4\OpenCodeAiProvider\Provider\OpenCodeProvider;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * OpenAI-compatible text model for OpenCode Zen and Go.
 */
class OpenCodeTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * {@inheritDoc}
     */
    protected function prepareGenerateTextParams(array $prompt): array
    {
        $params = parent::prepareGenerateTextParams($prompt);
        $params['model'] = $this->apiModelId($this->metadata()->getId());

        return $params;
    }

    /**
     * {@inheritDoc}
     */
    protected function createRequest(HttpMethodEnum $method, string $path, array $headers = [], $data = null): Request
    {
        $baseUrl = OpenCodeProvider::baseUrlForModel($this->metadata()->getId());
        $headers['X-AI-Provider-For-OpenCode-Route'] = $this->routeName($this->metadata()->getId());
        $headers['X-AI-Provider-For-OpenCode-Upstream'] = $baseUrl;

        return new Request(
            $method,
            rtrim($baseUrl, '/') . '/' . ltrim($path, '/'),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }

    /**
     * Returns the model ID expected by the OpenCode API endpoint.
     *
     * WordPress AI Client selections use OpenCode's provider-prefixed config
     * IDs, while the OpenAI-compatible endpoint expects the bare model ID.
     *
     * @param string $modelId The provider-prefixed model ID.
     * @return string The endpoint model ID.
     */
    private function apiModelId(string $modelId): string
    {
        if (strpos($modelId, '/') === false) {
            return $modelId;
        }

        return substr($modelId, strpos($modelId, '/') + 1);
    }

    /**
     * Returns the OpenCode surface name for route attribution.
     *
     * @param string $modelId The provider-prefixed model ID.
     * @return string Route name.
     */
    private function routeName(string $modelId): string
    {
        if (strpos($modelId, 'opencode-go/') === 0) {
            return 'opencode-go';
        }

        return 'opencode';
    }
}
