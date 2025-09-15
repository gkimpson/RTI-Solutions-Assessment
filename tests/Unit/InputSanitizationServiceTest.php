<?php

namespace Tests\Unit;

use App\Services\InputSanitizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputSanitizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitize_search_removes_sql_injection_attempts()
    {
        $maliciousInput = "test'; DROP TABLE tasks; --";
        $sanitized = InputSanitizationService::sanitizeSearch($maliciousInput);

        $this->assertStringNotContainsString('DROP', $sanitized);
        $this->assertStringNotContainsString('--', $sanitized);
        $this->assertStringNotContainsString(';', $sanitized);
    }

    public function test_sanitize_search_removes_excessive_whitespace()
    {
        $input = '  test   multiple    spaces   ';
        $sanitized = InputSanitizationService::sanitizeSearch($input);

        $this->assertEquals('test multiple spaces', $sanitized);
    }

    public function test_sanitize_search_removes_non_printable_characters()
    {
        $input = "test\x00\x01\x02control";
        $sanitized = InputSanitizationService::sanitizeSearch($input);

        $this->assertEquals('testcontrol', $sanitized);
    }

    public function test_sanitize_search_limits_length()
    {
        $longInput = str_repeat('a', 300);
        $sanitized = InputSanitizationService::sanitizeSearch($longInput);

        $this->assertEquals(255, strlen($sanitized));
    }

    public function test_sanitize_metadata_removes_script_tags()
    {
        $metadata = [
            'description' => '<script>alert("xss")</script>Safe content',
            'title' => 'Normal title',
        ];

        $sanitized = InputSanitizationService::sanitizeMetadata($metadata);

        $this->assertEquals('alert("xss")Safe content', $sanitized['description']);
        $this->assertEquals('Normal title', $sanitized['title']);
    }

    public function test_sanitize_metadata_removes_event_handlers()
    {
        $metadata = [
            'content' => 'Some text onclick="malicious()" more text',
            'data' => 'onmouseover="evil()" content',
        ];

        $sanitized = InputSanitizationService::sanitizeMetadata($metadata);

        $this->assertStringNotContainsString('onclick', $sanitized['content']);
        $this->assertStringNotContainsString('onmouseover', $sanitized['data']);
    }

    public function test_sanitize_metadata_removes_javascript_urls()
    {
        $metadata = [
            'link' => 'javascript:alert("xss")',
            'href' => 'https://example.com',
        ];

        $sanitized = InputSanitizationService::sanitizeMetadata($metadata);

        $this->assertStringNotContainsString('javascript:', $sanitized['link']);
        $this->assertStringContainsString('https://example.com', $sanitized['href']);
    }

    public function test_sanitize_metadata_handles_nested_arrays()
    {
        $metadata = [
            'nested' => [
                'level2' => [
                    'dangerous' => '<script>evil()</script>',
                    'safe' => 'content',
                ],
            ],
        ];

        $sanitized = InputSanitizationService::sanitizeMetadata($metadata);

        $this->assertStringNotContainsString('<script>', $sanitized['nested']['level2']['dangerous']);
        $this->assertEquals('content', $sanitized['nested']['level2']['safe']);
    }

    public function test_sanitize_tag_name_removes_html_tags()
    {
        $tagName = '<b>Important</b> Tag';
        $sanitized = InputSanitizationService::sanitizeTagName($tagName);

        $this->assertEquals('Important Tag', $sanitized);
    }

    public function test_sanitize_tag_name_removes_special_characters()
    {
        $tagName = 'Tag@#$%^&*()Name';
        $sanitized = InputSanitizationService::sanitizeTagName($tagName);

        $this->assertEquals('TagName', $sanitized);
    }

    public function test_sanitize_tag_name_keeps_allowed_characters()
    {
        $tagName = 'Tag_Name-123.test';
        $sanitized = InputSanitizationService::sanitizeTagName($tagName);

        $this->assertEquals('Tag_Name-123.test', $sanitized);
    }

    public function test_sanitize_tag_name_limits_length()
    {
        $longTagName = str_repeat('a', 60);
        $sanitized = InputSanitizationService::sanitizeTagName($longTagName);

        $this->assertEquals(50, strlen($sanitized));
    }

    public function test_validate_file_upload_size_limit()
    {
        $file = [
            'size' => 11 * 1024 * 1024, // 11MB
            'type' => 'image/jpeg',
            'name' => 'test.jpg',
        ];

        $errors = InputSanitizationService::validateFileUpload($file);

        $this->assertContains('File size cannot exceed 10MB', $errors);
    }

    public function test_validate_file_upload_type_restriction()
    {
        $file = [
            'size' => 1024,
            'type' => 'application/x-executable',
            'name' => 'virus.exe',
        ];

        $errors = InputSanitizationService::validateFileUpload($file);

        $this->assertContains('File type not allowed', $errors);
    }

    public function test_validate_file_upload_double_extension()
    {
        $file = [
            'size' => 1024,
            'type' => 'image/jpeg',
            'name' => 'image.jpg.exe',
        ];

        $errors = InputSanitizationService::validateFileUpload($file);

        $this->assertContains('Files with multiple extensions are not allowed', $errors);
    }

    public function test_validate_file_upload_allowed_file()
    {
        $file = [
            'size' => 1024,
            'type' => 'image/jpeg',
            'name' => 'image.jpg',
        ];

        $errors = InputSanitizationService::validateFileUpload($file);

        $this->assertEmpty($errors);
    }

    public function test_sanitize_order_by_removes_dangerous_characters()
    {
        $orderBy = 'title; DROP TABLE users; --';
        $sanitized = InputSanitizationService::sanitizeOrderBy($orderBy);

        $this->assertEquals('titleDROPTABLEusers', $sanitized);
    }

    public function test_sanitize_order_by_allows_safe_characters()
    {
        $orderBy = 'users.created_at';
        $sanitized = InputSanitizationService::sanitizeOrderBy($orderBy);

        $this->assertEquals('users.created_at', $sanitized);
    }

    public function test_get_security_headers_includes_required_headers()
    {
        $headers = InputSanitizationService::getSecurityHeaders();

        $expectedHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection',
            'Referrer-Policy',
            'Content-Security-Policy',
        ];

        foreach ($expectedHeaders as $header) {
            $this->assertArrayHasKey($header, $headers);
        }
    }

    public function test_rate_limit_key_generation_for_authenticated_user()
    {
        $user = \App\Models\User::factory()->create(['id' => 123]);

        // Set up Sanctum authentication
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $key = InputSanitizationService::getRateLimitKey('test');

        $this->assertEquals('rate_limit:test:123', $key);
    }

    public function test_rate_limit_key_generation_for_guest()
    {
        // Mock request IP
        request()->server->set('REMOTE_ADDR', '192.168.1.1');

        $key = InputSanitizationService::getRateLimitKey('test');

        $this->assertStringContainsString('rate_limit:test:', $key);
    }
}
