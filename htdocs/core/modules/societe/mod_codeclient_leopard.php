<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2006-2014 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2024		Frédéric France			<frederic.france@free.fr>
 * Copyright (C) 2024-2025	MDW						<mdeweerd@users.noreply.github.com>
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
 *       \file       htdocs/core/modules/societe/mod_codeclient_leopard.php
 *       \ingroup    societe
 *       \brief      Fichier de la class des gestion leopard des codes clients
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/societe/modules_societe.class.php';


/**
 *	Class to manage numbering of thirdparties code
 */
class mod_codeclient_leopard extends ModeleThirdPartyCode
{
	/*
	 * Attention ce module est utilise par default si aucun module n'a
	 * ete definite dans la configuration
	 *
	 * Le fonctionnement de celui-ci doit donc rester le plus ouvert possible
	 */

	// variables inherited from ModeleThirdPartyCode class
	public $name = 'Leopard';
	public $version = 'dolibarr';


	/**
	 *	Constructor
	 *
	 *	@param DoliDB		$db		Database object
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->code_null = 1;
		$this->code_modifiable = 1;
		$this->code_modifiable_invalide = 1;
		$this->code_modifiable_null = 1;
		$this->code_auto = 0;
	}


	/**
	 *  Return description of module
	 *
	 *  @param  Translate   $langs  Object langs
	 *  @return string              Description of module
	 */
	public function info($langs)
	{
		$langs->load("companies");
		return $langs->trans("LeopardNumRefModelDesc");
	}

	/**
	 * Return an example of result returned by getNextValue
	 *
	 * @param	?Translate		$langs		Object langs
	 * @param	Societe|string	$objsoc		Object thirdparty
	 * @param	int<-1,2>		$type		Type of third party (1:customer, 2:supplier, -1:autodetect)
	 * @return	string						Return string example
	 */
	public function getExample($langs = null, $objsoc = '', $type = -1)
	{
		return '';
	}

	/**
	 * Return an example of result returned by getNextValue
	 *
	 * @param	Societe|string|null	$objsoc	Object third party
	 * @param	int<-1,2>			$type	Type of third party (1:customer, 2:supplier, -1:autodetect)
	 * @return	string						Return next value
	 */
	public function getNextValue($objsoc = '', $type = -1)
	{
		return '';
	}


	/**
	 * 	Check validity of code according to its rules
	 *
	 *	@param	DoliDB		$db		Database handler
	 *	@param	string		$code	Code to check/correct
	 *	@param	Societe		$soc	Object third party
	 *  @param  int<0,1>  	$type   0 = customer/prospect , 1 = supplier
	 *  @return int<-6,0>			0 if OK
	 * 								-1 ErrorBadCustomerCodeSyntax
	 * 								-2 ErrorCustomerCodeRequired
	 * 								-3 ErrorCustomerCodeAlreadyUsed
	 * 								-4 ErrorPrefixRequired
	 * 								-5 NotConfigured - Setup empty so any value may be ok or not
	 * 								-6 Other (see this->error)
	 */
	public function verif($db, &$code, $soc, $type)
	{
		$result = 0;
		$code = trim($code);

		if (empty($code) && $this->code_null && !getDolGlobalString('MAIN_COMPANY_CODE_ALWAYS_REQUIRED')) {
			$result = 0;
		} elseif (empty($code) && (!$this->code_null || getDolGlobalString('MAIN_COMPANY_CODE_ALWAYS_REQUIRED'))) {
			$result = -2;
		}

		dol_syslog(get_class($this)."::verif type=".$type." result=".$result);
		return $result;
	}
}
