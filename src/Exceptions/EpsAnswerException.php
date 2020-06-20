<?php

namespace EpsBankTransfer\Exceptions;

class EpsAnswerException extends \Exception
{
    public $ErrorCode;
    public $ErrorMsg;

    public function __construct($errorCode, $errorMsg)
    {
        parent::__construct($errorMsg);
        $this->ErrorCode = $errorCode;
        $this->ErrorMsg = $errorMsg;
    }

    public function ToArray()
    {
        return
        [
            'ErrorCode' => $this->ErrorCode,
            'ErrorMsg' => $this->ErrorMsg
        ];
    }
}