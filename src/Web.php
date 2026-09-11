<?php
declare(strict_types=1);
namespace LGFC;
final class Web
{
    public static function start(): void
    {
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        if (!session_start(['use_strict_mode' => true, 'use_only_cookies' => true, 'cookie_secure' => Auth::https(), 'cookie_httponly' => true, 'cookie_samesite' => 'Strict'])) {
            http_response_code(500);
            exit('Session storage is unavailable. Please contact the host.');
        }
        header('Cache-Control: no-store');
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    public static function checkCsrf(?string $token = null, bool $json = false): void
    {
        $token ??= $_POST['csrf'] ?? null;
        if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
            http_response_code(403);
            exit($json ? json_encode(['error'=>'Please reload the page and try again.']) : 'Please reload the page and try again.');
        }
    }
}
