<?php
require_once __DIR__ . '/Blockpen/vendor/autoload.php';
require_once __DIR__ . '/Blockpen/const.php';

use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function blockpen_MetaData()
{
    return array(
        'DisplayName' => 'Blockpen Commerce',
        'APIVersion' => '1.0.0',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false
    );
}

function blockpen_config()
{
    // Global variable required
    global $customadminpath;

    // Build callback URL.
    $isHttps = (isset ($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');

    $protocol = $isHttps ? "https://" : "http://";
    $url = $protocol . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    $url = substr($url, 0, strpos($url, $customadminpath));
    $callbackUrl = $url . "modules/gateways/callback/blockpen.php";

    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Blockpen Commerce <a href=“https://commerce.blockpen.tech/” target=“_blank” rel=“noopener”>(Learn more)</a>'
        ),
        'readme' => array(
            'FriendlyName' => '',
            'Type' => '',
            'Size' => '',
            'Default' => '',
            'Description' => 'Read the README.md file for instructions on how to use this module'
        )
    );
}

function blockpen_link($params)
{
    if (!isset($params) || empty($params)) {
        die('Missing or invalid $params data.');
    }

    $description = '';

    try {
        $description = Capsule::table('tblinvoiceitems')
            ->where("invoiceid", "=", $params['invoiceid'])
            ->value('description');
        // Truncate descriptions longer than 200 per Commerce API requirements
        $description = (strlen($description) > 200) ? substr($description,0,197).'...' : $description;
    } catch (Exception $e) {
    }

    $chargeData = array(
        'total' => $params['amount'],
        'currency' => $params['currency'],
        'name' => $params['description'],
        'description' => empty($description) ? $params['description'] : $description,
        'metadata' => [
            METADATA_SOURCE_PARAM => METADATA_SOURCE_VALUE,
            METADATA_INVOICE_PARAM => $params['invoiceid'],
            METADATA_CLIENT_PARAM => $params['clientdetails']['userid'],
            'firstName' => isset($params['clientdetails']['firstname']) ? $params['clientdetails']['firstname'] : null,
            'lastName' => isset($params['clientdetails']['lastname']) ? $params['clientdetails']['lastname'] : null,
            'email' => isset($params['clientdetails']['email']) ? $params['clientdetails']['email'] : null
        ],
        'success_url' => $params['returnurl'] . "&paymentsuccess=true",
        'cancel_url' => $params['returnurl'] . "&paymentfailed=true"
    );

    $formed_url = 'https://alpha.blockpen.tech/woocommerce/pay?';
    
    $form = '<form action="' . $formed_url . '"method="GET">';
    foreach ($chargeData as $key => $value) {
        $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
    }
    
    $form .= '<input type="submit" value="' . $params['langpaynow'] . '" /></form>';

    return $form;
}
