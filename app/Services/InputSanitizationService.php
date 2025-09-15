<?php

declare(strict_types=1);

namespace App\Services;

class InputSanitizationService
{
    /**
     * Sanitize search input to prevent injection attacks
     */
    public static function sanitizeSearch(string $input): string
    {
        // Remove potential SQL injection characters
        $input = str_replace(['--', ';', '/*', '*/', 'DROP', 'DELETE', 'UPDATE', 'INSERT'], '', $input);

        // Remove excessive whitespace
        $input = preg_replace('/\s+/', ' ', trim($input));

        // Remove non-printable characters
        $input = preg_replace('/[^\x20-\x7E]/', '', $input);

        // Limit length
        return substr($input, 0, 255);
    }

    /**
     * Sanitize metadata input to prevent XSS and other attacks
     */
    public static function sanitizeMetadata(array $metadata): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                // Remove script tags but keep content inside them
                $value = preg_replace('/<script[^>]*>(.*?)<\/script>/is', '$1', $value);
                // Remove potential event handlers but preserve surrounding text
                $value = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/', '', $value);
                // Remove javascript: URLs but preserve surrounding text
                $value = preg_replace('/javascript\s*:\s*[^"\'\s]+/i', '', $value);
            } elseif (is_array($value)) {
                $value = self::sanitizeMetadata($value);
            }

            return $value;
        }, $metadata);
    }

    /**
     * Validate and sanitize tag names
     */
    public static function sanitizeTagName(string $name): string
    {
        // Remove HTML tags
        $name = strip_tags($name);

        // Remove special characters but keep basic punctuation
        $name = preg_replace('/[^\w\s\-_.]/', '', $name);

        // Remove excessive whitespace
        $name = preg_replace('/\s+/', ' ', trim($name));

        // Limit length
        return substr($name, 0, 50);
    }

    /**
     * Rate limiting check for API endpoints
     */
    public static function checkRateLimit(string $key, int $maxAttempts = 60, int $decayMinutes = 1): bool
    {
        $attempts = cache()->get($key, 0);

        if ($attempts >= $maxAttempts) {
            return false;
        }

        cache()->put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        return true;
    }

    /**
     * Generate a rate limiting key for the current user/IP
     */
    public static function getRateLimitKey(string $action = 'api'): string
    {
        // Try different authentication methods for compatibility
        $user = auth('sanctum')->user() ?? auth()->user();
        $identifier = $user ? $user->id : (request()->ip() ?? '127.0.0.1');

        return "rate_limit:{$action}:{$identifier}";
    }

    /**
     * Validate file upload security
     */
    public static function validateFileUpload(array $file): array
    {
        $errors = [];

        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = 'File size cannot exceed 10MB';
        }

        // Check allowed file types
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
        if (! in_array($file['type'], $allowedTypes)) {
            $errors[] = 'File type not allowed';
        }

        // Check for double extensions (security risk)
        if (substr_count($file['name'], '.') > 1) {
            $errors[] = 'Files with multiple extensions are not allowed';
        }

        return $errors;
    }

    /**
     * Sanitize SQL ORDER BY clause to prevent injection
     */
    public static function sanitizeOrderBy(string $orderBy): string
    {
        // Only allow alphanumeric characters, underscores, and dots
        return preg_replace('/[^a-zA-Z0-9_.]/', '', $orderBy);
    }

    /**
     * Generate CSRF-safe headers for API responses
     */
    public static function getSecurityHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'",
        ];
    }
}
