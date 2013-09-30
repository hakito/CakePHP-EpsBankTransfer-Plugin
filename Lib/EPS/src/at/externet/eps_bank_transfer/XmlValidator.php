<?php

namespace at\externet\eps_bank_transfer;

class XmlValidator
{
    public static function ValidateBankList($xml)
    {
        return self::ValidateXml($xml, self::GetXSD('epsSOBankListProtocol.xsd'));
    }

    public static function ValidateEpsProtocol($xml)
    {
        return self::ValidateXml($xml, self::GetXSD('EPSProtocol-V24.xsd'));
    }

    // HELPER FUNCTIONS

    private static function GetXSD($filename)
    {
        return dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'XSD' . DIRECTORY_SEPARATOR . $filename;
    }

    private static function ValidateXml($xml, $xsd)
    {
        $doc = new \DOMDocument();
        $doc->loadXml($xml);
        $prevState = libxml_use_internal_errors(true);
        if (!$doc->schemaValidate($xsd))
        {
            $xmlError = libxml_get_last_error();
            libxml_use_internal_errors($prevState);
            
            throw new XmlValidationException('XML does not validate against XSD. ' . $xmlError->message);
        }
        return true;
    }
}
