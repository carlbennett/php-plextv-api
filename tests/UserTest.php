<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests;

use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;
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
        $contents = \file_get_contents(__DIR__ . '/fixtures/' . $name);
        if ($contents === false)
            throw new \RuntimeException(\sprintf('cannot read fixture %s', $name));

        return $contents;
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
        $this->assertSame('Office', $alice->getServers()[1]->jsonSerialize()['name']);

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
        $json = \json_decode(\json_encode(User::getUsers('secret-token')[0], \JSON_THROW_ON_ERROR), true);

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

    public function testGetUsersWithoutCharsetInContentType(): void
    {
        $this->transport->queue(200, 'application/xml', self::fixture('users.xml'));
        $this->assertCount(2, User::getUsers('secret-token'));
    }

    public function testGetUsersKeepsUsersWithoutServers(): void
    {
        $this->transport->queue(200, 'application/xml', self::fixture('users-without-servers.xml'));
        $users = User::getUsers('secret-token');

        $this->assertSame([1001, 1002, 1003], \array_map(fn (User $user) => $user->getId(), $users));
        $this->assertSame([], $users[0]->getServers());
        $this->assertCount(1, $users[1]->getServers(), 'a Server outside any User must not be attached to the previous user');
        $this->assertSame([], $users[2]->getServers());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function brokenAttributeProvider(): array
    {
        return [
            'Server missing lastSeenAt' => ['lastSeenAt="1767225600" ', '', 'Server is missing its lastSeenAt attribute'],
            'Server with non-numeric id' => ['id="2001" ', 'id="abc" ', 'Server has a non-numeric id attribute'],
            'User missing title' => ['title="Alice" ', '', 'User is missing its title attribute'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenAttributeProvider')]
    public function testGetUsersWithBrokenAttributeThrows(string $search, string $replace, string $message): void
    {
        $xml = self::fixture('users.xml');
        $broken = \preg_replace('/' . \preg_quote($search, '/') . '/', $replace, $xml, 1);
        $this->assertIsString($broken);
        $this->assertNotSame($xml, $broken);
        $this->transport->queue(200, 'application/xml', $broken);

        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage($message);
        User::getUsers('secret-token');
    }
}
