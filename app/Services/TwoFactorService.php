<?php

namespace App\Services;

use App\Models\TwoFactorCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TwoFactorService
{
    public function generateCode(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        TwoFactorCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    public function verifyCode(User $user, string $code): bool
    {
        $twoFactorCode = TwoFactorCode::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $twoFactorCode || ! Hash::check($code, $twoFactorCode->code_hash)) {
            return false;
        }

        $twoFactorCode->update(['used_at' => now()]);

        return true;
    }

    public function cleanupExpiredCodes(): int
    {
        return TwoFactorCode::where('expires_at', '<', now()->subHours(24))->delete();
    }
}
