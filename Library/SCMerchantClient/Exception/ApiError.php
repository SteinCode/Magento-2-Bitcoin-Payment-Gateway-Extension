<?php

declare(strict_types=1);

namespace Spectrocoin\Merchant\Library\SCMerchantClient\Exception;

class ApiError extends GenericError
{
    /**
     * @param string $message
     * @param int $code
     */
    function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }
}