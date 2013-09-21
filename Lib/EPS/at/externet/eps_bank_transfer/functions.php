<?php

namespace at\externet\eps_bank_transfer;

function FormatMonetaryXsdDecimal($val)
{
    if (is_string($val))
    {
        if (preg_match('/^[0-9]+$/', $val) > 0)
            $val += 0;
    }

    if (!is_int($val))
    {
        throw new \InvalidArgumentException(sprintf("Int value expected but %s received", gettype($val)));
    }

    if (strlen($val) < 3)
        return '.' . $val;

    $intVal = substr($val, 0, -2);
    $centVal = substr($val, -2);
    return $intVal . '.' . $centVal;
}