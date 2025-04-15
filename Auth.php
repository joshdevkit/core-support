<?php

namespace Forpart\Core;

use App\Models\User;

class Auth
{
    public static function auth()
    {
        return Session::get("user_id") ?? null;
    }

    public static function user()
    {
        $userId = Session::get('user_id');
        return $userId ? User::find($userId) : null;
    }

    public static function check()
    {
        return self::user() !== null;
    }

    public static function logout()
    {
        Session::forget('user_id');
    }

    public static function login(array $credentials)
    {
        $user = User::where('email', $credentials['email'])->first();

        if ($user && password_verify($credentials['password'], $user->password)) {
            Session::put('user_id', $user->id);
            return true;
        }

        return false;
    }
}
