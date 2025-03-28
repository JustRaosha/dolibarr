<?php
/* Copyright (C) 2004		Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2005-2010	Laurent Destailleur		<eldy@users.sourceforge.org>
 * Copyright (C) 2011		Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2012		Regis Houssin			<regis.houssin@inodbox.com>
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
 *      \file       htdocs/webservices/admin/index.php
 *		\ingroup    webservices
 *		\brief      Page to setup webservices module
 */

// Load Dolibarr environment
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Form $form
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$langs->load("admin");

if (!$user->admin) {
	accessforbidden();
}

$actionsave = GETPOST("save");

// Sauvegardes parameters
if ($actionsave) {
	$i = 0;

	$db->begin();

	$i += dolibarr_set_const($db, 'WEBSERVICES_KEY', GETPOST("WEBSERVICES_KEY"), 'chaine', 0, '', $conf->entity);

	if ($i >= 1) {
		$db->commit();
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}


/*
 *	View
 */

llxHeader('', '', '', '', 0, 0, '', '', '', 'mod-webservices page-admin_index');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans("WebServicesSetup"), $linkback, 'title_setup');

print '<span class="opacitymedium">'.$langs->trans("WebServicesDesc")."</span><br>\n";
print "<br>\n";

print '<form name="agendasetupform" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print "<td>".$langs->trans("Parameter")."</td>";
print "<td>".$langs->trans("Value")."</td>";
//print "<td>".$langs->trans("Examples")."</td>";
print "<td>&nbsp;</td>";
print "</tr>";

print '<tr class="oddeven">';
print '<td class="fieldrequired">'.$langs->trans("KeyForWebServicesAccess").'</td>';
print '<td><input type="text" class="flat" id="WEBSERVICES_KEY" name="WEBSERVICES_KEY" value="'.(GETPOST('WEBSERVICES_KEY') ? GETPOST('WEBSERVICES_KEY') : (getDolGlobalString('WEBSERVICES_KEY') ? $conf->global->WEBSERVICES_KEY : '')).'" size="40">';
if (!empty($conf->use_javascript_ajax)) {
	print '&nbsp;'.img_picto($langs->trans('Generate'), 'refresh', 'id="generate_token" class="linkobject"');
}
print '</td>';
print '<td>&nbsp;</td>';
print '</tr>';

print '</table>';

print $form->buttonsSaveCancel("Save", '');

print '</form>';

print '<br><br>';

// Webservices list
$webservices = array(
		'user'				=> '',
		'thirdparty'		=> 'isModEnabled("societe")',
		'contact'			=> 'isModEnabled("societe")',
		'productorservice'	=> '(isModEnabled("product") || isModEnabled("service"))',
		'order'				=> 'isModEnabled("order")',
		'invoice'			=> 'isModEnabled("invoice")',
		'supplier_invoice'	=> 'isModEnabled("fournisseur")',
		'actioncomm'		=> 'isModEnabled("agenda")',
		'category'			=> 'isModEnabled("category")',
		'project'			=> 'isModEnabled("project")',
		'other'				=> ''
);


// WSDL
print '<u>'.$langs->trans("WSDLCanBeDownloadedHere").':</u><br>';
foreach ($webservices as $name => $right) {
	if (!empty($right) && !verifCond($right)) {
		continue;
	}
	$url = DOL_MAIN_URL_ROOT.'/webservices/server_'.$name.'.php?wsdl';
	print img_picto('', 'globe').' <a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$url."</a><br>\n";
}
print '<br>';


// Endpoint
print '<u>'.$langs->trans("EndPointIs").':</u><br>';
foreach ($webservices as $name => $right) {
	if (!empty($right) && !verifCond($right)) {
		continue;
	}
	$url = DOL_MAIN_URL_ROOT.'/webservices/server_'.$name.'.php';
	print img_picto('', 'globe').' <a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$url."</a><br>\n";
}
print '<br>';


print '<br>';
print $langs->trans("OnlyActiveElementsAreShown", DOL_URL_ROOT.'/admin/modules.php');

$constname = 'WEBSERVICES_KEY';

print dolJSToSetRandomPassword($constname);


// End of page
llxFooter();
$db->close();
