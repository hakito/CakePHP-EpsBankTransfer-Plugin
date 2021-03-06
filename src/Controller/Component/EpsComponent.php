<?php

namespace EpsBankTransfer\Controller\Component;

use at\externet\eps_bank_transfer;
use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\Utility\Security;
use EpsBankTransfer\Exceptions\EpsAnswerException;
use EpsBankTransfer\Plugin;

class EpsComponent extends Component
{
    /**
     * Webshop articles
     * @var eps_bank_transfer\WebshopArticle[]  */
    public $Articles = array();

    /**
     * Total of amount to pay in cents
     * @var int
     */
    public $Total = 0;

    /** @var \Controller */
    private $Controller = null;

    /** @var string */
    private $EncryptionKey;

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $defaults = array(
            'ObscuritySuffixLength' => 8,
            'ObscuritySeed'  => 'c2af496ecf4b9f095447a7b9f5c02d20924252bd',
            'TestMode' => false
            );

        $config = array_merge($defaults, Configure::read('EpsBankTransfer'));

        $SoCommunicator = Plugin::GetSoCommunicator($config['TestMode']);
        $SoCommunicator->ObscuritySuffixLength = $config['ObscuritySuffixLength'];
        $SoCommunicator->ObscuritySeed = $config['ObscuritySeed'];

        if (empty($config['encryptionKey']))
            throw new \InvalidArgumentException('No encryptionKey given in config');

        $this->EncryptionKey = $config['encryptionKey'];
    }

    public function startup($event)
    {
        $this->Controller = $event->getSubject();
    }

    /**
     * Add an Webshop Article. The article will be appended to the array or
     * at the given array position.
     * @param string $name
     * @param int $count
     * @param int $price in cents
     * @param string $arrayPosition optional identifier for internal storage
     */
    public function AddArticle($name, $count, $price, $arrayPosition = null)
    {
        $article = new eps_bank_transfer\WebshopArticle($name, $count, $price);
        if ($arrayPosition != null)
            $this->Articles[$arrayPosition] = $article;
        else
            $this->Articles[] = $article;

        $this->Total += (int) $count * (int) $price;
    }

    /**
     * Get banks as associative array. The bank array will be cached.
     * @param bool $invalidateCache set to TRUE to force reading not from cache
     * @param string $config cache config used for caching
     * @return array associative array with bank name as key
     */
    public function GetBanksArray($invalidateCache = false, $config = 'default')
    {
        $settings = Configure::read('EpsBankTransfer');
        $testMode = !empty($settings['TestMode']);
        return Plugin::GetBanksArray($invalidateCache, $config, $testMode);
    }

    /**
     * Redirect to Online Banking
     * @param string $remittanceIdentifier Identifier for the given order. For example Order.id
     * @param string $TransactionOkUrl The url the customer is redirected to if transaction was successful
     * @param string $TransactionNokUrl The url the customer is redirected to if transaction was not successful
     * @param string $bic optional bank name from GetBanksArray if the bank was already choosen on the site.
     * If not given the user will be prompted later to select his bank
     * @param int $expirationMinutes expiration of payment in minutes. Must be between 5 and 60
     * @throws \XmlValidationException when the returned BankResponseDetails does not validate against XSD
     * @throws \SocketException when communication with SO fails
     * @throws \UnexpectedValueException when using security suffix without security seed
     * @throws \EpsBankTransfer\Exceptions\EpsAnswerException when BankResponseDetails contains an error
     * @return returnvalue from Controller::redirect
     */
    public function PaymentRedirect($remittanceIdentifier, $TransactionOkUrl, $TransactionNokUrl, $bic = null, $expirationMinutes = null)
    {
        $config = Configure::read('EpsBankTransfer');
        $referenceIdentifier = uniqid($remittanceIdentifier . ' ');

        $eRemittanceIdentifier= rawurlencode(Plugin::Base64Encode(Security::encrypt($remittanceIdentifier, $this->EncryptionKey)));
        $confirmationUrl = Router::url(
            [
                'controller' => 'PaymentNotifications',
                'plugin' => 'EpsBankTransfer',
                'action' => 'process',
                'eRemittanceIdentifier' => $eRemittanceIdentifier,
                '_method' => 'POST',
            ], true);
        $transferMsgDetails = new eps_bank_transfer\TransferMsgDetails(
                        $confirmationUrl,
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
                        $this->Total,
                        $transferMsgDetails);

        $transferInitiatorDetails->RemittanceIdentifier = $remittanceIdentifier;
        $transferInitiatorDetails->WebshopArticles = $this->Articles;

        $epsUrl = null;
        if ($bic != null)
        {
            $banks = $this->GetBanksArray();
            if (!empty($banks[$bic]))
                $epsUrl = $banks[$bic]['epsUrl'];
        }

        $transferInitiatorDetails->OrderingCustomerOfiIdentifier = $bic;
        if ($expirationMinutes != null)
            $transferInitiatorDetails->SetExpirationMinutes($expirationMinutes);

        $logPrefix = 'SendPaymentOrder [' . $referenceIdentifier . ']';

        Log::info($logPrefix . ' over ' . $transferInitiatorDetails->InstructedAmount . ' ConfUrl: ' . $confirmationUrl, ['scope' => Plugin::$LogScope]);
        $testMode = !empty($config['TestMode']);
        $plain = Plugin::GetSoCommunicator($testMode)->SendTransferInitiatorDetails($transferInitiatorDetails, $epsUrl);
        $xml = new \SimpleXMLElement($plain);
        $soAnswer = $xml->children(eps_bank_transfer\XMLNS_epsp);
        /** @noinspection PhpUndefinedFieldInspection */
        $errorDetails = &$soAnswer->BankResponseDetails->ErrorDetails;

        if (('' . $errorDetails->ErrorCode) != '000')
        {
            $errorCode = '' . $errorDetails->ErrorCode;
            $errorMsg = '' . $errorDetails->ErrorMsg;

            Log::error($logPrefix . ' Error ' . $errorCode . ' ' . $errorMsg, ['scope' => Plugin::$LogScope]);
            throw new EpsAnswerException($errorCode, $errorMsg);
        }

        Log::info($logPrefix . ' SUCCEEDED', ['scope' => Plugin::$LogScope]);
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        return $this->Controller->redirect('' . $soAnswer->BankResponseDetails->ClientRedirectUrl);
    }

    /**
     * Call this function when the confirmation URL is called by the Scheme Operator.
     * @param string $eRemittanceIdentifier encrypted remittance identifier
     * @param string $rawPostStream will read from this stream or file with file_get_contents
     * @param string $outputStream will write to this stream the expected responses for the
     * @throws InvalidCallbackException when callback is not callable
     * @throws CallbackResponseException when callback does not return TRUE
     * @throws XmlValidationException when $rawInputStream does not validate against XSD
     * @throws \SocketException when communication with SO fails
     * @throws \UnexpectedValueException when using security suffix without security seed
     * @throws UnknownRemittanceIdentifierException when security suffix does not match
     */
    public function HandleConfirmationUrl($eRemittanceIdentifier, $rawPostStream = 'php://input', $outputStream = 'php://output')
    {
        Log::info('BEGIN: Handle confirmation url', ['scope' => Plugin::$LogScope]);
        $config = Configure::read('EpsBankTransfer');

        $remittanceIdentifier = Security::decrypt(Plugin::Base64Decode($eRemittanceIdentifier), $this->EncryptionKey);

        $confirmationCallbackWrapper = function($raw, $bankConfirmationDetails) use ($remittanceIdentifier)
        {
            if ($remittanceIdentifier != $bankConfirmationDetails->GetRemittanceIdentifier())
            {
                $message = 'Remittance identifier mismatch '
                    . $remittanceIdentifier . ' != ' . $bankConfirmationDetails->GetRemittanceIdentifier();
                Log::error($message, ['scope' => Plugin::$LogScope]);
                throw new eps_bank_transfer\UnknownRemittanceIdentifierException($message);
            }

            try
            {
                $event = new Event('EpsBankTransfer.Confirmation', $this,
                [
                    'args' =>
                    [
                        'raw' => $raw,
                        'bankConfirmationDetails' => $bankConfirmationDetails
                    ]
                ]);
                Log::info('Dispatching EpsBankTransfer.Confirmation', ['scope' => Plugin::$LogScope]);
                $this->Controller->getEventManager()->dispatch($event);

                $result = $event->getResult();
                if (empty($result['handled']))
                    Log::error('EpsBankTransfer.Confirmation was unhandled', ['scope' => Plugin::$LogScope]);
                return !empty($result['handled']);
            }
            catch (\Throwable $e)
            {
                Log::error('Exception in confirmationCallbackWrapper: ' . $e->getMessage(), ['scope' => Plugin::$LogScope]);
                Log::debug($e->getTraceAsString(), ['scope' => Plugin::$LogScope]);
                return false;
            }
        };

        $vitalityCheckCallbackWrapper = function($raw, $vitalityCheckDetails)
        {
            try
            {
                $event = new Event('EpsBankTransfer.VitalityCheck', $this,
                [
                    'args' =>
                    [
                        'raw' => $raw,
                        'vitalityCheckDetails' => $vitalityCheckDetails
                    ]
                ]);
                Log::info('Dispatching EpsBankTransfer.VitalityCheck', ['scope' => Plugin::$LogScope]);
                $this->Controller->getEventManager()->dispatch($event);

                $result = $event->getResult();
                if (empty($result['handled']))
                    Log::error('EpsBankTransfer.VitalityCheck was unhandled', ['scope' => Plugin::$LogScope]);
                return !empty($result['handled']);
            }
            catch (\Throwable $e)
            {
                Log::error('Exception in vitalityCheckCallbackWrapper: ' . $e->getMessage(), ['scope' => Plugin::$LogScope]);
                Log::debug($e->getTraceAsString(), ['scope' => Plugin::$LogScope]);
                return false;
            }
        };

        $testMode = !empty($config['TestMode']);
        try {
            Plugin::GetSoCommunicator($testMode)->HandleConfirmationUrl(
                    $confirmationCallbackWrapper,
                    $vitalityCheckCallbackWrapper,
                    $rawPostStream,
                    $outputStream);
        } catch (\Throwable $ex) {
            Log::error('Exception in SoCommunicator::HandleConfirmationUrl: ' . $ex->getMessage(), ['scope' => Plugin::$LogScope]);
            Log::debug($ex->getTraceAsString(), ['scope' => Plugin::$LogScope]);
            throw $ex;
        }

        Log::info('END: Handle confirmation url', ['scope' => Plugin::$LogScope]);
    }

}