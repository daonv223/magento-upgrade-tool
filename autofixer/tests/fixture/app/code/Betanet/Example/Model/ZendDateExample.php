<?php

class ZendDateExample
{
    public function isDate($dateStr): bool
    {
        return \Zend_Date::isDate($dateStr);
    }

    public function isDateWithFormat($dateStr): bool
    {
        return \Zend_Date::isDate($dateStr, 'yyyy-MM-dd');
    }

    public function isDateTimeWithFormat($dateStr): bool
    {
        return \Zend_Date::isDate($dateStr, 'yyyy-MM-dd HH:mm:ss');
    }

    public function isDateWithUnmappedFormat($dateStr): bool
    {
        return \Zend_Date::isDate($dateStr, 'EEE, dd MMM yyyy');
    }
}