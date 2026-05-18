<?php

declare(strict_types=1);

namespace Chubes4\OpenCodeAiProvider\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Static OpenCode model metadata directory.
 */
class OpenCodeModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory
{
    /**
     * {@inheritDoc}
     */
    protected function sendListModelsRequest(): array
    {
        $models = [
            'opencode/kimi-k2.6' => 'OpenCode Zen Kimi K2.6',
            'opencode/kimi-k2.5' => 'OpenCode Zen Kimi K2.5',
            'opencode-go/kimi-k2.6' => 'OpenCode Go Kimi K2.6',
            'opencode-go/kimi-k2.5' => 'OpenCode Go Kimi K2.5',
        ];

        $metadata = [];
        foreach ($models as $id => $name) {
            $metadata[$id] = new ModelMetadata(
                $id,
                $name,
                [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
                $this->textOptions()
            );
        }

        /**
         * Filters the OpenCode models exposed to the WordPress AI Client.
         *
         * @param array<string, ModelMetadata> $metadata Model metadata keyed by model ID.
         */
        return apply_filters('ai_provider_for_opencode_model_metadata', $metadata);
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
