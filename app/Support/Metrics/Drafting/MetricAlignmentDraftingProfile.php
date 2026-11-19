<?php

namespace App\Support\Metrics\Drafting;

final readonly class MetricAlignmentDraftingProfile
{
    public function __construct(
        private string $configPath = 'metric-alignment-drafting.profile',
    ) {}

    public function configPath(): string
    {
        return $this->configPath;
    }

    public function version(): string
    {
        return (string) config($this->key('version'), '1.0.0');
    }

    public function provider(): string
    {
        return (string) config($this->key('provider'), 'openrouter');
    }

    public function model(): string
    {
        return (string) config($this->key('model'), 'google/gemini-3-flash-preview');
    }

    public function temperature(): float
    {
        return (float) config($this->key('temperature'), 0.1);
    }

    public function maxTokens(): int
    {
        return (int) config($this->key('max_tokens'), 8192);
    }

    public function timeoutSeconds(): int
    {
        return (int) config($this->key('timeout_seconds'), 90);
    }

    /**
     * @todo Reserved for future streaming draft generation support.
     */
    public function streaming(): bool
    {
        return (bool) config($this->key('streaming'), false);
    }

    /**
     * @todo Reserved for future agent response auto-healing support.
     */
    public function responseHealing(): bool
    {
        return (bool) config($this->key('response_healing'), false);
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(): array
    {
        $options = config($this->key('provider_options'), []);

        return is_array($options) ? $options : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version(),
            'provider' => $this->provider(),
            'model' => $this->model(),
            'temperature' => $this->temperature(),
            'max_tokens' => $this->maxTokens(),
            'timeout_seconds' => $this->timeoutSeconds(),
            'streaming' => $this->streaming(),
            'response_healing' => $this->responseHealing(),
            'provider_options' => $this->providerOptions(),
        ];
    }

    private function key(string $suffix): string
    {
        return "{$this->configPath}.{$suffix}";
    }
}
