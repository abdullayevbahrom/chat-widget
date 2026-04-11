<?php

namespace Tests\Unit;

use App\Services\CssSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CssSanitizerTest extends TestCase
{
    #[Test]
    public function it_strips_html_tags_from_css(): void
    {
        $sanitizer = new CssSanitizer();
        $input = '<script>alert("xss")</script>body { color: red; }';
        $result = $sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('color: red', $result);
    }

    #[Test]
    public function it_blocks_css_expression(): void
    {
        $sanitizer = new CssSanitizer();
        $input = 'body { width: expression(alert("xss")); }';
        $result = $sanitizer->sanitize($input);

        $this->assertStringNotContainsString('expression(', $result);
        $this->assertStringContainsString('/* blocked */', $result);
    }

    #[Test]
    public function it_blocks_javascript_protocol(): void
    {
        $sanitizer = new CssSanitizer();

        $inputs = [
            'body { background: url("javascript:alert(1)"); }',
            'body { background: url(javascript:alert(1)); }',
            'body { background: url(  "javascript:alert(1)"  ); }',
        ];

        foreach ($inputs as $input) {
            $result = $sanitizer->sanitize($input);
            $this->assertStringNotContainsString('javascript:', $result, "Failed for: {$input}");
        }
    }

    #[Test]
    public function it_blocks_import(): void
    {
        $sanitizer = new CssSanitizer();
        $input = '@import url("https://evil.com/malicious.css");';
        $result = $sanitizer->sanitize($input);

        $this->assertStringNotContainsString('@import', $result);
    }

    #[Test]
    public function it_blocks_behavior(): void
    {
        $sanitizer = new CssSanitizer();
        $input = 'body { behavior: url("malicious.htc"); }';
        $result = $sanitizer->sanitize($input);

        $this->assertStringNotContainsString('behavior:', $result);
    }

    #[Test]
    public function it_blocks_moz_binding(): void
    {
        $sanitizer = new CssSanitizer();
        $input = 'body { -moz-binding: url("exploit.xml#xss"); }';
        $result = $sanitizer->sanitize($input);

        $this->assertStringNotContainsString('-moz-binding:', $result);
    }

    #[Test]
    public function it_blocks_css_hex_escape_attack(): void
    {
        $sanitizer = new CssSanitizer();
        // \65\78\70\72\65\73\73\69\6f\6e = "expression" in hex escapes
        $input = 'body { width: \65 \78 \70 \72 \65 \73 \73 \69 \6f \6e (alert(1)); }';
        $result = $sanitizer->sanitize($input);

        $this->assertStringNotContainsString('expression(', $result);
    }

    #[Test]
    public function it_removes_null_bytes(): void
    {
        $sanitizer = new CssSanitizer();
        $input = "body { color: red\0; }";
        $result = $sanitizer->sanitize($input);

        $this->assertStringNotContainsString("\0", $result);
    }

    #[Test]
    public function it_blocks_data_protocol_in_url(): void
    {
        $sanitizer = new CssSanitizer();
        $input = 'body { background: url("data:text/html,<script>alert(1)</script>"); }';
        $result = $sanitizer->sanitize($input);

        $this->assertStringNotContainsString('data:', $result);
    }

    #[Test]
    public function it_blocks_vbscript_protocol(): void
    {
        $sanitizer = new CssSanitizer();
        $input = 'body { background: url("vbscript:msgbox(1)"); }';
        $result = $sanitizer->sanitize($input);

        $this->assertStringNotContainsString('vbscript:', $result);
    }

    #[Test]
    public function it_allows_safe_css(): void
    {
        $sanitizer = new CssSanitizer();
        $input = <<<'CSS'
body {
    color: #333;
    font-size: 16px;
    margin: 0;
    padding: 10px;
    background-color: #fff;
    border: 1px solid #ccc;
    border-radius: 4px;
}
CSS;

        $result = $sanitizer->sanitize($input);

        $this->assertStringContainsString('color: #333', $result);
        $this->assertStringContainsString('font-size: 16px', $result);
        $this->assertStringContainsString('border-radius: 4px', $result);
    }

    #[Test]
    #[DataProvider('dangerousCssPatternProvider')]
    public function it_blocks_dangerous_pattern(string $pattern, string $description): void
    {
        $sanitizer = new CssSanitizer();
        $result = $sanitizer->sanitize($pattern);

        $this->assertStringNotContainsString(
            strtolower(explode('(', $pattern)[0]),
            strtolower($result),
            "Failed to block: {$description}"
        );
    }

    public static function dangerousCssPatternProvider(): array
    {
        return [
            ['body { width: Expression(alert(1)); }', 'Expression with capital E'],
            ['body { background: URL("javascript:alert(1)"); }', 'URL with uppercase'],
            ['body { -WEBKIT-BINDING: url("evil.xml"); }', '-webkit-binding uppercase'],
        ];
    }

    #[Test]
    public function it_removes_css_comments(): void
    {
        $sanitizer = new CssSanitizer();
        $input = 'body { color: red; /* this is a comment */ }';
        $result = $sanitizer->sanitize($input);

        $this->assertStringNotContainsString('/*', $result);
        $this->assertStringNotContainsString('*/', $result);
        $this->assertStringContainsString('color: red', $result);
    }

    #[Test]
    public function sanitize_file_returns_empty_for_nonexistent_file(): void
    {
        $sanitizer = new CssSanitizer();
        $result = $sanitizer->sanitizeFile('/nonexistent/path/file.css');

        $this->assertSame('', $result);
    }

    #[Test]
    public function sanitize_file_sanitizes_file_content(): void
    {
        $sanitizer = new CssSanitizer();
        $testFile = sys_get_temp_dir() . '/test_widget_css.css';
        file_put_contents($testFile, "body { color: red; }\n");

        $result = $sanitizer->sanitizeFile($testFile);

        $this->assertStringContainsString('color: red', $result);

        unlink($testFile);
    }
}
