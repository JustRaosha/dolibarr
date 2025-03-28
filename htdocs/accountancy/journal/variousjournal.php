<?php
/* Copyright (C) 2021-2024	Alexandre Spangaro			<alexandre@inovea-conseil.com>
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
 * \file		htdocs/accountancy/journal/variousjournal.php
 * \ingroup		Accountancy (Double entries)
 * \brief		Page of a journal
 */

// Load Dolibarr environment
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/accounting.lib.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingjournal.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Societe $mysoc
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array("banks", "accountancy", "compta", "other", "errors"));

$id_journal = GETPOSTINT('id_journal');
$action = GETPOST('action', 'aZ09');

$date_startmonth = GETPOSTINT('date_startmonth');
$date_startday = GETPOSTINT('date_startday');
$date_startyear = GETPOSTINT('date_startyear');
$date_endmonth = GETPOSTINT('date_endmonth');
$date_endday = GETPOSTINT('date_endday');
$date_endyear = GETPOSTINT('date_endyear');
$in_bookkeeping = GETPOST('in_bookkeeping');
if ($in_bookkeeping == '') {
	$in_bookkeeping = 'notyet';
}

// Get information of a journal
$object = new AccountingJournal($db);
$result = $object->fetch($id_journal);
if ($result > 0) {
	$id_journal = $object->id;
} elseif ($result < 0) {
	dol_print_error(null, $object->error, $object->errors);
} elseif ($result == 0) {
	accessforbidden('ErrorRecordNotFound');
}

$hookmanager->initHooks(array('globaljournal', $object->nature.'journal'));
$parameters = array();

$date_start = dol_mktime(0, 0, 0, $date_startmonth, $date_startday, $date_startyear);
$date_end = dol_mktime(23, 59, 59, $date_endmonth, $date_endday, $date_endyear);

$pastmonth = null;  // Initialise, could be unset
$pastmonthyear = null;  // Initialise, could be unset

if (empty($date_startmonth)) {
	// Period by default on transfer
	$dates = getDefaultDatesForTransfer();
	$date_start = $dates['date_start'];
	$pastmonthyear = $dates['pastmonthyear'];
	$pastmonth = $dates['pastmonth'];
}
if (empty($date_endmonth)) {
	// Period by default on transfer
	$dates = getDefaultDatesForTransfer();
	$date_end = $dates['date_end'];
	$pastmonthyear = $dates['pastmonthyear'];
	$pastmonth = $dates['pastmonth'];
}

if (!GETPOSTISSET('date_startmonth') && (empty($date_start) || empty($date_end))) { // We define date_start and date_end, only if we did not submit the form
	$date_start = dol_get_first_day((int) $pastmonthyear, (int) $pastmonth, false);
	$date_end = dol_get_last_day((int) $pastmonthyear, (int) $pastmonth, false);
}

$data_type = 'view';
if ($action == 'writebookkeeping') {
	$data_type = 'bookkeeping';
}
if ($action == 'exportcsv') {
	$data_type = 'csv';
}
$journal_data = $object->getData($user, $data_type, $date_start, $date_end, $in_bookkeeping);
if (!is_array($journal_data)) {
	setEventMessages($object->error, $object->errors, 'errors');
}

// Security check
if (!isModEnabled('accounting')) {
	accessforbidden();
}
if ($user->socid > 0) {
	accessforbidden();
}
if (!$user->hasRight('accounting', 'bind', 'write')) {
	accessforbidden();
}


/*
 * Actions
 */

$reshook = $hookmanager->executeHooks('doActions', $parameters, $user, $action); // Note that $action and $object may have been modified by some hooks

$reload = false;

// Bookkeeping Write
if ($action == 'writebookkeeping' && $user->hasRight('accounting', 'bind', 'write')) {
	$error = 0;

	$result = $object->writeIntoBookkeeping($user, $journal_data);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
		$error = abs($result);
	}

	$nb_elements = count($journal_data);
	if (empty($error) && $nb_elements > 0) {
		setEventMessages($langs->trans("GeneralLedgerIsWritten"), null, 'mesgs');
	} elseif ($nb_elements == $error) {
		setEventMessages($langs->trans("NoNewRecordSaved"), null, 'warnings');
	} else {
		setEventMessages($langs->trans("GeneralLedgerSomeRecordWasNotRecorded"), null, 'warnings');
	}

	$reload = true;
} elseif ($action == 'exportcsv' && $user->hasRight('accounting', 'bind', 'write')) {
	// Export CSV
	$result = $object->exportCsv($journal_data, $date_end);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
		$reload = true;
	} else {
		$filename = 'journal';
		$type_export = 'journal';

		require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
		include DOL_DOCUMENT_ROOT.'/accountancy/tpl/export_journal.tpl.php';

		print $result;

		$db->close();
		exit();
	}
}

// Must reload data, so we make a redirect
if ($reload) {
	$param = 'id_journal=' . $id_journal;
	$param .= '&date_startday=' . $date_startday;
	$param .= '&date_startmonth=' . $date_startmonth;
	$param .= '&date_startyear=' . $date_startyear;
	$param .= '&date_endday=' . $date_endday;
	$param .= '&date_endmonth=' . $date_endmonth;
	$param .= '&date_endyear=' . $date_endyear;
	$param .= '&in_bookkeeping=' . $in_bookkeeping;
	header("Location: " . $_SERVER['PHP_SELF'] . ($param ? '?' . $param : ''));
	exit;
}


/*
 * View
 */

$form = new Form($db);

if ($object->nature == 2) {
	$some_mandatory_steps_of_setup_were_not_done = getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER') == "" || getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER') == '-1';
	$account_accounting_not_defined = getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER') == "" || getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER') == '-1';
} elseif ($object->nature == 3) {
	$some_mandatory_steps_of_setup_were_not_done = getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER') == "" || getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER') == '-1';
	$account_accounting_not_defined = getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER') == "" || getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER') == '-1';
} elseif ($object->nature == 4) {
	$some_mandatory_steps_of_setup_were_not_done = getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER') == "" || getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER') == '-1'
		|| getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER') == "" || getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER') == '-1'
		|| !getDolGlobalString('SALARIES_ACCOUNTING_ACCOUNT_PAYMENT') || getDolGlobalString('SALARIES_ACCOUNTING_ACCOUNT_PAYMENT') == '-1';
	$account_accounting_not_defined = getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER') == "" || getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER') == '-1'
		|| getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER') == "" || getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER') == '-1';
} elseif ($object->nature == 5) {
	$some_mandatory_steps_of_setup_were_not_done = !getDolGlobalString('SALARIES_ACCOUNTING_ACCOUNT_PAYMENT') || getDolGlobalString('SALARIES_ACCOUNTING_ACCOUNT_PAYMENT') == '-1';
	$account_accounting_not_defined = !getDolGlobalString('SALARIES_ACCOUNTING_ACCOUNT_PAYMENT') || getDolGlobalString('SALARIES_ACCOUNTING_ACCOUNT_PAYMENT') == '-1';
} else {
	$title = $object->getLibType();
	$some_mandatory_steps_of_setup_were_not_done = false;
	$account_accounting_not_defined = false;
}

$title = $langs->trans("GenerationOfAccountingEntries") . ' - ' . $object->getNomUrl(0, 2, 1, '', 1);
$help_url = 'EN:Module_Double_Entry_Accounting|FR:Module_Comptabilit&eacute;_en_Partie_Double#G&eacute;n&eacute;ration_des_&eacute;critures_en_comptabilit&eacute;';
llxHeader('', dol_string_nohtmltag($title), $help_url, '', 0, 0, '', '', '', 'mod-accountancy accountancy-generation page-variousjournal');

$nom = $title;
$nomlink = '';
$periodlink = '';
$exportlink = '';
$builddate = dol_now();
$description = $langs->trans("DescJournalOnlyBindedVisible") . '<br>';
if ($object->nature == 2 || $object->nature == 3) {
	if (getDolGlobalString('FACTURE_DEPOSITS_ARE_JUST_PAYMENTS')) {
		$description .= $langs->trans("DepositsAreNotIncluded");
	} else {
		$description .= $langs->trans("DepositsAreIncluded");
	}
	if (getDolGlobalString('FACTURE_SUPPLIER_DEPOSITS_ARE_JUST_PAYMENTS')) {
		$description .= $langs->trans("SupplierDepositsAreNotIncluded");
	}
}

$listofchoices = array('notyet' => $langs->trans("NotYetInGeneralLedger"), 'already' => $langs->trans("AlreadyInGeneralLedger"));
$period = $form->selectDate($date_start ? $date_start : -1, 'date_start', 0, 0, 0, '', 1, 0) . ' - ' . $form->selectDate($date_end ? $date_end : -1, 'date_end', 0, 0, 0, '', 1, 0);
$period .= ' -  ' . $langs->trans("JournalizationInLedgerStatus") . ' ' . $form->selectarray('in_bookkeeping', $listofchoices, $in_bookkeeping, 1);

$varlink = 'id_journal=' . $id_journal;

journalHead($nom, $nomlink, $period, $periodlink, $description, $builddate, $exportlink, array('action' => ''), '', $varlink);

if (getDolGlobalString('ACCOUNTANCY_FISCAL_PERIOD_MODE') != 'blockedonclosed') {
	// Test that setup is complete (we are in accounting, so test on entity is always on $conf->entity only, no sharing allowed)
	// Fiscal period test
	$sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."accounting_fiscalyear WHERE entity = ".((int) $conf->entity);
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj->nb == 0) {
			print '<br><div class="warning">'.img_warning().' '.$langs->trans("TheFiscalPeriodIsNotDefined");
			$desc = ' : '.$langs->trans("AccountancyAreaDescFiscalPeriod", 4, '{link}');
			$desc = str_replace('{link}', '<strong>'.$langs->transnoentitiesnoconv("MenuAccountancy").'-'.$langs->transnoentitiesnoconv("Setup")."-".$langs->transnoentitiesnoconv("FiscalPeriod").'</strong>', $desc);
			print $desc;
			print '</div>';
		}
	} else {
		dol_print_error($db);
	}
}

if ($object->nature == 4) { // Bank journal
	// Test that setup is complete (we are in accounting, so test on entity is always on $conf->entity only, no sharing allowed)
	$sql = "SELECT COUNT(rowid) as nb";
	$sql .= " FROM " . MAIN_DB_PREFIX . "bank_account";
	$sql .= " WHERE entity = " . (int) $conf->entity;
	$sql .= " AND fk_accountancy_journal IS NULL";
	$sql .= " AND clos=0";
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj->nb > 0) {
			print '<br>' . img_warning() . ' ' . $langs->trans("TheJournalCodeIsNotDefinedOnSomeBankAccount");
			print ' : ' . $langs->trans("AccountancyAreaDescBank", 9, '<strong>' . $langs->transnoentitiesnoconv("MenuAccountancy") . '-' . $langs->transnoentitiesnoconv("Setup") . "-" . $langs->transnoentitiesnoconv("BankAccounts") . '</strong>');
		}
	} else {
		dol_print_error($db);
	}
}

// Button to write into Ledger
if ($some_mandatory_steps_of_setup_were_not_done) {
	print '<br><div class="warning">' . img_warning() . ' ' . $langs->trans("SomeMandatoryStepsOfSetupWereNotDone");
	print ' : ' . $langs->trans("AccountancyAreaDescMisc", 4, '<strong>' . $langs->transnoentitiesnoconv("MenuAccountancy") . '-' . $langs->transnoentitiesnoconv("Setup") . "-" . $langs->transnoentitiesnoconv("MenuDefaultAccounts") . '</strong>');
	print '</div>';
}
print '<br><div class="tabsAction tabsActionNoBottom centerimp">';
if (getDolGlobalString('ACCOUNTING_ENABLE_EXPORT_DRAFT_JOURNAL') && $in_bookkeeping == 'notyet') {
	print '<input type="button" class="butAction" name="exportcsv" value="' . $langs->trans("ExportDraftJournal") . '" onclick="launch_export();" />';
}
if ($account_accounting_not_defined) {
	print '<input type="button" class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans("SomeMandatoryStepsOfSetupWereNotDone")) . '" value="' . $langs->trans("WriteBookKeeping") . '" />';
} else {
	if ($in_bookkeeping == 'notyet') {
		print '<input type="button" class="butAction" name="writebookkeeping" value="' . $langs->trans("WriteBookKeeping") . '" onclick="writebookkeeping();" />';
	} else {
		print '<a href="#" class="butActionRefused classfortooltip" name="writebookkeeping">' . $langs->trans("WriteBookKeeping") . '</a>';
	}
}
print '</div>';

// TODO Avoid using js. We can use a direct link with $param
print '
	<script type="text/javascript">
		function launch_export() {
			$("div.fiche form input[name=\"action\"]").val("exportcsv");
			$("div.fiche form input[type=\"submit\"]").click();
			$("div.fiche form input[name=\"action\"]").val("");
		}
		function writebookkeeping() {
			console.log("click on writebookkeeping");
			$("div.fiche form input[name=\"action\"]").val("writebookkeeping");
			$("div.fiche form input[type=\"submit\"]").click();
			$("div.fiche form input[name=\"action\"]").val("");
		}
	</script>';

$object_label = $langs->trans("ObjectsRef");
if ($object->nature == 2 || $object->nature == 3) {
	$object_label = $langs->trans("InvoiceRef");
}
if ($object->nature == 5) {
	$object_label = $langs->trans("ExpenseReportRef");
}


// Show result array
$i = 0;

print '<br>';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans("Date") . '</td>';
print '<td>' . $langs->trans("Piece") . ' (' . $object_label . ')</td>';
print '<td>' . $langs->trans("AccountAccounting") . '</td>';
print '<td>' . $langs->trans("SubledgerAccount") . '</td>';
print '<td>' . $langs->trans("LabelOperation") . '</td>';
if ($object->nature == 4) {
	print '<td class="center">' . $langs->trans("PaymentMode") . '</td>';
} // bank
print '<td class="right">' . $langs->trans("AccountingDebit") . '</td>';
print '<td class="right">' . $langs->trans("AccountingCredit") . '</td>';
print "</tr>\n";

if (is_array($journal_data) && !empty($journal_data)) {
	foreach ($journal_data as $element_id => $element) {
		foreach ($element['blocks'] as $lines) {
			foreach ($lines as $line) {
				print '<tr class="oddeven">';
				print '<td>' . $line['date'] . '</td>';
				print '<td>' . $line['piece'] . '</td>';
				print '<td>' . $line['account_accounting'] . '</td>';
				print '<td>' . $line['subledger_account'] . '</td>';
				print '<td>' . $line['label_operation'] . '</td>';
				if ($object->nature == 4) {
					print '<td class="center">' . $line['payment_mode'] . '</td>';
				}
				print '<td class="right nowraponall">' . $line['debit'] . '</td>';
				print '<td class="right nowraponall">' . $line['credit'] . '</td>';
				print '</tr>';

				$i++;
			}
		}
	}
}

if (!$i) {
	$colspan = 7;
	if ($object->nature == 4) {
		$colspan++;
	}
	print '<tr class="oddeven"><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

print '</table>';
print '</div>';

llxFooter();

$db->close();
