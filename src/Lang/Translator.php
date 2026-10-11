<?php

namespace App\Lang;

class Translator
{
    private static array $translations = [];
    private static string $locale = 'en';

    public static function setLocale(string $locale): void
    {
        if (in_array($locale, ['en', 'ar', 'ckb'], true)) {
            self::$locale = $locale;
        } else {
            self::$locale = 'en';
        }
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function isRtl(): bool
    {
        return in_array(self::$locale, ['ar', 'ckb'], true);
    }

    public static function trans(string $key, array $params = []): string
    {
        if (!isset(self::$translations[self::$locale])) {
            $file = __DIR__ . '/' . self::$locale . '.php';
            if (file_exists($file)) {
                self::$translations[self::$locale] = require $file;
            } else {
                self::$translations[self::$locale] = [];
            }
        }

        $text = self::$translations[self::$locale][$key] ?? null;

        // Fallback to English if not found
        if ($text === null) {
            if (!isset(self::$translations['en'])) {
                self::$translations['en'] = require __DIR__ . '/en.php';
            }
            $text = self::$translations['en'][$key] ?? $key;
        }

        foreach ($params as $paramKey => $paramVal) {
            $text = str_replace(':' . $paramKey, (string)$paramVal, $text);
        }

        return $text;
    }
}
