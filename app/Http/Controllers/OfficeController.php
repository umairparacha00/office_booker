<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', Office::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(
                request('host_id'),
                fn($builder) => $builder->whereUserId(request('host_id'))
            )
            ->when(
                request('user_id'),
                fn(Builder $builder) => $builder->whereRelation(
                    'reservations',
                    'user_id',
                    '=',
                    request('user_id')
                )
            )
            ->withCount([
                'reservations' => fn($builder) => $builder->where(
                    'status',
                    Reservation::STATUS_ACTIVE
                )
            ])
            ->with(['images', 'tags', 'user'])
            ->latest('id')
            ->paginate(20);
        return OfficeResource::collection($offices);
    }
}
