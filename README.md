# Blockchain.info-v2
A php library for accepting payments via the Blockchain.info V2 API

Usage:

```php
<?php

// Generate a callback URL for blockchain to call when the address you generate has received a payment.
// It's a good idea to use a secret to verify the callback, generate it in a secure way.
// Be sure to include any identifying information needed for the transaction on your site. 
$callbackUrl = "https://example.com/btc_callback.php?payment_id=4&secret=2e0dasad7875hj29j2";

$paymentAmountInUSD = 50; //$50

$blockchain = new Blockchain();

$btcAmount = $blockchain->toBtc($paymentAmountInUSD);

if (!$btcAmount) {
    echo "Unable to request btc amounts from the api!"
    exit();
}

// Pass the callback url to the getAddress method as one of options params. 
// Any additional items in this options param will be added to the end of the
//   query string when the request is made to the blockchain.info API.
$btcAddress = $blockchain->getAddress(['callback' => $callbackUrl]);

if ($btcAddress) {
    echo "Success: Please send $btcAmount to $btcAddress.";
} else {
    echo "Request failed :(";
}

```

Example callback code (assuming the callback used above):

```php
<?php

//Store the success value output to stop the blockchain API from calling your callback address.
// if it does not receive this output it will continually call this page for every confirmation up
// to a full week, eventually your domain may be blocked if continue to let it go.
$success = "*ok*";

// How many confirmations are needed before this transaction is considered complete and successful.
$minConfirmations = 3;


//Capture the data sent by the blockchain API

//First grab the values you sent to it:
$secret = htmlspecialchars($_GET['secret']);
$paymentID = htmlspecialchars($_GET['payment_id']);

//Then the values sent with every payment:
$transactionHash = htmlspecialchars($_GET['transaction_hash']);
$destinationAddress = htmlspecialchars($_GET['address']);
$amountReceivedSatoshi = htmlspecialchars($_GET['value']);
$confirmations = htmlspecialchars($_GET['confirmations']);

//Validate confirmations. Usually I wait for 3 but you can act on 1 or how many you feel comfortable with.
if ($confirmations < $minConfirmations) {
    exit(); //return no output, the blockchain will then call the next time there's a confirmation.
}

//Validate secret with your local database
if (!validateSecret($secret, $paymentID))
    echo $success; //Stop the api from sending callbacks, this is a fraudulent request.
    exit();
}

//Validate amount
$amountRequiredSatoshi = getAmountRequired($paymentID); //pull this information from your database, best to store BTC amounts in satoshis. See: https://bitcoin.stackexchange.com/questions/114/what-is-a-satoshi.

//Simple validation. This has some caveats:
// 1. It will not adjust the value if BTC prices change between address generation and payment recieved.
// 2. It does not allow for the amount sent being off a bit because of transaction fees or inexperienced users. 
//    (i.e. if they send 12 cents less than requird by accident, it will get rejected) 
if ($amountRequiredSatoshi > $amountReceivedSatoshi) {
    echo $success; // Stop api, no need to keep sending.
    notifyPayer("invalid payment amount");
    exit();
}

// --- OR --- 

//Advanced validation. Requires storing fiat currency amounts in local database (price in USD for example).
//Allows for some variability in received amounts as well as auto-adjusting to currency flucuations. 

$blockchain = new Blockchain();

$amountRequiredUSD = getAmountRequiredUSD($paymentID);

//The amount, in fiat currency, you are willing to allow the payment to be off from the required value to still consider it a success. 
$paymentVariabilityAllowed = 1;  //In this example, we are allowing a payment of $1 less than required. 

$USDRates = $blockchain->getRates(); //Pulls the latest conversion rates for BTC->USD (or whatever currency you set in the API library).

//Convert satoshis to full BTC value for easier math.
$amountRecievedBTC = $amountReceivedSatoshi / 100000000;

//The 'last' item in the rates array is the last amount that bitcoin was sold for in the currency you chose in the API library.
$amountReceivedUSD = round($USDRates['last'] * $amountReceivedBTC, 2); //round to the nearest 10th of a cent (i.e. 50.00)

//First check the easy route.
if ($amountReceivedUSD >= $amountRequiredUSD) {
	echo $success;
	notifyPayer("payment success");
	exit();
}

$differenceInAmounts = ($amountRequiredUSD - $amountReceivedUSD);

if ($differenceInAmounts <= $payentVariabilityAllowed) {
	echo $success;
	notifyPayer("payment success");
	exit();
}

echo $success;
notifyPayer("payment failed");
notifyAdmin("unknown error");

/** 
 * Obviously the above code is not the most efficient, nor the safest from an error handling perspective. 
 * This code was intentionally drawn out to illustrate usage. In production it should be cleaned up and
 * duplicated code removed.
 */
```







