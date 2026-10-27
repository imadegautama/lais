<?php

namespace App\Models;

use App\Enums\TransactionSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use LogicException;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * One entry of the audit log (Log aktivitas): who did what, when, and through
 * which channel. Append-only, so the log can be trusted when the books are
 * questioned: once written, an entry is never changed or deleted.
 * shortcut: rows can still be edited straight in the database; chain a hash per row if that ever has to be detectable.
 *
 * @property int|null $business_id
 */
class Activity extends BaseActivity
{
    protected static function booted(): void
    {
        static::creating(function (Activity $activity) {
            $activity->business_id ??= self::businessOf($activity->subject) ?? self::businessOf($activity->causer);

            // Entry points other than the web (the WhatsApp bot) name their channel in the request context.
            if ($activity->getProperty('source') === null) {
                $activity->properties = collect($activity->properties)->put('source', Context::get('activity_source', TransactionSource::Web->value));
            }
        });

        static::updating(fn () => throw new LogicException('Log aktivitas tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Log aktivitas tidak boleh dihapus.'));
    }

    /**
     * A record's own history (Riwayat), newest first.
     *
     * @return Collection<int, self>
     */
    public static function historyOf(Model $subject, int $limit = 20): Collection
    {
        return self::query()->forSubject($subject)->with(['causer', 'subject'])->latest('id')->limit($limit)->get();
    }

    private static function businessOf(?Model $model): ?int
    {
        return match (true) {
            $model === null => null,
            $model instanceof Business => $model->id,
            $model instanceof User => $model->business()->value('id'),
            default => $model->getAttribute('business_id'),
        };
    }
}
