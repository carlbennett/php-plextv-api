<?php /* vim: set colorcolumn= expandtab shiftwidth=4 softtabstop=4 tabstop=4 smarttab: */

namespace CarlBennett\PlexTvAPI\Tests;

use \CarlBennett\PlexTvAPI\Attributes;
use \CarlBennett\PlexTvAPI\Exceptions\PlexTvAPIException;
use \PHPUnit\Framework\TestCase;

class AttributesTest extends TestCase
{
    private Attributes $attributes;

    protected function setUp(): void
    {
        $this->attributes = new Attributes(['empty' => '', 'id' => '42', 'negative' => '-7', 'one' => '1', 'text' => 'hi', 'word' => 'abc', 'zero' => '0'], 'Thing');
    }

    public function testBool(): void
    {
        $this->assertTrue($this->attributes->bool('one'));
        $this->assertFalse($this->attributes->bool('zero'));
        $this->assertFalse($this->attributes->bool('empty'));
        $this->assertFalse($this->attributes->bool('missing'));
    }

    public function testInt(): void
    {
        $this->assertSame(42, $this->attributes->int('id'));
        $this->assertSame(-7, $this->attributes->int('negative'));
        $this->assertNull($this->attributes->nullableInt('empty'));
        $this->assertNull($this->attributes->nullableInt('missing'));
    }

    public function testMissingIntThrows(): void
    {
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('Thing is missing its missing attribute');
        $this->attributes->int('missing');
    }

    public function testNonNumericIntThrows(): void
    {
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('Thing has a non-numeric word attribute');
        $this->attributes->nullableInt('word');
    }

    public function testString(): void
    {
        $this->assertSame('hi', $this->attributes->string('text'));
        $this->assertSame('', $this->attributes->string('empty'));
        $this->assertNull($this->attributes->nullableString('empty'));
        $this->assertNull($this->attributes->nullableString('missing'));
    }

    public function testMissingStringThrows(): void
    {
        $this->expectException(PlexTvAPIException::class);
        $this->expectExceptionMessage('Thing is missing its missing attribute');
        $this->attributes->string('missing');
    }
}
