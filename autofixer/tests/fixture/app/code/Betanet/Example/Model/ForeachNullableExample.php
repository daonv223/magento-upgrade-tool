<?php

class ForeachNullableExample
{
    public function iterateItems(?array $items): void
    {
        foreach ($items as $item) {
            echo $item;
        }
    }

    public function iterateWithKey(?array $data): void
    {
        foreach ($data as $key => $value) {
            echo $key;
        }
    }

    public function alreadyCoalesced(?array $items): void
    {
        foreach ($items ?? [] as $item) {
            echo $item;
        }
    }

    public function iterateUnionType(array|null $data): void
    {
        foreach ($data as $key => $value) {
            echo $key;
        }
    }
}