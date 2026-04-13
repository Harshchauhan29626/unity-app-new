<?php

namespace App\Http\Controllers\Api\V1\Forms;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Forms\StoreVisitorRegistrationRequest;
use App\Models\VisitorRegistration;
use Illuminate\Http\Request;

class VisitorRegistrationController extends BaseApiController
{
    public function store(StoreVisitorRegistrationRequest $request)
    {
        $authUser = $request->user();
        $data = $request->validated();

        $registration = VisitorRegistration::create([
            'user_id' => $authUser->id,
            'event_type' => $data['event_type'],
            'event_name' => $data['event_name'],
            'event_date' => $data['event_date'],
            'visitor_full_name' => $data['visitor_full_name'],
            'visitor_mobile' => $data['visitor_mobile'],
            'visitor_email' => $data['visitor_email'] ?? null,
            'visitor_city' => $data['visitor_city'],
            'visitor_business' => $data['visitor_business'],
            'how_known' => $data['how_known'],
            'note' => $data['note'] ?? null,
            'status' => 'pending',
            'coins_awarded' => false,
        ]);

        $updatedLifeImpact = $this->increaseLifeImpact(
            (string) $authUser->id,
            1,
            'visitor_registration',
            'Brought a quality visitor to the meeting',
            (string) $authUser->id,
            (string) $registration->id,
            'Life impact added for visitor registration activity.',
            [
                'event_type' => $registration->event_type,
                'event_name' => $registration->event_name,
                'event_date' => $registration->event_date,
                'visitor_full_name' => $registration->visitor_full_name,
                'visitor_mobile' => $registration->visitor_mobile,
                'visitor_email' => $registration->visitor_email,
                'visitor_city' => $registration->visitor_city,
                'visitor_business' => $registration->visitor_business,
                'how_known' => $registration->how_known,
                'note' => $registration->note,
            ]
        );

        return $this->success([
            'id' => $registration->id,
            'status' => $registration->status,
            'created_at' => $registration->created_at,
            'life_impacted_count' => $updatedLifeImpact,
        ], 'Visitor registration submitted successfully.', 201);
    }

    public function myIndex(Request $request)
    {
        $authUser = $request->user();

        $items = VisitorRegistration::query()
            ->where('user_id', $authUser->id)
            ->orderByDesc('created_at')
            ->select([
                'id',
                'event_type',
                'event_name',
                'event_date',
                'visitor_full_name',
                'visitor_mobile',
                'visitor_city',
                'visitor_business',
                'status',
                'created_at',
            ])
            ->get();

        return $this->success([
            'items' => $items,
        ]);
    }
}
