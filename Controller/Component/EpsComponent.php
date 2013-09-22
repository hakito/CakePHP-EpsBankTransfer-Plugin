<?php

App::uses('Component', 'Controller');
App::uses('HttpSocket', 'Network/Http');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

use at\externet\eps_bank_transfer;

class EpsComponent extends Component
{

    public $HttpSocket;

    /** @var eps_bank_transfer\WebshopArticle[] articles */
    public $Articles = array();

    /** @var int total of amount to pay in cents */
    public $Total = 0;

    /** @var string prefix for caching keys in this component */
    public $CacheKeyPrefix = 'EpsBankTransferComponent';

    /** @var \Controller */
    private $Controller = null;
    
    public function __construct($collection)
    {
        parent::__construct($collection);
        $this->HttpSocket = new HttpSocket();
    }
    
    public function startup(\Controller $controller)
    {
        parent::startup($controller);
        $this->Controller = $controller;
    }

    /**
     * Add an article
     * @param string $name
     * @param int $count
     * @param int $price in cents
     */
    public function AddArticle($name, $count, $price, $identifier = null)
    {
        $article = new eps_bank_transfer\WebshopArticle($name, $count, $price);
        if ($identifier != null)
            $this->Articles[$identifier] = $article;
        else
            $this->Articles[] = $article;

        $this->Total += (int) $count * (int) $price;
    }

    /**
     * Get banks as associative array
     * @param type $invalidateCache
     * @return array
     */
    public function GetBanksArray($invalidateCache = false)
    {
        $key = $this->CacheKeyPrefix . 'BanksArray';
        $banks = Cache::read($key);
        if (!$banks || $invalidateCache)
        {
            $xmlBanks = $this->GetBanksXml($invalidateCache);
            $banks = array();
            foreach ($xmlBanks as $xmlBank)
            {
                $bezeichnung = '' . $xmlBank->bezeichnung;
                $banks[$bezeichnung] = array(
                    'bic' => '' . $xmlBank->bic,
                    'bezeichnung' => $bezeichnung,
                    'land' => '' . $xmlBank->land,
                    'epsUrl' => '' . $xmlBank->epsUrl,
                );
            }
            Cache::write($key, $banks);
        }
        return $banks;
    }

    /**
     * Failsafe version of GetBanksArray()
     * @return null or result of GetBanksArray()
     */
    public function GetBanksArrayOrNull($invalidateCache = false)
    {
        try
        {
            return $this->GetBanksArray($invalidateCache);
        }
        catch (CakeException $e)
        {
            return null;
        }
    }

    /**
     * Get BankList as SimpleXml object
     * @return SimpleXMLElement banks
     */
    public function GetBanksXml($invalidateCache = false)
    {
        $url = 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4';
        $xsd = self::GetXSD('epsSOBankListProtocol.xsd');
        return $this->GetCachedXMLElement($url, $xsd, $invalidateCache);
    }

    public function PaymentRedirect($remittanceIdentifier, $TransactionOkUrl, $TransactionNokUrl, $bankName = null)
    {
        $config = Configure::read('EpsBankTransfer');
        $referenceIdentifier = uniqid($remittanceIdentifier . ' ');
        $transferMsgDetails = new eps_bank_transfer\TransferMsgDetails(
                        Router::url('/eps_bank_transfer/process', true),
                        $TransactionOkUrl,
                        $TransactionNokUrl
        );
        $transferInitiatorDetails = new eps_bank_transfer\TransferInitiatorDetails(
                        $config['userid'],
                        $config['secret'],
                        $config['bic'],
                        $config['account_owner'],
                        $config['iban'],
                        $referenceIdentifier,
                        $remittanceIdentifier,
                        $this->Total,
                        $this->Articles,
                        $transferMsgDetails);
        $bankUrl = null;
        
        if (!empty($bankName))
        {
            $banks = $this->GetBanksArrayOrNull();
            if ($banks != null)
            {
                $bankUrl = $banks[$bankName]['epsUrl'];
            }
        }
        
        self::WriteLog('SendPaymentOrder [' . $referenceIdentifier . '] over ' . $transferInitiatorDetails->InstructedAmount );
        $soAnswer = $this->SendPaymentOrder($transferInitiatorDetails, $bankUrl)->children("http://www.stuzza.at/namespaces/eps/protocol/2011/11");
        $errorDetails = &$soAnswer->BankResponseDetails->ErrorDetails;
        
        if (('' . $errorDetails->ErrorCode) != '000')
        {
            return array(
                'ErrorCode' => '' . $errorDetails->ErrorCode,
                'ErrorMsg' => '' . $errorDetails->ErrorMsg
                );
        }
        
        return $this->Controller->redirect('' . $soAnswer->BankResponseDetails->ClientRedirectUrl);
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

        $data = $transferInitiatorDetails->GetSimpleXml();
        $xmlData = $data->asXML();
        $response = $this->PostUrlLogged($targetUrl, $xmlData, 'Send payment order');

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

    private function GetCachedXMLElement($url, $xsd = null, $invalidateCache = false)
    {
        $key = $this->CacheKeyPrefix . $url;
        $xml = Cache::read($key);
        if (!$xml || $invalidateCache)
        {
            $response = $this->GetUrlLogged($url, 'Requesting bank list');
            $xml = $response->body;
            if ($xsd != null)
                self::ValidateXml($xml, $xsd);

            Cache::write($key, $xml);
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
        $message = 'Validating XML with ' . $xsd;
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