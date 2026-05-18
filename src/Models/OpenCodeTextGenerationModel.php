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
}
