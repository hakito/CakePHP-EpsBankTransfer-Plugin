<?php

App::uses('Component', 'Controller');
App::uses('HttpSocket', 'Network/Http');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

class EpsComponent extends Component
{

    public $HttpSocket;

    public function __construct($collection)
    {
        parent::__construct($collection);
        $this->HttpSocket = new HttpSocket();
    }

    /**
     * 
     * @return xml banks
     */
    public function GetBanks()
    {
        $url = 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4';
        $xsd = self::GetXSD('epsSOBankListProtocol.xsd');
        return $this->GetCachedXMLElement($url, $xsd);
    }

    /**
     * Failsafe version of GetBanks()
     * @return null or result of GetBanks()
     */
    public function GetBanksOrNull()
    {
        try
        {
            return $this->GetBanks();
        }
        catch (CakeException $e)
        {
            return null;
        }
    }

    /**
     * 
     * @param TransferInitiatorDetails $transferInitiatorDetails
     * @param targetUrl url with preselected bank identifier
     * @return SimpleXmlElement BankResponseDetails
     */
    public function SendPaymentOrder($transferInitiatorDetails, $targetUrl = null)
    {
        if ($targetUrl == null)
            $targetUrl = 'https://routing.eps.or.at/appl/epsSO/transinit/eps/v2_4';

        $data = $transferInitiatorDetails->GetSimpleXml()->asXML();
        $response = $this->PostUrlLogged($targetUrl, $data, 'Send payment order');
        
        $simpleXml = self::GetValidatedSimpleXmlElement($response->body, 'EPSProtocol-V24.xsd');
        return $simpleXml;
    }

    public static function GetValidatedSimpleXmlElement($xml, $xsdFilename = null)
    {
        if ($xsdFilename != null)
            self::ValidateXml($xml, self::GetXSD($xsdFilename));
        return new SimpleXMLElement($xml);
    }

    private static function GetXSD($filename)
    {
        return EPS_BANK_TRANSFER_APP . 'Lib' . DS . 'XSD' . DS . $filename;
    }

    private function GetCachedXMLElement($url, $xsd = null)
    {
        $xml = Cache::read($url);
        if (!$xml)
        {
            $response = $this->GetUrlLogged($url, 'Requesting bank list');          
            $xml = $response->body;
            if ($xsd != null)
                self::ValidateXml($xml, $xsd);

            Cache::write($url, $xml);
        }
        return new SimpleXMLElement($xml);
    }

    private function PostUrlLogged($url, $data, $message)
    {
        self::WriteLog($message);
        $response = $this->HttpSocket->post($url, $data, array('header' => array('Content-Type' => 'text/plain; charset=UTF-8')));
        
        if ($response->code != 200)
        {
            self::WriteLog($message, false);
            throw new CakeException('Could not load document. Server returned code: ' . $response->code);
        }
            
        self::WriteLog($message, true);
        return $response;
    }
    
    private function GetUrlLogged($url, $message)
    {
        self::WriteLog($message);
        $response = $this->HttpSocket->get($url);
        if ($response->code != 200)
        {
            $this->WriteLog($message, false);
            throw new CakeException('Could not load document. Server returned code: ' . $response->code);
        }
        self::WriteLog($message, true);
        return $response;
    }

    private static function WriteLog($message, $success = null)
    {
        if ($success != null)
            $message = $success ? 'SUCCESS: ' : 'FAILED: ' . $message;
        CakeLog::write('eps', $message);
    }

    private static function ValidateXml($xml, $xsd)
    {
        $message = 'Validating XML with '.$xsd;
        self::WriteLog($message);
        $doc = new DOMDocument();
        $doc->loadXml($xml);
        $prevState = libxml_use_internal_errors(true);
        if (!$doc->schemaValidate($xsd))
        {
            self::WriteLog($message, false);
            $xmlError = libxml_get_last_error();
            libxml_use_internal_errors($prevState);
            throw new CakeException('XML does not validate against XSD. ' . $xmlError->message);
        }
        self::WriteLog($message, true);
    }

}