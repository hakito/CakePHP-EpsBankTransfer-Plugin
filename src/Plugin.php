<?php

namespace EpsBankTransfer;

use Cake\Core\BasePlugin;
use Cake\Cache\Cache;
use Cake\Log\Log;

/**
 * Plugin for EpsBankTransfer
 */
class Plugin extends BasePlugin
{
    /** @var SoCommunicator[] */
    private static $SoCommunicator = [];

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

    public static function GetBanksArray($invalidateCache, $config = 'default', $testMode = false)
    {
        $key = self::$CacheKeyPrefix . 'BanksArray' . ($testMode ? 'Test' : 'Live');
        $banks = Cache::read($key, $config);
        if (!$banks || $invalidateCache)
        {
            $banks = Plugin::GetSoCommunicator($testMode)->TryGetBanksArray();
            if (!empty($banks))
                Cache::write($key, $banks, $config);
        }
        return $banks;
    }

    /**
     * Get scheme operator instance
     * @return \at\externet\eps_bank_transfer\SoCommunicator
     */
    public static function GetSoCommunicator($testMode = false)
    {
        $index = empty($testMode) ? 'live' : 'test';
        if (empty(self::$SoCommunicator[$index]))
        {
            self::$SoCommunicator[$index] = new \at\externet\eps_bank_transfer\SoCommunicator($testMode);
            self::$SoCommunicator[$index]->LogCallback = [Plugin::class, 'WriteLog'];
        }
        return self::$SoCommunicator[$index];
    }

    public static function WriteLog($message)
    {
        if (!self::$EnableLogging)
            return;

        Log::info($message, ['scope' => 'EpsBankTransfer']);
    }
}