<?php

/* Copyright (C) 2003-2007 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2012 Regis Houssin         <regis.houssin@inodbox.com>
 * Copyright (C) 2005      Simon TOSSER          <simon@kornog-computing.com>
 * Copyright (C) 2013      Florian Henry          <florian.henry@open-concept.pro>
 * Copyright (C) 2013      Cédric Salvador       <csalvador@gpcsolutions.fr>
 * Copyright (C) 2017      Ferran Marcet       	 <fmarcet@2byte.es>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		MDW						<mdeweerd@users.noreply.github.com>
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
 *       \file       htdocs/product/document.php
 *       \ingroup    product
 *       \brief      Page of documents attached to products/services
 */


// Load Dolibarr environment
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
if (getDolGlobalString('PRODUIT_PDF_MERGE_PROPAL')) {
	require_once DOL_DOCUMENT_ROOT.'/product/class/propalmergepdfproduct.class.php';
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array('other', 'products'));


// Get parameters
$id     = GETPOSTINT('id');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

// Security check
$fieldvalue = (!empty($id) ? $id : (!empty($ref) ? $ref : ''));
$fieldtype = (!empty($ref) ? 'ref' : 'rowid');
if ($user->socid) {
	$socid = $user->socid;
}

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
$hookmanager->initHooks(array('productdocuments'));

// Get parameters
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortorder) {
	$sortorder = "ASC";
}
if (!$sortfield) {
	$sortfield = "position_name";
}

$upload_dir = '';
$upload_dirold = '';
// Initialize objects
$object = new Product($db);
if ($id > 0 || !empty($ref)) {
	$result = $object->fetch($id, $ref);

	if (isModEnabled("product")) {
		$upload_dir = $conf->product->multidir_output[$object->entity].'/'.get_exdir(0, 0, 0, 1, $object, 'product');
	} elseif (isModEnabled("service")) {
		$upload_dir = $conf->service->multidir_output[$object->entity].'/'.get_exdir(0, 0, 0, 1, $object, 'product');
	}

	if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {    // For backward compatibility, we scan also old dirs
		if (isModEnabled("product")) {
			$upload_dirold = $conf->product->multidir_output[$object->entity].'/'.substr(substr("000".$object->id, -2), 1, 1).'/'.substr(substr("000".$object->id, -2), 0, 1).'/'.$object->id."/photos";
		} else {
			$upload_dirold = $conf->service->multidir_output[$object->entity].'/'.substr(substr("000".$object->id, -2), 1, 1).'/'.substr(substr("000".$object->id, -2), 0, 1).'/'.$object->id."/photos";
		}
	}
}

$modulepart = 'produit';


if ($object->id > 0) {
	if ($object->type == $object::TYPE_PRODUCT) {
		restrictedArea($user, 'produit', $object->id, 'product&product', '', '');
	}
	if ($object->type == $object::TYPE_SERVICE) {
		restrictedArea($user, 'service', $object->id, 'product&product', '', '');
	}
} else {
	restrictedArea($user, 'produit|service', $fieldvalue, 'product&product', '', '', $fieldtype);
}

$permissiontoadd = (($object->type == Product::TYPE_PRODUCT && $user->hasRight('produit', 'creer')) || ($object->type == Product::TYPE_SERVICE && $user->hasRight('service', 'creer')));


/*
 * Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Delete line if product propal merge is linked to a file
	if (getDolGlobalString('PRODUIT_PDF_MERGE_PROPAL')) {
		if ($action == 'confirm_deletefile' && $confirm == 'yes' && $permissiontoadd) {
			//extract file name
			$urlfile = GETPOST('urlfile', 'alpha');
			$filename = basename($urlfile);
			$filetomerge = new Propalmergepdfproduct($db);
			$filetomerge->fk_product = $object->id;
			$filetomerge->file_name = $filename;
			$result = $filetomerge->delete_by_file($user);
			if ($result < 0) {
				setEventMessages($filetomerge->error, $filetomerge->errors, 'errors');
			}
		}
	}

	// Action submit/delete file/link
	include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';
}

if ($action == 'filemerge' && $permissiontoadd) {
	$is_refresh = GETPOST('refresh');
	if (empty($is_refresh)) {
		$filetomerge_file_array = GETPOST('filetoadd');

		$filetomerge_file_array = GETPOST('filetoadd');

		$lang_id = null;
		if (getDolGlobalInt('MAIN_MULTILANGS')) {
			$lang_id = GETPOST('lang_id', 'aZ09');
		}

		// Delete all file already associated
		$filetomerge = new Propalmergepdfproduct($db);

		if (getDolGlobalInt('MAIN_MULTILANGS') && $lang_id !== null) {
			$result = $filetomerge->delete_by_product($user, $object->id, $lang_id);
		} else {
			$result = $filetomerge->delete_by_product($user, $object->id);
		}
		if ($result < 0) {
			setEventMessages($filetomerge->error, $filetomerge->errors, 'errors');
		}

		// for each file checked add it to the product
		if (is_array($filetomerge_file_array)) {
			foreach ($filetomerge_file_array as $filetomerge_file) {
				$filetomerge->fk_product = $object->id;
				$filetomerge->file_name = $filetomerge_file;

				if (getDolGlobalInt('MAIN_MULTILANGS') && $lang_id !== null) {
					$filetomerge->lang = $lang_id;
				}

				$result = $filetomerge->create($user);
				if ($result < 0) {
					setEventMessages($filetomerge->error, $filetomerge->errors, 'errors');
				}
			}
		}
	}
}


/*
 *	View
 */

$form = new Form($db);

$title = $langs->trans('ProductServiceCard');
$helpurl = '';
$shortlabel = dol_trunc($object->label, 16);
if (GETPOST("type") == '0' || ($object->type == Product::TYPE_PRODUCT)) {
	$title = $langs->trans('Product')." ".$shortlabel." - ".$langs->trans('Documents');
	$helpurl = 'EN:Module_Products|FR:Module_Produits|ES:M&oacute;dulo_Productos';
}
if (GETPOST("type") == '1' || ($object->type == Product::TYPE_SERVICE)) {
	$title = $langs->trans('Service')." ".$shortlabel." - ".$langs->trans('Documents');
	$helpurl = 'EN:Module_Services_En|FR:Module_Services|ES:M&oacute;dulo_Servicios';
}

llxHeader('', $title, $helpurl, '', 0, 0, '', '', '', 'mod-product page-card_document');


if ($object->id > 0) {
	$head = product_prepare_head($object);
	$titre = $langs->trans("CardProduct".$object->type);
	$picto = ($object->type == Product::TYPE_SERVICE ? 'service' : 'product');

	print dol_get_fiche_head($head, 'documents', $titre, -1, $picto);

	$parameters = array();
	$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	if ($reshook < 0) {
		setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
	}

	// Build file list
	$filearray = dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1);

	if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {    // For backward compatibility, we scan also old dirs
		$filearrayold = dol_dir_list($upload_dirold, "files", 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1);
		$filearray = array_merge($filearray, $filearrayold);
	}

	$totalsize = 0;
	foreach ($filearray as $key => $file) {
		$totalsize += $file['size'];
	}


	$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1&type='.$object->type.'">'.$langs->trans("BackToList").'</a>';
	$object->next_prev_filter = "(te.fk_product_type:=:".((int) $object->type).")";

	$shownav = 1;
	if ($user->socid && !in_array('product', explode(',', getDolGlobalString('MAIN_MODULES_FOR_EXTERNAL')))) {
		$shownav = 0;
	}

	dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');

	print '<div class="fichecenter">';

	print '<div class="underbanner clearboth"></div>';
	print '<table class="border tableforfield centpercent">';

	print '<tr><td class="titlefield">'.$langs->trans("NbOfAttachedFiles").'</td><td colspan="3">'.count($filearray).'</td></tr>';
	print '<tr><td>'.$langs->trans("TotalSizeOfAttachedFiles").'</td><td colspan="3">'.dol_print_size($totalsize, 1, 1).'</td></tr>';
	print '</table>';

	print '</div>';
	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();

	/* To move into tpl
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';

	$formmail = new FormMail($db);
	$formmail->withaiprompt = 'text';
	$out = '';

	$showlinktoai = ($formmail->withaiprompt && isModEnabled('ai')) ? 'textgenerationemail' : '';
	$showlinktoailabel = $langs->trans("GenerateImage");

	$formatforouput = 'image';
	$htmlname = 'bodyemail';

	print load_fiche_titre($langs->trans('GenerateWithAI'), '', '');
	print '<table class="border centpercent">';

	// Fill $out
	require DOL_DOCUMENT_ROOT.'/core/tpl/formlayoutai.tpl.php';

	print $out;
	print '</table>';
	*/

	$param = '&id='.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';


	// Merge propal PDF document PDF files
	if (getDolGlobalString('PRODUIT_PDF_MERGE_PROPAL')) {
		$filetomerge = new Propalmergepdfproduct($db);

		if (getDolGlobalInt('MAIN_MULTILANGS')) {
			$lang_id = GETPOST('lang_id', 'aZ09');
			$result = $filetomerge->fetch_by_product($object->id, $lang_id);
		} else {
			$result = $filetomerge->fetch_by_product($object->id);
		}

		$filearray = dol_dir_list($upload_dir, "files", 0, '', '\.meta$', 'name', SORT_ASC, 1);

		if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {    // For backward compatibility, we scan also old dirs
			$filearray = array_merge($filearray, dol_dir_list($upload_dirold, "files", 0, '', '\.meta$', 'name', SORT_ASC, 1));
		}

		// For each file build select list with PDF extension
		if (count($filearray) > 0) {
			print '<br>';
			// Actual file to merge is :
			if (count($filetomerge->lines) > 0) {
				print $langs->trans('PropalMergePdfProductActualFile');
			}

			print '<form name="filemerge" action="'.DOL_URL_ROOT.'/product/document.php?id='.$object->id.'" method="post">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="filemerge">';
			if (count($filetomerge->lines) == 0) {
				print $langs->trans('PropalMergePdfProductChooseFile');
			}

			print  '<table class="noborder">';

			$default_lang = null;
			// Get language
			if (getDolGlobalInt('MAIN_MULTILANGS')) {
				$langs->load("languages");

				print  '<tr class="liste_titre"><td>';

				$default_lang = empty($lang_id) ? $langs->getDefaultLang() : $lang_id;

				$langs_available = $langs->get_available_languages(DOL_DOCUMENT_ROOT, 12);

				print Form::selectarray('lang_id', $langs_available, $default_lang, 0, 0, 0, '', 0, 0, 0, 'ASC');

				if (getDolGlobalInt('MAIN_MULTILANGS')) {
					print  '<input type="submit" class="button smallpaddingimp" name="refresh" value="'.$langs->trans('Refresh').'">';
				}

				print  '</td></tr>';
			}

			foreach ($filearray as $filetoadd) {
				$ext = pathinfo($filetoadd['name'], PATHINFO_EXTENSION);
				if ($ext == 'pdf') {
					$checked = '';
					$filename = $filetoadd['name'];

					if (getDolGlobalInt('MAIN_MULTILANGS') && $default_lang !== null) {
						if (array_key_exists($filetoadd['name'].'_'.$default_lang, $filetomerge->lines)) {
							$filename = $filetoadd['name'].' - '.$langs->trans('Language_'.$default_lang);
							$checked = ' checked ';
						}
					} else {
						if (array_key_exists($filetoadd['name'], $filetomerge->lines)) {
							$checked = ' checked ';
						}
					}

					print  '<tr class="oddeven"><td>';
					print  '<input type="checkbox" '.$checked.' name="filetoadd[]" id="filetoadd" value="'.$filetoadd['name'].'">'.$filename.'</input>';
					print  '</td></tr>';
				}
			}

			print  '<tr><td>';
			print  '<input type="submit" class="button button-save" name="save" value="'.$langs->trans("Save").'">';
			print  '</td></tr>';

			print  '</table>';

			print  '</form>';
		}
	}
} else {
	print $langs->trans("ErrorUnknown");
}

// End of page
llxFooter();
$db->close();
