<?php

namespace at\externet\eps_bank_transfer;

function GetEpsData($filename)
{

    $file = new \File(EPS_BANK_TRANSFER_APP . 'Test' . DS . 'Case' . DS . 'Controller' . DS . 'Component' . DS . 'EpsData' . DS . $filename);
    return $file->read();
}
