<?php

namespace TruthRenderer\Exceptions;

use \Exception;

class TemplateNotFoundException extends Exception
{
    public function __construct()
    {
        parent::__construct('Missing template or templateName');
    }
}
