<?php
/* Copyright (C) 2014-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024		MDW						<mdeweerd@users.noreply.github.com>
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
 * or see https://www.gnu.org/
 */

if (!defined('SUBTOTALS_SPECIAL_CODE')) {
	define('SUBTOTALS_SPECIAL_CODE', 81);
}

/**
 * Trait CommonSubtotal
 * add subtotals lines
 */
trait CommonSubtotal
{
	/**
	 * @var int
	 * Type for subtotals module lines
	 */
	public static $PRODUCT_TYPE = 9;

	/**
	 * @var array<string>
	 * Options for subtotals module title lines
	 */
	public static $TITLE_OPTIONS = ['titleshowuponpdf', 'titleshowtotalexludingvatonpdf', 'titleforcepagebreak'];

	/**
	 * @var array<string>
	 * Options for subtotals module subtotal lines
	 */
	public static $SUBTOTAL_OPTIONS = ['subtotalshowtotalexludingvatonpdf'];

	/**
	 * Adds a subtotals line to a document.
	 * This function inserts a subtotal line based on the given parameters.
	 *
	 * @param Translate					$langs  	Translation.
	 * @param string					$desc		Description of the line.
	 * @param int						$depth		Level of the line (>0 for title lines, <0 for subtotal lines)
	 * @param array<string,int|float>	$options	Subtotal options for pdf view
	 * @return int									ID of the added line if successful, 0 on warning, -1 on error
	 */
	public function addSubtotalLine($langs, $desc, $depth, $options)
	{
		if (empty($desc)) {
			if (isset($this->errors)) {
				$this->errors[] = $langs->trans("TitleNeedDesc");
			}
			return -1;
		}
		$current_module = $this->element ?? "";
		// Ensure the object is one of the supported types
		$allowed_types = array('propal', 'commande', 'facture');
		if (!in_array($current_module, $allowed_types)) {
			if (isset($this->errors)) {
				$this->errors[] = $langs->trans("UnsupportedModuleError");
			}
			return -1; // Unsupported type
		}
		$error = 0;
		$desc = dol_html_entity_decode($desc, ENT_QUOTES);
		$rang = -1;
		$next_line = false;

		if ($depth<0) {
			foreach ($this->lines as $line) {
				if (!$next_line && $line->desc == $desc && $line->qty == -$depth) {
					$next_line = true;
					continue;
				}
				if ($next_line && $line->desc == $desc && $line->qty == $depth) {
					$next_line = false;
					continue;
				}
				if ($next_line && $line->special_code == SUBTOTALS_SPECIAL_CODE && abs($line->qty) <= abs($depth)) {
					$rang = $line->rang;
					break;
				}
			}
		}

		if ($depth>0) {
			$max_existing_level = 0;

			foreach ($this->lines as $line) {
				if ($line->special_code == SUBTOTALS_SPECIAL_CODE && $line->qty > $max_existing_level) {
					$max_existing_level = $line->qty;
				}
			}

			if ($max_existing_level+1 < $depth) {
				$depth = $max_existing_level+1;
				if (isset($this->errors)) {
					$this->errors[] = $langs->trans("TitleAddedLevelTooHigh", $depth);
				}

				$error ++;
			}
		}

		// Add the line calling the right module
		if ($current_module == 'facture') {
			$result = $this->addline(
				$desc,					// Description
				0,						// Unit price
				$depth,					// Quantity
				0,						// VAT rate
				0,						// Local tax 1
				0,						// Local tax 2
				null,					// FK product
				0,						// Discount percentage
				'',						// Date start
				'',						// Date end
				0,						// FK code ventilation
				0,						// Info bits
				0,						// FK remise except
				'',						// Price base type
				0,						// PU ttc
				self::$PRODUCT_TYPE,	// Type
				$rang,					// Rang
				SUBTOTALS_SPECIAL_CODE,	// Special code
				'', 					// Origin
				0, 						// Origin_id
				0, 						// FK parent line
				null, 					// FK fournprice
				0, 						// PA ht
				'', 					// Label
				array(), 				// Array options
				100, 					// Situation percent
				0, 						// FK prev id
				null, 					// FK unit
				0, 						// PU ht devise
				'', 					// Ref ext
				0, 						// Noupdateafterinsertline
				$options 				// Subtotal options
			);
		} elseif ($current_module == 'propal') {
			$result = $this->addline(
				$desc,					// Description
				0,						// Unit price
				$depth,					// Quantity
				0,						// VAT rate
				0,						// Local tax 1
				0,						// Local tax 2
				null,					// FK product
				0,						// Discount percentage
				'',						// Price base type
				0,						// PU ttc
				0,						// Info bits
				self::$PRODUCT_TYPE,	// Type
				$rang,					// Rang
				SUBTOTALS_SPECIAL_CODE,	// Special code
				0, 						// FK parent line
				0, 						// FK fournprice
				0, 						// PA ht
				'', 					// Label
				'', 					// Date start
				'', 					// Date end
				array(), 				// Array options
				null, 					// FK unit
				'', 					// Origin
				0, 						// Origin id
				0, 						// PU ht devise
				0, 						// FK remise except
				0, 						// Noupdateafterinsertline
				$options 				// Subtotal options
			);
		} elseif ($current_module == 'commande') {
			$result = $this->addline(
				$desc,					// Description
				0,						// Unit price
				$depth,					// Quantity
				0,						// VAT rate
				0,						// Local tax 1
				0,						// Local tax 2
				null,					// FK product
				0,						// Discount percentage
				0,						// Info bits
				0,						// FK remise except
				'',						// Price base type
				0,						// PU ttc
				'',						// Date start
				'',						// Date end
				self::$PRODUCT_TYPE,	// Type
				$rang,					// Rang
				SUBTOTALS_SPECIAL_CODE,	// Special code
				0, 						// FK parent line
				null, 					// FK fournprice
				0, 					// PA ht
				'', 					// Label
				array(), 				// Array options
				null, 					// FK unit
				'', 					// Origin
				0, 					// Origin id
				0, 					// PU ht devise
				'', 					// Ref ext
				0, 					// Noupdateafterinsertline
				$options				// Subtotal options
			);
		}

		if ($result < 0) {
			return $result;
		}

		return $error > 0 ? 0 : $result;
	}

	/**
	 * Deletes a subtotal or a title line from a document.
	 * If the corresponding subtotal line exists and second parameter true, it will also be deleted.
	 *
	 * @param Translate	$langs					Translation.
	 * @param int		$id						ID of the line to delete
	 * @param boolean	$correspondingstline	If true, also deletes the corresponding subtotal line
	 * @param User		$user					performing the deletion (used for permissions in some modules)
	 * @return int								ID of deleted line if successful, -1 on error
	 */
	public function deleteSubtotalLine($langs, $id, $correspondingstline = false, $user = null)
	{
		$current_module = $this->element ?? "";
		// Ensure the object is one of the supported types
		$allowed_types = array('propal', 'commande', 'facture');
		if (!in_array($current_module, $allowed_types)) {
			if (isset($this->errors)) {
				$this->errors[] = $langs->trans("UnsupportedModuleError");
			}
			return -1; // Unsupported type
		}

		if ($correspondingstline) {
			$oldDesc = "";
			$oldDepth =  0;
			foreach ($this->lines as $line) {
				if ($line->id == $id) {
					$oldDesc = $line->desc;
					$oldDepth = $line->qty;
				}
				if ($line->special_code == SUBTOTALS_SPECIAL_CODE && $line->qty == -$oldDepth && $line->desc == $oldDesc) {
					$this->deleteSubtotalLine($langs, $line->id, false, $user);
					break;
				}
			}
		}

		// Add the line calling the right module
		if ($current_module == 'facture') {
			$result = $this->deleteLine($id);
		} elseif ($current_module== 'propal') {
			$result = $this->deleteLine($id);
		} elseif ($current_module== 'commande') {
			$result = $this->deleteLine($user, $id);
		}

		return $result >= 0 ? $result : -1; // Return line ID or false
	}

	/**
	 * Updates a subtotal line of a document.
	 * This function updates a subtotals line based on its id and the given parameters.
	 * Updating a title line updates the corresponding subtotal line except options.
	 *
	 * @param Translate					$langs  	Translation.
	 * @param int						$lineid  	ID of the line to update.
	 * @param string					$desc		Description of the line.
	 * @param int						$depth		Level of the line (>0 for title lines, <0 for subtotal lines)
	 * @param array<string,int|float>	$options	Subtotal options for pdf view
	 * @return int									ID of the added line if successful, 0 on warning, -1 on error
	 */
	public function updateSubtotalLine($langs, $lineid, $desc, $depth, $options)
	{
		$current_module = $this->element ?? "";
		// Ensure the object is one of the supported types
		$allowed_types = array('propal', 'commande', 'facture');
		if (!in_array($current_module, $allowed_types)) {
			if (isset($this->errors)) {
				$this->errors[] = $langs->trans("UnsupportedModuleError");
			}
			return -1; // Unsupported type
		}

		$error = 0;

		$max_existing_level = 0;

		if ($depth>0) {
			foreach ($this->lines as $line) {
				if ($line->special_code == SUBTOTALS_SPECIAL_CODE && $line->qty > $max_existing_level && $line->id != $lineid) {
					$max_existing_level = $line->qty;
				}
			}
		}

		if ($max_existing_level+1 < $depth) {
			$depth = $max_existing_level+1;
			if (isset($this->errors)) {
				$this->errors[] = $langs->trans("TitleEditedLevelTooHigh");
			}
			$error ++;
		}

		if ($depth>0) {
			$oldDesc = "";
			$oldDepth =  0;
			foreach ($this->lines as $line) {
				if ($line->id == $lineid) {
					$oldDesc = $line->desc;
					$oldDepth = $line->qty;
				}
				if ($line->special_code == SUBTOTALS_SPECIAL_CODE && $line->qty == -$oldDepth && $line->desc == $oldDesc) {
					$this->updateSubtotalLine($langs, $line->id, $desc, -$depth, $line->subtotal_options);
					break;
				}
			}
		}

		// Update the line calling the right module
		if ($current_module == 'facture') {
			$result = $this->updateline(
				$lineid, 				// ID of line to change
				$desc,					// Description
				0,						// Unit price
				$depth,					// Quantity
				0,						// Discount percentage
				'',						// Date start
				'',						// Date end
				0,						// VAT rate
				0,						// Local tax 1
				0,						// Local tax 2
				'',						// Price base type
				0, 						// Info bits
				self::$PRODUCT_TYPE,	// Type
				0,						// FK parent line
				0,						// Skip update total
				null,					// FK fournprice
				0,						// PA ht
				'',						// Label
				SUBTOTALS_SPECIAL_CODE,	// Special code
				array(), 				// Array options
				100, 					// Situation percent
				null,					// FK unit
				0, 						// PU ht devise
				0, 						// Notrigger
				'', 					// Ref ext
				0, 						// Rang
				$options 				// Subtotal_options
			);
		} elseif ($current_module== 'propal') {
			$result = $this->updateline(
				$lineid, 				// ID of line to change
				0,						// Unit price
				$depth,					// Quantity
				0,						// Discount percentage
				0,						// VAT rate
				0,						// Local tax 1
				0,						// Local tax 2
				$desc,					// Description
				'',						// Price base type
				0,						// Info bits
				SUBTOTALS_SPECIAL_CODE, 	// Special code
				0, 						// FK parent line
				0, 						// Skip update total
				0, 						// FK fournprice
				0, 						// PA ht
				'',						// Label
				self::$PRODUCT_TYPE,	// Type
				'', 					// Date start
				'', 					// Date end
				array(), 				// Array options
				null, 					// FK unit
				0, 						// PU ht devise
				0, 						// Notrigger
				0,						// Rang
				$options 				// Subtotal options
			);
		} elseif ($current_module== 'commande') {
			$result = $this->updateline(
				$lineid, 				// ID of line to change
				$desc,					// Description
				0,						// Unit price
				$depth,					// Quantity
				0,						// Discount percentage
				0,						// VAT rate
				0,						// Local tax 1
				0,						// Local tax 2
				'',						// Price base type
				0,						// Info bits
				'',						// Date start
				'',						// Date end
				self::$PRODUCT_TYPE,	// Type
				0, 						// FK parent line
				0, 						// Skip update total
				0, 						// FK fournprice
				0, 						// PA ht
				'',						// Label
				SUBTOTALS_SPECIAL_CODE, 	// Special code
				array(), 				// Array options
				null, 					// FK unit
				0, 						// PU ht devise
				0, 						// Notrigger
				'', 					// Ref ext
				0, 						// Rang
				$options 				// Subtotal options
			);
		}

		if ($result < 0) {
			return $result;
		}

		return $error > 0 ? 0 : $result;
	}

	/**
	 * Updates a block of lines of a document.
	 *
	 * @param Translate	$langs  	Translation.
	 * @param int		$linerang	Rang of the line to start from.
	 * @param string	$mode		Column to change (discount or vat).
	 * @param int		$value		Value of the change.
	 * @return int					Return integer < 0 if KO, 1 if OK
	 */
	public function updateSubtotalLineBlockLines($langs, $linerang, $mode, $value)
	{
		$current_module = $this->element ?? "";
		// Ensure the object is one of the supported types
		$allowed_types = array('propal', 'commande', 'facture');
		if (!in_array($current_module, $allowed_types)) {
			if (isset($this->errors)) {
				$this->errors[] = $langs->trans("UnsupportedModuleError");
			}
			return -1; // Unsupported type
		}

		$linerang -= 1;

		$nb_lines = count($this->lines)+1;

		for ($i = $linerang+1; $i < $nb_lines; $i++) {
			if ($this->lines[$i]->special_code == SUBTOTALS_SPECIAL_CODE) {
				if (abs($this->lines[$i]->qty) <= (int) $this->lines[$linerang]->qty) {
					return 1;
				}
			} else {
				if ($current_module == 'facture') {
					$result = $this->updateline(
						$this->lines[$i]->id,
						$this->lines[$i]->desc,
						$this->lines[$i]->subprice,
						$this->lines[$i]->qty,
						$mode == 'discount' ? $value : $this->lines[$i]->remise_percent,
						$this->lines[$i]->date_start,
						$this->lines[$i]->date_end,
						$mode == 'tva' ? $value : $this->lines[$i]->tva_tx,
						$this->lines[$i]->localtax1_tx,
						$this->lines[$i]->localtax2_tx,
						'HT',
						$this->lines[$i]->info_bits,
						$this->lines[$i]->product_type,
						$this->lines[$i]->fk_parent_line, 0,
						$this->lines[$i]->fk_fournprice,
						$this->lines[$i]->pa_ht,
						$this->lines[$i]->label,
						$this->lines[$i]->special_code,
						$this->lines[$i]->array_options,
						$this->lines[$i]->situation_percent,
						$this->lines[$i]->fk_unit,
						$this->lines[$i]->multicurrency_subprice);
				} elseif ($current_module == 'commande') {
					$result = $this->updateline(
						$this->lines[$i]->id,
						$this->lines[$i]->desc,
						$this->lines[$i]->subprice,
						$this->lines[$i]->qty,
						$mode == 'discount' ? $value : $this->lines[$i]->remise_percent,
						$mode == 'tva' ? $value : $this->lines[$i]->tva_tx,
						$this->lines[$i]->localtax1_rate,
						$this->lines[$i]->localtax2_rate,
						'HT',
						$this->lines[$i]->info_bits,
						$this->lines[$i]->date_start,
						$this->lines[$i]->date_end,
						$this->lines[$i]->product_type,
						$this->lines[$i]->fk_parent_line, 0,
						$this->lines[$i]->fk_fournprice,
						$this->lines[$i]->pa_ht,
						$this->lines[$i]->label,
						$this->lines[$i]->special_code,
						$this->lines[$i]->array_options,
						$this->lines[$i]->fk_unit,
						$this->lines[$i]->multicurrency_subprice);
				} elseif ($current_module == 'propal') {
					$result = $this->updateline(
						$this->lines[$i]->id,
						$this->lines[$i]->subprice,
						$this->lines[$i]->qty,
						$mode == 'discount' ? $value : $this->lines[$i]->remise_percent,
						$mode == 'tva' ? $value : $this->lines[$i]->tva_tx,
						$this->lines[$i]->localtax1_rate,
						$this->lines[$i]->localtax2_rate,
						$this->lines[$i]->desc,
						'HT',
						$this->lines[$i]->info_bits,
						$this->lines[$i]->special_code,
						$this->lines[$i]->fk_parent_line, 0,
						$this->lines[$i]->fk_fournprice,
						$this->lines[$i]->pa_ht,
						$this->lines[$i]->label,
						$this->lines[$i]->product_type,
						$this->lines[$i]->date_start,
						$this->lines[$i]->date_end,
						$this->lines[$i]->array_options,
						$this->lines[$i]->fk_unit,
						$this->lines[$i]->multicurrency_subprice);
				}
				if ($result < 0) {
					return $result;
				}
			}
		}
	}

	/**
	 * Return the total_ht of lines that are above the current line (excluded) and that are not a subtotal line
	 * until a title line of the same level is found
	 *
	 * @param object	$line	Line that needs the subtotal amount.
	 * @return string	$total_ht
	 */
	public function getSubtotalLineAmount($line)
	{
		$final_amount = 0;
		for ($i = $line->rang-1; $i > 0; $i--) {
			if ($this->lines[$i-1]->special_code == SUBTOTALS_SPECIAL_CODE && $this->lines[$i-1]->qty>0) {
				if ($this->lines[$i-1]->qty <= abs($line->qty)) {
					return price($final_amount);
				}
			} else {
				$final_amount += $this->lines[$i-1]->total_ht;
			}
		}
		return price($final_amount);
	}

	/**
	 * Return the multicurrency_total_ht of lines that are above the current line (excluded) and that are not a subtotal line
	 * until a title line of the same level is found
	 *
	 * @param object	$line	Line that needs the subtotal amount with multicurrency mod activated.
	 * @return string	$total_ht
	 */
	public function getSubtotalLineMulticurrencyAmount($line)
	{
		$final_amount = 0;
		for ($i = $line->rang-1; $i > 0; $i--) {
			if ($this->lines[$i-1]->special_code == SUBTOTALS_SPECIAL_CODE && $this->lines[$i-1]->qty>0) {
				if ($this->lines[$i-1]->qty <= abs($line->qty)) {
					return price($final_amount);
				}
			} else {
				$final_amount += $this->lines[$i-1]->multicurrency_total_ht;
			}
		}
		return price($final_amount);
	}

	/**
	 * Returns a form array to add a subtotal or title line
	 *
	 * @param Form $form		Form class to use in template.
	 * @param Translate $langs	Translation.
	 * @param string $type 		Type to show form to add a 'title' or 'subtotal' line.
	 * @return string $formconfirm
	 */
	public function getSubtotalForm($form, $langs, $type)
	{
		$langs->load('subtotals');

		if ($type == 'subtotal') {
			$titles = $this->getPossibleTitles();
		}

		$depth_array = $this->getPossibleLevels($langs);

		$tpl = dol_buildpath('/core/tpl/subtotal_create.tpl.php');

		return include $tpl;
	}

	/**
	 * Retrieve the background color associated with a specific subtotal level.
	 *
	 * @param int $level The level of the subtotal for which the color is requested.
	 * @return string|null The background color in hexadecimal format or null if not set.
	 */
	public function getSubtotalColors($level)
	{
		return getDolGlobalString('SUBTOTAL_BACK_COLOR_LEVEL_'.abs($level));
	}

	/**
	 * Retrieve current object possible titles to choose from
	 *
	 * @return array<string,string> The set of titles, empty if no title line set.
	 */
	public function getPossibleTitles()
	{
		$titles = array();
		foreach ($this->lines as $line) {
			if ($line->special_code == SUBTOTALS_SPECIAL_CODE && $line->qty > 0) {
				$titles[$line->desc] = $line->desc;
			}
			if ($line->special_code == SUBTOTALS_SPECIAL_CODE && $line->qty < 0) {
				unset($titles[$line->desc]);
			}
		}
		return $titles;
	}

	/**
	 * Retrieve the current object possible levels (defined in admin page)
	 *
	 * @param Translate $langs 		Translations.
	 * @return array<int,string>	The set of possible levels, empty if not defined correctly.
	 */
	public function getPossibleLevels($langs)
	{
		$depth_array = array();
		$element = $this->element ?? "";
		$max_depth = getDolGlobalString('SUBTOTAL_'.strtoupper($element).'_MAX_DEPTH', 2);
		for ($i = 0; $i < $max_depth; $i++) {
			$depth_array[$i + 1] = $langs->trans("Level", $i + 1);
		}
		return $depth_array;
	}
}
