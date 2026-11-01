<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests;

use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;
use \CarlBennett\PlexTvAPI\Server;
use \CarlBennett\PlexTvAPI\Tests\Support\FakeTransport;
use \CarlBennett\PlexTvAPI\User;
use \PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private FakeTransport $transport;

    protected function setUp(): void
    {
        $this->transport = FakeTransport::install();
    }

    protected function tearDown(): void
    {
        FakeTransport::uninstall();
    }

    private static function fixture(string $name): string
    {
        return \file_get_contents(__DIR__ . '/fixtures/' . $name);
    }

    public function testGetUsersParsesUsersAndServers(): void
    {
        $this->transport->queue(200, 'application/xml; charset=utf-8', self::fixture('users.xml'));
        $users = User::getUsers('secret-token');

        $this->assertCount(2, $users);
        [$alice, $bob] = $users;

        $this->assertSame(1001, $alice->getId());
        $this->assertSame('Alice', $alice->getTitle());
        $this->assertSame('alice@example.com', $alice->getEmail());
        $this->assertTrue($alice->getProtected());
        $this->assertTrue($alice->getAllowSync());
        $this->assertFalse($alice->getAllowTuners());
        $this->assertNull($alice->getRecommendationsPlaylistId());
        $this->assertCount(2, $alice->getServers());
        $this->assertContainsOnlyInstancesOf(Server::class, $alice->getServers());

        $this->assertSame(1002, $bob->getId());
        $this->assertNull($bob->getEmail());
        $this->assertSame(42, $bob->getRecommendationsPlaylistId());
        $this->assertSame('label=kids', $bob->getFilterMovies());
        $this->assertTrue($bob->getRestricted());
        $this->assertCount(1, $bob->getServers());
    }

    public function testGetUsersSendsTokenInHeader(): void
    {
        $this->transport->queue(200, 'application/xml; charset=utf-8', self::fixture('users.xml'));
        User::getUsers('secret-token');

        $request = $this->transport->lastRequest();
        $this->assertStringStartsWith('https://plex.tv/api/users?', $request['url']);
        $this->assertStringNotContainsString('secret-token', $request['url']);
        $this->assertContains('X-Plex-Token: secret-token', $request['headers']);
    }

    public function testJsonSerializeIncludesServers(): void
    {
        $this->transport->queue(200, 'application/xml; charset=utf-8', self::fixture('users.xml'));
        $json = \json_decode(\json_encode(User::getUsers('secret-token')[0]), true);

        $this->assertSame(1001, $json['id']);
        $this->assertSame('Living Room', $json['servers'][0]['name']);
        $this->assertSame(1767225600, $json['servers'][0]['lastSeenAt']);
        $this->assertTrue($json['servers'][0]['owned']);
        $this->assertTrue($json['servers'][1]['pending']);
    }

    public function testGetUsersWithTruncatedXmlThrows(): void
    {
        $xml = self::fixture('users.xml');
        $this->transport->queue(200, 'application/xml; charset=utf-8', \substr($xml, 0, (int) (\strlen($xml) / 2)));

        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('malformed XML response');
        User::getUsers('secret-token');
    }

    public function testGetUsersUnauthorizedThrows(): void
    {
        $this->transport->queue(401, 'text/html', 'Unauthorized');
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('access unauthorized');
        User::getUsers('secret-token');
    }
}
