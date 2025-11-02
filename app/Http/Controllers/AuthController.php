<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Notifications\PasswordOtpNotification;

class AuthController extends Controller
{
    // ====== OTP config ======
    // 60s to input the OTP initially
    private int $otpTtlInitialSeconds   = 60;

    // After successful verify, give user N more minutes to set the password
    private int $graceAfterVerifyMinutes = 10;

    private int $maxAttempts = 5;   // wrong code tries
    private int $maxResends  = 5;   // resend limit

    // ====== Auth ======

    // Register (deferred account creation) -> send OTP to email; do NOT create user yet
    public function register(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        $email = strtolower($request->email);

        // If already registered, stop here
        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'Email is already registered'], 422);
        }

        // Create fresh OTP
        $code = (string) random_int(100000, 999999);
        $hash = Hash::make($code);

        // clear any old pending rows for this email
        DB::table('registration_otps')->where('email', $email)->delete();

        DB::table('registration_otps')->insert([
            'name'       => $request->name,
            'email'      => $email,
            'code_hash'  => $hash,
            'expires_at' => now()->addSeconds($this->otpTtlInitialSeconds),
            'attempts'   => 0,
            'resends'    => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send OTP to raw email (no user yet)
        $displayMinutes = max(1, (int) ceil($this->otpTtlInitialSeconds / 60));
        Notification::route('mail', $email)
            ->notify(new PasswordOtpNotification($code, 'register', $displayMinutes));

        return response()->json([
            'message' => 'We sent a 6-digit code to your email. Use it to set your password.',
        ], 200);
    }

    // Login
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', strtolower($request->email))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load('role');

        return response()->json([
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role ? ['name' => $user->role->name] : null,
            ],
            'token' => $token,
        ], 200);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    // ====== OTP flows for password reset / registration ======

    // Request OTP for password reset (existing users only; generic response to avoid enumeration)
    public function forgot(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower($request->email);

        $user = User::where('email', $email)->first();
        if ($user) {
            $this->issuePasswordResetOtp($email);
        }

        return response()->json(['message' => 'If the email exists, a 6-digit code was sent.']);
    }

    // Resend OTP (works for pending registration and password reset)
    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower($request->email);

        // Try pending registration first
        $row = DB::table('registration_otps')->where('email', $email)->orderByDesc('id')->first();
        if ($row) {
            if ($row->resends >= $this->maxResends) {
                return response()->json(['message' => 'Resend limit reached'], 429);
            }
            $code = (string) random_int(100000, 999999);
            $hash = Hash::make($code);

            DB::table('registration_otps')->where('id', $row->id)->update([
                'code_hash'  => $hash,
                'expires_at' => now()->addSeconds($this->otpTtlInitialSeconds),
                'resends'    => $row->resends + 1,
                'updated_at' => now(),
            ]);

            $displayMinutes = max(1, (int) ceil($this->otpTtlInitialSeconds / 60));
            Notification::route('mail', $email)
                ->notify(new PasswordOtpNotification($code, 'register', $displayMinutes));

            return response()->json(['message' => 'OTP resent']);
        }

        // Else try password reset OTP (must have a real user)
        $user = User::where('email', $email)->first();
        if ($user) {
            $this->issuePasswordResetOtp($email, true); // rotate if exists
            return response()->json(['message' => 'OTP resent']);
        }

        // No pending registration and no user — still reply generically
        return response()->json(['message' => 'OTP sent if the email exists.']);
    }

    // Verify OTP (no password yet) — extend expiry for extra time to set password
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|digits:6',
        ]);

        $email = strtolower($request->email);

        // Prefer pending registration; else fall back to password reset OTP
        $row = DB::table('registration_otps')->where('email', $email)->orderByDesc('id')->first();
        $context = 'register';
        $table = 'registration_otps';

        if (!$row) {
            $row = DB::table('password_otps')->where('email', $email)->orderByDesc('id')->first();
            $context = 'password_reset';
            $table = 'password_otps';
        }

        $invalid = fn() => response()->json(['message' => 'Invalid or expired code'], 422);
        if (!$row) return $invalid();

        if (now()->greaterThan(Carbon::parse($row->expires_at))) {
            DB::table($table)->where('id', $row->id)->delete();
            return $invalid();
        }

        if (($row->attempts ?? 0) >= $this->maxAttempts) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }

        $ok = Hash::check($request->code, $row->code_hash);

        if (!$ok) {
            // increment ONLY on failure
            DB::table($table)->where('id', $row->id)->update([
                'attempts'   => ($row->attempts ?? 0) + 1,
                'updated_at' => now(),
            ]);
            return $invalid();
        }

        // SUCCESS: extend expiry to give the user more time to set password
        $newExpiry = now()->addMinutes($this->graceAfterVerifyMinutes);

        DB::table($table)->where('id', $row->id)->update([
            'expires_at' => $newExpiry,
            'attempts'   => 0, // optional reset
            'updated_at' => now(),
        ]);

        return response()->json([
            'message'     => 'Code verified',
            'context'     => $context,
            'ttl_seconds' => Carbon::parse($newExpiry)->diffInSeconds(now()),
        ]);
    }

    // Reset/Set password with OTP
    public function resetWithOtp(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'code'     => 'required|digits:6',
            'password' => [
                'required', 'string', 'confirmed',
                PasswordRule::min(8)->letters()->numbers()->symbols(),
                'regex:/[A-Z]/',
            ],
        ]);

        $email = strtolower($request->email);

        // Case A: existing user -> standard reset
        $user = User::where('email', $email)->first();
        if ($user) {
            $row = DB::table('password_otps')->where('email', $email)->orderByDesc('id')->first();
            $invalid = fn() => response()->json(['message' => 'Invalid or expired code'], 422);

            if (!$row) return $invalid();
            if (now()->greaterThan(Carbon::parse($row->expires_at))) {
                DB::table('password_otps')->where('id', $row->id)->delete();
                return $invalid();
            }
            if (($row->attempts ?? 0) >= $this->maxAttempts) {
                return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
            }

            $match = Hash::check($request->code, $row->code_hash);
            if (!$match) {
                DB::table('password_otps')->where('id', $row->id)->update([
                    'attempts'   => ($row->attempts ?? 0) + 1,
                    'updated_at' => now(),
                ]);
                return $invalid();
            }

            $user->password = Hash::make($request->password);
            $user->remember_token = Str::random(60);
            $user->save();

            // revoke any existing tokens (Sanctum)
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            // Clean up all OTPs for this email
            DB::table('password_otps')->where('email', $email)->delete();

            return response()->json(['message' => 'Password reset successful. You can log in now.']);
        }

        // Case B: pending registration -> create user now
        $pending = DB::table('registration_otps')->where('email', $email)->orderByDesc('id')->first();
        $invalid = fn() => response()->json(['message' => 'Invalid or expired code'], 422);

        if (!$pending) return $invalid();
        if (now()->greaterThan(Carbon::parse($pending->expires_at))) {
            DB::table('registration_otps')->where('id', $pending->id)->delete();
            return $invalid();
        }
        if (($pending->attempts ?? 0) >= $this->maxAttempts) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }

        $match = Hash::check($request->code, $pending->code_hash);
        if (!$match) {
            DB::table('registration_otps')->where('id', $pending->id)->update([
                'attempts'   => ($pending->attempts ?? 0) + 1,
                'updated_at' => now(),
            ]);
            return $invalid();
        }

        // Re-check uniqueness right before create (race-safe)
        if (User::where('email', $email)->exists()) {
            // Someone created this user meanwhile; treat as conflict
            DB::table('registration_otps')->where('id', $pending->id)->delete();
            return response()->json(['message' => 'This email is already registered.'], 422);
        }

        // Create the actual user now
        $newUser = User::create([
            'name'     => $pending->name,
            'email'    => $email,
            'password' => Hash::make($request->password),
            'role_id'  => 4, // Customer
        ]);

        // Cleanup
        DB::table('registration_otps')->where('email', $email)->delete();
        DB::table('password_otps')->where('email', $email)->delete();

        return response()->json(['message' => 'Account created. You can now log in.']);
    }

    // ====== helpers ======
    // Issue or rotate OTP for password reset (only for existing users)
    private function issuePasswordResetOtp(string $email, bool $rotateIfExists = false): void
    {
        // Ensure user exists
        $user = User::where('email', $email)->first();
        if (!$user) return;

        $existing = DB::table('password_otps')->where('email', $email)->orderByDesc('id')->first();

        $code = (string) random_int(100000, 999999);
        $hash = Hash::make($code);
        $displayMinutes = max(1, (int) ceil($this->otpTtlInitialSeconds / 60));

        if ($existing && $rotateIfExists) {
            DB::table('password_otps')->where('id', $existing->id)->update([
                'code_hash'  => $hash,
                'expires_at' => now()->addSeconds($this->otpTtlInitialSeconds),
                'resends'    => ($existing->resends ?? 0) + 1,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('password_otps')->where('email', $email)->delete(); // clean old rows
            DB::table('password_otps')->insert([
                'email'      => $email,
                'code_hash'  => $hash,
                'expires_at' => now()->addSeconds($this->otpTtlInitialSeconds),
                'attempts'   => 0,
                'resends'    => 0,
                'purpose'    => 'password_reset',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // notify the actual user model
        $user->notify(new PasswordOtpNotification($code, 'password_reset', $displayMinutes));
    }

    // === session/me ===
    public function me(Request $request)
    {
        $user = $request->user()->load('role');

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role ? ['name' => $user->role->name] : null,
        ]);
    }
}
