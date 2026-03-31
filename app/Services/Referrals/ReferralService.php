<?php

namespace App\Services\Referrals;

use App\Models\CoinsLedger;
use App\Models\ReferralData;
use App\Models\User;
use App\Mail\ReferralJoinedMail;
use App\Http\Resources\MemberDetailResource;
use App\Services\Coins\CoinsService;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    public function __construct(
        private readonly ReferralCodeService $referralCodeService,
        private readonly CoinsService $coinsService,
        private readonly NotifyUserService $notifyUserService,
    ) {
    }

    public function generateOrGetReferral(User $user): array
    {
        $existing = DB::table('referral_links')
            ->where('user_id', $user->id)
            ->orderBy('id', 'asc')
            ->first();

        if ($existing) {
            Log::info('referral.code.existing_returned', [
                'referrer_user_id' => (string) $user->id,
                'referral_code' => (string) $existing->referral_code,
            ]);

            return [
                'referral_code' => (string) $existing->referral_code,
                'referral_link' => (string) $existing->referral_link,
                'is_existing' => true,
            ];
        }

        $name = trim((string) ($user->display_name ?: ($user->first_name . ' ' . $user->last_name)));
        $code = $this->referralCodeService->generateUniqueCode($name);
        $link = $this->referralCodeService->buildReferralLink($code);

        DB::table('referral_links')->insert([
            'user_id' => $user->id,
            'referral_code' => $code,
            'referral_link' => $link,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('referral.code.generated', [
            'referrer_user_id' => (string) $user->id,
            'referral_code' => $code,
        ]);

        return [
            'referral_code' => $code,
            'referral_link' => $link,
            'is_existing' => false,
        ];
    }

    public function validateReferralCode(string $code): ?array
    {
        $normalized = strtoupper(trim($code));

        $row = DB::table('referral_links as rl')
            ->join('users as u', 'u.id', '=', 'rl.user_id')
            ->where('rl.referral_code', $normalized)
            ->select([
                'rl.user_id',
                'rl.referral_code',
                'rl.referral_link',
                'u.first_name',
                'u.last_name',
                'u.display_name',
                'u.email',
            ])
            ->first();

        Log::info('referral.code.validated', [
            'referral_code' => $normalized,
            'valid' => $row !== null,
            'referrer_user_id' => $row?->user_id,
        ]);

        if (! $row) {
            return null;
        }

        return [
            'referrer_user_id' => (string) $row->user_id,
            'referral_code' => (string) $row->referral_code,
            'referral_link' => (string) $row->referral_link,
            'referrer_name' => trim((string) (($row->display_name ?: '') ?: (($row->first_name ?? '') . ' ' . ($row->last_name ?? '')))),
            'referrer_email' => (string) ($row->email ?? ''),
        ];
    }

    public function validateReferralCodeOrFail(?string $code): ?array
    {
        if (blank($code)) {
            return null;
        }

        $row = $this->validateReferralCode((string) $code);

        if (! $row) {
            Log::warning('referral.code.invalid', [
                'referral_code' => strtoupper(trim((string) $code)),
            ]);

            throw ValidationException::withMessages([
                'referral_code' => ['The selected referral code is invalid.'],
            ]);
        }

        return $row;
    }

    public function applyReferralOnRegistration(User $newUser, string $code): array
    {
        $normalized = strtoupper(trim($code));

        return DB::transaction(function () use ($newUser, $normalized) {
            $link = DB::table('referral_links')
                ->where('referral_code', $normalized)
                ->lockForUpdate()
                ->first();

            if (! $link) {
                throw ValidationException::withMessages([
                    'referral_code' => ['The selected referral code is invalid.'],
                ]);
            }

            if ((string) $link->user_id === (string) $newUser->id) {
                throw ValidationException::withMessages([
                    'referral_code' => ['A user cannot refer themselves.'],
                ]);
            }

            $newUserId = (string) $newUser->id;
            $referrerUserId = (string) $link->user_id;

            $alreadyReferred = ReferralData::query()
                ->where('referred_user_id', $newUserId)
                ->exists();

            Log::info('referral.registration.referred_lookup', [
                'referred_user_id' => $newUserId,
                'already_referred' => $alreadyReferred,
                'referral_code' => $normalized,
            ]);

            if ($alreadyReferred) {
                Log::warning('referral.registration.duplicate_referred_user', [
                    'referred_user_id' => $newUserId,
                ]);

                throw ValidationException::withMessages([
                    'referral_code' => ['Referral already applied for this user.'],
                ]);
            }

            $alreadyRewarded = CoinsLedger::query()
                ->where('reference', 'referral_signup:' . $newUserId)
                ->exists();

            if ($alreadyRewarded) {
                throw ValidationException::withMessages([
                    'referral_code' => ['Referral reward already processed for this user.'],
                ]);
            }

            $rewardCoins = (int) config('coins.activity_rewards.referral_signup', 100);

            Log::info('referral.registration.before_insert', [
                'referrer_user_id' => $referrerUserId,
                'referred_user_id' => $newUserId,
                'referral_code' => $normalized,
                'coins' => $rewardCoins,
            ]);

            $data = ReferralData::query()->create([
                'referrer_user_id' => $referrerUserId,
                'referred_user_id' => $newUserId,
                'referral_code' => $normalized,
                'referrer_email' => null,
                'coins' => $rewardCoins,
                'reward_status' => 'granted',
                'used_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! $data->exists || ! $data->id) {
                throw new \RuntimeException('Referral registration failed: referraldata row was not created.');
            }

            Log::info('referral.registration.insert_success', [
                'referral_data_id' => (int) $data->id,
                'referred_user_id' => $newUserId,
                'referrer_user_id' => $referrerUserId,
            ]);

            $referrer = User::query()->find($referrerUserId);

            if ($referrer && blank($data->referrer_email)) {
                $data->referrer_email = $referrer->email;
                $data->save();
            }

            if ($referrer && $rewardCoins > 0) {
                $this->coinsService->reward(
                    $referrer,
                    $rewardCoins,
                    'referral_signup:' . $newUserId,
                    $newUserId
                );

                Log::info('referral.reward.granted', [
                    'referrer_user_id' => (string) $referrer->id,
                    'referred_user_id' => $newUserId,
                    'coins' => $rewardCoins,
                    'referral_data_id' => (int) $data->id,
                ]);
            }

            if ($referrer) {
                $this->notifyUserService->notifyUser(
                    $referrer,
                    $newUser,
                    'referral_signup',
                    [
                        'title' => 'New Referral Joined',
                        'body' => 'A new peer has joined using your referral code.',
                        'referred_user_id' => $newUserId,
                        'referred_user_name' => trim((string) ($newUser->display_name ?: ($newUser->first_name . ' ' . $newUser->last_name))),
                        'referral_code_used' => $normalized,
                    ],
                    $data
                );

                $this->sendReferralEmail($referrer, $newUser, $normalized);
            }

            Log::info('referral.registration.applied', [
                'referral_data_id' => (int) $data->id,
                'referrer_user_id' => $referrerUserId,
                'referred_user_id' => $newUserId,
                'referral_code' => $normalized,
            ]);

            return [
                'referrer_user_id' => $referrerUserId,
                'referrer_email' => (string) ($data->referrer_email ?? ''),
                'referral_code' => $normalized,
                'coins' => (int) $rewardCoins,
                'reward_status' => 'granted',
            ];
        });
    }

    public function getReferralMembers(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        return ReferralData::query()
            ->with(['referredUser:id,first_name,last_name,display_name,email,company_name,designation,created_at'])
            ->where('referrer_user_id', $user->id)
            ->whereNotNull('referred_user_id')
            ->orderByRaw('used_at DESC NULLS LAST')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getReferralStats(User $user): array
    {
        $query = ReferralData::query()->where('referrer_user_id', $user->id);

        $referrer = User::query()
            ->with(['city', 'activeCircle.cityRef'])
            ->find($user->id);

        $referredUsers = ReferralData::query()
            ->with([
                'referredUser',
                'referredUser.city',
                'referredUser.activeCircle.cityRef',
            ])
            ->where('referrer_user_id', $user->id)
            ->whereNotNull('referred_user_id')
            ->orderByRaw('used_at DESC NULLS LAST')
            ->orderByDesc('created_at')
            ->get()
            ->pluck('referredUser')
            ->filter()
            ->map(fn (User $referredUser): array => (new MemberDetailResource($referredUser))->resolve())
            ->values()
            ->all();

        $referrerProfile = $referrer ? (new MemberDetailResource($referrer))->resolve() : null;

        return [
            'total_referrals' => (clone $query)->whereNotNull('referred_user_id')->count(),
            'total_referral_coins' => (int) (clone $query)->where('reward_status', 'granted')->sum('coins'),
            'granted_referrals' => (clone $query)->where('reward_status', 'granted')->whereNotNull('referred_user_id')->count(),
            'pending_referrals' => (clone $query)->where('reward_status', 'pending')->whereNull('referred_user_id')->count(),
            'referrer' => $referrerProfile,
            'referred_users' => $referredUsers,
        ];
    }

    public function getMyReferralSummary(User $user): array
    {
        $link = DB::table('referral_links')
            ->where('user_id', $user->id)
            ->orderBy('id', 'asc')
            ->first();

        if (! $link) {
            $generated = $this->generateOrGetReferral($user);
            $link = (object) [
                'referral_code' => $generated['referral_code'],
                'referral_link' => $generated['referral_link'],
            ];
        }

        $stats = $this->getReferralStats($user);

        return array_merge([
            'referral_code' => (string) ($link->referral_code ?? ''),
            'referral_link' => (string) ($link->referral_link ?? ''),
        ], $stats);
    }

    private function sendReferralEmail(User $referrer, User $referredUser, string $referralCode): void
    {
        if (blank($referrer->email)) {
            return;
        }

        $referrerName = trim((string) (($referrer->display_name ?: '') ?: (($referrer->first_name ?? '') . ' ' . ($referrer->last_name ?? ''))));
        $peerName = trim((string) (($referredUser->display_name ?: '') ?: (($referredUser->first_name ?? '') . ' ' . ($referredUser->last_name ?? ''))));
        $mailable = new ReferralJoinedMail(
            $referrerName !== '' ? $referrerName : 'Peer',
            $peerName !== '' ? $peerName : 'New Peer',
            $referralCode
        );

        try {
            Mail::to($referrer->email)->send($mailable);

            app(EmailLogService::class)->logMailableSent($mailable, [
                'user_id' => (string) $referrer->id,
                'to_email' => (string) $referrer->email,
                'to_name' => $referrerName !== '' ? $referrerName : null,
                'template_key' => 'referral_joined',
                'source_module' => 'Referral',
                'related_type' => User::class,
                'related_id' => (string) $referredUser->id,
                'payload' => [
                    'referrer_user_id' => (string) $referrer->id,
                    'referred_user_id' => (string) $referredUser->id,
                    'referral_code' => $referralCode,
                ],
            ]);

            Log::info('referral.email.sent', [
                'referrer_user_id' => (string) $referrer->id,
                'referrer_email' => (string) $referrer->email,
            ]);
        } catch (\Throwable $exception) {
            app(EmailLogService::class)->logMailableFailed($mailable, [
                'user_id' => (string) $referrer->id,
                'to_email' => (string) $referrer->email,
                'to_name' => $referrerName !== '' ? $referrerName : null,
                'template_key' => 'referral_joined',
                'source_module' => 'Referral',
                'related_type' => User::class,
                'related_id' => (string) $referredUser->id,
                'payload' => [
                    'referrer_user_id' => (string) $referrer->id,
                    'referred_user_id' => (string) $referredUser->id,
                    'referral_code' => $referralCode,
                ],
            ], $exception);

            Log::warning('referral.email.failed', [
                'referrer_user_id' => (string) $referrer->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
