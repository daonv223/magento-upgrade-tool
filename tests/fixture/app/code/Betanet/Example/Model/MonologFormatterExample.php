<?php

namespace Amasty\Base\Debug\System;

class AmastyFormatter extends \Monolog\Formatter\LineFormatter
{
    public function format(array $record): string
    {
        $message = $record['message'];
        $level = $record['level'];
        $channel = $record['channel'];
        return sprintf('[%s] %s: %s', $level, $channel, $message);
    }

    public function formatBatch(array $records): array
    {
        $result = [];
        foreach ($records as $record) {
            $result[] = $this->format($record);
        }
        return $result;
    }
}
