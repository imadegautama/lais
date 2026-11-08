<?php

namespace Tests\Feature;

use App\Services\Ai\AssistantUnavailable;
use App\Services\Ai\LlmClient;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/**
 * The Claude driver against a fake transport: nothing here reaches the API or
 * spends credit. Quality is measured by AssistantEvalTest.
 */
class LlmClaudeTest extends TestCase
{
    private const TOOLS = [[
        'type' => 'function',
        'function' => ['name' => 'isi_transaksi', 'description' => 'Isi form.', 'parameters' => ['type' => 'object', 'properties' => ['jenis' => ['type' => 'string']], 'required' => ['jenis']]],
    ]];

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.llm.driver' => 'claude', 'services.llm.claude.key' => 'test-key', 'services.llm.claude.model' => 'claude-haiku-5-5']);
    }

    public function test_it_forces_one_tool_call_without_thinking_and_reads_the_answer(): void
    {
        $this->reply(200, $this->message('tool_use', [
            ['type' => 'text', 'text' => 'Baik.'],
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'isi_transaksi', 'input' => ['jenis' => 'penjualan']],
        ]));

        $call = $this->client()->toolCall('Aturan tetap', [['role' => 'user', 'content' => 'jual 3 nasgor']], self::TOOLS, context: 'Sekarang: pukul 10.15');

        $this->assertSame(['name' => 'isi_transaksi', 'arguments' => ['jenis' => 'penjualan']], $call);

        $body = $this->sent[0];
        $this->assertSame('claude-haiku-5-5', $body['model']);
        $this->assertSame(['type' => 'disabled'], $body['thinking']);
        $this->assertSame(['type' => 'any', 'disable_parallel_tool_use' => true], $body['tool_choice']);
        $this->assertArrayNotHasKey('temperature', $body);
        $this->assertSame('isi_transaksi', $body['tools'][0]['name']);
        $this->assertSame(['jenis'], $body['tools'][0]['input_schema']['required']);
        $this->assertSame([['role' => 'user', 'content' => 'jual 3 nasgor']], $body['messages']);
    }

    public function test_only_the_part_that_stays_the_same_is_cached(): void
    {
        $this->reply(200, $this->message('tool_use', [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'isi_transaksi', 'input' => []]]));

        $this->client()->toolCall('Aturan tetap', [['role' => 'user', 'content' => 'halo']], self::TOOLS, context: 'Sekarang: pukul 10.15');

        [$rules, $context] = $this->sent[0]['system'];
        $this->assertSame(['type' => 'ephemeral'], $rules['cache_control']);
        $this->assertSame('Sekarang: pukul 10.15', $context['text']);
        $this->assertArrayNotHasKey('cache_control', $context);
    }

    public function test_a_photo_goes_as_an_image_block(): void
    {
        $this->reply(200, $this->message('tool_use', [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'isi_transaksi', 'input' => []]]));

        $this->client()->toolCall('Salin nota.', [['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => 'Salin nota ini.'],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,QUJD']],
        ]]], self::TOOLS, vision: true);

        $this->assertSame(
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => 'QUJD']],
            $this->sent[0]['messages'][0]['content'][1],
        );
    }

    public function test_history_starts_with_the_owner_and_has_no_empty_turns(): void
    {
        $this->reply(200, $this->message('tool_use', [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'isi_transaksi', 'input' => []]]));

        $this->client()->toolCall('Aturan', [
            ['role' => 'assistant', 'content' => 'Mau dicatat?'],
            ['role' => 'user', 'content' => ''],
            ['role' => 'user', 'content' => 'jual 1 nasgor'],
            ['role' => 'assistant', 'content' => 'Tunai?'],
            ['role' => 'user', 'content' => 'iya tunai'],
        ], self::TOOLS);

        $this->assertSame(['user', 'assistant', 'user'], array_column($this->sent[0]['messages'], 'role'));
    }

    public function test_a_refusal_or_a_cut_off_answer_means_filling_the_form_by_hand(): void
    {
        foreach (['refusal', 'max_tokens'] as $stop) {
            $this->reply(200, $this->message($stop, [['type' => 'text', 'text' => 'Maaf.']]));

            try {
                $this->client()->toolCall('Aturan', [['role' => 'user', 'content' => 'halo']], self::TOOLS);
                $this->fail("A {$stop} answer must not count as a tool call.");
            } catch (AssistantUnavailable $exception) {
                $this->assertStringContainsString($stop, $exception->getMessage());
            }
        }
    }

    public function test_an_empty_credit_balance_means_filling_the_form_by_hand(): void
    {
        $this->reply(400, ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low.']]);

        $this->expectException(AssistantUnavailable::class);

        $this->client()->toolCall('Aturan', [['role' => 'user', 'content' => 'halo']], self::TOOLS);
    }

    public function test_without_a_key_nothing_is_sent(): void
    {
        config(['services.llm.claude.key' => null]);
        $this->reply(200, []);

        try {
            $this->client()->toolCall('Aturan', [['role' => 'user', 'content' => 'halo']], self::TOOLS);
            $this->fail('A missing key must stop the call.');
        } catch (AssistantUnavailable) {
            $this->assertSame([], $this->sent);
        }
    }

    private function client(): LlmClient
    {
        return app(LlmClient::class);
    }

    /**
     * @param  list<array<string, mixed>>  $content
     * @return array<string, mixed>
     */
    private function message(string $stop, array $content): array
    {
        return [
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-5-5',
            'content' => $content, 'stop_reason' => $stop, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function reply(int $status, array $body): void
    {
        $sent = &$this->sent;
        $sent = [];

        $this->app->instance(ClientInterface::class, new class($status, $body, $sent) implements ClientInterface
        {
            /**
             * @param  array<string, mixed>  $body
             * @param  list<array<string, mixed>>  $sent
             */
            public function __construct(private int $status, private array $body, private array &$sent) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->sent[] = json_decode((string) $request->getBody(), true);

                return new Response($this->status, ['Content-Type' => 'application/json'], (string) json_encode($this->body));
            }
        });
    }
}
