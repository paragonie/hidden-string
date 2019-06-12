<?php
declare(strict_types=1);

use ParagonIE\HiddenString\HiddenString;
use ParagonIE\ConstantTime\Base64UrlSafe;
use PHPUnit\Framework\TestCase;

final class HiddenStringTest extends TestCase
{
    public function testEquals()
    {
        $A = HiddenString::create(Base64UrlSafe::encode(random_bytes(32)));
        $B = HiddenString::create(Base64UrlSafe::encode(random_bytes(32)));
        $C = HiddenString::create($A->getString());
        $D = HiddenString::create($B->getString());

        $this->assertFalse($A->equals($B));
        $this->assertTrue($A->equals($C));
        $this->assertFalse($A->equals($D));
        $this->assertFalse($B->equals($A));
        $this->assertFalse($B->equals($C));
        $this->assertTrue($B->equals($D));
        $this->assertTrue($C->equals($A));
        $this->assertFalse($C->equals($B));
        $this->assertFalse($C->equals($D));
        $this->assertFalse($D->equals($A));
        $this->assertTrue($D->equals($B));
        $this->assertFalse($D->equals($C));
    }

    /**
     * @dataProvider dpOptions
     */
    public function testRandomString(bool $disallowInline, bool $disallowSerialization)
    {
        $str = Base64UrlSafe::encode(random_bytes(32));

        $hidden = new HiddenString($str, $disallowInline, $disallowSerialization);

        $this->assertStringNotPresentWhenDumped($hidden, $str);

        $cast = (string)$hidden;
        if ($disallowInline) {
            $this->assertFalse(strpos($cast, $str));
        } else {
            $this->assertNotFalse(strpos($cast, $str));
        }

        $serial = serialize($hidden);
        if ($disallowSerialization) {
            $this->assertFalse(strpos($serial, $str));
        } else {
            $this->assertNotFalse(strpos($serial, $str));
        }
    }

    public function dpOptions(): array
    {
        return [
            'disallow both' => [true, true],
            'serializable'  => [true, false],
            'inlineable'    => [false, true],
            'allow both'    => [false, false],
        ];
    }

    public function testCreateInlineable()
    {
        $value = Base64UrlSafe::encode(random_bytes(32));
        $hidden = HiddenString::createInlineable($value);

        $this->assertStringNotPresentWhenDumped($hidden, $value);
        $this->assertNotFalse(strpos((string)$hidden, $value));
        $this->assertFalse(strpos(serialize($hidden), $value));
    }

    public function testCreateSerializable()
    {
        $value = Base64UrlSafe::encode(random_bytes(32));
        $hidden = HiddenString::createSerializable($value);

        $this->assertStringNotPresentWhenDumped($hidden, $value);
        $this->assertFalse(strpos((string)$hidden, $value));
        $this->assertNotFalse(strpos(serialize($hidden), $value));
    }

    public function testCreateOpen()
    {
        $value = Base64UrlSafe::encode(random_bytes(32));
        $hidden = HiddenString::createOpen($value);

        $this->assertStringNotPresentWhenDumped($hidden, $value);
        $this->assertNotFalse(strpos((string)$hidden, $value));
        $this->assertNotFalse(strpos(serialize($hidden), $value));
    }

    private function assertStringNotPresentWhenDumped(HiddenString $hidden, string $string)
    {
        ob_start();
        var_dump($hidden);
        $dump = ob_get_clean();
        $this->assertFalse(strpos($dump, $string));

        $print = \print_r($hidden, true);
        $this->assertFalse(strpos($print, $string));
    }
}
