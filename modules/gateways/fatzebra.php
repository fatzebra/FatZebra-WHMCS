<?php
define("FATZEBRA_VERSION", 1.0);

function fatzebra_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"Credit Card (Fat Zebra)"),
     "username" => array("FriendlyName" => "Username", "Type" => "text", "Size" => "20", ),
     "token" => array("FriendlyName" => "Token", "Type" => "text", "Size" => "20", ),
     "enable_sandbox" => array("FriendlyName" => "Transact via Sandbox", "Type" => "yesno", "Description" => "Tick this to send transactions via the Sandbox gateway (please note you must use a Sandbox account for this)", ),
     "bless_token" => array("FriendlyName" => "Cart Blessing Token", "Type" => "text", "Description" => "This will be provided if Fat Zebra permit credit card details to be submitted without the CVV. The format must be CartName:token as provided by Fat Zebra.")
    );
  return $configarray;
}

function fatzebra_storeremote($params) {
  $token = $params['gatewayid'];
  $is_new = empty($token);
  $card_number = $params['cardnum'];
  $card_expiry = substr($params['cardexp'], 0, 2) . "/20" . substr($params['cardexp'], 2, 2);
  $card_cvv = $params['cardcvv'];
  $card_holder = $params['clientdetails']['firstname'] . " " . $params['clientdetails']['lastname'];

  if (isset($_SERVER['REMOTE_ADDR'])) {
    $customer_ip = $_SERVER['REMOTE_ADDR'];
  } else {
    $customer_ip = "127.0.0.1";
  }

  $payload = array(
    'customer_ip' => $customer_ip,
    'card_holder' => $card_holder,
    'card_number' => $card_number,
    'card_expiry' => $card_expiry,
    'cvv' => $card_cvv
  );

  // Post and then handle response
  try {
    $result = do_request("POST", "/credit_cards", $payload, $params);
    return array("status" => $result->successful, "gatewayid" => $result->response->token, "rawdata" => json_encode($result));
  } catch(Exception $e) {
    return array("status" => "error", "rawdata" => "Exception raised while submitting card: " . $e->message);
  }
}

function fatzebra_capture($params) {
  # Gateway Specific Variables
  $username = $params['username'];
  $token = $params['token'];
  $sandbox = $params['enable_sandbox'];

  # Invoice Variables
  $invoiceid = $params['invoiceid'];
  $amount = $params['amount']; # Format: ##.##
  $currency = $params['currency']; # Currency Code

  # Card Details
  $card_number = $params['cardnum'];
  $card_expiry = substr($params['cardexp'], 0, 2) . "/20" . substr($params['cardexp'], 2, 2);
  $card_cvv = $params['cccvv'];
  $card_holder = $params['clientdetails']['firstname'] . " " . $params['clientdetails']['lastname'];

  $customer_ip = $_SERVER['REMOTE_ADDR'];
  if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $forwarded_ips = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $customer_ip = $forwarded_ips[0];
  }

  $payload = array(
                   "amount" => (int)(floatval($amount) * 100),
                   "reference" => $invoiceid,
                   "customer_ip" => $customer_ip,
                   "currency" => $currency
                   );

  if (isset($params['gatewayid'])) {
    $payload['card_token'] = $params['gatewayid'];
  } else {
    $payload['card_number'] = $card_number;
    $payload['card_holder'] = $card_holder;
    $payload['card_expiry'] = $card_expiry;
  }

  if (isset($params['cccvv'])) {
    $payload['ccv'] = $params['cccvv'];
  } else {
    $params['requires_bless'] = true; // Tells the call to the gateway to insert the blessed cart header
  }

  try {
    $result = do_request("POST", "/purchases", $payload, $params);

    # Return Results
    if($result->successful) {
      if($result->response->successful) {
        return array("status" => "success", "transid" => $result->response->id, "rawdata" => $result->response->message);
      } else {
        return array("status" => "declined", "transid" => $result->response->id, "rawdata" => $result->response->message);
      }
    } else {
      return array("status" => "error", "rawdata" => join(", ", $result->errors));
    }
  } catch(Exception $e) {
    return array("status" => "error", "rawdata" => "Exception raised while performing capture: " . $e->message);
  }
}

function fatzebra_refund($params) {
  # Invoice Variables
  $transid = $params['transid']; # Transaction ID of Original Payment
  $amount = $params['amount']; # Format: ##.##
  $currency = $params['currency']; # Currency Code

  $payload = array(
    "amount" => (int)(floatVal($amount) * 100),
    "transaction_id" => $transid,
    "reference" => $transid . "-" . time()
  );

  # Perform Refund Here & Generate $results Array, eg:
  try {
    $result = do_request("POST", "/refunds", $payload, $params);
    if ($result->successful) {
      if($result->response->successful) {
        return array("status" => "success", "transid" => $result->response->id, "rawdata" => $result->response->message);
      } else {
        return array("status" => "declined", "transid" => $result->response->id, "rawdata" => $result->response->message);
      }
    } else {
      return array("status" => "error", "rawdata" => join(", ", $result->errors));
    }
  } catch(Exception $e) {
    return array("status" => "error", "rawdata" => "Exception raised while performing refund: " . $e->message);
  }
}

/************** Private functions ***************/

/**
* Performs the request against the Fat Zebra gateway
* @param string $method the request method ("POST" or "GET")
* @param string $uri the request URI (e.g. /purchases, /credit_cards etc)
* @param Array $payload the request payload (if a POST request)
* @return StdObject
*/
function do_request($method, $uri, $payload = null, $params) {
  $curl = curl_init();
  $base_url = $params['enable_sandbox'] == "on" ? "https://gateway.sandbox.fatzebra.com.au/v1.0" : "https://gateway.fatzebra.com.au/v1.0";
  $url = $base_url. $uri;
  curl_setopt($curl, CURLOPT_URL, $url);

  $headers = array("User-Agent: FatZebra WHMCS Library " . FATZEBRA_VERSION,
                   "Content-Type: application/json");
  if (isset($params['requires_bless']) && isset($params['bless_token'])) {
    $headers[] = "X-FatZebra-Cart: " . $params['bless_token'];
  }

  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
  curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($curl, CURLOPT_USERPWD, $params['username'].":". $params['token']);
  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

  if ($method == "POST" || $method == "PUT") {
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
  }

  if ($method == "PUT") {
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
  }

  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
  curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . DIRECTORY_SEPARATOR . 'cacert.pem');
  curl_setopt($curl, CURLOPT_TIMEOUT, 40);

  $data = curl_exec($curl);

  if (curl_errno($curl) !== 0) {
    throw new Exception("cURL error: " . curl_error($curl));
  }
  curl_close($curl);

  $response =  json_decode($data);
  if (is_null($response)) {
    $err = json_last_error();
    if ($err == JSON_ERROR_SYNTAX) {
      throw new Exception("JSON Syntax error. JSON attempted to parse: " . $data);
    } elseif ($err == JSON_ERROR_UTF8) {
      throw new Exception("JSON Data invalid - Malformed UTF-8 characters. Data: " . $data);
    } else {
      throw new Exception("JSON parse failed. Unknown error. Data:" . $data);
    }
  }

  return $response;
}

?>