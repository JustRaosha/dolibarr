<?php
/*
 * Copyright (C) 2013   Florian Henry      <florian.henry@open-concept.pro>
 * Copyright (C) 2025		MDW					<mdeweerd@users.noreply.github.com>
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
 *      \file       htdocs/core/class/html.formcron.class.php
 *      \brief      Fichier de la class des functions predefinie de composants html cron
 */


/**
 *      Class to manage building of HTML components
 */
class FormCron extends Form
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 *  Constructor
	 *
	 *  @param      DoliDB      $db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Display On Off selector
	 *
	 *  @param  string		$htmlname	Html control name
	 *  @param  string		$selected	Selected value
	 *  @param  int<0,1>	$readonly	Select is read only or not
	 *  @return string					HTML select field
	 */
	public function select_typejob($htmlname, $selected = '', $readonly = 0)
	{
		// phpcs:enable
		global $langs;

		$langs->load('cron');

		$out = '';
		if (!empty($readonly)) {
			if ($selected == 'command') {
				$out = img_picto('', 'terminal', 'class="pictofixedwidth marginrightonly"').'<span class="">'.$langs->trans('CronType_command').'</span>';
			} elseif ($selected == 'method') {
				$out = img_picto('', 'code', 'class="pictofixedwidth marginrightonly"').'<span class="">'.$langs->trans('CronType_method').'</span>';
			}
		} else {
			$out = '<SELECT class="flat" name="'.$htmlname.'" id="'.$htmlname.'" />';

			if ($selected == 'command') {
				$selected_attr = ' selected ';
			} else {
				$selected_attr = '';
			}
			$out .= '<OPTION value="command" '.$selected_attr.'>'.$langs->trans('CronType_command').'</OPTION>';

			if ($selected == 'method') {
				$selected_attr = ' selected ';
			} else {
				$selected_attr = '';
			}
			$out .= '<OPTION value="method" '.$selected_attr.'>'.$langs->trans('CronType_method').'</OPTION>';

			$out .= '</SELECT>';
		}
		if (empty($readonly)) {
			$out .= ajax_combobox($htmlname);
		}

		return $out;
	}
}
