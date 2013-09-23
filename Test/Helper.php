<?php

namespace at\externet\eps_bank_transfer;

function GetEpsData($filename)
{

    $file = new \File(GetEpsDataPath($filename));
    return $file->read();
}

function GetEpsDataPath($filename)
{
    return EPS_BANK_TRANSFER_APP . 'Test' . DS . 'Case' . DS . 'Controller' . DS . 'Component' . DS . 'EpsData' . DS . $filename;
}