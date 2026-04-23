<?php

class DynamicPropertyExample
{
    public function __construct()
    {
        $this->customField = 'value';
        $this->extraData = [];
    }

    public function assignLater(): void
    {
        $this->runtimeProp = 42;
    }
}

class DeclaredPropertyExample
{
    private string $name;

    public function __construct()
    {
        $this->name = 'test';
    }
}

#[\AllowDynamicProperties]
class AlreadyAttributedExample
{
    public function setDynamic(): void
    {
        $this->anything = true;
    }
}
