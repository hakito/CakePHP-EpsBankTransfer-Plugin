<?php

class EpsCommon
{
    /** @var eps_bank_transfer\SoCommunicator */
    public static $SoCommunicator;

    /** @var string prefix for caching keys in this component */
    public static $CacheKeyPrefix = 'EpsBankTransfer';

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

    public static function GetSoCommunicator()
    {
        if (self::$SoCommunicator == null)
            self::$SoCommunicator = new at\externet\eps_bank_transfer\SoCommunicator();
        return self::$SoCommunicator;
    }

}