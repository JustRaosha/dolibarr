<?php
print '<!-- origin line id = ' . $lines[$i]->origin_line_id . ' -->'; // id of order line

$langs->load('subtotals');

$line_color = $object->getSubtotalColors($lines[$i]->qty);
$line_options = $lines[$i]->extraparams["subtotal"] ?? array();
$colspan = 8;

if (isModEnabled('productbatch')) {
	$colspan++;
}
if (isModEnabled('stock')) {
	$colspan++;
}

print '<tr id="row-' . $lines[$i]->id . '" data-id="' . $lines[$i]->id . '" data-element="' . $lines[$i]->element . '" style="background:#' . $line_color . '" >';

if (getDolGlobalString('MAIN_VIEW_LINE_NUMBER')) {
	print '<td class="center linecolnum">' . ($i + 1) . '</td>';
}

//print '<td class="linecoldescription" colspan="' . $colspan . '">' . $lines[$i]->description . "</td>\n";

if ($lines[$i]->qty > 0) { ?>
	<td class="linecollabel" colspan="<?php echo $colspan ?>" <?php echo !colorIsLight($line_color) ? ' style="color: white"' : ' style="color: black"' ?>><?php echo str_repeat('&nbsp;', (int)($lines[$i]->qty - 1) * 8); ?>
		<?php
		echo $lines[$i]->desc;
		if ($line_options) {
			if (!empty($line_options['titleshowuponpdf'])) {
				echo '&nbsp;' . img_picto($langs->trans("ShowUPOnPDF"), 'invoicing');
			}
			if (!empty($line_options['titleshowtotalexludingvatonpdf'])) {
				echo '&nbsp; <span title="' . $langs->trans("ShowTotalExludingVATOnPDF") . '">%</span>';
			}
			if (!empty($line_options['titleforcepagebreak'])) {
				echo '&nbsp;' . img_picto($langs->trans("ForcePageBreak"), 'file');
			}
		}
		?>
	</td>
<?php } elseif ($lines[$i]->qty < 0) { ?>
<td class="linecollabel nowrap right" <?php echo !colorIsLight($line_color) ? ' style="color: white"' : ' style="color: black"' ?> colspan="<?php echo $colspan ?>">
	<?php
	echo $lines[$i]->desc;
	if (!empty($line_options['subtotalshowtotalexludingvatonpdf'])) {
		echo '&nbsp; <span title="' . $langs->trans("ShowTotalExludingVATOnPDF") . '">%</span>';
	}
	?>
</td>
<?php }

print "</tr>";
