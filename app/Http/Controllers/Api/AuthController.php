<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\LoginOtpMail;
use App\Mail\PasswordResetOtpMail;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends BaseApiController
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        // Build a display name from first + last name
        $displayName = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));

        $user                 = new User();
        $user->id             = Str::uuid();
        $user->first_name     = $data['first_name'];
        $user->last_name      = $data['last_name'] ?? null;
        $user->display_name   = $displayName;
        $user->email          = $data['email'];
        $user->phone          = $data['phone'] ?? null;
        $user->company_name   = $data['company_name'] ?? null;
        $user->designation    = $data['designation'] ?? null;
        $user->city_id        = $user->city_id ?? null;
        $trialEndsAt = now()->addDays(3);

        $user->membership_status = User::STATUS_FREE_TRIAL;
        $user->membership_starts_at = now();
        $user->membership_ends_at = $trialEndsAt;
        $user->membership_expiry = $trialEndsAt;
        $user->coins_balance  = $user->coins_balance ?? 0;

        // Store the hashed password in password_hash (not password)
        $user->password_hash = Hash::make($data['password']);

        // Ensure any legacy password attribute isn't used
        if (isset($user->password)) {
            $user->password = null;
        }

        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'data'    => [
                'token' => $token,
                'user'  => $user,
            ],
        ], 201);
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

        Mail::to($user->email)->send(new LoginOtpMail($otp, $user));

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
            'user' => new UserResource($user->load('city')),
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

        Mail::to($user->email)->send(new PasswordResetOtpMail($otp, $user));

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

        return $this->success(new UserResource($user->loadMissing('city')));
    }
}
