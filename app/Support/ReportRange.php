<?php

namespace App\Support;

use App\Enums\Period;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The time a report covers: one of the quick periods, or the owner's own dates
 * (?dari=Y-m-d&sampai=Y-m-d). It knows its comparative period too, because
 * every report shows one (SAK EMKM par. 3.8).
 */
final readonly class ReportRange
{
    public function __construct(
        public CarbonImmutable $from,
        /** Exclusive; null when the range runs up to now. */
        public ?CarbonImmutable $before,
        public string $label,
        public CarbonImmutable $priorFrom,
        public CarbonImmutable $priorBefore,
        public string $priorLabel,
        /** Null when the owner picked the dates. */
        public ?Period $period,
    ) {}

    /**
     * Dates that cannot be read, run backwards, or lie ahead fall back to
     * the quick period, so a stale link never shows an empty report.
     */
    public static function fromRequest(Request $request): self
    {
        $from = self::date($request->query('dari'));
        $to = self::date($request->query('sampai'));

        if ($from !== null && $to !== null && $from <= $to && $to <= today()->toImmutable()) {
            return self::between($from, $to);
        }

        return self::forPeriod(Period::tryFrom((string) $request->query('periode')) ?? Period::BulanIni);
    }

    public static function forPeriod(Period $period): self
    {
        [$priorFrom, $priorBefore] = $period->prior();

        return new self($period->start(), $period->end(), $period->label(), $priorFrom, $priorBefore, $period->priorLabel(), $period);
    }

    /**
     * Both days inclusive, compared with the same number of days just before.
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        $before = $to->addDay();
        $days = (int) round($from->diffInDays($before));

        return new self($from, $before, self::span($from, $to), $from->subDays($days), $from, 'Periode sebelumnya', null);
    }

    /** "5 November 2026", "1–10 November 2026", "28 Oktober – 5 November 2026". */
    private static function span(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $last = Format::date($to);

        return match (true) {
            $from->isSameDay($to) => $last,
            $from->isSameMonth($to) => "{$from->day}–{$last}",
            $from->isSameYear($to) => mb_substr(Format::date($from), 0, -5).' – '.$last,
            default => Format::date($from).' – '.$last,
        };
    }

    /**
     * The query string that brings this range back, carried between reports.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return $this->period === null
            ? ['dari' => $this->from->toDateString(), 'sampai' => $this->lastDay()->toDateString()]
            : ['periode' => $this->period->value];
    }

    public function lastDay(): CarbonImmutable
    {
        return ($this->before ?? today()->toImmutable()->addDay())->subDay();
    }

    /** How a sentence names the range: "bulan ini", or the dates as they are. */
    public function phrase(): string
    {
        return $this->period === null ? $this->label : mb_strtolower($this->label);
    }

    public function priorPhrase(): string
    {
        return mb_strtolower($this->priorLabel);
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', $value), null, false);

        // 31 February would otherwise roll over into March.
        return $date instanceof CarbonImmutable && $date->toDateString() === $value ? $date : null;
    }
}
