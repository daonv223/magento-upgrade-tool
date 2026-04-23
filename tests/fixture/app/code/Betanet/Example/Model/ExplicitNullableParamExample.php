<?php

class ExplicitNullableParamExample
{
    public function process(string $name = null): void
    {
    }

    public function find(int $id = null): ?string
    {
        return null;
    }

    public function configure(array $options = null): void
    {
    }

    public function dispatch(\Magento\Framework\Event\Manager $event = null): void
    {
    }
}
