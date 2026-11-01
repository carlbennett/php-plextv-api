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

    public function testExtraHeadersAreSent(): void
    {
        $echo = $this->echo(HttpRequest::execute(HttpRequest::METHOD_GET, self::$server->url('/echo'), '', null, ['X-Test: yes']));
        $this->assertSame('yes', $echo['headers']['X-Test']);
    }

    public function testHandlerReplacesTransport(): void
    {
        $transport = FakeTransport::install()->queue(204);
        $reply = HttpRequest::execute(HttpRequest::METHOD_GET, 'https://plex.invalid/');

        $this->assertSame(204, $reply['reply_http_code']);
        $this->assertSame('https://plex.invalid/', $transport->lastRequest()['url']);
    }
}
