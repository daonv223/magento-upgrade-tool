<?php

class ZendValidateExample
{
    public function isEmail($email): bool
    {
        return \Zend_Validate::is($email, 'EmailAddress');
    }

    public function isInt($value): bool
    {
        return \Zend_Validate::is($value, 'Int');
    }

    public function isNotEmpty($value): bool
    {
        return \Zend_Validate::is($value, 'NotEmpty');
    }

    public function isRegexMatch($value): bool
    {
        return \Zend_Validate::is($value, 'Regex', ['pattern' => '/^[a-z]+$/']);
    }

    public function isAlpha($value): bool
    {
        return \Zend_Validate::is($value, 'Alpha');
    }

    public function isDigits($value): bool
    {
        return \Zend_Validate::is($value, 'Digits');
    }

    public function isAlnum($value): bool
    {
        return \Zend_Validate::is($value, 'Alnum');
    }

    public function isEmailWithClass($email): bool
    {
        return Zend_Validate::is($email, 'EmailAddress');
    }

    public function isRegexWithoutPattern($value): bool
    {
        return \Zend_Validate::is($value, 'Regex');
    }

    public function isRegexWithNonStringPattern($value, $pattern): bool
    {
        return \Zend_Validate::is($value, 'Regex', ['pattern' => $pattern]);
    }
}