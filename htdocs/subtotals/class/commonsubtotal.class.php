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

/**
 * Trait CommonSubtotal
 * add subtotals lines
 */
trait CommonSubtotal
{
	/**
	 * Special ode for subtotals module lines
	 */
	public static $SPECIAL_CODE = 81;

	/**
	 * Type for subtotals module lines
	 */
	public static $PRODUCT_TYPE = 9;

	/**
	 * Options for subtotals module title lines
	 */
	public static $TITLE_OPTIONS = ['titleshowuponpdf', 'titleshowtotalexludingvatonpdf', 'titleforcepagebreak'];

	/**
	 * Options for subtotals module subtotal lines
	 */
	public static $SUBTOTAL_OPTIONS = ['subtotalshowtotalexludingvatonpdf'];

	public function isSubtotalLine($line) {
		if ($line->special_code == self::$SPECIAL_CODE) {
			return true;
		}
		return false;
	}

	/**
	 * Adds a subtotal or a title line to a document
	 */
	public function addSubtotalLine($langs, $desc, $depth, $options)
	{
		if (empty($desc)) {
			setEventMessages("TitleNeedDesc", null, 'errors');
			return 0;
		}
		$current_module = $this->element;
		// Ensure the object is one of the supported types
		$allowed_types = array('propal', 'commande', 'facture');
		if (!in_array($current_module, $allowed_types)) {
			return false; // Unsupported type
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
				if ($next_line && $line->special_code == self::$SPECIAL_CODE && abs($line->qty) <= abs($depth)) {
					$rang = $line->rang;
					break;
				}
			}
		}

		if ($depth>0) {
			$max_existing_level = 0;

			foreach ($this->lines as $line) {
				if ($line->special_code == self::$SPECIAL_CODE && $line->qty > $max_existing_level) {
					$max_existing_level = $line->qty;
				}
			}

			if ($max_existing_level+1 < $depth) {
				$depth = $max_existing_level+1;
				$this->errors[] = $langs->trans("TitleAddedLevelTooHigh", $depth);
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
				self::$SPECIAL_CODE,	// Special code
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
		} elseif ($current_module== 'propal') {
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
				self::$SPECIAL_CODE,	// Special code
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
		} elseif ($current_module== 'commande') {
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
				self::$SPECIAL_CODE,	// Special code
				0, 						// FK parent line
				null , 					// FK fournprice
				0 , 					// PA ht
				'' , 					// Label
				array() , 				// Array options
				null , 					// FK unit
				'' , 					// Origin
				0 , 					// Origin id
				0 , 					// PU ht devise
				'' , 					// Ref ext
				0 , 					// Noupdateafterinsertline
				$options				// Subtotal options
			);
		}

		if ($result < 0) {
			return $result;
		}

		return $error > 0 ? 0 : $result;
	}

	/**
	 * Delete a subtotal or a title line to a document
	 */
	public function deleteSubtotalLine($id, $correspondingstline = false, $user = null)
	{
		$current_module = $this->element;
		// Ensure the object is one of the supported types
		$allowed_types = array('propal', 'commande', 'facture');
		if (!in_array($current_module, $allowed_types)) {
			return false; // Unsupported type
		}

		if ($correspondingstline) {
			$oldDesc = "";
			$oldDepth =  0;
			foreach ($this->lines as $line) {
				if ($line->id == $id) {
					$oldDesc = $line->desc;
					$oldDepth = $line->qty;
				}
				if ($line->special_code == self::$SPECIAL_CODE && $line->qty == -$oldDepth && $line->desc == $oldDesc) {
					$this->deleteSubtotalLine($line->id, false, $user);
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
	 * Updates a subtotals line to a document
	 */
	public function updateSubtotalLine($langs, $lineid, $desc, $depth, $options)
	{

		$current_module = $this->element;
		// Ensure the object is one of the supported types
		$allowed_types = array('propal', 'commande', 'facture');
		if (!in_array($current_module, $allowed_types)) {
			return false; // Unsupported type
		}

		$error = 0;

		$max_existing_level = 0;

		if ($depth>0) {
			foreach ($this->lines as $line) {
				if ($line->special_code == self::$SPECIAL_CODE && $line->qty > $max_existing_level && $line->id != $lineid) {
					$max_existing_level = $line->qty;
				}
			}
		}

		if ($max_existing_level+1 < $depth) {
			$depth = $max_existing_level+1;
			$this->errors[] = $langs->trans("TitleEditedLevelTooHigh", $depth);
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
				if ($line->special_code == self::$SPECIAL_CODE && $line->qty == -$oldDepth && $line->desc == $oldDesc) {
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
				self::$SPECIAL_CODE,	// Special code
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
				self::$SPECIAL_CODE, 	// Special code
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
				self::$SPECIAL_CODE, 	// Special code
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
	 * Updates a block of subtotals line of a document
	 */
	public function updateSubtotalLineBlockLines($linerang, $mode, $value)
	{

		$linerang -= 1;

		for ($i = $linerang+1; $i < count($this->lines)+1; $i++) {
			if ($this->lines[$i]->special_code == self::$SPECIAL_CODE) {
				if (abs($this->lines[$i]->qty) <= (int)$this->lines[$linerang]->qty) {
					return 1;
				}
			} else {
				if ($this->element == 'facture') {
					$result = $this->updateline(
						$this->lines[$i]->id,
						$this->lines[$i]->desc,
						$this->lines[$i]->subprice,
						$this->lines[$i]->qty,
						$mode == 'discount' ? $value : $this->lines[$i]->remise_percent,
						$this->lines[$i]->date_start,
						$this->lines[$i]->date_end,
						$mode == 'tva' ? $value : $this->lines[$i]->tvatx,
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
				} elseif ($this->element == 'commande') {
					$result = $this->updateline(
						$this->lines[$i]->id,
						$this->lines[$i]->desc,
						$this->lines[$i]->subprice,
						$this->lines[$i]->qty,
						$mode == 'discount' ? $value : $this->lines[$i]->remise_percent,
						$mode == 'tva' ? $value : $this->lines[$i]->tvatx,
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
				} elseif ($this->element == 'propal') {
					$result = $this->updateline(
						$this->lines[$i]->id,
						$this->lines[$i]->subprice,
						$this->lines[$i]->qty,
						$mode == 'discount' ? $value : $this->lines[$i]->remise_percent,
						$mode == 'tva' ? $value : $this->lines[$i]->tvatx,
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
			}
		}
	}

	/**
	 * Return the total_ht of lines that are above the current line (excluded) and that are not a subtotal line
	 * until a title line of the same level is found
	 *
	 * @param object	$line
	 * @return int		$total_ht
	 */
	public function getSubtotalLineAmount($line)
	{
		$final_amount = 0;
		for ($i = $line->rang-1; $i > 0; $i--) {
			if ($this->lines[$i-1]->special_code == self::$SPECIAL_CODE && $this->lines[$i-1]->qty>0) {
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
	 * @param object	$line
	 * @return int		$total_ht
	 */
	public function getSubtotalLineMulticurrencyAmount($line)
	{
		$final_amount = 0;
		for ($i = $line->rang-1; $i > 0; $i--) {
			if ($this->lines[$i-1]->special_code == self::$SPECIAL_CODE && $this->lines[$i-1]->qty>0) {
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
	 * @param Form $form
	 * @param Translate $langs
	 * @param string $type 'title' or 'subtotal'
	 * @return string $formconfirm
	 */
	public function getSubtotalForm($form, $langs, $type, $seller)
	{
		$langs->load('subtotals');

		if ($type == 'subtotal') {
			$titles = $this->getPossibleTitles();
		}

		$depth_array = $this->getPossibleLevels($langs);

		$tpl = dol_buildpath('/core/tpl/subtotal_create.tpl.php');

		if (empty($conf->file->strict_mode)) {
			$res = @include $tpl;
		} else {
			$res = include $tpl; // for debug
		}

		return $res;
	}

	/**
	 * Retrieve the background color associated with a specific subtotal level.
	 *
	 * @param int $level The level of the subtotal for which the color is requested.
	 * @return string|null The background color in hexadecimal format or null if not set.
	 */
	public function getSubtotalColors($level) {
		return getDolGlobalString('SUBTOTAL_BACK_COLOR_LEVEL_'.abs($level));
	}

	/**
	 * Retrieve current object possible titles to choose from
	 *
	 * @return array The set of titles, empty if no title line set.
	 */
	public function getPossibleTitles() {
		$titles = array();
		foreach ($this->lines as $line) {
			if ($line->special_code == self::$SPECIAL_CODE && $line->qty > 0) {
				$titles[$line->desc] = $line->desc;
			}
			if ($line->special_code == self::$SPECIAL_CODE && $line->qty < 0) {
				unset($titles[$line->desc]);
			}
		}
		return $titles;
	}

	/**
	 * Retrieve the current object possible levels (defined in admin page)
	 *
	 * @param Translate $langs
	 * @return array The set of possible levels, empty if not defined correctly.
	 */
	public function getPossibleLevels($langs) {
		$depth_array = array();
		for ($i = 0; $i < getDolGlobalString('SUBTOTAL_'.strtoupper($this->element).'_MAX_DEPTH', 2); $i++) {
			$depth_array[$i + 1] = $langs->trans("Level", $i + 1);
		}
		return $depth_array;
	}
}
