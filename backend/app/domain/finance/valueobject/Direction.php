<?php

namespace app\domain\finance\valueobject;

use InvalidArgumentException;

class Direction
{
    public const DEBIT = 'debit';
    public const CREDIT = 'credit';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function debit(): self
    {
        return new self(self::DEBIT);
    }

    public static function credit(): self
    {
        return new self(self::CREDIT);
    }

    public static function fromString(string $value): self
    {
        if ($value !== self::DEBIT && $value !== self::CREDIT) {
            throw new InvalidArgumentException("Direction must be 'debit' or 'credit', got: {$value}");
        }
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isDebit(): bool
    {
        return $this->value === self::DEBIT;
    }

    public function isCredit(): bool
    {
        return $this->value === self::CREDIT;
    }

    public function equals(Direction $other): bool
    {
        return $this->value === $other->value;
    }

    public function opposite(): Direction
    {
        return $this->isDebit() ? self::credit() : self::debit();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
