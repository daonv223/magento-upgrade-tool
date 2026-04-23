<?php

class HttpBuildQueryExample
{
    public function buildWithNull(array $data): string
    {
        return http_build_query($data, null);
    }

    public function buildWithNullAndSeparator(array $data): string
    {
        return http_build_query($data, null, '&');
    }

    public function buildWithPrefix(array $data): string
    {
        return http_build_query($data, 'prefix_');
    }

    public function buildNoSecondArg(array $data): string
    {
        return http_build_query($data);
    }
}