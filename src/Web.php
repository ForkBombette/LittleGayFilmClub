<?php
declare(strict_types=1);
namespace LGFC;
final class Web
{
    public static function start(): void
    {
        if (!session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict'])) {
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
    public static function checkCsrf(): void
    {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            http_response_code(403);
            exit('Please reload the page and try again.');
        }
    }
}
