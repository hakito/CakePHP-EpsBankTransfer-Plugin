<?php

namespace Lib;

class EpsCommon
{
    /** @var SoCommunicator */
    public static $SoCommunicator;

    /** @var string prefix for caching keys in this component */
    public static $CacheKeyPrefix = 'EpsBankTransfer';

    /** @var boolean Enable logging */
    public static $EnableLogging = true;

    public static function Base64Encode($s)
    {
        return str_replace(array('\\', '/'), array(',', '-'), base64_encode($s));
    }

    public static function Base64Decode($s)
    {
        return base64_decode(str_replace(array(',', '-'), array('\\', '/'), $s));
    }

    public static function GetBanksArray($invalidateCache, $config = 'default')
    {
        $key = self::$CacheKeyPrefix . 'BanksArray';
        $banks = Cache::read($key, $config);
        if (!$banks || $invalidateCache)
        {
            $banks = EpsCommon::GetSoCommunicator()->TryGetBanksArray();
            if (!empty($banks))
                Cache::write($key, $banks, $config);
        }
        return $banks;
    }

    /**
     * Get scheme operator instance
     * @return \at\externet\eps_bank_transfer\SoCommunicator
     */
    public static function GetSoCommunicator()
    {
        if (self::$SoCommunicator == null)
        {
            self::$SoCommunicator = new at\externet\eps_bank_transfer\SoCommunicator();
            self::$SoCommunicator->LogCallback = array('EpsCommon', 'WriteLog');
        }
        return self::$SoCommunicator;
    }

    public static function WriteLog($message)
    {
        if (!self::$EnableLogging)
            return;

        Log::write('eps', $message);
    }
}