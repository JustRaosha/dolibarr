<?php
/* Copyright (C) 2003-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Eric Seigne          <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2016      Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2024-2025	MDW					<mdeweerd@users.noreply.github.com>
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
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/cheque/modules_chequereceipts.php
 *	\ingroup    invoice
 *	\brief      File with parent class of check receipt document generators
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php'; // Requis car utilise dans les classes qui heritent

/**
 *  Class parent for cheque Receipts numbering references mother class
 */
abstract class ModeleNumRefChequeReceipts extends CommonNumRefGenerator
{
	// No overload code
	/**
	 * 	Return next free value
	 *
	 *  @param	Societe			$objsoc		Object thirdparty
	 *  @param	RemiseCheque	$object		Object we need next value for
	 *  @return	string|int<-1,0>			Next value if OK, 0 if KO
	 */
	abstract public function getNextValue($objsoc, $object);

	/**
	 *  Return an example of numbering
	 *
	 *  @return     string      Example
	 */
	abstract public function getExample();
}

/**
 *	Class parent for templates of document generation
 */
abstract class ModeleChequeReceipts extends CommonDocGenerator
{
	/**
	 * @var Account bank account
	 */
	public $account;

	/**
	 * @var string|float
	 */
	public $amount;
	/**
	 * @var string
	 */
	public $date;
	/**
	 * @var int
	 */
	public $nbcheque;
	/**
	 * @var string
	 */
	public $ref;
	/**
	 * @var stdClass[] lines
	 */
	public $lines;
	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return list of active generation modules
	 *
	 *  @param  DoliDB  	$db                 Database handler
	 *  @param  int<0,max>	$maxfilenamelength  Max length of value to show
	 *  @return string[]|int<-1,0>				List of templates
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		// phpcs:enable
		$type = 'chequereceipt';
		$list = array();

		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		$list = getListOfModels($db, $type, $maxfilenamelength);
		// TODO Remove this to use getListOfModels only
		$list = array('blochet' => 'blochet');

		return $list;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Fonction to generate document on disk
	 *
	 *	@param	RemiseCheque	$object			Object RemiseCheque
	 *	@param	string			$_dir			Directory
	 *	@param	string			$number			Number
	 *	@param	Translate		$outputlangs	Lang output object
	 *	@return	int<-1,1>  						1=ok, 0=ko
	 */
	abstract public function write_file($object, $_dir, $number, $outputlangs);
	// phpcs:enable
}


/**
 *  Cree un bordereau remise de cheque
 *
 * 	@param	DoliDB		$db				Database handler
 *	@param	int			$id				Object invoice (or id of invoice)
 *	@param	string		$message		Message
 *	@param	string		$modele			Force le modele a utiliser ('' to not force)
 *	@param	Translate	$outputlangs	Object lang a utiliser pour traduction
 *	@return int        					Return integer <0 if KO, >0 if OK
 * 	TODO Use commonDocGenerator
 */
function chequereceipt_pdf_create($db, $id, $message, $modele, $outputlangs)
{
	global $conf, $langs;
	$langs->load("bills");

	$dir = DOL_DOCUMENT_ROOT."/core/modules/cheque/doc/";

	// Positionne modele sur le nom du modele a utiliser
	if (!dol_strlen($modele)) {
		if (getDolGlobalString('CHEQUERECEIPT_ADDON_PDF')) {
			$modele = getDolGlobalString('CHEQUERECEIPT_ADDON_PDF');
		} else {
			//print $langs->trans("Error")." ".$langs->trans("Error_FACTURE_ADDON_PDF_NotDefined");
			//return 0;
			$modele = 'blochet';
		}
	}

	// Charge le modele
	$file = "pdf_".$modele.".modules.php";
	if (file_exists($dir.$file)) {
		$classname = "pdf_".$modele;
		require_once $dir.$file;

		$obj = new $classname($db);
		'@phan-var-force ModeleChequeReceipts $obj';

		// We save charset_output to restore it because write_file can change it if needed for
		// output format that does not support UTF8.
		$sav_charset_output = $outputlangs->charset_output;
		// TODO: write_file seems invalid, function is likely no longer used - delete ?
		if ($obj->write_file($id, $outputlangs) > 0) { // @phan-suppress-current-line PhanParamTooFew,PhanPluginSuspiciousParamPosition
			$outputlangs->charset_output = $sav_charset_output;
			return 1;
		} else {
			$outputlangs->charset_output = $sav_charset_output;
			dol_print_error($db, "chequereceipt_pdf_create Error: ".$obj->error);
			return -1;
		}
	} else {
		dol_print_error(null, $langs->trans("Error")." ".$langs->trans("ErrorFileDoesNotExists", $dir.$file));
		return -1;
	}
}
