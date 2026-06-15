<?php

declare(strict_types=1);

namespace Chubes4\OpenCodeAiProvider\Models;

use Chubes4\OpenCodeAiProvider\Provider\OpenCodeProvider;
use Chubes4\OpenCodeAiProvider\Support\OpenCodeLocalState;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

/**
 * Chat-completions text model for OpenCode Zen and Go.
 */
class OpenCodeTextGenerationModel extends AbstractChatCompletionsTextGenerationModel
{
    public function getRequestAuthentication(): RequestAuthenticationInterface
    {
        try {
            return parent::getRequestAuthentication();
        } catch (\Throwable $e) {
            $key = OpenCodeLocalState::apiKeyForSurfaces(OpenCodeProvider::authSurfacesForModel($this->metadata()->getId()));
            if ('' !== $key) {
                return new ApiKeyRequestAuthentication($key);
            }

            throw $e;
        }
    }

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
     * WordPress AI Client selections use OpenCode's provider-prefixed config IDs,
     * while configured routes keep their provider/model shape for OpenCode.
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
}
