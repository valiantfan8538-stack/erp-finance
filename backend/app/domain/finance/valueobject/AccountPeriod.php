<?php

namespace app\domain\finance\valueobject;

use InvalidArgumentException;

class AccountPeriod
{
    public readonly int $year;
    public readonly int $period;

    public function __construct(int $year, int $period)
    {
        if ($year < 2000 || $year > 2099) {
            throw new InvalidArgumentException("Invalid year: {$year}");
        }
        if ($period < 1 || $period > 12) {
            throw new InvalidArgumentException("Period must be 1-12, got: {$period}");
        }
        $this->year = $year;
        $this->period = $period;
    }

    public static function fromDate(string $date): self
    {
        $ts = strtotime($date);
        if ($ts === false) {
            throw new InvalidArgumentException("Invalid date: {$date}");
        }
        return new self((int)date('Y', $ts), (int)date('n', $ts));
    }

    public function isBefore(AccountPeriod $other): bool
    {
        if ($this->year !== $other->year) {
            return $this->year < $other->year;
        }
        return $this->period < $other->period;
    }

    public function isAfter(AccountPeriod $other): bool
    {
        if ($this->year !== $other->year) {
            return $this->year > $other->year;
        }
        return $this->period > $other->period;
    }

    public function equals(AccountPeriod $other): bool
    {
        return $this->year === $other->year && $this->period === $other->period;
    }

    public function nextPeriod(): AccountPeriod
    {
        if ($this->period === 12) {
            return new AccountPeriod($this->year + 1, 1);
        }
        return new AccountPeriod($this->year, $this->period + 1);
    }

    public function prevPeriod(): AccountPeriod
    {
        if ($this->period === 1) {
            return new AccountPeriod($this->year - 1, 12);
        }
        return new AccountPeriod($this->year, $this->period - 1);
    }

    public function __toString(): string
    {
        return sprintf('%d-%02d', $this->year, $this->period);
    }
}
