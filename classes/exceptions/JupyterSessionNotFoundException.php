<?php

namespace exceptions;

class JupyterSessionNotFoundException extends \Exception
{
    public function __construct($session_id)
    {
        parent::__construct('Jupyter session with ID "'. $session_id .'" not found.');
    }
}