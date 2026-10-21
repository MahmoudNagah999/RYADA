<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\LoginData;
use App\Domain\Auth\DTOs\LoginResult;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    public function execute(LoginData $data): LoginResult
    {
        $user = User::query()->where('email', $data->email)->first();

        if ($user === null || ! Hash::check($data->password, $user->password)) {
            // Same error for unknown email and wrong password (no user enumeration).
            throw new BusinessRuleException('auth.invalid_credentials', [], 401);
        }

        $token = $user->createToken($data->deviceName, $data->abilities)->plainTextToken;

        return new LoginResult($user, $token, $data->abilities);
    }
}
