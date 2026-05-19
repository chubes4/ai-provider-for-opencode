<?php

declare(strict_types=1);

namespace Chubes4\OpenCodeAiProvider\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * OpenCode model metadata directory.
 */
class OpenCodeModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory
{
    /**
     * {@inheritDoc}
     */
    protected function sendListModelsRequest(): array
    {
        $metadata = array_merge(
            $this->listSurfaceModels('opencode', 'OpenCode Zen', 'https://opencode.ai/zen/v1'),
            $this->listSurfaceModels('opencode-go', 'OpenCode Go', 'https://opencode.ai/zen/go/v1')
        );

        /**
         * Filters the OpenCode models exposed to the WordPress AI Client.
         *
         * @param array<string, ModelMetadata> $metadata Model metadata keyed by model ID.
         */
        if (function_exists('apply_filters')) {
            return \apply_filters('ai_provider_for_opencode_model_metadata', $metadata);
        }

        return $metadata;
    }

    /**
     * Lists models for one OpenCode API surface.
     *
     * @param string $prefix The WordPress-facing model ID prefix.
     * @param string $labelPrefix Display-name prefix.
     * @param string $baseUrl OpenCode API base URL.
     * @return array<string, ModelMetadata>
     */
    private function listSurfaceModels(string $prefix, string $labelPrefix, string $baseUrl): array
    {
        $request = new Request(
            HttpMethodEnum::GET(),
            rtrim($baseUrl, '/') . '/models'
        );
        $request = $this->getRequestAuthentication()->authenticateRequest($request);
        $response = $this->getHttpTransporter()->send($request);
        ResponseUtil::throwIfNotSuccessful($response);

        return $this->responseToModelMetadata($response, $prefix, $labelPrefix);
    }

    /**
     * Converts an OpenAI-compatible models response into WordPress AI metadata.
     *
     * @param Response $response The models response.
     * @param string   $prefix The WordPress-facing model ID prefix.
     * @param string   $labelPrefix Display-name prefix.
     * @return array<string, ModelMetadata>
     */
    private function responseToModelMetadata(Response $response, string $prefix, string $labelPrefix): array
    {
        $responseData = $response->getData();
        if (!isset($responseData['data']) || !is_array($responseData['data'])) {
            throw ResponseException::fromMissingData($labelPrefix, 'data');
        }

        $metadata = [];
        foreach ($responseData['data'] as $modelData) {
            if (!is_array($modelData) || !isset($modelData['id']) || !is_string($modelData['id']) || $modelData['id'] === '') {
                continue;
            }

            $id = $this->providerModelId($prefix, $modelData['id']);
            $metadata[$id] = new ModelMetadata(
                $id,
                $this->modelName($labelPrefix, $modelData),
                [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
                $this->textOptions()
            );
        }

        ksort($metadata);

        return $metadata;
    }

    /**
     * Returns the WordPress-facing provider model ID.
     *
     * @param string $prefix The model ID prefix.
     * @param string $apiModelId The model ID returned by OpenCode.
     * @return string
     */
    private function providerModelId(string $prefix, string $apiModelId): string
    {
        if (strpos($apiModelId, $prefix . '/') === 0) {
            return $apiModelId;
        }

        return $prefix . '/' . $apiModelId;
    }

    /**
     * Returns a display name for an OpenCode model.
     *
     * @param string $labelPrefix Display-name prefix.
     * @param array<string, mixed> $modelData Model data returned by OpenCode.
     * @return string
     */
    private function modelName(string $labelPrefix, array $modelData): string
    {
        $name = $modelData['name'] ?? $modelData['id'] ?? '';
        if (!is_string($name) || $name === '') {
            return $labelPrefix;
        }

        return $labelPrefix . ' ' . $name;
    }

    /**
     * Returns supported text-generation options for OpenCode chat models.
     *
     * @return list<SupportedOption>
     */
    private function textOptions(): array
    {
        return [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::presencePenalty()),
            new SupportedOption(OptionEnum::frequencyPenalty()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::outputSchema()),
            new SupportedOption(OptionEnum::functionDeclarations()),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
        ];
    }
}
