<?php

namespace Infira\MeritAktiva;

if ( ! function_exists('debug')) {
    function debug()
    {
        $GLOBALS["debugIsActive"] = true;
        $args                     = func_get_args();
        $html                     = "";
        
        if (count($args) == 1) {
            $html .= dump($args[0]);
        } else {
            $html .= dump($args);
        }
        $html = "<pre>$html</pre>";
        echo($html);
    }
}
if ( ! function_exists('dump')) {
    function dump($variable, $echo = false)
    {
        
        if (is_array($variable) or is_object($variable)) {
            $html = print_r($variable, true);
        } else {
            ob_start();
            var_dump($variable);
            $html = ob_get_clean();
        }
        if ($echo == true) {
            exit($html);
        }
        
        return $html;
    }
}


use Infira\MeritAktiva\APIResult as APIResult;
use Infira\MeritAktiva\SalesInvoice as Invoice;

class API extends \Infira\MeritAktiva\General
{
    private $apiID           = "";
    private $apiKey          = "";
    private $lastRequestData = "";
    private $lastRequestUrl  = "";
    private $url             = "";
    private $debug           = false;
    
    public function __construct($apiID, $apiKey, $country = 'ee')
    {
        $this->apiID  = $apiID;
        $this->apiKey = $apiKey;
        if ($country == 'ee') {
            $this->url = 'https://aktiva.merit.ee/api/v1/';
        } elseif ($country == 'fi') {
            $this->url = 'https://aktiva.meritaktiva.fi/api/v1/';
        } elseif ($country == 'pl') {
            $this->url = 'https://program.360ksiegowosc.pl/api/v1/';
        } else {
            throw new \Error("Unknown country");
        }
    }
    
    public function setDebug(bool $bool)
    {
        $this->debug = $bool;
    }
    
    
    public function getLastRequestData()
    {
        return $this->lastRequestData;
    }
    
    public function getLastRequestURL()
    {
        return $this->lastRequestUrl;
    }
    
    private function send($endPoint, $payload = null): array
    {
        $ret       = [];
        $timestamp = date("YmdHis");
        $urlParams = "";
        $json      = "";
        if ($payload) {
            if ($this->debug) {
                debug($payload);
            }
            $json = self::toUTF8(json_encode($payload));
        }
        
        $dataString            = $this->apiID.$timestamp.$json;
        $hash                  = hash_hmac("sha256", $dataString, $this->apiKey);
        $signature             = base64_encode($hash);
        $url                   = sprintf('%s%s?ApiId=%s&timestamp=%s&signature=%s'.$urlParams, $this->url, $endPoint, $this->apiID, $timestamp, $signature);
        $this->lastRequestUrl  = $url;
        $this->lastRequestData = $payload;
        
        $headers = [
            "Content-type: application/json",
        ];
        
        if (isset($json)) {
            $headers[] = "Content-Length: ".strlen($json);
        }
        
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, true);
        if ($json) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        }
        $curlResponse = curl_exec($curl);
        if ($this->debug) {
            debug('$curlResponse', $curlResponse);
        }
        
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        $ret['status'] = $status;
        
        if ($status != 200) {
            $error = "Error: call to URL $url <br>STATUS: $status<br>CURL_ERROR: ".curl_error($curl)."<br> CURL_ERRNO: ".curl_errno($curl);
            $error .= '<br><br>API SAYS:'.dump($this->jsonDecode($curlResponse, true));
            
            $ret['data'] = $error;
        } else {
            $ret['data'] = $this->jsonDecode($curlResponse);
        }
        if (isset($curl)) {
            curl_close($curl);
        }
        
        return $ret;
    }
    
    private function jsonDecode($json, $checkError = false)
    {
        if (substr($json, 0, 1) == '"' and substr($json, -1) == '"') {
            $data = json_decode(substr($json, 1, -1));
        } else {
            $data = json_decode($json);
        }
        if (json_last_error() and $checkError) {
            return $json;
        }
        
        return $data;
    }
    
    private static function toUTF8($string)
    {
        $string = trim($string);
        // return mb_convert_encoding($string, 'UTF-8');
        $encoding_list = 'UTF-8, ISO-8859-13, ISO-8859-1, ASCII, UTF-7';
        if (mb_detect_encoding($string, $encoding_list) == 'UTF-8') {
            return $string;
        }
        
        return mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string, $encoding_list));
    }
    
    //############ START OF endpoints
    
    /**
     * @param string $periodStart - string what is convertoed to time using strtotime()
     * @param string $periodEnd   - string what is convertoed to time using strtotime()
     *
     * @return APIResult
     * @see https://api.merit.ee/reference-manual/sales-invoices/get-list-of-invoices/
     */
    public function getSalesInvoices($periodStart, $periodEnd): APIResult
    {
        $payload = ["PeriodStart" => date("Ymd", strtotime($periodStart)), "PeriodEnd" => date("Ymd", strtotime($periodEnd))];
        
        return new APIResult($this->send("getinvoices", $payload));
    }
    
    /**
     * Get sales invoice details
     *
     * @param string $GUID
     *
     * @return APIResult
     * @see https://api.merit.ee/reference-manual/sales-invoices/get-invoice-details/
     */
    public function getSalesInvoiceByID(string $GUID): APIResult
    {
        return new APIResult($this->send("getinvoice", ['id' => $GUID]));
    }
    
    /**
     * Delete sales invoice
     *
     * @param string $GUID
     *
     * @return APIResult
     * @see https://api.merit.ee/reference-manual/sales-invoices/delete-invoice/
     */
    public function deleteSalesInvoiceByID(string $GUID): APIResult
    {
        return new APIResult($this->send("deleteinvoice", ['id' => $GUID]));
    }
    
    /**
     * Returns created invoice data
     *
     * @param SalesInvoice $Invoice
     *
     * @return APIResult
     * @see https://api.merit.ee/reference-manual/sales-invoices/create-sales-invoice/
     */
    public function createSalesInvoice(SalesInvoice $Invoice): APIResult
    {
        return new APIResult($this->send("sendinvoice", $Invoice->getData()));
    }
    
    /**
     * Returns created invoice data
     *
     * @param SalesInvoice $Invoice
     *
     * @return APIResult
     * @see https://api.merit.ee/reference-manual/sales-invoices/create-sales-invoice/
     */
    public function createCreditSalesInvoice(SalesInvoice $Invoice): APIResult
    {
        return new APIResult($this->send("sendinvoice", $Invoice->getData()));
    }
    
    /**
     * @param string $periodStart - string what is convertoed to time using strtotime()
     * @param string $periodEnd   - string what is convertoed to time using strtotime()
     *
     * @return APIResult
     * @see https://api.merit.ee/reference-manual/purchase-invoices/get-list-of-purchase-invoices/
     */
    public function getPurchaseInvoices($periodStart, $periodEnd): APIResult
    {
        $payload = ["PeriodStart" => date("Ymd", strtotime($periodStart)), "PeriodEnd" => date("Ymd", strtotime($periodEnd))];
        
        return new APIResult($this->send("getpurchorders", $payload));
    }
    
    /**
     * Get sales invoice details
     *
     * @param string $GUID
     *
     * @return APIResult
     * @see https://api.merit.ee/reference-manual/purchase-invoices/get-purchase-invoice-details/
     */
    public function getPurchaseInvoiceByID(string $GUID): APIResult
    {
        return new APIResult($this->send("getpurchorder", ['id' => $GUID]));
    }
    
    /**
     * Save purcahse invoice
     *
     * @param \Infira\MeritAktiva\PurchaseInvoice $Invoice
     *
     * @return APIResult
     * @see https://api.merit.ee/reference-manual/purchase-invoices/create-purchase-invoice/
     */
    public function createPurchaseInvoice(\Infira\MeritAktiva\PurchaseInvoice $Invoice)
    {
        return new APIResult($this->send("sendpurchinvoice", $Invoice->getData()));
    }
    
    /**
     * Save purcahse invoice
     *
     * @param \Infira\MeritAktiva\Payment $Invoice
     *
     * @return APIResult
     */
    public function savePayment(\Infira\MeritAktiva\Payment $Invoice)
    {
        return new APIResult($this->send("sendpayment", $Invoice->getData()));
    }
    
    /**
     * @see https://api.merit.ee/reference-manual/payments/list-of-payments/
     * @return APIResult
     */
    public function getPayments($periodStart, $periodEnd): APIResult
    {
        $payload = ["PeriodStart" => date("Ymd", strtotime($periodStart)), "PeriodEnd" => date("Ymd", strtotime($periodEnd))];
        
        return new APIResult($this->send("getpayments", $payload));
    }
    
    /**
     * get Merit customers
     *
     * @param array $payload
     *
     * @return APIResult
     * @see https://api.merit.ee/reference-manual/get-customer-list/
     */
    private function getCustomersBy(array $payload): APIResult
    {
        return new APIResult($this->send("getcustomers", $payload));
    }
    
    /**
     * Get customer list
     *
     * @see https://api.merit.ee/reference-manual/get-customer-list/
     * @return APIResult
     */
    public function getCustomers(): APIResult
    {
        return $this->getCustomersBy([]);
    }
    
    /**
     * get merit vendor by ID
     *
     * @see https://api.merit.ee/reference-manual/get-customer-list/
     * @return APIResult
     */
    public function getCustomersByID($GUID)
    {
        return $this->getCustomersBy(["Id" => $this->validateGUID($GUID)]);
    }
    
    /**
     * get merit vendor by RegNo
     *
     * @see https://api.merit.ee/reference-manual/get-customer-list/
     * @return APIResult
     */
    public function getCustomersByRegNo($no)
    {
        return $this->getCustomersBy(["RegNo" => $no]);
    }
    
    /**
     * get merit vendor by VatRegNo
     *
     * @see https://api.merit.ee/reference-manual/get-customer-list/
     * @return APIResult
     */
    public function getCustomersByVatRegNo($no)
    {
        return $this->getCustomersBy(["VatRegNo" => $no]);
    }
    
    /**
     * get merit vendor by Name
     *
     * @see https://api.merit.ee/reference-manual/get-customer-list/
     * @return APIResult
     */
    public function getCustomersByName($name)
    {
        return $this->getCustomersBy(["Name" => $name]);
    }
    
    /**
     * @see https://api.merit.ee/reference-manual/tax-list/
     * @return APIResult
     */
    public function getTaxes()
    {
        return new APIResult($this->send("gettaxes"));
    }
    
    /**
     * Get tax details
     *
     * @param string $code
     *
     * @return \stdClass|null
     * @see https://api.merit.ee/reference-manual/tax-list/
     */
    public function getTaxDetails(string $code)
    {
        $Taxes = $this->getTaxes();
        if ($Taxes->isError()) {
            $this->intError($Taxes->getError());
        }
        foreach ($Taxes->getRaw() as $Row) {
            if ($Row->Code == $code) {
                return $Row;
            }
        }
        
        return null;
    }
    
    
    /**
     * get merit vendors
     *
     * @return APIResult
     */
    
    /**
     * @param array $payload
     *
     * @return APIResult
     * @see https://api.merit.ee/reference-manual/get-vendor-list/
     */
    private function getVendorsBy(array $payload): APIResult
    {
        return new APIResult($this->send("getvendors", $payload));
    }
    
    /**
     * get vendors by ID
     *
     * @return APIResult
     */
    public function getVendors()
    {
        return $this->getVendorsBy([]);
    }
    
    /**
     * get vendors by ID
     *
     * @return APIResult
     */
    public function getVendorsByID($ID)
    {
        return $this->getVendorsBy(["Id" => $ID]);
    }
    
    /**
     * get merit vendor by RegNo
     *
     * @return APIResult
     */
    public function getVendorsByRegNo($no)
    {
        return $this->getVendorsBy(["RegNo" => $no]);
    }
    
    /**
     * get merit vendor by VatRegNo
     *
     * @return APIResult
     */
    public function getVendorsByVatRegNo($no)
    {
        return $this->getVendorsBy(["VatRegNo" => $no]);
    }
    
    /**
     * get merit vendor by Name
     *
     * @return APIResult
     */
    public function getVendorsByName($name)
    {
        return $this->getVendorsBy(["Name" => $name]);
    }
    
    
    /**
     * @param array $payload
     *
     * @return \Infira\MeritAktiva\APIResult
     */
    public function getItemsBy(array $payload): APIResult
    {
        return new APIResult($this->send("getitems", $payload));
    }
    
    
    /**
     * @return \Infira\MeritAktiva\APIResult
     */
    public function getItems(): APIResult
    {
        return $this->getItemsBy([]);
    }
    
    /**
     * @param string $code
     *
     * @return \Infira\MeritAktiva\APIResult
     */
    public function getItemByCode(string $code): APIResult
    {
        return $this->getItemsBy(["Code" => $code]);
    }
}
