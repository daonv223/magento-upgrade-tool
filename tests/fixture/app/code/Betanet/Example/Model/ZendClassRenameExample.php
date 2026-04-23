<?php

class ZendClassRenameExample
{
    public function getCurrency(): \Zend_Currency
    {
        return new \Zend_Currency();
    }

    public function filterLocalized($value): string
    {
        $filter = new \Zend_Filter_LocalizedToNormalized();
        return $filter->filter($value);
    }

    public function getUri(): \Zend_Uri
    {
        return \Zend_Uri::factory('https://example.com');
    }

    public function soapClient(): \Zend_Soap_Client
    {
        return new \Zend_Soap_Client();
    }

    public function filterInput(array $data): void
    {
        $input = new \Zend_Filter_Input([], [], $data);
    }

    public function implementsFilter(): \Zend_Filter_Interface
    {
        return new class implements \Zend_Filter_Interface {
            public function filter($value) { return $value; }
        };
    }

    public function getUriFactory(): \Zend\Uri\UriFactory
    {
        return new \Zend\Uri\UriFactory();
    }

    public function getUriInstance(): \Zend\Uri\Uri
    {
        return new \Zend\Uri\Uri();
    }

    public function catchJsonException(): void
    {
        try {
            \Zend_Json::encode([]);
        } catch (\Zend_Json_Exception $e) {
            echo $e->getMessage();
        }
    }

    public function catchCurrencyException(): void
    {
        try {
            new \Zend_Currency();
        } catch (\Zend_Currency_Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @throws \Zend_Validate_Exception
     */
    public function validateThrow(): bool
    {
        return true;
    }
}