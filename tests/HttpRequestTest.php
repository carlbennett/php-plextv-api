<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests;

use \CarlBennett\PlexTvAPI\HttpRequest;
use \CarlBennett\PlexTvAPI\Tests\Support\FakeTransport;
use \CarlBennett\PlexTvAPI\Tests\Support\LocalServer;
use \PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type Reply from HttpRequest
 */
class HttpRequestTest extends TestCase
{
    private static LocalServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = new LocalServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    protected function setUp(): void
    {
        HttpRequest::$redirect_protocols = 'http,https'; // the local test server only speaks http
    }

    protected function tearDown(): void
    {
        HttpRequest::$redirect_protocols = 'https';
        FakeTransport::uninstall();
        HttpRequest::$timeout = 30;
    }

    /**
     * @param Reply $reply
     * @return array<string, mixed> The request as the test server saw it.
     */
    private function echo(array $reply): array
    {
        $this->assertSame(200, $reply['reply_http_code']);
        $this->assertIsString($reply['reply']);
        return \json_decode($reply['reply'], true, 512, \JSON_THROW_ON_ERROR);
    }

    public function testGetRequestReturnsReply(): void
    {
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, self::$server->url('/echo?a=1'));
        $echo = $this->echo($reply);

        $this->assertSame('GET', $echo['method']);
        $this->assertSame(['a' => '1'], $echo['query']);
        $this->assertSame('application/json; charset=utf-8', $reply['reply_mime_type']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function methodProvider(): array
    {
        return [
            'DELETE' => [HttpRequest::METHOD_DELETE],
            'GET' => [HttpRequest::METHOD_GET],
            'OPTIONS' => [HttpRequest::METHOD_OPTIONS],
            'POST' => [HttpRequest::METHOD_POST],
            'PUT' => [HttpRequest::METHOD_PUT],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('methodProvider')]
    public function testRequestMethodIsSentWithoutForm(string $method): void
    {
        $echo = $this->echo(HttpRequest::execute($method, self::$server->url('/echo')));
        $this->assertSame($method, $echo['method']);
    }

    public function testHeadRequestReturnsNoBody(): void
    {
        $reply = HttpRequest::execute(HttpRequest::METHOD_HEAD, self::$server->url('/echo'));
        $this->assertSame(200, $reply['reply_http_code']);
        $this->assertSame('', $reply['reply']);
    }

    public function testFormIsSentUrlEncodedByDefault(): void
    {
        $echo = $this->echo(HttpRequest::execute(HttpRequest::METHOD_POST, self::$server->url('/echo'), '', ['a' => '1', 'b' => 'x y']));

        $this->assertSame('POST', $echo['method']);
        $this->assertSame('application/x-www-form-urlencoded', $echo['headers']['Content-Type']);
        $this->assertSame('a=1&b=x+y', $echo['body']);
        $this->assertSame(['a' => '1', 'b' => 'x y'], $echo['post']);
    }

    public function testFormIsSentAsMultipartWhenRequested(): void
    {
        $echo = $this->echo(HttpRequest::execute(HttpRequest::METHOD_POST, self::$server->url('/echo'), 'multipart/form-data', ['a' => '1']));

        $this->assertStringStartsWith('multipart/form-data; boundary=', $echo['headers']['Content-Type']);
        $this->assertSame(['a' => '1'], $echo['post']);
    }

    public function testDefaultHeadersIdentifyTheLibrary(): void
    {
        $echo = $this->echo(HttpRequest::execute(HttpRequest::METHOD_GET, self::$server->url('/echo')));

        $this->assertStringStartsWith('php-plextv-api', $echo['headers']['User-Agent']);
        $this->assertArrayNotHasKey('Range', $echo['headers']);
    }

    public function testExtraHeadersAreSent(): void
    {
        $echo = $this->echo(HttpRequest::execute(HttpRequest::METHOD_GET, self::$server->url('/echo'), '', null, ['X-Test: yes']));
        $this->assertSame('yes', $echo['headers']['X-Test']);
    }

    public function testUrlIsTheEffectiveUrlAfterRedirects(): void
    {
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, self::$server->url('/redirect'));

        $this->assertSame(['redirected' => '1'], $this->echo($reply)['query']);
        $this->assertSame(self::$server->url('/echo?redirected=1'), $reply['url']);
        $this->assertSame('', $reply['error']);
    }

    public function testRedirectDoesNotSendReferer(): void
    {
        $echo = $this->echo(HttpRequest::execute(HttpRequest::METHOD_GET, self::$server->url('/redirect?X-Plex-Token=secret')));
        $this->assertArrayNotHasKey('Referer', $echo['headers']);
    }

    public function testRedirectToPlainHttpIsRefusedByDefault(): void
    {
        HttpRequest::$redirect_protocols = 'https';
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, self::$server->url('/redirect'));

        $this->assertFalse($reply['reply']);
        $this->assertStringContainsStringIgnoringCase('protocol', $reply['error']);
    }

    public function testTransportFailureReportsCurlError(): void
    {
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, 'http://127.0.0.1:1/');

        $this->assertFalse($reply['reply']);
        $this->assertSame(0, $reply['reply_http_code']);
        $this->assertNull($reply['reply_mime_type']);
        $this->assertNotSame('', $reply['error']);
    }

    public function testSlowReplyTimesOut(): void
    {
        HttpRequest::$timeout = 1;
        $started = \microtime(true);
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, self::$server->url('/slow'));

        $this->assertFalse($reply['reply']);
        $this->assertStringContainsStringIgnoringCase('timed out', $reply['error']);
        $this->assertLessThan(4, \microtime(true) - $started);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function injectedHeaderProvider(): array
    {
        return [
            'CRLF' => ["X-Test: a\r\nX-Injected: b"],
            'LF' => ["X-Test: a\nX-Injected: b"],
            'NUL' => ["X-Test: a\0b"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('injectedHeaderProvider')]
    public function testHeaderInjectionIsRejected(string $header): void
    {
        FakeTransport::install();
        $this->expectException(\InvalidArgumentException::class);
        HttpRequest::execute(HttpRequest::METHOD_GET, 'https://plex.invalid/', '', null, [$header]);
    }

    public function testFormMimeTypeInjectionIsRejected(): void
    {
        FakeTransport::install();
        $this->expectException(\InvalidArgumentException::class);
        HttpRequest::execute(HttpRequest::METHOD_POST, 'https://plex.invalid/', "text/plain\r\nX-Injected: b", ['a' => '1']);
    }

    public function testEmptyMethodIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HttpRequest::execute('', 'https://plex.invalid/');
    }

    public function testHandlerReplacesTransport(): void
    {
        $transport = FakeTransport::install()->queue(204);
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, 'https://plex.invalid/');

        $this->assertSame(204, $reply['reply_http_code']);
        $this->assertSame('https://plex.invalid/', $transport->lastRequest()['url']);
    }
}
