<?php

declare(strict_types=1);

namespace Spectrocoin\Merchant\Library\SCMerchantClient;

class Config
{
    const MERCHANT_API_URL = 'https://test.spectrocoin.com/api/public';
    const AUTH_URL = 'https://test.spectrocoin.com/api/public/oauth/token';
    const PUBLIC_SPECTROCOIN_CERT_LOCATION = 'https://test.spectrocoin.com/public.pem'; //PROD: https://spectrocoin.com/files/merchant.public.pem
    const ACCEPTED_FIAT_CURRENCIES = ["EUR", "USD", "PLN", "CHF", "SEK", "GBP", "AUD", "CAD", "CZK", "DKK", "NOK"];

    const ACCESS_TOKEN_CONFIG_PATH = 'spectrocoin/general/access_token';
}

