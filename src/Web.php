<?php
declare(strict_types=1);
namespace LGFC;
final class Web
{
    private static bool $csrfCreated = false;
    public static function start(): void
    {
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store');
        if (!session_start(['use_strict_mode' => true, 'use_only_cookies' => true, 'cookie_secure' => Auth::https(), 'cookie_httponly' => true, 'cookie_samesite' => 'Strict'])) {
            $reference = self::diagnostic('session_start_failed');
            http_response_code(500);
            exit('Session storage is unavailable. Please contact the host. Reference: ' . $reference);
        }
        self::$csrfCreated = !isset($_SESSION['csrf']);
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    /** Log metadata only: never cookies, tokens, session IDs, query strings or form bodies. */
    private static function diagnostic(string $reason): string
    {
        $reference = bin2hex(random_bytes(6));
        $cookieCount = preg_match_all('/(?:^|;\s*)' . preg_quote(session_name(), '/') . '=/', $_SERVER['HTTP_COOKIE'] ?? '');
        error_log('LGFC ' . json_encode([
            'reference' => $reference,
            'reason' => $reason,
            'endpoint' => basename($_SERVER['SCRIPT_NAME'] ?? ''),
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'https' => Auth::https(),
            'session_active' => session_status() === PHP_SESSION_ACTIVE,
            'session_cookie_present' => isset($_COOKIE[session_name()]),
            'session_cookie_count' => $cookieCount,
            'csrf_created_this_request' => self::$csrfCreated,
            'session_handler' => ini_get('session.save_handler'),
        ], JSON_UNESCAPED_SLASHES));
        return $reference;
    }
    public static function checkCsrf(?string $token = null, bool $json = false): void
    {
        $token ??= $_POST['csrf'] ?? null;
        $expected = $_SESSION['csrf'] ?? null;
        if (!is_string($token) || $token === '' || !is_string($expected) || $expected === '' || !hash_equals($expected, $token)) {
            $reason = !is_string($token) || $token === '' ? 'csrf_token_missing' : (!is_string($expected) || $expected === '' ? 'csrf_session_token_missing' : 'csrf_token_mismatch');
            $reference = self::diagnostic($reason);
            $message = 'This page could not be verified against your current browser session. Reload it and try again. If this continues, ask the organiser to check the server session settings. Reference: ' . $reference;
            http_response_code(403);
            if ($json) header('Content-Type: application/json; charset=utf-8');
            exit($json ? json_encode(['error'=>$message, 'code'=>'csrf_failed', 'reference'=>$reference]) : $message);
        }
    }
}
