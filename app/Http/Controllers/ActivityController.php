<?php

namespace App\Http\Controllers;

use App\Enums\ActivityCategory;
use App\Enums\Period;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Support\TableQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    /**
     * Log aktivitas: every audit entry of the business, newest first. Read only.
     */
    public function index(Request $request): Response
    {
        $business = $request->user()->currentBusiness();

        return Inertia::render('activity/index', [
            'activities' => TableQuery::paginate(
                $request,
                Activity::query()->where('activity_log.business_id', $business->id)->with(['causer', 'subject']),
                map: fn (Activity $activity) => ActivityResource::make($activity)->resolve(),
                sorts: ['waktu' => 'created_at'],
                defaultSort: '-waktu',
                filters: [
                    'kategori' => ['log_name', ActivityCategory::values()],
                    'periode' => [
                        fn (Builder $query, array $values) => $query->where('activity_log.created_at', '>=', Period::from($values[0])->start())
                            ->when(Period::from($values[0])->end(), fn (Builder $query, CarbonImmutable $end) => $query->where('activity_log.created_at', '<', $end)),
                        Period::values(),
                    ],
                ],
                // Only users cause activity in Lais, so causer_id is always a users.id.
                search: ['description', '(select users.name from users where users.id = activity_log.causer_id)'],
            ),
        ]);
    }
}
