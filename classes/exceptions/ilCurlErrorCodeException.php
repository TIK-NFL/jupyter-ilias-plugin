<?php

namespace exceptions;

class ilCurlErrorCodeException extends \Exception
{
    private int $http_code;

    public function __construct($message, $http_code) {
        parent::__construct($message);
        $this->http_code = $http_code;
    }

    public function getHttpCode(): int {
        return $this->http_code;
    }
}