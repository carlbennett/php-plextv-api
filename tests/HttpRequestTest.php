<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests;

use \CarlBennett\PlexTvAPI\HttpRequest;
use \CarlBennett\PlexTvAPI\Tests\Support\FakeTransport;
use \CarlBennett\PlexTvAPI\Tests\Support\LocalServer;
use \PHPUnit\Framework\TestCase;

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

    protected function tearDown(): void
    {
        FakeTransport::uninstall();
    }

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

    public function testTransportFailureReportsCurlError(): void
    {
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, 'http://127.0.0.1:1/');

        $this->assertFalse($reply['reply']);
        $this->assertSame(0, $reply['reply_http_code']);
        $this->assertNotSame('', $reply['error']);
    }

    public function testHandlerReplacesTransport(): void
    {
        $transport = FakeTransport::install()->queue(204);
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, 'https://plex.invalid/');

        $this->assertSame(204, $reply['reply_http_code']);
        $this->assertSame('https://plex.invalid/', $transport->lastRequest()['url']);
    }
}
