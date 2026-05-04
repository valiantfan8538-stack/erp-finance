<?php

namespace app\domain\finance\valueobject;

use InvalidArgumentException;

class Money
{
    private string $amount;

    public function __construct(int|float|string $amount)
    {
        if (is_numeric($amount)) {
            $this->amount = bcadd((string)$amount, '0', 2);
        } else {
            throw new InvalidArgumentException("Money amount must be numeric, got: {$amount}");
        }
    }

    public static function zero(): self
    {
        return new self('0.00');
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function add(Money $other): Money
    {
        return new Money(bcadd($this->amount, $other->amount, 2));
    }

    public function subtract(Money $other): Money
    {
        return new Money(bcsub($this->amount, $other->amount, 2));
    }

    public function multiply(string $multiplier): Money
    {
        return new Money(bcmul($this->amount, $multiplier, 2));
    }

    public function divide(string $divisor): Money
    {
        if (bccomp($divisor, '0', 2) === 0) {
            throw new InvalidArgumentException('Division by zero');
        }
        return new Money(bcdiv($this->amount, $divisor, 2));
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0.00', 2) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0.00', 2) > 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0.00', 2) < 0;
    }

    public function equals(Money $other): bool
    {
        return bccomp($this->amount, $other->amount, 2) === 0;
    }

    public function __toString(): string
    {
        return $this->amount;
    }
}
