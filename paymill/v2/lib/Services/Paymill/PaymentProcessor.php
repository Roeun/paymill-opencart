<?php

/**
 * This class acts as an easy to use gateway for the paymill phph wrapper.
 * @version    1.0.0
 * @category   PayIntelligent
 * @copyright  Copyright (c) 2013 PayIntelligent GmbH (http://payintelligent.de)
 */
class Services_Paymill_PaymentProcessor
{

    //Options: Variables needed to create Paymill Lib Components
    private $_libBase;
    private $_privateKey;
    private $_apiUrl;
    //Objects: Objects used by the methods
    private $_clientsObject;
    private $_transactionsObject;
    private $_paymentsObject;
    private $_logger;               //Only this object can be set using a set function.
    //Process Payment relevant
    private $_token;                //Token generated for the Transaction
    private $_amount;               //Current Amount
    private $_currency;             //Currency (of both amounts)
    private $_name;                 //Customername
    private $_email;                //Customer Email Adress
    private $_description;
    private $_lastResponse;
    private $_transactionId;        //Transaction Id generated by the createTransaction function.
    //Fast Checkout Variables
    private $_clientId = null;
    private $_paymentId = null;

    /**
     * Creates an object of the PaymentProcessor class.
     * @param String <b>$privateKey</b> Paymill-PrivateKey
     * @param String <b>$apiUrl</b> Paymill-Api Url
     * @param String <b>$libBase</b> Path to the lib Base (Can be null, Default Path will be used)
     * @param array <b>$params</b>( <br />
     *    <b>token</b>,               generated Token <br />
     *    <b>amount</b>,              Basketamount <br />
     *    <b>currency</b>,            Transaction currency <br />
     *    <b>name</b>,                Customer name <br />
     *    <b>email</b>,               Customer emailaddress <br />
     *    <b>description</b>,         Description for transactions <br />
     * ) <p color='red'><b>(If not set here, the use of setters is required for the class to work)</b></p>
     * @param object $loggingClassInstance Instance of Object implementing the Services_Paymill_PaymentProcessorInterface. If not set, there will be no logging.
     */
    public function __construct($privateKey = null, $apiUrl = null, $libBase = null, $params = null, Services_Paymill_LoggingInterface $loggingClassInstance = null)
    {
        $this->setPrivateKey($privateKey);
        $this->setApiUrl($apiUrl);
        $this->setLibBase($libBase);
        $this->_token = $params['token'];
        $this->_amount = $params['amount'];
        $this->_currency = $params['currency'];
        $this->_name = $params['name'];
        $this->_email = $params['email'];
        $this->_description = $params['description'];
        $this->setLogger($loggingClassInstance);
    }

    /**
     * Creates a Paymill-Client with the given Data
     *
     * @param array $params
     * @return boolean
     */
    private function _createClient()
    {
        if (isset($this->_clientId)) {
            $this->_log("Client using: " . $this->_clientId);
        } else {
            $client = $this->_clientsObject->create(
                    array(
                        'email' => $this->_email,
                        'description' => $this->_description
                    )
            );

            $this->_validateResult($client, 'Client');

            $this->_clientId = $client['id'];
        }
        return $this->_clientId;
    }

    /**
     * Creates a Paymill-Payment with the given Data
     *
     * @param array $params
     * @return boolean
     */
    private function _createPayment()
    {
        if (isset($this->_paymentId)) {
            $this->_log("Payment using: " . $this->_paymentId);
        } else {
            $payment = $this->_paymentsObject->create(
                    array(
                        'token' => $this->_token,
                        'client' => $this->_clientId
                    )
            );
            $this->_validateResult($payment, 'Payment');

            $this->_paymentId = $payment['id'];
        }
        return true;
    }

    /**
     * Creates a Paymill-Transaction with the given Data
     *
     * @param array $params
     * @return boolean
     */
    private function _createTransaction()
    {
        $transaction = $this->_transactionsObject->create(
                array(
                    'amount' => $this->_amount,
                    'currency' => $this->_currency,
                    'description' => $this->_description,
                    'payment' => $this->_paymentId,
                    'client' => $this->_clientId
                )
        );
        $this->_validateResult($transaction, 'Transaction');

        $this->_transactionId  = $transaction['id'];
        return true;
    }

    /**
     * Load the PhpWrapper-Classes and creates an instance for each class.
     */
    private function _initiatePhpWrapperClasses()
    {
        require_once $this->_libBase . 'Transactions.php';
        require_once $this->_libBase . 'Clients.php';
        require_once $this->_libBase . 'Payments.php';
        $this->_clientsObject = new Services_Paymill_Clients($this->_privateKey, $this->_apiUrl);
        $this->_transactionsObject = new Services_Paymill_Transactions($this->_privateKey, $this->_apiUrl);
        $this->_paymentsObject = new Services_Paymill_Payments($this->_privateKey, $this->_apiUrl);
    }

    /**
     * Calls the log() function of the logger object if the object has been set.
     *
     * @param string $message
     * @param string $debugInfo
     */
    private function _log($message, $debugInfo = null)
    {
        if (isset($this->_logger)) {
            $this->_logger->log($message, $debugInfo);
        }
    }

    /**
     * Validates the array passed as an argument to be processPayment() compliant
     * @param mixed $parameter
     * @return boolean
     */
    private function _validateParameter()
    {
        $validation = true;
        $parameter = array(
            "token" => $this->_token,
            "amount" => $this->_amount,
            "currency" => $this->_currency,
            "name" => $this->_name,
            "email" => $this->_email,
            "description" => $this->_description);

        $arrayMask = array(
            "token" => 'string',
            "amount" => 'integer',
            "currency" => 'string',
            "name" => 'string',
            "email" => 'string',
            "description" => 'string');

        foreach ($arrayMask as $mask => $type) {
            if (is_null($parameter[$mask])) {
                $validation = false;
                $this->_log("The Parameter $mask is missing.", var_export($parameter, true));
            } else {
                switch ($type) {
                    case 'string':
                        if (!is_string($parameter[$mask])) {
                            $this->_log("The Parameter $mask is not a string.", var_export($parameter, true));
                            $validation = false;
                        }
                        break;
                    case 'integer':
                        if (!is_integer($parameter[$mask])) {
                            $this->_log("The Parameter $mask is not an integer.", var_export($parameter, true));
                            $validation = false;
                        }
                        break;
                }
            }

            if (!$validation) {
                break;
            }
        }
        return $validation;
    }

    /**
     * Validates the created Paymill-Objects
     *
     * @param array $transaction
     * @param string $type
     * @return boolean
     */
    private function _validateResult($transaction, $type)
    {
        $this->_lastResponse = $transaction;
        if (isset($transaction['data']['response_code']) && $transaction['data']['response_code'] !== 20000) {
            $this->_log("An Error occured: " . $transaction['data']['response_code'], var_export($transaction, true));
            throw new Exception("Invalid Result Exception: Invalid ResponseCode");
        }

        if (!isset($transaction['id']) && !isset($transaction['data']['id'])) {
            $this->_log("No $type created.", var_export($transaction, true));
            throw new Exception("Invalid Result Exception: Invalid Id");
        } else {
            $this->_log("$type created.", isset($transaction['id']) ? $transaction['id'] : $transaction['data']['id']);
        }

        // check result
        if ($type == 'Transaction') {
            if (is_array($transaction) && array_key_exists('status', $transaction)) {
                if ($transaction['status'] == "closed") {
                    // transaction was successfully issued
                    return true;
                } elseif ($transaction['status'] == "open") {
                    // transaction was issued but status is open for any reason
                    $this->_log("Status is open.", var_export($transaction, true));
                    throw new Exception("Invalid Result Exception: Invalid Orderstate");
                } else {
                    // another error occured
                    $this->_log("Unknown error." . var_export($transaction, true));
                    throw new Exception("Invalid Result Exception: Unknown Error");
                }
            } else {
                // another error occured
                $this->_log("$type could not be issued.", var_export($transaction, true));
                throw new Exception("Invalid Result Exception: $type could not be issued.");
            }
        } else {
            return true;
        }
    }

    /**
     * Executes the Payment Process
     *
     * @return boolean
     */
    final public function processPayment()
    {
        $this->_initiatePhpWrapperClasses();
        if (!$this->_validateParameter()) {
            return false;
        }

        try {
            $this->_createClient();
            $this->_createPayment();
            $this->_createTransaction();
            return true;
        } catch (Exception $ex) {
            // paymill wrapper threw an exception
            $this->_log("Exception thrown from paymill wrapper.", $ex->getMessage());
            return false;
        }
    }
    /**
     * Returns the objects data
     *
     * @return array
     */
    public function toArray(){
        return array(
            'apiurl' => $this->_apiUrl,
            'libbase' => $this->_libBase,
            'privatekey' => $this->_privateKey,
            'logger' => $this->_logger,
            'token' => $this->_token,
            'amount' => $this->_amount,
            'currency' => $this->_currency,
            'description' => $this->_description,
            'email' => $this->_email,
            'name' => $this->_name
        );
    }




    /* ***************************************************************************************************************
     * ***********************************************    Getter    **************************************************
     * *************************************************************************************************************** */

    /**
     * <p align = 'center'><b>Can only be called after the call of processPayment(). Otherwise null will be returned</b></p>
     * Returns the ClientId
     * @return String ClientId
     */
    public function getClientId()
    {
        return $this->_clientId;
    }

    /**
     * <p align = 'center'><b>Can only be called after the call of processPayment(). Otherwise null will be returned</b></p>
     * Returns the PaymentId
     * @return String PaymentId
     */
    public function getPaymentId()
    {
        return $this->_paymentId;
    }

    /**
     * <p align = 'center'><b>Can only be called after the call of processPayment(). Otherwise null will be returned</b></p>
     * Returns the TransactionId
     * @return String TransactionId
     */
    public function getTransactionId()
    {
        return $this->_transactionId;
    }

    /**
     * <p align = 'center'><b>Can only be called after the call of processPayment(). Otherwise null will be returned</b></p>
     * Returns the last response send by Paymill
     * @return array LastResponse
     */
    public function getLastResponse()
    {
        return $this->_lastResponse;
    }

    /* ***************************************************************************************************************
     * ***********************************************    Setter    **************************************************
     * *************************************************************************************************************** */

    /**
     * Sets the clientId
     * @param String $clientId
     */
    public function setClientId($clientId = null)
    {
        $this->_clientId = $clientId;
    }

    /**
     * Sets the paymentId
     * @param String $paymentId
     */
    public function setPaymentId($paymentId = null)
    {
        $this->_paymentId = $paymentId;
    }

    /**
     * This method sets the token
     * @param String $token
     */
    public function setToken($token = null)
    {
        $this->_token = $token;
    }

    /**
     * This method sets the amount
     * @param String $amount
     */
    public function setAmount($amount = null)
    {
        $this->_amount = $amount;
    }

    /**
     * Sets the currency
     * @param String $currency
     */
    public function setCurrency($currency = null)
    {
        $this->_currency = $currency;
    }

    /**
     * Sets the Customer name
     * @param String $name
     */
    public function setName($name = null)
    {
        $this->_name = $name;
    }

    /**
     * Sets the Customer Email Adress
     * @param String $email
     */
    public function setEmail($email = null)
    {
        $this->_email = $email;
    }

    /**
     * Sets the Description
     * @param String $description
     */
    public function setDescription($description = null)
    {
        $this->_description = $description;
    }

    /**
     * Sets the Api URL
     * @param String $apiUrl
     */
    public function setApiUrl($apiUrl = null)
    {
        $this->_apiUrl = $apiUrl;
    }

    /**
     * Sets the Path to the libBase
     * @param String $libBase Path to the Lib base. If not set, the default path is set.
     */
    public function setLibBase($libBase = null)
    {
        $this->_libBase = $libBase == null ? dirname(__FILE__) . DIRECTORY_SEPARATOR : $libBase;
    }

    /**
     * Sets up the Logger Object.
     * <b>The Logger object can be any class implementing the Services_Paymill_PaymentProcessorInterface.</b>
     * @param any $logger
     */
    public function setLogger(Services_Paymill_LoggingInterface $logger = null)
    {
        $this->_logger = $logger;
    }

    /**
     * Sets the Paymill-PrivateKey
     * @param string $privateKey
     */
    public function setPrivateKey($privateKey = null)
    {
        $this->_privateKey = $privateKey;
    }

}