<?php

namespace App\Http\Controllers\Api;

use App\Models\UserContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserContactController extends BaseApiController
{
    public function syncContacts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required'],
            'device' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:255'],
            'contacts' => ['required', 'array'],
            'contacts.*.name' => ['nullable', 'string', 'max:255'],
            'contacts.*.mobile' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $contacts = collect($payload['contacts']);
        $syncedCount = 0;

        DB::transaction(function () use ($contacts, $payload, &$syncedCount): void {
            $contacts
                ->chunk(500)
                ->each(function ($chunk) use ($payload, &$syncedCount): void {
                    foreach ($chunk as $contact) {
                        $mobileNormalized = normalize_mobile_number($contact['mobile']);

                        if ($mobileNormalized === '') {
                            continue;
                        }

                        UserContact::updateOrCreate(
                            [
                                'user_id' => $payload['user_id'],
                                'mobile_normalized' => $mobileNormalized,
                            ],
                            [
                                'name' => $contact['name'] ?? null,
                                'mobile' => $contact['mobile'],
                                'mobile_normalized' => $mobileNormalized,
                                'device' => $payload['device'] ?? null,
                                'app_version' => $payload['app_version'] ?? null,
                            ]
                        );

                        $syncedCount++;
                    }
                });
        });

        return $this->success([
            'total' => $syncedCount,
        ], 'Contacts synced successfully');
    }

    public function getContacts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contacts = UserContact::query()
            ->where('user_id', $request->input('user_id'))
            ->orderBy('id', 'desc')
            ->get();

        return $this->success($contacts, 'Contacts fetched successfully');
    }
}
