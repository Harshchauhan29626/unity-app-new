<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\LoginOtpMail;
use App\Mail\PasswordResetOtpMail;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\UserLoginHistory;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Referrals\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends BaseApiController
{
    public function register(RegisterRequest $request, ReferralService $referralService)
    {
        $data = $request->validated();
        $normalizedReferralCode = isset($data['referral_code']) ? strtoupper(trim((string) $data['referral_code'])) : null;

        Log::info('auth.register.before_user_creation', [
            'email' => (string) ($data['email'] ?? ''),
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name' => (string) ($data['last_name'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'display_name' => trim((string) (($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''))),
            'has_referral_code' => filled($normalizedReferralCode),
            'referral_code' => (string) ($normalizedReferralCode ?? ''),
        ]);

        $referralService->validateReferralCodeOrFail($normalizedReferralCode);

        $transactionUser = $this->createRegisteredUser($data);

        if (! $transactionUser->exists || blank($transactionUser->id)) {
            throw new \RuntimeException('Registration failed: user model was not persisted.');
        }

        $persistedUser = User::query()
            ->useWritePdo()
            ->find((string) $transactionUser->id);

        if (! $persistedUser) {
            Log::error('auth.register.user_not_found_on_write_connection', [
                'user_id' => (string) $transactionUser->id,
                'email' => (string) ($data['email'] ?? ''),
            ]);

            throw new \RuntimeException('Registration failed: persisted user row was not found in users table.');
        }

        Log::info('auth.register.database_existence_check', [
            'user_id' => (string) $persistedUser->id,
            'email' => (string) $persistedUser->email,
            'exists' => true,
        ]);

        $referralMeta = null;

        if (filled($normalizedReferralCode)) {
            Log::info('auth.register.before_referral_apply', [
                'user_id' => (string) $persistedUser->id,
                'referral_code' => (string) $normalizedReferralCode,
            ]);

            $referralMeta = $referralService->applyReferralOnRegistration($persistedUser, (string) $normalizedReferralCode);

            Log::info('auth.register.after_referral_apply', [
                'user_id' => (string) $persistedUser->id,
                'referral_code' => (string) $normalizedReferralCode,
                'referrer_user_id' => (string) ($referralMeta['referrer_user_id'] ?? ''),
                'reward_status' => (string) ($referralMeta['reward_status'] ?? ''),
            ]);
        }

        Log::info('auth.register.before_token_creation', [
            'user_id' => (string) $persistedUser->id,
            'email' => (string) $persistedUser->email,
        ]);

        $token = $persistedUser->createToken('auth_token')->plainTextToken;

        Log::info('auth.register.before_response', [
            'user_id' => (string) $persistedUser->id,
            'email' => (string) $persistedUser->email,
            'has_referral_meta' => $referralMeta !== null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'data'    => [
                'token' => $token,
                'user'  => $persistedUser,
                'referral' => $referralMeta,
            ],
        ], 201);
    }

    private function createRegisteredUser(array $data): User
    {
        // Build a display name from first + last name
        $displayName = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));

        $user = new User();
        $user->id = (string) Str::uuid();
        $user->first_name = $data['first_name'];
        $user->last_name = $data['last_name'] ?? null;
        $user->display_name = $displayName;
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->company_name = $data['company_name'] ?? null;
        $user->designation = $data['designation'] ?? null;
        $user->city_id = $user->city_id ?? null;

        $trialEndsAt = now()->addDays(3);

        $user->membership_status = User::STATUS_FREE_TRIAL;
        $user->membership_starts_at = now();
        $user->membership_ends_at = $trialEndsAt;
        $user->membership_expiry = $trialEndsAt;
        $user->coins_balance = $user->coins_balance ?? 0;

        // Store the hashed password in password_hash (not password)
        $user->password_hash = Hash::make($data['password']);

        // Ensure any legacy password attribute isn't used
        if (isset($user->password)) {
            $user->password = null;
        }

        Log::info('auth.register.before_user_save', [
            'email' => (string) $user->email,
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'phone' => (string) ($user->phone ?? ''),
        ]);

        $user->save();

        Log::info('auth.register.after_user_saved', [
            'user_id' => (string) $user->id,
            'email' => (string) $user->email,
        ]);

        $user->refresh();

        Log::info('auth.register.after_user_refresh', [
            'user_id' => (string) $user->id,
            'email' => (string) $user->email,
        ]);

        $persisted = DB::table('users')->where('id', (string) $user->id)->exists();

        if (! $persisted) {
            throw new \RuntimeException('Registration failed: user was not persisted in users table.');
        }

        return $user;
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Find user by email
        $user = User::where('email', $credentials['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
                'data'    => null,
            ], 401);
        }

        // IMPORTANT: use password_hash column
        if (! Hash::check($credentials['password'], $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
                'data'    => null,
            ], 401);
        }

        $user->expireFreeTrialIfNeeded();
        $user->refresh();

        if (($user->status ?? 'active') !== 'active') {
            // Manual test: inactive user login should return 403 and no token.
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact support.',
                'data'    => null,
            ], 403);
        }

        // Create Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        // If you already have a UserResource, you can use it here instead of returning $user directly
        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data'    => [
                'token' => $token,
                'user'  => $user,
            ],
        ]);
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a registered user.',
                'data' => null,
            ], 404);
        }

        if (($user->status ?? 'active') !== 'active') {
            // Manual test: inactive user request OTP should return 403 and not send OTP.
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact support.',
                'data' => null,
            ], 403);
        }

        $otp = (string) random_int(1000, 9999);

        OtpCode::create([
            'user_id'    => $user->id,
            'email'      => $user->email,
            'purpose'    => 'login_otp',
            'code'       => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
            'used_at'    => null,
        ]);

        $mailable = new LoginOtpMail($otp, $user);

        try {
            Mail::to($user->email)->send($mailable);

            app(EmailLogService::class)->logMailableSent($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => (string) $user->email,
                'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                'template_key' => 'login_otp',
                'source_module' => 'Auth',
                'related_type' => User::class,
                'related_id' => (string) $user->id,
                'payload' => [
                    'purpose' => 'login_otp',
                ],
            ]);
        } catch (\Throwable $exception) {
            app(EmailLogService::class)->logMailableFailed($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => (string) $user->email,
                'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                'template_key' => 'login_otp',
                'source_module' => 'Auth',
                'related_type' => User::class,
                'related_id' => (string) $user->id,
                'payload' => [
                    'purpose' => 'login_otp',
                ],
            ], $exception);

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'data'    => null,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'digits:4'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a registered user.',
                'data'    => null,
            ], 404);
        }

        $otpRecord = OtpCode::where('user_id', $user->id)
            ->where('purpose', 'login_otp')
            ->whereNull('used_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
                'data'    => null,
            ], 422);
        }

        if (now()->greaterThan($otpRecord->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired.',
                'data'    => null,
            ], 422);
        }

        if (! Hash::check($data['otp'], $otpRecord->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
                'data'    => null,
            ], 422);
        }

        $otpRecord->used_at = now();
        $otpRecord->save();

        $user->expireFreeTrialIfNeeded();
        $user->refresh();

        if ($user->membership_status === 'suspended') {
            return $this->error('Account is suspended', 403);
        }

        if (($user->status ?? 'active') !== 'active') {
            // Manual test: inactive user OTP login should return 403 and no token.
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact support.',
                'data' => null,
            ], 403);
        }

        $user->last_login_at = now();
        $user->save();
        $user->refresh();

        $token = $user->createToken('api')->plainTextToken;

        UserLoginHistory::create([
            'user_id' => $user->id,
            'logged_in_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return $this->success([
            'user' => new UserResource($user->load([
                'city',
                'activeCircle:id,name,slug,city_id',
                'activeCircle.cityRef:id,name',
                'circleMemberships' => fn ($query) => $query
                    ->where('status', (string) config('circle.member_joined_status', 'approved'))
                    ->whereNull('deleted_at')
                    ->whereNull('left_at')
                    ->where(function ($nested): void {
                        $nested->whereNull('paid_ends_at')->orWhere('paid_ends_at', '>=', now());
                    })
                    ->orderByDesc('joined_at')
                    ->with('circle:id,name,slug'),
            ])),
            'token' => $token,
        ], 'Login successful');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a registered user.',
                'data' => null,
            ], 404);
        }

        $otp = (string) random_int(1000, 9999);

        OtpCode::create([
            'user_id'    => $user->id,
            'email'      => $user->email,
            'purpose'    => 'password_reset',
            'code'       => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
            'used_at'    => null,
        ]);

        $mailable = new PasswordResetOtpMail($otp, $user);

        try {
            Mail::to($user->email)->send($mailable);

            app(EmailLogService::class)->logMailableSent($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => (string) $user->email,
                'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                'template_key' => 'password_reset_otp',
                'source_module' => 'Auth',
                'related_type' => User::class,
                'related_id' => (string) $user->id,
                'payload' => [
                    'purpose' => 'password_reset',
                ],
            ]);
        } catch (\Throwable $exception) {
            app(EmailLogService::class)->logMailableFailed($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => (string) $user->email,
                'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                'template_key' => 'password_reset_otp',
                'source_module' => 'Auth',
                'related_type' => User::class,
                'related_id' => (string) $user->id,
                'payload' => [
                    'purpose' => 'password_reset',
                ],
            ], $exception);

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'If your email is registered, a password reset OTP has been sent.',
            'data'    => null,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'                 => ['required', 'email'],
            'otp'                   => ['required', 'digits:4'],
            'password'              => ['required', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a registered user.',
                'data'    => null,
            ], 404);
        }

        $otpRecord = OtpCode::where('user_id', $user->id)
            ->where('purpose', 'password_reset')
            ->whereNull('used_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
                'data'    => null,
            ], 422);
        }

        if (now()->greaterThan($otpRecord->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired.',
                'data'    => null,
            ], 422);
        }

        if (! Hash::check($data['otp'], $otpRecord->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
                'data'    => null,
            ], 422);
        }

        $otpRecord->used_at = now();
        $otpRecord->save();

        $user->password_hash = Hash::make($data['password']);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
            'data'    => null,
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return $this->success(null, 'Logged out successfully');
    }

    public function me(): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        $user->expireFreeTrialIfNeeded();
        $user->refresh();

        return $this->success(new UserResource($user->loadMissing([
            'city',
            'activeCircle:id,name,slug,city_id',
            'activeCircle.cityRef:id,name',
            'circleMemberships' => fn ($query) => $query
                ->where('status', (string) config('circle.member_joined_status', 'approved'))
                ->whereNull('deleted_at')
                ->whereNull('left_at')
                ->where(function ($nested): void {
                    $nested->whereNull('paid_ends_at')->orWhere('paid_ends_at', '>=', now());
                })
                ->orderByDesc('joined_at')
                ->with('circle:id,name,slug'),
        ])));
    }
}
