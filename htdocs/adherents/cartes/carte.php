<?php
/* Copyright (C) 2003		Rodolphe Quiedeville		<rodolphe@quiedeville.org>
 * Copyright (C) 2003		Jean-Louis Bergamo			<jlb@j1b.org>
 * Copyright (C) 2006-2013	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2024		Alexandre Spangaro			<alexandre@inovea-conseil.com>
 * Copyright (C) 2024-2025	MDW							<mdeweerd@users.noreply.github.com>
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

/**
 *	\file 		htdocs/adherents/cartes/carte.php
 *	\ingroup    member
 *	\brief      Page to output members business cards
 */
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/format_cards.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/member/modules_cards.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/printsheet/modules_labels.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 *
 * @var array<string,array{name:string,paper-size:string|array{0:float,1:float},orientation:string,metric:string,marginLeft:float,marginTop:float,NX:int,NY:int,SpaceX:float,SpaceY:float,width:float,height:float,font-size:int,custom_x:float,custom_y:float}> $_Avery_Labels
 */

$langs->loadLangs(array("members", "errors"));

// Choice of printing year or current year.
$now = dol_now();
$year = dol_print_date($now, '%Y');
$month = dol_print_date($now, '%m');
$day = dol_print_date($now, '%d');
$foruserid = GETPOST('foruserid', 'alphanohtml');
$foruserlogin = GETPOST('foruserlogin', 'alphanohtml');
$mode = GETPOST('mode', 'aZ09');
$modelcard = GETPOST("modelcard", 'aZ09'); // Doc template to use for business cards
$model = GETPOST("model", 'aZ09'); // Doc template to use for business cards
$modellabel = GETPOST("modellabel", 'aZ09'); // Doc template to use for address sheet
$mesg = '';

$adherentstatic = new Adherent($db);
$object = new Adherent($db);

$extrafields = new ExtraFields($db);
// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

// Security check
$result = restrictedArea($user, 'adherent');


/*
 * Actions
 */

if ($mode == 'cardlogin' && empty($foruserlogin)) {
	$mesg = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Login"));
}

if ((!empty($foruserid) || !empty($foruserlogin) || !empty($mode)) && !$mesg) {
	$arrayofmembers = array();

	// request taking into account member with up to date subscriptions
	$sql = "SELECT d.rowid, d.ref, d.civility, d.firstname, d.lastname, d.login, d.societe as company, d.datefin,";
	$sql .= " d.address, d.zip, d.town, d.country, d.birth, d.email, d.photo,";
	$sql .= " t.libelle as type,";
	$sql .= " c.code as country_code, c.label as country";
	// Add fields from extrafields
	if (!empty($extrafields->attributes[$object->table_element]['label'])) {
		foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
			$sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? ", ef.".$key." as options_".$key : '');
		}
	}
	$sql .= " FROM ".MAIN_DB_PREFIX."adherent_type as t, ".MAIN_DB_PREFIX."adherent as d";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_country as c ON d.country = c.rowid";
	if (isset($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) {
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."adherent_extrafields as ef on (d.rowid = ef.fk_object)";
	}
	$sql .= " WHERE d.fk_adherent_type = t.rowid AND d.statut = 1";
	$sql .= " AND d.entity IN (".getEntity('adherent').")";
	if (is_numeric($foruserid)) {
		$sql .= " AND d.rowid = ".(int) $foruserid;
	}
	if ($foruserlogin) {
		$sql .= " AND d.login = '".$db->escape($foruserlogin)."'";
	}
	$sql .= " ORDER BY d.rowid ASC";

	dol_syslog("Search members", LOG_DEBUG);
	$result = $db->query($sql);
	if ($result) {
		$num = $db->num_rows($result);
		$i = 0;
		while ($i < $num) {
			$objp = $db->fetch_object($result);

			if ($objp->country == '-') {
				$objp->country = '';
			}

			$adherentstatic->id = $objp->rowid;
			$adherentstatic->ref = $objp->ref;
			$adherentstatic->lastname = $objp->lastname;
			$adherentstatic->firstname = $objp->firstname;

			// Format extrafield so they can be parsed in function complete_substitutions_array
			if (isset($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) {
				$adherentstatic->array_options = array();
				foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
					$tmpkey = 'options_'.$key;
					if (!empty($objp->$tmpkey)) {
						$adherentstatic->array_options[$tmpkey] = $objp->$tmpkey;
					}
					//if (!empty($objp->$key))
					//    $objp->array_options[$tmpkey] = $objp->$key;
					//$objp->array_options[$tmpkey] = $extrafields->showOutputField($key, $objp->$tmpkey, '', $object->table_element); //$objp->$tmpkey;
				}
			}

			// List of values to scan for a replacement
			$substitutionarray = array(
				'__MEMBER_ID__' => $objp->rowid,
				'__MEMBER_REF__' => $objp->ref,
				'__MEMBER_LOGIN__' => empty($objp->login) ? '' : $objp->login,
				'__MEMBER_TITLE__' => empty($objp->civility) ? '' : $langs->trans("Civility".$objp->civility),
				'__MEMBER_FIRSTNAME__' => empty($objp->firstname) ? '' : $objp->firstname,
				'__MEMBER_LASTNAME__' => empty($objp->lastname) ? '' : $objp->lastname,
				'__MEMBER_FULLNAME__' => $adherentstatic->getFullName($langs),
				'__MEMBER_COMPANY__' => empty($objp->company) ? '' : $objp->company,
				'__MEMBER_ADDRESS__' => empty($objp->address) ? '' : $objp->address,
				'__MEMBER_ZIP__' => empty($objp->zip) ? '' : $objp->zip,
				'__MEMBER_TOWN__' => empty($objp->town) ? '' : $objp->town,
				'__MEMBER_COUNTRY__' => empty($objp->country) ? '' : $objp->country,
				'__MEMBER_COUNTRY_CODE__' => empty($objp->country_code) ? '' : $objp->country_code,
				'__MEMBER_EMAIL__' => empty($objp->email) ? '' : $objp->email,
				'__MEMBER_BIRTH__' => dol_print_date($objp->birth, 'day'),
				'__MEMBER_TYPE__' => empty($objp->type) ? '' : $objp->type,
				'__YEAR__' => $year,
				'__MONTH__' => $month,
				'__DAY__' => $day,
				'__DOL_MAIN_URL_ROOT__' => DOL_MAIN_URL_ROOT,
				'__SERVER__' => "https://".$_SERVER["SERVER_NAME"]."/"
			);
			foreach ($adherentstatic->array_options as $key => $val) {
				$substitutionarray['__'.strtoupper($key).'__'] = $val;
			}
			complete_substitutions_array($substitutionarray, $langs, $adherentstatic);

			// For business cards
			if (empty($mode) || $mode == 'card' || $mode == 'cardlogin') {
				$textleft = make_substitutions(getDolGlobalString('ADHERENT_CARD_TEXT'), $substitutionarray);
				$textheader = make_substitutions(getDolGlobalString('ADHERENT_CARD_HEADER_TEXT'), $substitutionarray);
				$textfooter = make_substitutions(getDolGlobalString('ADHERENT_CARD_FOOTER_TEXT'), $substitutionarray);
				$textright = make_substitutions(getDolGlobalString('ADHERENT_CARD_TEXT_RIGHT'), $substitutionarray);

				if (is_numeric($foruserid) || $foruserlogin) {
					$nb = $_Avery_Labels[$model]['NX'] * $_Avery_Labels[$model]['NY'];	// $_Avery_Labels is defined into an include
					if ($nb <= 0) {
						$nb = 1; // Protection to avoid empty page
					}

					for ($j = 0; $j < $nb; $j++) {
						$arrayofmembers[] = array(
							'textleft' => $textleft,
							'textheader' => $textheader,
							'textfooter' => $textfooter,
							'textright' => $textright,
							'id' => $objp->rowid,
							'ref' => $objp->ref,
							'photo' => $objp->photo
						);
					}
				} else {
					$arrayofmembers[] = array(
						'textleft' => $textleft,
						'textheader' => $textheader,
						'textfooter' => $textfooter,
						'textright' => $textright,
						'id' => $objp->rowid,
						'ref' => $objp->ref,
						'photo' => $objp->photo
					);
				}
			}

			// For labels
			if ($mode == 'label') {
				if (!getDolGlobalString('ADHERENT_ETIQUETTE_TEXT')) {
					$conf->global->ADHERENT_ETIQUETTE_TEXT = "__MEMBER_TITLE__\n__MEMBER_FULLNAME__\n__MEMBER_ADDRESS__\n__MEMBER_ZIP__ __MEMBER_TOWN__\n__MEMBER_COUNTRY__";
				}
				$textleft = make_substitutions(getDolGlobalString('ADHERENT_ETIQUETTE_TEXT'), $substitutionarray);
				$textheader = '';
				$textfooter = '';
				$textright = '';

				$arrayofmembers[] = array(
					'textleft' => $textleft,
					'textheader' => $textheader,
					'textfooter' => $textfooter,
					'textright' => $textright,
					'id' => $objp->rowid,
					'ref' => $objp->ref,
					'photo' => $objp->photo,
				);
			}

			$i++;
		}

		// Build and output PDF
		$outputlangs = $langs;

		if (empty($mode) || $mode == 'card') {
			if (!count($arrayofmembers)) {
				$mesg = $langs->trans("ErrorRecordNotFound");
			}
			if (empty($modelcard) || $modelcard == '-1') {
				$mesg = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("DescADHERENT_CARD_TYPE"));
			}
			if (!$mesg) {
				$result = members_card_pdf_create($db, $arrayofmembers, $modelcard, $outputlangs, '', 'standard', 'tmp_cards');
			}
		} elseif ($mode == 'cardlogin') {
			if (!count($arrayofmembers)) {
				$mesg = $langs->trans("ErrorRecordNotFound");
			}
			if (empty($model) || $model == '-1') {
				$mesg = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("DescADHERENT_CARD_TYPE"));
			}
			if (!$mesg) {
				$result = members_card_pdf_create($db, $arrayofmembers, $model, $outputlangs, '', 'standard', 'tmp_cards_login');
			}
		} elseif ($mode == 'label') {
			if (!count($arrayofmembers)) {
				$mesg = $langs->trans("ErrorRecordNotFound");
			}
			if (empty($modellabel) || $modellabel == '-1') {
				$mesg = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("DescADHERENT_ETIQUETTE_TYPE"));
			}
			if (!$mesg) {
				$result = doc_label_pdf_create($db, $arrayofmembers, $modellabel, $outputlangs);
			}
		}

		if ($result <= 0) {
			dol_print_error(null, $mesg);
		}
	} else {
		dol_print_error($db);
	}

	if (!$mesg) {
		$db->close();
		exit;
	}
}


/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('MembersCards');
$help_url = 'EN:Module_Services_En|FR:Module_Services|ES:M&oacute;dulo_Servicios|DE:Modul_Mitglieder';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-member page-cards');

print load_fiche_titre($langs->trans("LinkToGeneratedPages"), '', $adherentstatic->picto);

print '<span class="opacitymedium">'.$langs->trans("LinkToGeneratedPagesDesc").'</span><br>';
print '<br>';

dol_htmloutput_errors($mesg);

print '<br>';

print img_picto('', 'card').' '.$langs->trans("DocForAllMembersCards", getDolGlobalString('ADHERENT_CARD_TYPE', $langs->transnoentitiesnoconv("None"))).' ';
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="foruserid" value="all">';
print '<input type="hidden" name="mode" value="card">';
print '<input type="hidden" name="action" value="builddoc">';
print $langs->trans("DescADHERENT_CARD_TYPE").' ';
// List of possible labels (defined into $_Avery_Labels variable set into format_cards.lib.php)
$arrayoflabels = array();
foreach (array_keys($_Avery_Labels) as $codecards) {
	$arrayoflabels[$codecards] = $_Avery_Labels[$codecards]['name'];
}
asort($arrayoflabels);
print $form->selectarray('modelcard', $arrayoflabels, (GETPOST('modelcard') ? GETPOST('modelcard') : getDolGlobalString('ADHERENT_CARD_TYPE')), 1, 0, 0, '', 0, 0, 0, '', '', 1);
print '<br><input type="submit" class="button small" value="'.$langs->trans("BuildDoc").'">';
print '</form>';

print '<br><br>';

print img_picto('', 'card').' '.$langs->trans("DocForOneMemberCards", getDolGlobalString('ADHERENT_CARD_TYPE', $langs->transnoentitiesnoconv("None"))).' ';
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="mode" value="cardlogin">';
print '<input type="hidden" name="action" value="builddoc">';
print $langs->trans("DescADHERENT_CARD_TYPE").' ';
// List of possible labels (defined into $_Avery_Labels variable set into format_cards.lib.php)
$arrayoflabels = array();
foreach (array_keys($_Avery_Labels) as $codecards) {
	$arrayoflabels[$codecards] = $_Avery_Labels[$codecards]['name'];
}
asort($arrayoflabels);
print $form->selectarray('model', $arrayoflabels, (GETPOST('model') ? GETPOST('model') : getDolGlobalString('ADHERENT_CARD_TYPE')), 1, 0, 0, '', 0, 0, 0, '', '', 1);
print '<br>'.$langs->trans("Login").': <input class="width100" type="text" name="foruserlogin" value="'.GETPOST('foruserlogin').'">';
print '<br><input type="submit" class="button small" value="'.$langs->trans("BuildDoc").'">';
print '</form>';

print '<br><br>';

print img_picto('', 'card').' '.$langs->trans("DocForLabels", getDolGlobalString('ADHERENT_ETIQUETTE_TYPE')).' ';
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="mode" value="label">';
print '<input type="hidden" name="action" value="builddoc">';
print $langs->trans("DescADHERENT_ETIQUETTE_TYPE").' ';
// List of possible labels (defined into $_Avery_Labels variable set into format_cards.lib.php)
$arrayoflabels = array();
foreach (array_keys($_Avery_Labels) as $codecards) {
	$arrayoflabels[$codecards] = $_Avery_Labels[$codecards]['name'];
}
asort($arrayoflabels);
print $form->selectarray('modellabel', $arrayoflabels, (GETPOST('modellabel') ? GETPOST('modellabel') : getDolGlobalString('ADHERENT_ETIQUETTE_TYPE')), 1, 0, 0, '', 0, 0, 0, '', '', 1);
print '<br><input type="submit" class="button small" value="'.$langs->trans("BuildDoc").'">';
print '</form>';

// End of page
llxFooter();
$db->close();
