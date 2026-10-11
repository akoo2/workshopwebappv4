<?php

namespace App\Security;

class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        if (empty($_SESSION[self::SESSION_KEY]) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    public static function regenerate(): string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
            return $_SESSION[self::SESSION_KEY];
        }
        return '';
    }
}
