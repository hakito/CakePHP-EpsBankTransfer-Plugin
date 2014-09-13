<?php

namespace at\externet\eps_bank_transfer;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    public static function GetEpsData($filename)
    {
        return file_get_contents(self::GetEpsDataPath($filename));
    }

    public static function GetEpsDataPath($filename)
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'EpsData' . DIRECTORY_SEPARATOR . $filename;
    }
}