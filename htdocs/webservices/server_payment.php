<?php
/* Copyright (C) 2006-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2024-2025	MDW					<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * The payment webservice was initially created by Nicolas Nunge <me@nikkow.eu>
 */

/**
 *       \file       htdocs/webservices/server_payment.php
 *       \brief      File that is entry point to call Dolibarr WebServices
 */

if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1'); // Do not check anti CSRF attack test
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1'); // Do not check anti POST attack test
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1'); // If there is no need to load and show top and left menu
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1'); // If we don't need to load the html.form.class.php
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1'); // Do not load ajax.lib.php library
}
if (!defined("NOLOGIN")) {
	define("NOLOGIN", '1'); // If this page is public (can be called outside logged session)
}
if (!defined("NOSESSION")) {
	define("NOSESSION", '1');
}

require '../main.inc.php';
require_once NUSOAP_PATH.'/nusoap.php'; // Include SOAP
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';

/**
 * @var DoliDB $db
 * @var Translate $langs
 */

dol_syslog("Call Dolibarr webservices interfaces");

$langs->load("main");

// Enable and test if module web services is enabled
if (!getDolGlobalString('MAIN_MODULE_WEBSERVICES')) {
	$langs->load("admin");

	dol_syslog("Call Dolibarr webservices interfaces with module webservices disabled");
	print $langs->trans("WarningModuleNotActive", 'WebServices').'.<br><br>';
	print $langs->trans("ToActivateModule");
	exit;
}

// Create the soap Object
$server                              = new nusoap_server();
$server->soap_defencoding            = 'UTF-8';
$server->decode_utf8                 = false;
$ns                                  = 'http://www.dolibarr.org/ns/';
$server->configureWSDL('WebServicesDolibarrPayment', $ns);
$server->wsdl->schemaTargetNamespace = $ns;


// Define WSDL Authentication object
$server->wsdl->addComplexType(
	'authentication',
	'complexType',
	'struct',
	'all',
	'',
	array(
		'dolibarrkey'       => array('name' => 'dolibarrkey', 'type' => 'xsd:string'),
	   'sourceapplication' => array('name' => 'sourceapplication', 'type' => 'xsd:string'),
	   'login'             => array('name' => 'login', 'type' => 'xsd:string'),
		'password'          => array('name' => 'password', 'type' => 'xsd:string'),
		'entity'            => array('name' => 'entity', 'type' => 'xsd:string')
	)
);
// Define WSDL Return object
$server->wsdl->addComplexType(
	'result',
	'complexType',
	'struct',
	'all',
	'',
	array(
		'result_code'  => array('name' => 'result_code', 'type' => 'xsd:string'),
		'result_label' => array('name' => 'result_label', 'type' => 'xsd:string'),
	)
);

// Define WSDL for Payment object
$server->wsdl->addComplexType(
	'payment',
	'complexType',
	'struct',
	'all',
	'',
	array(
		'amount'          => array('name' => 'amount', 'type' => 'xsd:double'),
		'num_payment'     => array('name' => 'num_payment', 'type' => 'xsd:string'),
		'thirdparty_id'   => array('name' => 'thirdparty_id', 'type' => 'xsd:int'),
			   'bank_account'    => array('name' => 'bank_account', 'type' => 'xsd:int'),
			   'payment_mode_id' => array('name' => 'payment_mode_id', 'type' => 'xsd:int'),
			   'invoice_id'      => array('name' => 'invoice_id', 'type' => 'xsd:int'),
			   'int_label'       => array('name' => 'int_label', 'type' => 'xsd:string'),
			   'emitter'         => array('name' => 'emitter', 'type' => 'xsd:string'),
			   'bank_source'     => array('name' => 'bank_source', 'type' => 'xsd:string'),
	)
);

// 5 styles: RPC/encoded, RPC/literal, Document/encoded (not WS-I compliant), Document/literal, Document/literal wrapped
// Style merely dictates how to translate a WSDL binding to a SOAP message. Nothing more. You can use either style with any programming model.
// http://www.ibm.com/developerworks/webservices/library/ws-whichwsdl/
$styledoc = 'rpc'; // rpc/document (document is an extend into SOAP 1.0 to support unstructured messages)
$styleuse = 'encoded'; // encoded/literal/literal wrapped
// Better choice is document/literal wrapped but literal wrapped not supported by nusoap.

// Register WSDL
$server->register(
	'createPayment',
	// Entry values
	array('authentication' => 'tns:authentication', 'payment' => 'tns:payment'),
	// Exit values
	array('result' => 'tns:result', 'id' => 'xsd:string', 'ref' => 'xsd:string', 'ref_ext' => 'xsd:string'),
	$ns,
	$ns.'#createPayment',
	$styledoc,
	$styleuse,
	'WS to create a new payment'
);


/**
 * Create a payment
 *
 * @param      array{login:string,password:string,entity:?int,dolibarrkey:string}           $authentication         Array of authentication information
 * @param      array{id:int,thirdparty_id:int|string,amount:float|string,num_payment:string,bank_account:int|string,payment_mode_id?:int|string,invoice_id?:int|string,int_label?:string,emitter:string,bank_source:string} $payment	Payment
 * @return     array{result:array{result_code:string,result_label:string},id?:int}	Array result
 */
function createPayment($authentication, $payment)
{
	global $db, $conf;

	$now = dol_now();

	// TODO: Verify 'ref' and 'ref_ext', not defined in the WS Payment object.
	dol_syslog("Function: createPayment login=".$authentication['login']." id=".$payment['id'].
			   ", ref=".($payment['ref'] ?? '').", ref_ext=".($payment['ref_ext'] ?? ''));

	if ($authentication['entity']) {
		$conf->entity = $authentication['entity'];
	}

	// Init and check authentication
	$objectresp = array();
	$errorcode  = '';
	$errorlabel = '';
	$error      = 0;
	$fuser      = check_authentication($authentication, $error, $errorcode, $errorlabel);

	// Check parameters
	if (empty($payment['amount']) && empty($payment['thirdparty_id'])) {
		$error++;
		$errorcode  = 'KO';
		$errorlabel = "You must specify the amount and the third party's ID.";
	}

	if (!$error) {
		$soc = new Societe($db);
		$soc->fetch($payment['thirdparty_id']);

		$new_payment              = new Paiement($db);
		$new_payment->num_payment = $payment['num_payment'];
		$new_payment->fk_account  = intval($payment['bank_account']);
		$new_payment->paiementid  = !empty($payment['payment_mode_id']) ? intval($payment['payment_mode_id']) : $soc->mode_reglement_id;
		$new_payment->datepaye    = $now;
		$new_payment->author      = $payment['thirdparty_id'];
		$new_payment->amounts     = array($payment['invoice_id'] => (float) $payment['amount']);

		$db->begin();
		$result = $new_payment->create($fuser, 1);

		if ($payment['bank_account']) {
			$new_payment->addPaymentToBank($fuser, 'payment', $payment['int_label'], $payment['bank_account'], $payment['emitter'], $payment['bank_source']);
		}

		if ($result < 0) {
			$error++;
		}

		if (!$error) {
			$db->commit();
			$objectresp = array('result' => array('result_code' => 'OK', 'result_label' => ''), 'id' => $new_payment->id);
		} else {
			$db->rollback();
			$error++;
			$errorcode = 'KO';
			$errorlabel = $new_payment->error;
			dol_syslog("Function: createInvoice error while creating".$errorlabel);
		}
	}

	if ($error) {
		$objectresp = array('result' => array('result_code' => $errorcode, 'result_label' => $errorlabel));
	}

	return $objectresp;
}

// Return the results.
$server->service(file_get_contents("php://input"));
