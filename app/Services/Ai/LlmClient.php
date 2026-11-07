<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The only door to the language model: an OpenAI-compatible chat endpoint
 * (9Router, PRD §3.1). Plain HTTP rather than the AI SDK because 9Router
 * ignores structured output, and the SDK's tool loop would cost a second
 * round trip; one forced tool call is all the assistant needs.
 */
class LlmClient
{
    /**
     * One model turn that must end in a tool call. With $vision a message
     * may carry an image part, and the vision model and timeout are used;
     * keep that prompt short (FormAssistant::readReceipt).
     *
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @param  list<array<string, mixed>>  $tools  OpenAI function tools
     * @return array{name: string, arguments: array<string, mixed>}
     */
    public function toolCall(string $system, array $messages, array $tools, bool $vision = false): array
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
