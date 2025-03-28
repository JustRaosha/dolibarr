<?php
/* Copyright (C) 2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/webhook/lib/webhook.lib.php
 * \ingroup webhook
 * \brief   Library files with common functions for Webhook
 */

/**
 * Prepare admin pages header
 *
 * @return	array<array{0:string,1:string,2:string}>	Array of tabs to show
 */
function webhookAdminPrepareHead()
{
	global $langs, $conf;

	$h = 0;
	$head = array();
	$head[$h][0] = DOL_URL_ROOT . '/admin/webhook.php';
	$head[$h][1] = $langs->trans("Miscellaneous");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = DOL_URL_ROOT . '/webhook/target_list.php?mode=modulesetup';
	$head[$h][1] = $langs->trans("Targets");
	$head[$h][2] = 'targets';
	$h++;


	/*
	$head[$h][0] = dol_buildpath("/webhook/admin/myobject_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$head[$h][2] = 'myobject_extrafields';
	$h++;
	*/



	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@webhook:/webhook/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@webhook:/webhook/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'webhook');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'webhook', 'remove');

	return $head;
}
