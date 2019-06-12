<?php
declare(strict_types=1);
namespace ParagonIE\HiddenString;

use ParagonIE\ConstantTime\Binary;

/**
 * Class HiddenString
 *
 * The purpose of this class is to encapsulate strings and hide their contents
 * from stack traces should an unhandled exception occur.
 *
 * The only things that should be protected:
 * - Passwords
 * - Plaintext (before encryption)
 * - Plaintext (after decryption)
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
final class HiddenString
{
    /**
     * @var string
     */
    private $internalStringValue;

    /**
     * Disallow the contents from being accessed via __toString()?
     *
     * @var bool
     */
    private $disallowInline;

    /**
     * Disallow the contents from being accessed via __sleep()?
     *
     * @var bool
     */
    private $disallowSerialization;

    /**
     * @deprecated Please use one of the static factory methods.
     */
    public function __construct(
        string $value,
        bool $disallowInline = false,
        bool $disallowSerialization = false
    ) {
        $this->internalStringValue = self::safeStrcpy($value);
        $this->disallowInline = $disallowInline;
        $this->disallowSerialization = $disallowSerialization;
    }

    /**
     * Create an instance that does not allow inlining and serialization.
     */
    public static function create(string $value): self
    {
        return new self($value, true, true);
    }

    /**
     * Create an instance that supports casting to string.
     */
    public static function createInlineable(string $value): self
    {
        return new self($value, false, true);
    }

    /**
     * Create an instance that can be serialized.
     */
    public static function createSerializable(string $value): self
    {
        return new self($value, true, false);
    }

    /**
     * Create an instance that can be cast to string and be serialized.
     */
    public static function createOpen(string $value): self
    {
        return new self($value, false, false);
    }

    public function equals(HiddenString $other): bool
    {
        return \hash_equals(
            $this->getString(),
            $other->getString()
        );
    }

    /**
     * Hide its internal state from var_dump()
     */
    public function __debugInfo(): array
    {
        return [
            'internalStringValue' =>
                '*',
            'attention' =>
                'If you need the value of a HiddenString, ' .
                'invoke getString() instead of dumping it.'
        ];
    }

    /**
     * Wipe it from memory after it's been used.
     * @return void
     */
    public function __destruct()
    {
        if (\is_callable('sodium_memzero')) {
            try {
                \sodium_memzero($this->internalStringValue);
                return;
            } catch (\Throwable $ex) {
            }
        }

        // Last-ditch attempt to wipe existing values if libsodium is not
        // available. Don't rely on this.
        $zero = \str_repeat("\0", (int) Binary::safeStrlen($this->internalStringValue));
        $this->internalStringValue = $this->internalStringValue ^ (
            $zero ^ $this->internalStringValue
        );
        unset($zero);
        unset($this->internalStringValue);
    }

    /**
     * Explicit invocation -- get the raw string value
     */
    public function getString(): string
    {
        return self::safeStrcpy($this->internalStringValue);
    }

    /**
     * Returns a copy of the string's internal value, which should be zeroed.
     * Optionally, it can return an empty string.
     */
    public function __toString(): string
    {
        if (!$this->disallowInline) {
            return self::safeStrcpy($this->internalStringValue);
        }
        return '';
    }

    public function __sleep(): array
    {
        if (!$this->disallowSerialization) {
            return [
                'internalStringValue',
                'disallowInline',
                'disallowSerialization'
            ];
        }
        return [];
    }

    /**
     * PHP 7 uses interned strings. We don't want altering this one to alter
     * the original string.
     */
    public static function safeStrcpy(string $string): string
    {
        $length = Binary::safeStrlen($string);
        $return = '';
        /** @var int $chunk */
        $chunk = $length >> 1;
        if ($chunk < 1) {
            $chunk = 1;
        }
        for ($i = 0; $i < $length; $i += $chunk) {
            $return .= Binary::safeSubstr($string, $i, $chunk);
        }
        return $return;
    }
}
