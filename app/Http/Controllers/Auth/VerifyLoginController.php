<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Services\BrevoMailService;
use App\Services\TechsoftSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class VerifyLoginController extends Controller
{
    /* =========================
     *  1) SEND OTP (forgot)
     * ========================= */
    public function sendForgotOtp(Request $request, BrevoMailService $mail, TechsoftSmsService $sms)
    {
        $rid = (string) Str::uuid();

        Log::info("[OTP][$rid] sendForgotOtp:start", [
            'ip' => $request->ip(),
            'ua' => substr((string) $request->userAgent(), 0, 120),
        ]);

        $request->validate([
            'login' => ['required', 'string', 'min:3'],
        ]);

        $raw = trim((string) $request->input('login'));
        [$channel, $normalized] = $this->detectChannelAndNormalize($raw);

        Log::info("[OTP][$rid] detect", [
            'raw' => $raw,
            'channel' => $channel,
            'normalized' => $normalized,
        ]);

        // Anti-spam: IP + destination
        $rlKey = $this->rlKey('send', $request->ip(), $channel, $normalized);
        if (RateLimiter::tooManyAttempts($rlKey, (int) env('OTP_SEND_MAX_PER_MIN', 5))) {
            Log::warning("[OTP][$rid] rate_limited_send", ['key' => $rlKey]);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['login' => 'Trop de tentatives. Réessaie dans 1 minute.'])
                ->withInput();
        }
        RateLimiter::hit($rlKey, 60);

        // Trouver employé
        $employe = $this->findEmployeByLogin($channel, $normalized);

        Log::info("[OTP][$rid] employe_lookup", [
            'found' => (bool) $employe,
            'employe_id' => $employe?->id,
        ]);

        // ✅ IMPORTANT: tu as demandé d’informer si compte n’existe pas
        if (!$employe) {
            return back()
                ->with('show_forgot', true)
                ->withErrors(['login' => 'Compte introuvable. Vérifie votre email ou numéro.'])
                ->withInput();
        }

        // Générer OTP
        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) env('OTP_TTL_MINUTES', 10);

        $otpKey = $this->otpCacheKey($channel, $normalized);

        Cache::put($otpKey, [
            'hash'       => Hash::make($code),
            'channel'    => $channel,
            'normalized' => $normalized,
            'employe_id' => $employe->id,
            'attempts'   => 0,
            'resends'    => 0,
            'expires_at' => now()->addMinutes($ttlMinutes)->timestamp,
        ], now()->addMinutes($ttlMinutes));

        Log::info("[OTP][$rid] cache_written", [
            'otpKey' => $otpKey,
            'ttlMin' => $ttlMinutes,
            'expires_at' => now()->addMinutes($ttlMinutes)->toDateTimeString(),
        ]);

        // Mettre la vue en mode forgot + ouvrir modale OTP
        $request->session()->put('show_forgot', true);
        $request->session()->put('pwd_reset', [
            'channel'    => $channel,
            'normalized' => $normalized,
            'masked_to'  => $this->maskDestination($channel, $normalized),
        ]);
        $request->session()->put('pwd_reset_modal', true);

        // Envoi
        $sendOk = false;
        $sendError = null;
        $sendResp = null;

        try {
            if ($channel === 'email') {
                // ⚠️ ton service email doit être validé côté Brevo (sender/domain). Sinon, laisse pour plus tard.
                $fullName = trim(($employe->prenom ?? '') . ' ' . ($employe->nom ?? '')) ?: 'Utilisateur';
                Log::info("[OTP][$rid] send_email", ['to' => $normalized]);
                $mail->sendResetOtp($normalized, $fullName, $code);
                $sendOk = true;
            } else {
                Log::info("[OTP][$rid] send_sms", ['to' => $normalized]);
                // IMPORTANT: tu as noté que certains textes OTP étaient filtrés.
                // TechsoftSmsService->sendOtp doit utiliser un texte "neutre" qui passe.
                $sendResp = $sms->sendOtp($normalized, $code, $ttlMinutes, 'reset');
                $sendOk = (bool)($sendResp['ok'] ?? false);
                $sendError = $sendOk ? null : ($sendResp['body'] ?? 'SMS failed');
                Log::info("[OTP][$rid] send_sms_response", ['resp' => $sendResp]);
            }
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
            Log::error("[OTP][$rid] send_exception", ['error' => $sendError]);
        }

        if (!$sendOk) {
            // On garde la modale ouverte
            $request->session()->put('pwd_reset_modal', true);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['login' => "Impossible d’envoyer le code. {$sendError}"])
                ->withInput();
        }

        return back()
            ->with('show_forgot', true)
            ->with('status', 'Code envoyé. Vérifiez vos messages.')
            ->withInput();
    }

    /* =========================
     *  2) RESEND OTP
     * ========================= */
    public function resendForgotOtp(Request $request, BrevoMailService $mail, TechsoftSmsService $sms)
    {
        $rid = (string) Str::uuid();
        Log::info("[OTP][$rid] resendForgotOtp:start", ['ip' => $request->ip()]);

        $sess = $request->session()->get('pwd_reset');
        Log::info("[OTP][$rid] session_pwd_reset", ['pwd_reset' => $sess]);

        if (!$sess || empty($sess['channel']) || empty($sess['normalized'])) {
            return back()
                ->with('show_forgot', true)
                ->withErrors(['login' => 'Veuillez saisir votre email/téléphone.']);
        }

        $channel = $sess['channel'];
        $normalized = $sess['normalized'];

        // Rate limit renvoi
        $rlKey = $this->rlKey('resend', $request->ip(), $channel, $normalized);
        if (RateLimiter::tooManyAttempts($rlKey, (int) env('OTP_RESEND_MAX_PER_MIN', 5))) {
            Log::warning("[OTP][$rid] rate_limited_resend", ['key' => $rlKey]);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => 'Trop de renvois. Réessaie dans 1 minute.']);
        }
        RateLimiter::hit($rlKey, 60);

        $otpKey = $this->otpCacheKey($channel, $normalized);
        $data = Cache::get($otpKey);

        Log::info("[OTP][$rid] cache_read", [
            'otpKey' => $otpKey,
            'hasData' => (bool)$data,
            'expires_at' => $data['expires_at'] ?? null,
            'resends' => $data['resends'] ?? null,
            'attempts' => $data['attempts'] ?? null,
        ]);

        if (!$data || now()->timestamp > (int)($data['expires_at'] ?? 0)) {
            $request->session()->put('pwd_reset_modal', true);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => 'Code expiré. Cliquez sur “Envoyer le code”.']);
        }

        $maxResends = (int) env('OTP_MAX_RESENDS', 3);
        if ((int)($data['resends'] ?? 0) >= $maxResends) {
            $request->session()->put('pwd_reset_modal', true);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => "Limite de renvoi atteinte ({$maxResends})."]);
        }

        $employe = !empty($data['employe_id']) ? Employe::find($data['employe_id']) : null;
        Log::info("[OTP][$rid] employe_for_resend", [
            'found' => (bool)$employe,
            'employe_id' => $employe?->id,
        ]);

        if (!$employe) {
            // sécurité
            $request->session()->put('pwd_reset_modal', true);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => 'Session invalide. Recommencez.']);
        }

        // Nouveau code
        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) env('OTP_TTL_MINUTES', 10);

        $data['hash'] = Hash::make($code);
        $data['resends'] = ((int)($data['resends'] ?? 0)) + 1;
        $data['attempts'] = 0;
        $data['expires_at'] = now()->addMinutes($ttlMinutes)->timestamp;

        Cache::put($otpKey, $data, now()->addMinutes($ttlMinutes));

        // Envoi
        $sendOk = false;
        $sendError = null;
        $sendResp = null;

        try {
            if ($channel === 'email') {
                $fullName = trim(($employe->prenom ?? '') . ' ' . ($employe->nom ?? '')) ?: 'Utilisateur';
                Log::info("[OTP][$rid] resend_email", ['to' => $normalized]);
                $mail->sendResetOtp($normalized, $fullName, $code);
                $sendOk = true;
            } else {
                Log::info("[OTP][$rid] resend_sms", ['to' => $normalized]);
                $sendResp = $sms->sendOtp($normalized, $code, $ttlMinutes, 'reset');
                $sendOk = (bool)($sendResp['ok'] ?? false);
                $sendError = $sendOk ? null : ($sendResp['body'] ?? 'SMS failed');
                Log::info("[OTP][$rid] resend_sms_response", ['resp' => $sendResp]);
            }
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
            Log::error("[OTP][$rid] resend_exception", ['error' => $sendError]);
        }

        Log::info("[OTP][$rid] resend_result", [
            'sendOk' => $sendOk,
            'sendError' => $sendError,
        ]);

        $request->session()->put('show_forgot', true);
        $request->session()->put('pwd_reset_modal', true);

        if (!$sendOk) {
            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => "Impossible de renvoyer le code. {$sendError}"]);
        }

        Log::info("[OTP][$rid] cache_updated", [
            'otpKey' => $otpKey,
            'resends' => $data['resends'],
            'expires_at' => $data['expires_at'],
        ]);

        return back()
            ->with('show_forgot', true)
            ->with('status', 'Code renvoyé.');
    }

    /* =========================
     *  3) VERIFY OTP => redirect to /reset-password/{token}
     * ========================= */
    public function verifyForgotOtp(Request $request)
    {
        $rid = (string) Str::uuid();
        Log::info("[OTP][$rid] verifyForgotOtp:start", ['ip' => $request->ip()]);

        $request->validate([
            'otp_code' => ['required', 'digits:6'],
        ]);

        $sess = $request->session()->get('pwd_reset');
        Log::info("[OTP][$rid] session_pwd_reset", ['pwd_reset' => $sess]);

        if (!$sess || empty($sess['channel']) || empty($sess['normalized'])) {
            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => 'Session expirée. Recommencez.']);
        }

        $channel = $sess['channel'];
        $normalized = $sess['normalized'];

        // Rate limit verify
        $rlKey = $this->rlKey('verify', $request->ip(), $channel, $normalized);
        if (RateLimiter::tooManyAttempts($rlKey, (int) env('OTP_VERIFY_MAX_PER_MIN', 10))) {
            Log::warning("[OTP][$rid] rate_limited_verify", ['key' => $rlKey]);
            $request->session()->put('pwd_reset_modal', true);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => 'Trop de tentatives. Réessaie dans 1 minute.']);
        }
        RateLimiter::hit($rlKey, 60);

        $otpKey = $this->otpCacheKey($channel, $normalized);
        $data = Cache::get($otpKey);

        Log::info("[OTP][$rid] cache_read", [
            'otpKey' => $otpKey,
            'hasData' => (bool)$data,
            'expires_at' => $data['expires_at'] ?? null,
            'attempts' => $data['attempts'] ?? null,
        ]);

        if (!$data || now()->timestamp > (int)($data['expires_at'] ?? 0)) {
            $request->session()->put('show_forgot', true);
            $request->session()->put('pwd_reset_modal', true);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => 'Code expiré. Cliquez sur “Renvoyer le code”.']);
        }

        $maxAttempts = (int) env('OTP_MAX_ATTEMPTS', 5);
        if ((int)($data['attempts'] ?? 0) >= $maxAttempts) {
            $request->session()->put('show_forgot', true);
            $request->session()->put('pwd_reset_modal', true);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => "Trop d’essais ({$maxAttempts}). Renvoyez un nouveau code."]);
        }

        $code = (string) $request->input('otp_code');

        if (!Hash::check($code, (string)($data['hash'] ?? ''))) {
            $data['attempts'] = ((int)($data['attempts'] ?? 0)) + 1;
            Cache::put($otpKey, $data, now()->addMinutes((int) env('OTP_TTL_MINUTES', 10)));

            Log::warning("[OTP][$rid] otp_invalid", [
                'attempts' => $data['attempts'],
                'maxAttempts' => $maxAttempts,
            ]);

            $request->session()->put('show_forgot', true);
            $request->session()->put('pwd_reset_modal', true);

            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => 'Code invalide.']);
        }

        $employeId = $data['employe_id'] ?? null;
        if (!$employeId) {
            Log::warning("[OTP][$rid] missing_employe_id_in_cache");
            $request->session()->put('show_forgot', true);
            $request->session()->put('pwd_reset_modal', true);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['otp_code' => 'Code invalide ou expiré.']);
        }

        // OK => créer reset token court
        $resetToken = Str::random(64);
        $resetTtl = (int) env('RESET_TOKEN_TTL_MINUTES', 15);

        Cache::put($this->resetTokenCacheKey($resetToken), [
            'employe_id' => $employeId,
        ], now()->addMinutes($resetTtl));

        Log::info("[OTP][$rid] reset_token_created", [
            'resetTtlMin' => $resetTtl,
            'employe_id' => $employeId,
        ]);

        // cleanup OTP + session
        Cache::forget($otpKey);
        $request->session()->forget(['pwd_reset_modal', 'pwd_reset', 'show_forgot']);

        return redirect()->route('otp.password.reset', ['token' => $resetToken]);
    }

    /* =========================
     *  4) SHOW RESET FORM (GET /reset-password/{token})
     * ========================= */
    public function showResetForm(string $token)
    {
        $data = Cache::get($this->resetTokenCacheKey($token));
        abort_if(!$data, 404);

        return view('auth.reset-password-otp', ['token' => $token]);
    }

    /* =========================
     *  5) RESET PASSWORD (POST /reset-password)
     * ========================= */
    public function resetPassword(Request $request)
    {
        $rid = (string) Str::uuid();
        Log::info("[OTP][$rid] resetPassword:start", ['ip' => $request->ip()]);

        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $token = (string) $request->input('token');
        $data = Cache::get($this->resetTokenCacheKey($token));

        Log::info("[OTP][$rid] reset_token_read", [
            'token_present' => (bool)$data,
        ]);

        if (!$data) {
            return redirect()
                ->route('login')
                ->with('status', 'Jeton expiré. Recommencez la procédure.');
        }

        $employe = Employe::find($data['employe_id'] ?? null);
        if (!$employe) {
            return redirect()
                ->route('login')
                ->with('status', 'Compte introuvable.');
        }

        // cast "hashed" sur Employe => hash auto
        $employe->password = $request->input('password');
        $employe->save();

        Cache::forget($this->resetTokenCacheKey($token));

        Log::info("[OTP][$rid] resetPassword:done", [
            'employe_id' => $employe->id,
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
    }

    /* =========================
     * Helpers
     * ========================= */

    /**
     * Détecte email vs téléphone et normalise.
     * - email => lowercase
     * - phone => digits-only normalisé 2376XXXXXXXX
     */
    private function detectChannelAndNormalize(string $raw): array
    {
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return ['email', mb_strtolower($raw)];
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        // 00237XXXXXXXXX -> 237XXXXXXXXX
        if (str_starts_with($digits, '00237')) {
            $digits = substr($digits, 2);
        }

        // 0696XXXXXX -> 696XXXXXX
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // 696XXXXXX -> 237696XXXXXX
        if (strlen($digits) === 9 && str_starts_with($digits, '6')) {
            $digits = '237' . $digits;
        }

        return ['sms', $digits];
    }

    /**
     * Recherche employé de manière robuste:
     * - email exact
     * - phone exact (peut être stocké avec +237 ou 237 ou sans)
     */
    private function findEmployeByLogin(string $channel, string $normalized): ?Employe
    {
        if ($channel === 'email') {
            return Employe::where('email', $normalized)->first();
        }

        // phone: le stockage peut être "+237..." ou "237..." ou "696..."
        $digits = preg_replace('/\D+/', '', $normalized) ?? $normalized;

        $variants = [];
        $variants[] = $digits;                       // 237696...
        $variants[] = '+' . $digits;                 // +237696...
        if (str_starts_with($digits, '237') && strlen($digits) === 12) {
            $variants[] = substr($digits, 3);        // 696...
            $variants[] = '0' . substr($digits, 3);  // 0696...
        }

        return Employe::query()
            ->whereIn('phone', $variants)
            ->first();
    }

    private function maskDestination(string $channel, string $normalized): string
    {
        if ($channel === 'email') {
            [$u, $d] = array_pad(explode('@', $normalized, 2), 2, '');
            $uMask = mb_substr($u, 0, 1) . '***';
            $dotPos = mb_strrpos($d, '.');
            $ext = $dotPos !== false ? mb_substr($d, $dotPos) : '';
            $dMask = mb_substr($d, 0, 1) . '***' . $ext;
            return $uMask . '@' . $dMask;
        }
        // phone
        $digits = preg_replace('/\D+/', '', $normalized) ?? $normalized;
        return '+237 ***' . mb_substr($digits, -3);
    }

    private function otpCacheKey(string $channel, string $normalized): string
    {
        return 'pwd_reset_otp:' . sha1($channel . '|' . $normalized);
    }

    private function resetTokenCacheKey(string $token): string
    {
        return 'pwd_reset_token:' . $token;
    }

    private function rlKey(string $action, string $ip, string $channel, string $normalized): string
    {
        return "pwdotp:{$action}:{$ip}:" . sha1($channel . '|' . $normalized);
    }
}
