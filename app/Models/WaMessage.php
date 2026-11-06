<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One WhatsApp message to or from the bot. Incoming ones are kept by
 * GOWA's id so a webhook delivered twice is processed once; the last few
 * are the conversation history the assistant reads.
 *
 * @property int $id
 * @property string|null $message_id
 * @property string $direction in|out
 * @property string $phone 62… or a …@lid id
 * @property int|null $user_id
 * @property string|null $body
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $processed_at
 */
#[Fillable(['message_id', 'direction', 'phone', 'user_id', 'body', 'payload', 'processed_at'])]
class WaMessage extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
