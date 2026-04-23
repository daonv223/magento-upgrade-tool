<?php

class ZendHttpClientExample
{
    public function getMethod(): string
    {
        return \Zend_Http_Client::GET;
    }

    public function postMethod(): string
    {
        return \Zend_Http_Client::POST;
    }

    public function putMethod(): string
    {
        return \Zend_Http_Client::PUT;
    }

    public function deleteMethod(): string
    {
        return \Zend_Http_Client::DELETE;
    }

    public function headMethod(): string
    {
        return Zend_Http_Client::HEAD;
    }

    public function patchMethod(): string
    {
        return Zend_Http_Client::PATCH;
    }
}