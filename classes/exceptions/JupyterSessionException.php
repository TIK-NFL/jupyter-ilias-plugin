<?php

namespace exceptions;

class JupyterSessionException extends \Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}