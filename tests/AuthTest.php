<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests;

use \CarlBennett\PlexTvAPI\Auth;
use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;
use \CarlBennett\PlexTvAPI\Tests\Support\FakeTransport;
use \PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    private FakeTransport $transport;

    protected function setUp(): void
    {
        Auth::$client_id = 'test-client-id';
        $this->transport = FakeTransport::install();
    }

    protected function tearDown(): void
    {
        FakeTransport::uninstall();
        Auth::$client_id = null;
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
}
