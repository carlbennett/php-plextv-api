<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests;

use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;
use \CarlBennett\PlexTvAPI\HttpRequest;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\TestCase;

class ExpectReplyTest extends TestCase
{
    private static function reply(int $code, ?string $mime_type, string|false $body = '{}', string $error = ''): array
    {
        return ['error' => $error, 'reply' => $body, 'reply_http_code' => $code, 'reply_mime_type' => $mime_type];
    }

    public static function charsetProvider(): array
    {
        return [
            'no charset' => ['application/json', ''],
            'charset' => ['application/json; charset=utf-8', 'utf-8'],
            'quoted charset' => ['application/json; charset="UTF-8"', 'UTF-8'],
            'extra parameters' => ['Application/JSON;version=2; Charset=utf-8', 'utf-8'],
        ];
    }

    #[DataProvider('charsetProvider')]
    public function testReturnsCharsetFromContentType(string $content_type, string $charset): void
    {
        $this->assertSame($charset, HttpRequest::expectReply(self::reply(200, $content_type), 'application/json'));
    }

    public function testAcceptsListedHttpCodes(): void
    {
        $this->assertSame('', HttpRequest::expectReply(self::reply(201, 'application/json'), 'application/json', [200, 201]));
    }

    public static function badReplyProvider(): array
    {
        return [
            'transport failure' => [self::reply(0, null, false, 'Could not resolve host: plex.tv'), 'HTTP request failed: Could not resolve host: plex.tv'],
            'unexpected code' => [self::reply(500, 'application/json'), 'unexpected HTTP response code: 500'],
            'empty body' => [self::reply(200, 'application/json', ''), 'empty HTTP response'],
            'missing content type' => [self::reply(200, null), 'HTTP response has no Content-Type'],
            'wrong content type' => [self::reply(200, 'text/html; charset=utf-8'), 'unexpected MIME-type returned: text/html'],
        ];
    }

    #[DataProvider('badReplyProvider')]
    public function testRejectsBadReplies(array $reply, string $message): void
    {
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage($message);
        HttpRequest::expectReply($reply, 'application/json');
    }
}
