<?php

class EachCreateFunctionExample
{
    public function iterateWithEach(array $data): void
    {
        while (list($key, $value) = each($data)) {
            echo $key . '=' . $value;
        }
    }

    public function listEach(array $data): void
    {
        list($a, $b) = each($data);
    }

    public function oldCallback(): void
    {
        $callback = create_function('$x', 'return $x * 2;');
        array_map(create_function('$item', 'return strtoupper($item);'), ['a', 'b']);
    }
}
