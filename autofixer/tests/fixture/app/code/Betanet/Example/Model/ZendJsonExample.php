<?php
use Zend_Json;
class ZendJsonExample
{
    public function encodeData(array $data): string
    {
        return \Zend_Json::encode($data);
    }

    public function decodeData(string $json): array
    {
        return \Zend_Json::decode($json);
    }

    public function encodeWithOptions(array $data, int $depth): string
    {
        return \Zend_Json::encode($data, $depth);
    }

    public function decodeWithObjectMode(string $json): array
    {
        return \Zend_Json::decode($json, true);
    }

    public function nestedExample(array $items): string
    {
        $encoded = \Zend_Json::encode($items);
        return strtoupper($encoded);
    }

    public function importZendJson(array $items): string
    {
        $encoded = Zend_Json::encode($items);
        return strtoupper($encoded);
    }

    public function decodeWithObjectFlag(string $json): object
    {
        return \Zend_Json::decode($json, false);
    }
}