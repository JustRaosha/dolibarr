<?php
/* Copyright (C) 2012      Regis Houssin       <regis.houssin@inodbox.com>
 * Copyright (C) 2013      Florian Henry	   <florian.henry@open-concept.pro>
 * Copyright (C) 2014-2020 Laurent Destailleur <eldy@destailleur.fr>
 * Copyright (C) 2024-2025  Frédéric France         <frederic.france@free.fr>
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
 * @var ?CommonObject $object
 * @var Form $form
 * @var Translate $langs
 * @var User $user
 *
 * @var ?int<0,1> $permissionnote
 * @var string $moreparam
 * @var ?int $colwidth
 * @var string $cssclass
 */
// Protection to avoid direct call of template
if (empty($object) || !is_object($object)) {
	print "Error, template page can't be called as URL";
	exit(1);
}
'
@phan-var-force ?int<0,1> $permissionnote
@phan-var-force string $moreparam
@phan-var-force ?int $colwidth
';

// $permissionnote 	must be defined by caller. For example $permissionnote=$user->rights->module->create
// $cssclass   		must be defined by caller. For example $cssclass='fieldtitle'
$module       = $object->element;
$note_public  = 'note_public';
$note_private = 'note_private';

if ($module == "product") {
	$module = ($object->type == Product::TYPE_SERVICE ? 'service' : 'product');
}
$colwidth = (isset($colwidth) ? $colwidth : (empty($cssclass) ? '25' : ''));
// Set $permission from the $permissionnote var defined on calling page
$permission = (isset($permissionnote) ? $permissionnote : (isset($permission) ? $permission : ($user->hasRight($module, 'create') ? $user->rights->$module->create : ($user->hasRight($module, 'creer') ? $user->rights->$module->creer : 0))));
$moreparam = (isset($moreparam) ? $moreparam : '');
$value_public = $object->note_public;
$value_private = $object->note_private;
if (getDolGlobalString('MAIN_AUTO_TIMESTAMP_IN_PUBLIC_NOTES')) {
	$stringtoadd = dol_print_date(dol_now(), 'dayhour').' '.$user->getFullName($langs).' --';
	if (GETPOST('action', 'aZ09') == 'edit'.$note_public) {
		$value_public = dol_concatdesc($value_public, ($value_public ? "\n" : "")."-- ".$stringtoadd);
		if (dol_textishtml($value_public)) {
			$value_public .= "<br>\n";
		} else {
			$value_public .= "\n";
		}
	}
}
if (getDolGlobalString('MAIN_AUTO_TIMESTAMP_IN_PRIVATE_NOTES')) {
	$stringtoadd = dol_print_date(dol_now(), 'dayhour').' '.$user->getFullName($langs).' --';
	if (GETPOST('action', 'aZ09') == 'edit'.$note_private) {
		$value_private = dol_concatdesc($value_private, ($value_private ? "\n" : "")."-- ".$stringtoadd);
		if (dol_textishtml($value_private)) {
			$value_private .= "<br>\n";
		} else {
			$value_private .= "\n";
		}
	}
}

// Special cases
if ($module == 'propal') {
	$permission = $user->hasRight("propal", "creer");
} elseif ($module == 'supplier_proposal') {
	$permission = $user->hasRight("supplier_proposal", "creer");
} elseif ($module == 'fichinter') {
	$permission = $user->hasRight("ficheinter", "creer");
} elseif ($module == 'project') {
	$permission = $user->hasRight("projet", "creer");
} elseif ($module == 'project_task') {
	$permission = $user->hasRight("projet", "creer");
} elseif ($module == 'invoice_supplier') {
	if (!getDolGlobalString('MAIN_USE_NEW_SUPPLIERMOD')) {
		$permission = $user->hasRight("fournisseur", "facture", "creer");
	} else {
		$permission = $user->hasRight("supplier_invoice", "creer");
	}
} elseif ($module == 'order_supplier') {
	if (!getDolGlobalString('MAIN_USE_NEW_SUPPLIERMOD')) {
		$permission = $user->hasRight("fournisseur", "commande", "creer");
	} else {
		$permission = $user->hasRight("supplier_order", "creer");
	}
} elseif ($module == 'societe') {
	$permission = $user->hasRight("societe", "creer");
} elseif ($module == 'contact') {
	$permission = $user->hasRight("societe", "creer");
} elseif ($module == 'shipping') {
	$permission = $user->hasRight("expedition", "creer");
} elseif ($module == 'product') {
	$permission = $user->hasRight("product", "creer");
} elseif ($module == 'service') {
	$permission = $user->hasRight("service", "creer");
} elseif ($module == 'ecmfiles') {
	$permission = $user->hasRight("ecm", "setup");
} elseif ($module == 'user') {
	$permission = $user->hasRight("user", "self", "write");
}
//else dol_print_error(null,'Bad value '.$module.' for param module');

if (isModEnabled('fckeditor') && getDolGlobalString('FCKEDITOR_ENABLE_NOTE_PUBLIC')) {
	$typeofdatapub = 'ckeditor:dolibarr_notes:100%:200::1:12:95%:0'; // Rem: This var is for all notes, not only thirdparties note.
} else {
	$typeofdatapub = 'textarea:12:95%';
}
if (isModEnabled('fckeditor') && getDolGlobalString('FCKEDITOR_ENABLE_NOTE_PRIVATE')) {
	$typeofdatapriv = 'ckeditor:dolibarr_notes:100%:200::1:12:95%:0'; // Rem: This var is for all notes, not only thirdparties note.
} else {
	$typeofdatapriv = 'textarea:12:95%';
}

print '<!-- BEGIN PHP TEMPLATE NOTES -->'."\n";
print '<div class="tagtable border table-border tableforfield centpercent">'."\n";
print '<div class="tagtr table-border-row">'."\n";
$editmode = (GETPOST('action', 'aZ09') == 'edit'.$note_public);
print '<div class="tagtd tagtdnote tdtop'.($editmode ? '' : ' sensiblehtmlcontent').' table-key-border-col'.(empty($cssclass) ? '' : ' '.$cssclass).'"'.($colwidth ? ' style="width: '.$colwidth.'%"' : '').'>'."\n";
print $form->editfieldkey((empty($textNotePub) ? "NotePublic" : $textNotePub), $note_public, (string) $value_public, $object, $permission, $typeofdatapub, $moreparam, 0, 0);
print '</div>'."\n";
print '<div class="tagtd wordbreak table-val-border-col'.($editmode ? '' : ' sensiblehtmlcontent').'">'."\n";
print $form->editfieldval("NotePublic", $note_public, (string) $value_public, $object, $permission, $typeofdatapub, '', null, null, $moreparam, 1)."\n";
print '</div>'."\n";
print '</div>'."\n";
if (empty($user->socid)) {
	// Private notes (always hidden to external users)
	print '<div class="tagtr table-border-row">'."\n";
	$editmode = (GETPOST('action', 'aZ09') == 'edit'.$note_private);
	print '<div class="tagtd tagtdnote tdtop'.($editmode ? '' : ' sensiblehtmlcontent').' table-key-border-col'.(empty($cssclass) ? '' : ' '.$cssclass).'"'.($colwidth ? ' style="width: '.$colwidth.'%"' : '').'>'."\n";
	print $form->editfieldkey((empty($textNotePrive) ? "NotePrivate" : $textNotePrive), $note_private, (string) $value_private, $object, $permission, $typeofdatapriv, $moreparam, 0, 0);
	print '</div>'."\n";
	print '<div class="tagtd wordbreak table-val-border-col'.($editmode ? '' : ' sensiblehtmlcontent').'">'."\n";
	print $form->editfieldval("NotePrivate", $note_private, (string) $value_private, $object, $permission, $typeofdatapriv, '', null, null, $moreparam, 1);
	print '</div>'."\n";
	print '</div>'."\n";
}
print '</div>'."\n";
?>
<!-- END PHP TEMPLATE NOTES-->
