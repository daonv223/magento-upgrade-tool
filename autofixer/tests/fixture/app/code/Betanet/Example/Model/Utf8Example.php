<?php

class Utf8Example
{
    public function encodeToUtf8($input): string
    {
        return utf8_encode($input);
    }

    public function decodeFromUtf8($input): string
    {
        return utf8_decode($input);
    }

    public function roundTrip($input): string
    {
        return utf8_encode(utf8_decode($input));
    }

    public function encodeAndProcess($input): string
    {
        $encoded = utf8_encode($input);
        return strtoupper($encoded);
    }
}