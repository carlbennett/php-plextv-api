<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests;

use \CarlBennett\PlexTvAPI\Auth;
use \PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function testGenerateClientIdReturnsUniqueV4Uuids(): void
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
        $a = Auth::generateClientId();
        $b = Auth::generateClientId();

        $this->assertMatchesRegularExpression($pattern, $a);
        $this->assertMatchesRegularExpression($pattern, $b);
        $this->assertNotSame($a, $b);
    }
}
