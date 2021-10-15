<?php

namespace App\Http\Controllers;

use App\Http\Requests\OfficeCreateRequest;
use App\Http\Resources\OfficeResource;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Office;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', Office::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(
                request('user_id'),
                fn($builder) => $builder->whereUserId(request('user_id'))
            )
            ->when(
                request('visitor_id'),
                fn(Builder $builder) => $builder->whereRelation(
                    'reservations',
                    'user_id',
                    '=',
                    request('visitor_id')
                )
            )
            ->when(
                request('lat') && request('lng'),
                fn($builder) => $builder->nearestTo(
                    request('lat'),
                    request('lng')
                ),
                fn($builder) => $builder->orderBy('id', 'ASC')
            )
            ->withCount([
                'reservations' => fn($builder) => $builder->where(
                    'status',
                    Reservation::STATUS_ACTIVE
                )
            ])
            ->with(['images', 'tags', 'user'])
            ->paginate(20);
        return OfficeResource::collection($offices);
    }

    /**
     * @param Office $office
     * @return OfficeResource
     */
    public function show(Office $office)
    {
        $office = $office
            ->loadCount([
                'reservations' => fn($builder) => $builder->where(
                    'status',
                    Reservation::STATUS_ACTIVE
                )
            ])
            ->load(['images', 'tags', 'user']);
        return OfficeResource::make($office);
    }

    public function create(OfficeCreateRequest $request)
    {
        $request->merge([
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        $office = auth()
            ->user()
            ->offices()
            ->create(Arr::except($request->all(), ['tags']));

        $office->tags()->sync($request->tags);
        return OfficeResource::make($office);
    }
}
