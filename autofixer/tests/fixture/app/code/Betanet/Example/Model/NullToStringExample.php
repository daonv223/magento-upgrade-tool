<?php

class ExampleService
{
    public function greet($name): string
    {
        return trim($name);
    }

    public function process($data): string
    {
        return strtoupper($data);
    }

    public function cleanInput($input): string
    {
        return htmlspecialchars(trim($input));
    }

    public function formatLabel($label, $suffix): string
    {
        return rtrim($label) . $suffix;
    }

    public function slugify($text): string
    {
        return strtolower(str_replace(' ', '-', trim($text)));
    }
}