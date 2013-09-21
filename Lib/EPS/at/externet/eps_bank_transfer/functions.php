<?php
namespace at\externet\eps_bank_transfer;

function FormatMonetaryXsdDecimal($val, $precision = 2)
{
    $format = "%01." . $precision . "F";
    return sprintf($format, $val);
}