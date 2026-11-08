<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Messages\ToolUseBlock;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Client\ClientInterface;

/**
 * The only door to the language model. `services.llm.driver` picks the Claude
 * API (official SDK) or an OpenAI-compatible gateway (9Router, plain HTTP
 * because it ignores structured output). Callers speak the OpenAI shape and
 * this class translates, so switching provider touches nothing else. One
 * forced tool call is all the assistant needs.
 */
class LlmClient
{
    /** Enough for a long receipt; a reply that hits it is cut off and refused. */
    private const MAX_OUTPUT_TOKENS = 2048;

    public function __construct(private ?ClientInterface $transport = null) {}

    /**
     * One model turn that must end in a tool call. With $vision a message
     * may carry an image part, and the vision model and timeout are used;
     * keep that prompt short (FormAssistant::readReceipt). $system is what
     * stays the same between calls and $context what changes, so the Claude
     * driver can cache the first.
     *
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @param  list<array<string, mixed>>  $tools  OpenAI function tools
     * @return array{name: string, arguments: array<string, mixed>}
     */
    public function toolCall(string $system, array $messages, array $tools, bool $vision = false, string $context = ''): array
    {
        return config('services.llm.driver') === 'claude'
            ? $this->claude($system, $context, $messages, $tools, $vision)
            : $this->gateway(trim("{$system}\n\n{$context}"), $messages, $tools, $vision);
    }

    /**
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array{name: string, arguments: array<string, mixed>}
     */
    private function claude(string $system, string $context, array $messages, array $tools, bool $vision): array
    {
        $config = config('services.llm');
        $key = (string) $config['claude']['key'];

        if ($key === '') {
            throw new AssistantUnavailable('ANTHROPIC_API_KEY is not set.');
        }

        $blocks = [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]];

        if ($context !== '') {
            $blocks[] = ['type' => 'text', 'text' => $context];
        }

        try {
            $reply = (new Client(apiKey: $key, requestOptions: [
                'maxRetries' => 1,
                // The SDK leaves the timeout to its transport; without this a stuck call waits ten minutes.
                'transporter' => $this->transport ?? new Guzzle([
                    'connect_timeout' => 5,
                    'timeout' => (int) $config[$vision ? 'vision_timeout' : 'timeout'],
                ]),
            ]))->messages->create(
                maxTokens: self::MAX_OUTPUT_TOKENS,
                // @phpstan-ignore argument.type (the SDK's shape lists every block; these are text and image)
                messages: self::claudeMessages($messages),
                model: (string) $config['claude']['model'],
                system: $blocks,
                // A form to fill needs no reasoning, and thinking is billed as output.
                thinking: ['type' => 'disabled'],
                toolChoice: ['type' => 'any', 'disableParallelToolUse' => true],
                tools: array_map(fn (array $tool) => [
                    'name' => $tool['function']['name'],
                    'description' => $tool['function']['description'],
                    'inputSchema' => $tool['function']['parameters'],
                ], $tools),
            );
        } catch (AnthropicException $exception) {
            // Includes an empty credit balance, which the owner has to hear about.
            report($exception);

            throw new AssistantUnavailable('Claude did not answer.', previous: $exception);
        }

        Log::info('Claude usage', [
            'vision' => $vision,
            'input' => $reply->usage->inputTokens,
            'output' => $reply->usage->outputTokens,
            'cache_write' => $reply->usage->cacheCreationInputTokens,
            'cache_read' => $reply->usage->cacheReadInputTokens,
        ]);

        $call = collect($reply->content)->first(fn ($block) => $block instanceof ToolUseBlock);

        // A refusal has no tool, and a reply cut off at the limit has half of one.
        if ($reply->stopReason !== 'tool_use' || ! $call instanceof ToolUseBlock) {
            throw new AssistantUnavailable("The model answered without a tool call ({$reply->stopReason}).");
        }

        return ['name' => $call->name, 'arguments' => $call->input];
    }

    /**
     * Claude wants the first turn from the user and refuses empty ones; the
     * chat history can hold both, since it is cut to its last few turns.
     *
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @return list<array{role: string, content: string|list<array<string, mixed>>}>
     */
    private static function claudeMessages(array $messages): array
    {
        $turns = array_values(array_filter($messages, fn (array $turn) => $turn['content'] !== ''));

        while (($turns[0]['role'] ?? null) === 'assistant') {
            array_shift($turns);
        }

        return array_map(fn (array $turn) => [
            'role' => $turn['role'],
            'content' => is_string($turn['content']) ? $turn['content'] : array_map(self::claudePart(...), $turn['content']),
        ], $turns);
    }

    /**
     * @param  array<string, mixed>  $part
     * @return array<string, mixed>
     */
    private static function claudePart(array $part): array
    {
        if ($part['type'] !== 'image_url') {
            return $part;
        }

        [$header, $data] = explode(',', (string) $part['image_url']['url'], 2);

        return ['type' => 'image', 'source' => [
            'type' => 'base64',
            'mediaType' => Str::between($header, 'data:', ';'),
            'data' => $data,
        ]];
    }

    /**
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array{name: string, arguments: array<string, mixed>}
     */
    private function gateway(string $system, array $messages, array $tools, bool $vision): array
    {
        $config = config('services.llm');

        try {
            $response = Http::baseUrl((string) $config['url'])
                ->withToken((string) $config['key'])
                ->acceptJson()
                ->timeout((int) $config[$vision ? 'vision_timeout' : 'timeout'])
                ->post('chat/completions', [
                    'model' => $config[$vision ? 'vision_model' : 'model'],
                    // 9Router streams unless told not to.
                    'stream' => false,
                    'temperature' => 0,
                    'tool_choice' => 'required',
                    'messages' => [['role' => 'system', 'content' => $system], ...$messages],
                    'tools' => $tools,
                ]);
        } catch (ConnectionException $exception) {
            throw new AssistantUnavailable('The model did not answer in time.', previous: $exception);
        }

        $call = $response->json('choices.0.message.tool_calls.0.function');

        if ($response->failed() || ! is_array($call) || ! is_string($call['name'] ?? null)) {
            throw new AssistantUnavailable("The model answered without a tool call (HTTP {$response->status()}).");
        }

        $arguments = json_decode(is_string($call['arguments'] ?? null) ? $call['arguments'] : '{}', true);

        return ['name' => $call['name'], 'arguments' => is_array($arguments) ? $arguments : []];
    }
}
