<?php

declare(strict_types=1);

namespace Chubes4\OpenCodeAiProvider\Provider;

use Chubes4\OpenCodeAiProvider\Metadata\OpenCodeModelMetadataDirectory;
use Chubes4\OpenCodeAiProvider\Models\OpenCodeTextGenerationModel;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
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

        return self::ZEN_BASE_URL;
    }

    /**
     * {@inheritDoc}
     */
    protected static function createModel(ModelMetadata $modelMetadata, ProviderMetadata $providerMetadata): ModelInterface
    {
        foreach ($modelMetadata->getSupportedCapabilities() as $capability) {
            if ($capability->isTextGeneration()) {
                return new OpenCodeTextGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException('Unsupported OpenCode model capabilities.');
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
