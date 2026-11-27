<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Seals the email address and the reset token into one opaque, URL-safe code, so the reset link is
 * /reset-password/{code} with no editable ?email= parameter. The code is encrypted and authenticated
 * with APP_KEY: changing any character makes it invalid. Laravel's password broker still checks the
 * token itself (hash, expiry and single use).
 */
final class ResetLink
{
    public static function make(string $email, string $token): string
    {
        $sealed = Crypt::encryptString(json_encode(['e' => $email, 't' => $token], JSON_THROW_ON_ERROR));

        return rtrim(strtr(base64_encode($sealed), '+/', '-_'), '=');
    }

    /** @return array{email: string, token: string}|null  null when the code was altered or is not one of ours */
    public static function read(string $code): ?array
    {
        if ($code === '' || strlen($code) > 2048 || ! preg_match('/^[A-Za-z0-9_-]+$/', $code)) {
            return null;
        }

        try {
            $sealed = base64_decode(strtr($code, '-_', '+/'), true);
            $data = $sealed === false ? null : json_decode(Crypt::decryptString($sealed), true);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($data) || ! is_string($data['e'] ?? null) || ! is_string($data['t'] ?? null)) {
            return null;
        }

        return ['email' => $data['e'], 'token' => $data['t']];
    }
}
