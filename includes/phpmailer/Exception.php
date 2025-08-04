<?php
/**
 * PHPMailer Exception class.
 * PHP Version 5.5.
 *
 * @see       https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 */

namespace PHPMailer\PHPMailer;

/**
 * PHPMailer exception handler.
 */
class Exception extends \Exception
{
    /**
     * Prettify error message output.
     *
     * @return string
     */
    public function errorMessage()
    {
        return '<strong>PHPMailer Error:</strong> ' . htmlspecialchars($this->getMessage(), ENT_QUOTES);
    }
}
