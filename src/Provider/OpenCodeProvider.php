<?php

declare(strict_types=1);

namespace Chubes4\OpenCodeAiProvider\Provider;

use Chubes4\OpenCodeAiProvider\Metadata\OpenCodeModelMetadataDirectory;
use Chubes4\OpenCodeAiProvider\Models\OpenCodeTextGenerationModel;
use Chubes4\OpenCodeAiProvider\Support\OpenCodeLocalState;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * OpenCode Zen and Go provider for the WordPress AI Client.
 */
class OpenCodeProvider extends AbstractApiProvider
{
    public const PROVIDER_ID = 'opencode';
    public const ZEN_BASE_URL = 'https://opencode.ai/zen/v1';
    public const GO_BASE_URL = 'https://opencode.ai/zen/go/v1';

    /**
     * {@inheritDoc}
     */
    protected static function baseUrl(): string
    {
        return self::ZEN_BASE_URL;
    }

    /**
     * Returns the API base URL for a model ID.
     *
     * @param string $modelId The model ID.
     * @return string The API base URL.
     */
    public static function baseUrlForModel(string $modelId): string
    {
        if (strpos($modelId, 'opencode-go/') === 0) {
            return self::GO_BASE_URL;
        }

        $configuredModel = self::configuredProviderModel($modelId);
        if (null !== $configuredModel) {
            $baseUrl = OpenCodeLocalState::apiBaseUrlForModel($configuredModel['provider'], $configuredModel['model']);
            if ('' === $baseUrl) {
                throw new RuntimeException('Configured OpenCode provider model requires a provider API base URL in local OpenCode config.');
            }

            return $baseUrl;
        }

        return self::ZEN_BASE_URL;
    }

    /**
     * @return array{provider:string,model:string}|null
     */
    public static function configuredProviderModel(string $modelId): ?array
    {
        if (strpos($modelId, 'opencode/') !== 0) {
            return null;
        }

        $apiModelId = substr($modelId, strlen('opencode/'));
        $parts = explode('/', $apiModelId, 2);
        if (2 !== count($parts) || '' === $parts[0] || '' === $parts[1]) {
            return null;
        }

        if ('opencode' === $parts[0] || 'opencode-go' === $parts[0]) {
            return null;
        }

        return [
            'provider' => $parts[0],
            'model' => $parts[1],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected static function createModel(ModelMetadata $modelMetadata, ProviderMetadata $providerMetadata): ModelInterface
    {
        foreach ($modelMetadata->getSupportedCapabilities() as $capability) {
            if ($capability->isTextGeneration()) {
                $model = new OpenCodeTextGenerationModel($modelMetadata, $providerMetadata);
                $key = OpenCodeLocalState::apiKeyForSurfaces(static::authSurfacesForModel($modelMetadata->getId()));

                if ('' !== $key) {
                    $model->setRequestAuthentication(new ApiKeyRequestAuthentication($key));
                }

                return $model;
            }
        }

        throw new RuntimeException('Unsupported OpenCode model capabilities.');
    }

    /**
     * Returns the OpenCode auth surface for a provider model ID.
     *
     * @param string $modelId The provider model ID.
     * @return string The OpenCode auth surface.
     */
    public static function authSurfaceForModel(string $modelId): string
    {
        return self::authSurfacesForModel($modelId)[0];
    }

    /**
     * Returns ordered OpenCode auth surfaces for a provider model ID.
     *
     * @param string $modelId The provider model ID.
     * @return array<int, string> The OpenCode auth surfaces.
     */
    public static function authSurfacesForModel(string $modelId): array
    {
        if (strpos($modelId, 'opencode-go/') === 0) {
            return ['opencode-go'];
        }

        if (strpos($modelId, 'opencode/') === 0) {
            $configuredModel = self::configuredProviderModel($modelId);
            if (null !== $configuredModel) {
                return [$configuredModel['provider']];
            }
        }

        return ['opencode'];
    }

    /**
     * {@inheritDoc}
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        $args = [
            self::PROVIDER_ID,
            'OpenCode',
            ProviderTypeEnum::cloud(),
            'https://opencode.ai/zen',
            RequestAuthenticationMethod::apiKey(),
        ];

        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            $args[] = function_exists('__')
                ? __('Text generation through OpenCode Zen and OpenCode Go.', 'ai-provider-for-opencode')
                : 'Text generation through OpenCode Zen and OpenCode Go.';
        }

        return new ProviderMetadata(...$args);
    }

    /**
     * {@inheritDoc}
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new ListModelsApiBasedProviderAvailability(static::modelMetadataDirectory());
    }

    /**
     * {@inheritDoc}
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new OpenCodeModelMetadataDirectory();
    }
}
