<?php
/* Copyright (C) 2006-2016	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2012		JF FERRY			<jfefe@aternatik.fr>
 * Copyright (C) 2012		Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2024-2025	MDW					<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024       Frédéric France             <frederic.france@free.fr>
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

/**
 *       \file       htdocs/webservices/server_order.php
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT."/commande/class/commande.class.php";

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
$server = new nusoap_server();
$server->soap_defencoding = 'UTF-8';
$server->decode_utf8 = false;
$ns = 'http://www.dolibarr.org/ns/';
$server->configureWSDL('WebServicesDolibarrOrder', $ns);
$server->wsdl->schemaTargetNamespace = $ns;


// Define WSDL Authentication object
$server->wsdl->addComplexType(
	'authentication',
	'complexType',
	'struct',
	'all',
	'',
	array(
				'dolibarrkey' => array('name' => 'dolibarrkey', 'type' => 'xsd:string'),
				'sourceapplication' => array('name' => 'sourceapplication', 'type' => 'xsd:string'),
				'login' => array('name' => 'login', 'type' => 'xsd:string'),
				'password' => array('name' => 'password', 'type' => 'xsd:string'),
				'entity' => array('name' => 'entity', 'type' => 'xsd:string')
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
				'result_code' => array('name' => 'result_code', 'type' => 'xsd:string'),
				'result_label' => array('name' => 'result_label', 'type' => 'xsd:string'),
		)
);

$line_fields = array(
	'id' => array('name' => 'id', 'type' => 'xsd:string'),
	'type' => array('name' => 'type', 'type' => 'xsd:int'),
	'fk_commande' => array('name' => 'fk_commande', 'type' => 'xsd:int'),
	'fk_parent_line' => array('name' => 'fk_parent_line', 'type' => 'xsd:int'),
	'desc' => array('name' => 'desc', 'type' => 'xsd:string'),
	'qty' => array('name' => 'qty', 'type' => 'xsd:double'),
	'price' => array('name' => 'price', 'type' => 'xsd:double'),
	'unitprice' => array('name' => 'unitprice', 'type' => 'xsd:double'),
	'vat_rate' => array('name' => 'vat_rate', 'type' => 'xsd:double'),

	'remise' => array('name' => 'remise', 'type' => 'xsd:double'),
	'remise_percent' => array('name' => 'remise_percent', 'type' => 'xsd:double'),

	'total_net' => array('name' => 'total_net', 'type' => 'xsd:double'),
	'total_vat' => array('name' => 'total_vat', 'type' => 'xsd:double'),
	'total' => array('name' => 'total', 'type' => 'xsd:double'),

	'date_start' => array('name' => 'date_start', 'type' => 'xsd:date'),
	'date_end' => array('name' => 'date_end', 'type' => 'xsd:date'),

	// From product
	'product_id' => array('name' => 'product_id', 'type' => 'xsd:int'),
	'product_ref' => array('name' => 'product_ref', 'type' => 'xsd:string'),
	'product_label' => array('name' => 'product_label', 'type' => 'xsd:string'),
	'product_desc' => array('name' => 'product_desc', 'type' => 'xsd:string')
);

$elementtype = 'commandedet';

//Retrieve all extrafield for thirdsparty
// fetch optionals attributes and labels
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($elementtype, true);
$extrafield_line_array = null;
if (is_array($extrafields->attributes) && $extrafields->attributes[$elementtype]['count'] > 0) {
	$extrafield_line_array = array();
}
if (isset($extrafields->attributes[$elementtype]['label']) && is_array($extrafields->attributes[$elementtype]['label']) && count($extrafields->attributes[$elementtype]['label'])) {
	foreach ($extrafields->attributes[$elementtype]['label'] as $key => $label) {
		//$value=$object->array_options["options_".$key];
		$type = $extrafields->attributes[$elementtype]['type'][$key];
		if ($type == 'date' || $type == 'datetime') {
			$type = 'xsd:dateTime';
		} else {
			$type = 'xsd:string';
		}
		$extrafield_line_array['options_'.$key] = array('name' => 'options_'.$key, 'type' => $type);
	}
}
if (is_array($extrafield_line_array)) {
	$line_fields = array_merge($line_fields, $extrafield_line_array);
}

// Define other specific objects
$server->wsdl->addComplexType(
	'line',
	'complexType',
	'struct',
	'all',
	'',
	$line_fields
);

/*$server->wsdl->addComplexType(
		'LinesArray',
		'complexType',
		'array',
		'',
		'SOAP-ENC:Array',
		array(),
		array(
				array(
						'ref'=>'SOAP-ENC:arrayType',
						'wsdl:arrayType'=>'tns:line[]'
				)
		),
		'tns:line'
);*/
$server->wsdl->addComplexType(
	'LinesArray2',
	'complexType',
	'array',
	'sequence',
	'',
	array(
				'line' => array(
						'name' => 'line',
						'type' => 'tns:line',
						'minOccurs' => '0',
						'maxOccurs' => 'unbounded'
				)
		)
);

$order_fields = array(
	'id' => array('name' => 'id', 'type' => 'xsd:string'),
	'ref' => array('name' => 'ref', 'type' => 'xsd:string'),
	'ref_client' => array('name' => 'ref_client', 'type' => 'xsd:string'),
	'ref_ext' => array('name' => 'ref_ext', 'type' => 'xsd:string'),
	'thirdparty_id' => array('name' => 'thirdparty_id', 'type' => 'xsd:int'),
	'status' => array('name' => 'status', 'type' => 'xsd:int'),
	'billed' => array('name' => 'billed', 'type' => 'xsd:string'),
	'total_net' => array('name' => 'total_net', 'type' => 'xsd:double'),
	'total_vat' => array('name' => 'total_vat', 'type' => 'xsd:double'),
	'total_localtax1' => array('name' => 'total_localtax1', 'type' => 'xsd:double'),
	'total_localtax2' => array('name' => 'total_localtax2', 'type' => 'xsd:double'),
	'total' => array('name' => 'total', 'type' => 'xsd:double'),
	'date' => array('name' => 'date', 'type' => 'xsd:date'),
	'date_creation' => array('name' => 'date_creation', 'type' => 'xsd:dateTime'),
	'date_validation' => array('name' => 'date_validation', 'type' => 'xsd:dateTime'),
	'date_modification' => array('name' => 'date_modification', 'type' => 'xsd:dateTime'),
	'source' => array('name' => 'source', 'type' => 'xsd:string'),
	'note_private' => array('name' => 'note_private', 'type' => 'xsd:string'),
	'note_public' => array('name' => 'note_public', 'type' => 'xsd:string'),
	'project_id' => array('name' => 'project_id', 'type' => 'xsd:string'),

	'mode_reglement_id' => array('name' => 'mode_reglement_id', 'type' => 'xsd:string'),
	'mode_reglement_code' => array('name' => 'mode_reglement_code', 'type' => 'xsd:string'),
	'mode_reglement' => array('name' => 'mode_reglement', 'type' => 'xsd:string'),
	'cond_reglement_id' => array('name' => 'cond_reglement_id', 'type' => 'xsd:string'),
	'cond_reglement_code' => array('name' => 'cond_reglement_code', 'type' => 'xsd:string'),
	'cond_reglement' => array('name' => 'cond_reglement', 'type' => 'xsd:string'),
	'cond_reglement_doc' => array('name' => 'cond_reglement_doc', 'type' => 'xsd:string'),

	'date_livraison' => array('name' => 'date_livraison', 'type' => 'xsd:date'),
	'demand_reason_id' => array('name' => 'demand_reason_id', 'type' => 'xsd:string'),

	'lines' => array('name' => 'lines', 'type' => 'tns:LinesArray2')
);

$elementtype = 'commande';

//Retrieve all extrafield for thirdsparty
// fetch optionals attributes and labels
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($elementtype, true);
$extrafield_array = null;
if (is_array($extrafields->attributes) && $extrafields->attributes[$elementtype]['count'] > 0) {
	$extrafield_array = array();
}
if (isset($extrafields->attributes[$elementtype]['label']) && is_array($extrafields->attributes[$elementtype]['label']) && count($extrafields->attributes[$elementtype]['label'])) {
	foreach ($extrafields->attributes[$elementtype]['label'] as $key => $label) {
		//$value=$object->array_options["options_".$key];
		$type = $extrafields->attributes[$elementtype]['type'][$key];
		if ($type == 'date' || $type == 'datetime') {
			$type = 'xsd:dateTime';
		} else {
			$type = 'xsd:string';
		}
		$extrafield_array['options_'.$key] = array('name' => 'options_'.$key, 'type' => $type);
	}
}
if (is_array($extrafield_array)) {
	$order_fields = array_merge($order_fields, $extrafield_array);
}

$server->wsdl->addComplexType(
	'order',
	'complexType',
	'struct',
	'all',
	'',
	$order_fields
);

/*
$server->wsdl->addComplexType(
		'OrdersArray',
		'complexType',
		'array',
		'',
		'SOAP-ENC:Array',
		array(),
		array(
				array(
						'ref'=>'SOAP-ENC:arrayType',
						'wsdl:arrayType'=>'tns:order[]'
				)
		),
		'tns:order'
);*/
$server->wsdl->addComplexType(
	'OrdersArray2',
	'complexType',
	'array',
	'sequence',
	'',
	array(
				'order' => array(
						'name' => 'order',
						'type' => 'tns:order',
						'minOccurs' => '0',
						'maxOccurs' => 'unbounded'
				)
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
	'getOrder',
	array('authentication' => 'tns:authentication', 'id' => 'xsd:string', 'ref' => 'xsd:string', 'ref_ext' => 'xsd:string'), // Entry values
	array('result' => 'tns:result', 'order' => 'tns:order'), // Exit values
	$ns,
	$ns.'#getOrder',
	$styledoc,
	$styleuse,
	'WS to get a particular invoice'
);

$server->register(
	'getOrdersForThirdParty',
	array('authentication' => 'tns:authentication', 'idthirdparty' => 'xsd:string'), // Entry values
	array('result' => 'tns:result', 'orders' => 'tns:OrdersArray2'), // Exit values
	$ns,
	$ns.'#getOrdersForThirdParty',
	$styledoc,
	$styleuse,
	'WS to get all orders of a third party'
);

$server->register(
	'createOrder',
	array('authentication' => 'tns:authentication', 'order' => 'tns:order'), // Entry values
	array('result' => 'tns:result', 'id' => 'xsd:string', 'ref' => 'xsd:string'), // Exit values
	$ns,
	$ns.'#createOrder',
	$styledoc,
	$styleuse,
	'WS to create an order'
);

$server->register(
	'updateOrder',
	array('authentication' => 'tns:authentication', 'order' => 'tns:order'), // Entry values
	array('result' => 'tns:result', 'id' => 'xsd:string', 'ref' => 'xsd:string', 'ref_ext' => 'xsd:string'), // Exit values
	$ns,
	$ns.'#updateOrder',
	$styledoc,
	$styleuse,
	'WS to update an order'
);

$server->register(
	'validOrder',
	array('authentication' => 'tns:authentication', 'id' => 'xsd:string', 'id_warehouse' => 'xsd:string'), // Entry values
	array('result' => 'tns:result'), // Exit values
	$ns,
	$ns.'#validOrder',
	$styledoc,
	$styleuse,
	'WS to valid an order'
);

/**
 * Get order from id, ref or ref_ext.
 *
 * @param	array{login:string,password:string,entity:?int,dolibarrkey:string}		$authentication		Array of authentication information
 * @param	int			$id					Id
 * @param	string		$ref				Ref
 * @param	string		$ref_ext			Ref_ext
 * @return array{result:array{result_code:string,result_label:string}} Array result
 */
function getOrder($authentication, $id = 0, $ref = '', $ref_ext = '')
{
	global $db, $conf;

	dol_syslog("Function: getOrder login=".$authentication['login']." id=".$id." ref=".$ref." ref_ext=".$ref_ext);

	if ($authentication['entity']) {
		$conf->entity = $authentication['entity'];
	}

	// Init and check authentication
	$objectresp = array();
	$errorcode = '';
	$errorlabel = '';
	$error = 0;
	$socid = 0;

	$fuser = check_authentication($authentication, $error, $errorcode, $errorlabel);

	if ($fuser->socid) {
		$socid = $fuser->socid;
	}

	// Check parameters
	if (!$error && (($id && $ref) || ($id && $ref_ext) || ($ref && $ref_ext))) {
		$error++;
		$errorcode = 'BAD_PARAMETERS';
		$errorlabel = "Parameter id, ref and ref_ext can't be both provided. You must choose one or other but not both.";
	}

	if (!$error) {
		$fuser->loadRights();

		if ($fuser->hasRight('commande', 'lire')) {
			$order = new Commande($db);
			$result = $order->fetch($id, $ref, $ref_ext);
			if ($result > 0) {
				// Security for external user
				if ($socid && $socid != $order->socid) {
					$error++;
					$errorcode = 'PERMISSION_DENIED';
					$errorlabel = 'User does not have permission for this request';
				}

				if (!$error) {
					$linesresp = array();
					$i = 0;
					foreach ($order->lines as $line) {
						//var_dump($line); exit;
						$linesresp[] = array(
						'id' => $line->rowid,
						'fk_commande' => $line->fk_commande,
						'fk_parent_line' => $line->fk_parent_line,
						'desc' => $line->desc,
						'qty' => $line->qty,
						'price' => $line->price,
						'unitprice' => $line->subprice,
						'vat_rate' => $line->tva_tx,
						'remise' => $line->remise,
						'remise_percent' => $line->remise_percent,
						'product_id' => $line->fk_product,
						'product_type' => $line->product_type,
						'total_net' => $line->total_ht,
						'total_vat' => $line->total_tva,
						'total' => $line->total_ttc,
						'date_start' => $line->date_start,
						'date_end' => $line->date_end,
						'product_ref' => $line->product_ref,
						'product_label' => $line->product_label,
						'product_desc' => $line->product_desc
						);
						$i++;
					}

					// Create order
					$objectresp = array(
					'result' => array('result_code' => 'OK', 'result_label' => ''),
					'order' => array(
					'id' => $order->id,
					'ref' => $order->ref,
					'ref_client' => $order->ref_client,
					'ref_ext' => $order->ref_ext,
					'thirdparty_id' => $order->socid,
					'status' => $order->statut,

					'total_net' => $order->total_ht,
					'total_vat' => $order->total_tva,
					'total_localtax1' => $order->total_localtax1,
					'total_localtax2' => $order->total_localtax2,
					'total' => $order->total_ttc,
					'project_id' => $order->fk_project,

					'date' => $order->date ? dol_print_date($order->date, 'dayrfc') : '',
					'date_creation' => $order->date_creation ? dol_print_date($order->date_creation, 'dayhourrfc') : '',
					'date_validation' => $order->date_validation ? dol_print_date($order->date_creation, 'dayhourrfc') : '',
					'date_modification' => $order->date_modification ? dol_print_date($order->date_modification, 'dayhourrfc') : '',

					'source' => $order->source,
					'billed' => $order->billed,
					'note_private' => $order->note_private,
					'note_public' => $order->note_public,
					'cond_reglement_id' => $order->cond_reglement_id,
					'cond_reglement_code' => $order->cond_reglement_code,
					'cond_reglement' => $order->cond_reglement,
					'mode_reglement_id' => $order->mode_reglement_id,
					'mode_reglement_code' => $order->mode_reglement_code,
					'mode_reglement' => $order->mode_reglement,

					'date_livraison' => $order->delivery_date,

					'demand_reason_id' => $order->demand_reason_id,
					'demand_reason_code' => $order->demand_reason_code,

					'lines' => $linesresp
					));
				}
			} else {
				$error++;
				$errorcode = 'NOT_FOUND';
				$errorlabel = 'Object not found for id='.$id.' nor ref='.$ref.' nor ref_ext='.$ref_ext;
			}
		} else {
			$error++;
			$errorcode = 'PERMISSION_DENIED';
			$errorlabel = 'User does not have permission for this request';
		}
	}

	if ($error) {
		$objectresp = array('result' => array('result_code' => $errorcode, 'result_label' => $errorlabel));
	}

	return $objectresp;
}


/**
 * Get list of orders for third party
 *
 * @param	array{login:string,password:string,entity:?int,dolibarrkey:string}		$authentication		Array of authentication information
 * @param	int			$idthirdparty		Id of thirdparty
 * @return array{result:array{result_code:string,result_label:string}} Array result
 */
function getOrdersForThirdParty($authentication, $idthirdparty)
{
	global $db, $conf;

	dol_syslog("Function: getOrdersForThirdParty login=".$authentication['login']." idthirdparty=".$idthirdparty);

	if ($authentication['entity']) {
		$conf->entity = $authentication['entity'];
	}

	// Init and check authentication
	$objectresp = array();
	$errorcode = '';
	$errorlabel = '';
	$error = 0;
	$fuser = check_authentication($authentication, $error, $errorcode, $errorlabel);

	if ($fuser->socid) {
		$socid = $fuser->socid;
	} else {
		$socid = 0;
	}

	// Check parameters
	if (!$error && empty($idthirdparty)) {
		$error++;
		$errorcode = 'BAD_PARAMETERS';
		$errorlabel = 'Parameter id is not provided';
	}

	if (!$error) {
		$linesorders = array();

		$sql = 'SELECT c.rowid as orderid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'commande as c';
		$sql .= " WHERE c.entity = ".$conf->entity;
		if ($idthirdparty != 'all') {
			$sql .= " AND c.fk_soc = ".((int) $idthirdparty);
		}


		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				// En attendant remplissage par boucle
				$obj = $db->fetch_object($resql);

				$order = new Commande($db);
				$order->fetch($obj->orderid);

				// Sécurité pour utilisateur externe
				if ($socid && ($socid != $order->socid)) {
					$error++;
					$errorcode = 'PERMISSION_DENIED';
					$errorlabel = $order->socid.' User does not have permission for this request';
				}

				if (!$error) {
					// Define lines of invoice
					$linesresp = array();
					foreach ($order->lines as $line) {
						$linesresp[] = array(
						'id' => $line->rowid,
						'type' => $line->product_type,
						'fk_commande' => $line->fk_commande,
						'fk_parent_line' => $line->fk_parent_line,
						'desc' => $line->desc,
						'qty' => $line->qty,
						'price' => $line->price,
						'unitprice' => $line->subprice,
						'tva_tx' => $line->tva_tx,
						'remise' => $line->remise,
						'remise_percent' => $line->remise_percent,
						'total_net' => $line->total_ht,
						'total_vat' => $line->total_tva,
						'total' => $line->total_ttc,
						'date_start' => $line->date_start,
						'date_end' => $line->date_end,
						'product_id' => $line->fk_product,
						'product_ref' => $line->product_ref,
						'product_label' => $line->product_label,
						'product_desc' => $line->product_desc
						);
					}

					// Now define invoice
					$linesorders[] = array(
					'id' => $order->id,
					'ref' => $order->ref,
					'ref_client' => $order->ref_client,
					'ref_ext' => $order->ref_ext,
					'socid' => $order->socid,
					'status' => $order->statut,

					'total_net' => $order->total_ht,
					'total_vat' => $order->total_tva,
					'total_localtax1' => $order->total_localtax1,
					'total_localtax2' => $order->total_localtax2,
					'total' => $order->total_ttc,
					'project_id' => $order->fk_project,

					'date' => $order->date_commande ? dol_print_date($order->date_commande, 'dayrfc') : '',

					'source' => $order->source,
					'billed' => $order->billed,
					'note_private' => $order->note_private,
					'note_public' => $order->note_public,
					'cond_reglement_id' => $order->cond_reglement_id,
					'cond_reglement' => $order->cond_reglement,
					'cond_reglement_doc' => $order->cond_reglement_doc,
					'cond_reglement_code' => $order->cond_reglement_code,
					'mode_reglement_id' => $order->mode_reglement_id,
					'mode_reglement' => $order->mode_reglement,
					'mode_reglement_code' => $order->mode_reglement_code,

					'date_livraison' => $order->delivery_date,

					'demand_reason_id' => $order->demand_reason_id,
					'demand_reason_code' => $order->demand_reason_code,

					'lines' => $linesresp
					);
				}
				$i++;
			}

			$objectresp = array(
			'result' => array('result_code' => 'OK', 'result_label' => ''),
			'orders' => $linesorders

			);
		} else {
			$error++;
			$errorcode = $db->lasterrno();
			$errorlabel = $db->lasterror();
		}
	}

	if ($error) {
		$objectresp = array('result' => array('result_code' => $errorcode, 'result_label' => $errorlabel));
	}

	return $objectresp;
}


/**
 * Create order
 *
 * @param	array{login:string,password:string,entity:?int,dolibarrkey:string}		$authentication		Array of authentication information
 * @param array{id:string,ref:string,ref_client:string,ref_ext:string,thirdparty_id:int,status:int,billed:string,total_net:float,total_vat:float,total_localtax1:float,total_localtax2:float,total:float,date:string,date_creation:string,date_validation:string,date_modification:string,source:string,note_private:string,note_public:string,project_id:string,mode_reglement_id:string,mode_reglement_code:string,mode_reglement:string,cond_reglement_id:string,cond_reglement_code:string,cond_reglement:string,cond_reglement_doc:string,date_livraison:int,demand_reason_id:string,lines:array{lines:array<array{id:string,type:int,fk_commande:int,fk_parent_line:int,desc:string,qty:float,price:float,unitprice:float,vat_rate:float,remise:float,remise_percent:float,total_net:float,total_vat:float,total:float,date_start:int,date_end:int,product_id:int,product_ref:string,product_label:string,product_desc:string}>}}		$order		Order info
 * @return array{result:array{result_code:string,result_label:string}} Array result
 */
function createOrder($authentication, $order)
{
	global $db, $conf, $langs;

	include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

	$now = dol_now();

	// TODO: socid is not defined in '$order_fields' above used to define the web interface - verify.
	dol_syslog("Function: createOrder login=".$authentication['login']." socid :".$order['socid']);

	if ($authentication['entity']) {
		$conf->entity = $authentication['entity'];
	}

	// Init and check authentication
	$objectresp = array();
	$errorcode = '';
	$errorlabel = '';
	$error = 0;
	$fuser = check_authentication($authentication, $error, $errorcode, $errorlabel);
	$newobject = null;

	// Check parameters


	if (!$error) {
		$newobject = new Commande($db);
		$newobject->socid = $order['thirdparty_id'];
		// TODO: 'type' is not defined in the $order_fields for the WS - verify
		$newobject->type = $order['type'];
		$newobject->ref_ext = $order['ref_ext'];
		$newobject->date = dol_stringtotime($order['date'], 'dayrfc');
		// TODO: 'date_due' is not defined in the $order_fields for the WS - verify
		$newobject->date_lim_reglement = dol_stringtotime((string) $order['date_due'], 'dayrfc');
		$newobject->note_private = $order['note_private'];
		$newobject->note_public = $order['note_public'];
		$newobject->statut = Commande::STATUS_DRAFT; // We start with status draft
		$newobject->billed = (int) $order['billed'];
		$newobject->fk_project = (int) $order['project_id'];
		$newobject->cond_reglement_id = (int) $order['cond_reglement_id'];
		$newobject->demand_reason_id = (int) $order['demand_reason_id'];
		$newobject->date_creation = $now;

		$elementtype = 'commande';

		// Retrieve all extrafield for order
		// fetch optionals attributes and labels
		$extrafields = new ExtraFields($db);
		$extrafields->fetch_name_optionals_label($elementtype, true);
		if (isset($extrafields->attributes[$elementtype]['label']) && is_array($extrafields->attributes[$elementtype]['label']) && count($extrafields->attributes[$elementtype]['label'])) {
			foreach ($extrafields->attributes[$elementtype]['label'] as $key => $label) {
				$key = 'options_'.$key;
				$newobject->array_options[$key] = $order[$key];
			}
		}

		// Trick because nusoap does not store data with same structure if there is one or several lines
		$arrayoflines = array();
		if (isset($order['lines']['line'][0])) {
			$arrayoflines = $order['lines']['line'];  // @phan-suppress-current-line PhanTypeInvalidDimOffset
		} else {
			$arrayoflines = $order['lines'];
		}
		if (!is_array($arrayoflines)) {
			$arrayoflines = array();
		}

		foreach ($arrayoflines as $key => $line) {
			// $key can be 'line' or '0','1',...
			$newline = new OrderLine($db);

			$newline->type = $line['type'];
			$newline->desc = $line['desc'];
			$newline->fk_product = $line['product_id'];
			$newline->tva_tx = $line['vat_rate'];
			$newline->qty = $line['qty'];
			$newline->price = $line['price'];
			$newline->subprice = $line['unitprice'];
			$newline->total_ht = $line['total_net'];
			$newline->total_tva = $line['total_vat'];
			$newline->total_ttc = $line['total'];
			$newline->date_start = $line['date_start'];
			$newline->date_end = $line['date_end'];

			$elementtype = 'commandedet';

			// Retrieve all extrafield for lines
			// fetch optionals attributes and labels
			$extrafields = new ExtraFields($db);
			$extrafields->fetch_name_optionals_label($elementtype, true);
			if (isset($extrafields->attributes[$elementtype]['label']) && is_array($extrafields->attributes[$elementtype]['label']) && count($extrafields->attributes[$elementtype]['label'])) {
				foreach ($extrafields->attributes[$elementtype]['label'] as $tmpkey => $tmplabel) {
					$tmpkey = 'options_'.$tmpkey;
					$newline->array_options[$tmpkey] = $line[$tmpkey];
				}
			}

			$newobject->lines[] = $newline;
		}


		$db->begin();
		dol_syslog("Webservice server_order:: order creation start", LOG_DEBUG);
		$result = $newobject->create($fuser);
		dol_syslog('Webservice server_order:: order creation done with $result='.$result, LOG_DEBUG);
		if ($result < 0) {
			dol_syslog("Webservice server_order:: order creation failed", LOG_ERR);
			$error++;
		}

		if ($order['status'] == 1) {   // We want order to have status validated
			dol_syslog("Webservice server_order:: order validation start", LOG_DEBUG);
			$result = $newobject->valid($fuser);
			if ($result < 0) {
				dol_syslog("Webservice server_order:: order validation failed", LOG_ERR);
				$error++;
			}
		}

		if ($result >= 0) {
			dol_syslog("Webservice server_order:: order creation & validation succeeded, commit", LOG_DEBUG);
			$db->commit();
			$objectresp = array('result' => array('result_code' => 'OK', 'result_label' => ''), 'id' => $newobject->id, 'ref' => $newobject->ref);
		} else {
			dol_syslog("Webservice server_order:: order creation or validation failed, rollback", LOG_ERR);
			$db->rollback();
			$error++;
			$errorcode = 'KO';
			$errorlabel = $newobject->error;
		}
	}

	if ($error) {
		$objectresp = array('result' => array('result_code' => $errorcode, 'result_label' => $errorlabel));
	}

	return $objectresp;
}


/**
 * Valid an order
 *
 * @param	array{login:string,password:string,entity:?int,dolibarrkey:string}		$authentication		Array of authentication information
 * @param	int			$id					Id of order to validate
 * @param	int			$id_warehouse		Id of warehouse to use for stock decrease
 * @return array{result:array{result_code:string,result_label:string}} Array result
 */
function validOrder($authentication, $id = 0, $id_warehouse = 0)
{
	global $db, $conf, $langs;

	dol_syslog("Function: validOrder login=".$authentication['login']." id=".$id." id_warehouse=".$id_warehouse);

	// Init and check authentication
	$objectresp = array();
	$errorcode = '';
	$errorlabel = '';
	$error = 0;
	if ($authentication['entity']) {
		$conf->entity = $authentication['entity'];
	}
	$fuser = check_authentication($authentication, $error, $errorcode, $errorlabel);

	if (!$error) {
		$fuser->loadRights();

		if ($fuser->hasRight('commande', 'lire')) {
			$order = new Commande($db);
			$result = $order->fetch($id);

			$order->fetch_thirdparty();
			$db->begin();
			if ($result > 0) {
				$result = $order->valid($fuser, $id_warehouse);

				if ($result >= 0) {
					// Define output language
					$outputlangs = $langs;
					$order->generateDocument($order->model_pdf, $outputlangs);
				} else {
					$db->rollback();
					$error++;
					$errorcode = 'KO';
					$errorlabel = $order->error;
				}
			} else {
				$db->rollback();
				$error++;
				$errorcode = 'KO';
				$errorlabel = $order->error;
			}
		} else {
			$db->rollback();
			$error++;
			$errorcode = 'KO';
			$errorlabel = 'Bad permission';
		}
	}

	if ($error) {
		$objectresp = array('result' => array('result_code' => $errorcode, 'result_label' => $errorlabel));
	} else {
		$db->commit();
		$objectresp = array('result' => array('result_code' => 'OK', 'result_label' => ''));
	}

	return $objectresp;
}

/**
 * Update an order
 *
 * @param	array{login:string,password:string,entity:?int,dolibarrkey:string}		$authentication		Array of authentication information
 * @param	array{id:string,ref:string,refext:string}	$order	Order info
 * @return array{result:array{result_code:string,result_label:string}} Array result
 */
function updateOrder($authentication, $order)
{
	global $db, $conf, $langs;

	dol_syslog("Function: updateOrder login=".$authentication['login']);

	if ($authentication['entity']) {
		$conf->entity = $authentication['entity'];
	}

	// Init and check authentication
	$objectresp = array();
	$errorcode = '';
	$errorlabel = '';
	$error = 0;
	$fuser = check_authentication($authentication, $error, $errorcode, $errorlabel);
	// Check parameters
	if (empty($order['id']) && empty($order['ref']) && empty($order['ref_ext'])) {
		$error++;
		$errorcode = 'KO';
		$errorlabel = "Order id or ref or ref_ext is mandatory.";
	}

	if (!$error) {
		$objectfound = false;

		include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

		$object = new Commande($db);
		$result = $object->fetch((int) $order['id'], (empty($order['id']) ? $order['ref'] : ''), (empty($order['id']) && empty($order['ref']) ? $order['ref_ext'] : ''));

		if (!empty($object->id)) {
			$objectfound = true;

			$db->begin();

			if (isset($order['status'])) {
				if ($order['status'] == -1) {
					$result = $object->cancel($fuser);
				}
				if ($order['status'] == 1) {
					$result = $object->valid($fuser);
					if ($result >= 0) {
						// Define output language
						$outputlangs = $langs;
						$object->generateDocument($object->model_pdf, $outputlangs);
					}
				}
				if ($order['status'] == 0) {
					$result = $object->set_reopen($fuser);
				}
				if ($order['status'] == 3) {
					$result = $object->cloture($fuser);
				}
			}

			if (isset($order['billed'])) {
				if ($order['billed']) {
					$result = $object->classifyBilled($fuser);
				}
				if (!$order['billed']) {
					$result = $object->classifyUnBilled($fuser);
				}
			}

			$elementtype = 'commande';

			//Retrieve all extrafield for object
			// fetch optionals attributes and labels
			$extrafields = new ExtraFields($db);
			$extrafields->fetch_name_optionals_label($elementtype, true);
			if (isset($extrafields->attributes[$elementtype]['label']) && is_array($extrafields->attributes[$elementtype]['label']) && count($extrafields->attributes[$elementtype]['label'])) {
				foreach ($extrafields->attributes[$elementtype]['label'] as $key => $label) {
					$key = 'options_'.$key;
					if (isset($order[$key])) {
						$result = $object->setValueFrom($key, $order[$key], 'commande_extrafields');
					}
				}
			}

			if ($result <= 0) {
				$error++;
			}
		}

		if ((!$error) && ($objectfound)) {
			$db->commit();
			$objectresp = array(
					'result' => array('result_code' => 'OK', 'result_label' => ''),
					'id' => $object->id,
					'ref' => $object->ref,
					'ref_ext' => $object->ref_ext
			);
		} elseif ($objectfound) {
			$db->rollback();
			$error++;
			$errorcode = 'KO';
			$errorlabel = $object->error;
		} else {
			$error++;
			$errorcode = 'NOT_FOUND';
			$errorlabel = 'Order id='.$order['id'].' ref='.$order['ref'].' ref_ext='.$order['ref_ext'].' cannot be found';
		}
	}

	if ($error) {
		$objectresp = array('result' => array('result_code' => $errorcode, 'result_label' => $errorlabel));
	}

	return $objectresp;
}


// Return the results.
$server->service(file_get_contents("php://input"));
