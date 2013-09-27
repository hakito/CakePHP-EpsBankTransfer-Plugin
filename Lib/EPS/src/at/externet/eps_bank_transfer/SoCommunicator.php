<?php
namespace at\externet\eps_bank_transfer;

class SoCommunicator
{
//    /**
//     * Get BankList as SimpleXml object
//     * @return SimpleXMLElement banks
//     */
//    public function GetBanksXml($invalidateCache = false)
//    {
//        $url = 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4';
//        $xsd = self::GetXSD('epsSOBankListProtocol.xsd');
//        return $this->GetCachedXMLElement($url, $xsd, $invalidateCache);
//    }
    
    public $HttpSocket;
    
    public function __construct()
    {
        $this->HttpSocket = new HttpSocket();
    }
    
    public function SendGetRequest($url)
    {
        $response = $this->HttpSocket->get($url, array(), $info);
        if ($info['response_code'] != 200)
        {
            throw new HttpRequestException("Send HTTP Get request returned response code " . $info['response_code']);
        }
    }
}