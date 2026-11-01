<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests;

use \CarlBennett\PlexTvAPI\Auth;
use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;
use \CarlBennett\PlexTvAPI\Tests\Support\FakeTransport;
use \PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    private FakeTransport $transport;
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        Auth::$client_id = 'test-client-id';
        $this->server = $_SERVER;
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']);
        $this->transport = FakeTransport::install();
    }

    protected function tearDown(): void
    {
        FakeTransport::uninstall();
        Auth::$client_id = null;
        Auth::$forward_url_base = null;
        Auth::$forward_url_endpoint = '/plex/auth';
        $_SERVER = $this->server;
    }

    public function testRequestsWithoutClientIdThrow(): void
    {
        Auth::$client_id = null;
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('Auth::$client_id must be set');
        Auth::verifyUserAccessToken('token');
    }

    public function testGenerateClientIdReturnsUniqueV4Uuids(): void
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
        $a = Auth::generateClientId();
        $b = Auth::generateClientId();

        $this->assertMatchesRegularExpression($pattern, $a);
        $this->assertMatchesRegularExpression($pattern, $b);
        $this->assertNotSame($a, $b);
    }

    public function testGetInteractiveAuthUrl(): void
    {
        $url = Auth::getInteractiveAuthUrl('client id', 'ABCD', 'https://example.com/plex/auth?a=1&b=2', 'My App');

        $this->assertSame(
            'https://app.plex.tv/auth#?clientID=client%20id&code=ABCD'
            . '&forwardUrl=https%3A%2F%2Fexample.com%2Fplex%2Fauth%3Fa%3D1%26b%3D2'
            . '&context%5Bdevice%5D%5Bproduct%5D=My%20App',
            $url
        );
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function forwardUrlProvider(): array
    {
        return [
            'http' => [['HTTP_HOST' => 'example.com'], 'http://example.com/plex/auth'],
            'https' => [['HTTP_HOST' => 'example.com', 'HTTPS' => 'on'], 'https://example.com/plex/auth'],
            'HTTPS=off' => [['HTTP_HOST' => 'example.com', 'HTTPS' => 'off'], 'http://example.com/plex/auth'],
            'SERVER_NAME fallback' => [['SERVER_NAME' => 'fallback.example'], 'http://fallback.example/plex/auth'],
        ];
    }

    /**
     * @param array<string, string> $server
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('forwardUrlProvider')]
    public function testGetForwardUrlFromRequest(array $server, string $expected): void
    {
        $_SERVER = $server + $_SERVER;
        $this->assertSame($expected, Auth::getForwardUrl());
    }

    public function testGetForwardUrlPrefersConfiguredBase(): void
    {
        $_SERVER['HTTP_HOST'] = 'attacker.example';
        Auth::$forward_url_base = 'https://example.com/';
        Auth::$forward_url_endpoint = 'return';

        $this->assertSame('https://example.com/return', Auth::getForwardUrl());
    }

    public function testGetForwardUrlWithoutHostThrows(): void
    {
        $this->expectException(PlexTvAPIException::class);
        Auth::getForwardUrl();
    }

    /**
     * @return array<string, array{int}>
     */
    public static function pinReplyCodeProvider(): array
    {
        return ['200 OK' => [200], '201 Created' => [201]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pinReplyCodeProvider')]
    public function testGeneratePINCodePostsAndReturnsIdAndCode(int $code): void
    {
        $this->transport->queue($code, 'application/json; charset=utf-8', '{"id":1234,"code":"abcd"}');

        $this->assertSame([1234, 'abcd'], Auth::generatePINCode());
        $request = $this->transport->lastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringStartsWith('https://plex.tv/api/v2/pins?', $request['url']);
        $this->assertStringContainsString('X-Plex-Client-Identifier=test-client-id', $request['url']);
    }

    public function testGeneratePINCodeWithoutCodeThrows(): void
    {
        $this->transport->queue(201, 'application/json', '{"id":1234}');
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('missing its id or code');
        Auth::generatePINCode();
    }

    public function testGeneratePINCodeWithNonNumericIdThrows(): void
    {
        $this->transport->queue(201, 'application/json', '{"id":"1234","code":"abcd"}');
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('missing its id or code');
        Auth::generatePINCode();
    }

    public function testGeneratePINCodeWithInvalidJsonThrows(): void
    {
        $this->transport->queue(201, 'application/json', '{"id":');
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('unexpected JSON error');
        Auth::generatePINCode();
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function tokenReplyProvider(): array
    {
        return ['valid' => [200, true], 'invalid' => [401, false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tokenReplyProvider')]
    public function testVerifyUserAccessToken(int $code, bool $expected): void
    {
        $this->transport->queue($code, 'application/json', '{}');

        $this->assertSame($expected, Auth::verifyUserAccessToken('secret-token'));
        $request = $this->transport->lastRequest();
        $this->assertStringNotContainsString('secret-token', $request['url']);
        $this->assertContains('X-Plex-Token: secret-token', $request['headers']);
    }

    public function testVerifyUserAccessTokenUnexpectedCodeThrows(): void
    {
        $this->transport->queue(500, 'application/json', '{}');
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('unexpected HTTP response code: 500');
        Auth::verifyUserAccessToken('secret-token');
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function pinTokenProvider(): array
    {
        return [
            'signed in' => ['{"id":1234,"code":"abcd","authToken":"user-token"}', 'user-token'],
            'not signed in yet' => ['{"id":1234,"code":"abcd","authToken":null}', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pinTokenProvider')]
    public function testVerifyPINCode(string $body, ?string $expected): void
    {
        $this->transport->queue(200, 'application/json', $body);

        $this->assertSame($expected, Auth::verifyPINCode('1234', 'abcd'));
        $this->assertStringStartsWith('https://plex.tv/api/v2/pins/1234?code=abcd&', $this->transport->lastRequest()['url']);
    }

    public function testVerifyPINCodeRejectsNonNumericId(): void
    {
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('PIN id must be numeric');
        Auth::verifyPINCode('../user', 'abcd');
    }
}
