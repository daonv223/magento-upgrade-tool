<?php

class DateTimeNullExample
{
    public function createDate(): \DateTime
    {
        return new \DateTime(null);
    }

    public function createImmutableDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(null);
    }

    public function createWithFormat(): \DateTime
    {
        return new \DateTime('2024-01-01');
    }

    public function createNoArgs(): \DateTime
    {
        return new \DateTime();
    }
}