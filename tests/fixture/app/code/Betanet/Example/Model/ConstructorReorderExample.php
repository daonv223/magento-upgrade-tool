<?php

namespace Mageplaza\SocialLogin\Model\Providers\Oauth;

class OAuth2Client
{
    public function __construct($client_id = false, HelperData $helperData)
    {
        $this->clientId = $client_id;
        $this->helper = $helperData;
    }
}

class ValidConstructorExample
{
    public function __construct(HelperData $helperData, $client_id = false)
    {
    }
}

class NoTypeAfterOptionalExample
{
    public function __construct($name = 'default', $value = null)
    {
    }
}
