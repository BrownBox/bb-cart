<?php
/**
 * One-time recovery of the transactions 3.8.28 left in draft.
 *
 * 3.8.28 made bb_cart_complete_pending_transaction() skip any entry already marked 'Paid'. Gravity
 * Forms' payment add-ons write 'Paid' before firing the hooks that call that function, so payments
 * completed after submission (PayPal, and any method a site marks as not complete at submission)
 * were never completed: the entry says 'Paid' and the transaction post stayed in draft. 3.8.29
 * fixes the gate; this file completes the transactions that were already stuck.
 *
 * The 3.8.29 database update queues it once (admin/updates.php). It runs in batches on WP-Cron, or
 * from BB Cart > Transaction Recovery, and keeps its log in the bb_cart_recovery_3_8_29 option.
 */

define('BB_CART_RECOVERY_OPTION', 'bb_cart_recovery_3_8_29');
define('BB_CART_RECOVERY_LOCK', 'bb_cart_recovery_3_8_29_lock');
define('BB_CART_RECOVERY_NOTICE', 'bb_cart_recovery_3_8_29_notice_dismissed');
define('BB_CART_RECOVERY_CRON', 'bb_cart_recovery_3_8_29_batch');
// 3.8.28 was released on this day, so nothing it left stuck can be older.
define('BB_CART_RECOVERY_WINDOW_START', '2026-09-29 00:00:00');
define('BB_CART_RECOVERY_BATCH_SIZE', 20);

/**
 * Queue the recovery. Does nothing if it has already been queued or run on this site.
 */
function bb_cart_recovery_queue() {
	if (get_option(BB_CART_RECOVERY_OPTION)) {
		return;
	}
	$log = array(
			'status' => 'queued',
			'queued' => current_time('mysql'),
			'window_start' => BB_CART_RECOVERY_WINDOW_START,
			'window_end' => current_time('mysql'), // Anything newer is completed by the fixed code
			'started' => '',
			'finished' => '',
			'override' => false,
			'earlier_matches' => 0,
			'emails_suppressed' => 0,
			'cursor' => 0,
			'rows' => array(),
	);
	update_option(BB_CART_RECOVERY_OPTION, $log, false);
	wp_schedule_single_event(time(), BB_CART_RECOVERY_CRON);
}

/**
 * Find one-off transactions that are still in draft although their entry was paid by a payment add-on.
 *
 * Every condition is needed to tell a stuck transaction from a legitimately pending one:
 * - post in draft and entry 'Paid': until 3.8.28 this pair could not exist, because the transaction is
 *   created with the entry 'Pending' and completing it writes the status and publishes in one step.
 *   Unpaid pledges, direct debits and future-dated gifts stay 'Pending'.
 * - entry has a gateway transaction ID and transaction type 1 (one-time payment): written only by a
 *   payment add-on recording a completed payment, never by editing the status by hand.
 * - no published transaction for the same entry, so nothing can be recorded twice.
 * @param string $from Earliest transaction date (Y-m-d H:i:s, local time), inclusive
 * @param string $to Latest transaction date (Y-m-d H:i:s, local time), exclusive
 * @param int $limit Optional maximum number of rows (0 = no limit)
 * @param int $after_id Optional only return transactions with an ID greater than this
 * @return array Row objects ordered by transaction ID
 */
function bb_cart_recovery_find($from, $to, $limit = 0, $after_id = 0) {
	global $wpdb;
	if (!class_exists('GFFormsModel')) {
		return array();
	}
	$entry_table = GFFormsModel::get_entry_table_name();
	$sql = "SELECT p.ID AS transaction_id, p.post_date, e.id AS entry_id, e.form_id, e.payment_date, e.payment_amount,
				e.transaction_id AS gateway_transaction_id, pm.meta_value AS payment_method
			FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} fq ON fq.post_id = p.ID AND fq.meta_key = 'frequency' AND fq.meta_value = 'one-off'
			JOIN {$wpdb->postmeta} ge ON ge.post_id = p.ID AND ge.meta_key = 'gf_entry_id'
			JOIN {$entry_table} e ON e.id = ge.meta_value
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'payment_method'
			WHERE p.post_type = 'transaction' AND p.post_status = 'draft'
				AND e.status = 'active' AND e.payment_status = 'Paid' AND e.transaction_type = 1
				AND e.transaction_id <> '' AND e.payment_date IS NOT NULL
				AND p.post_date >= %s AND p.post_date < %s AND p.ID > %d
				AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} ge2
					JOIN {$wpdb->posts} p2 ON p2.ID = ge2.post_id
					WHERE ge2.meta_key = 'gf_entry_id' AND ge2.meta_value = ge.meta_value
						AND p2.post_type = 'transaction' AND p2.post_status = 'publish'
				)
			ORDER BY p.ID";
	$args = array($from, $to, $after_id);
	if ($limit > 0) {
		$sql .= ' LIMIT %d';
		$args[] = $limit;
	}
	return $wpdb->get_results($wpdb->prepare($sql, $args));
}

/**
 * The entry's payment date, converted from UTC (as GF stores it) to local time
 * @param object $row
 * @return DateTime
 */
function bb_cart_recovery_payment_date($row) {
	$date = new DateTime($row->payment_date, new DateTimeZone('UTC'));
	$date->setTimezone(wp_timezone());
	return $date;
}

/**
 * Whether a found row is safe to complete. The payment must be dated within a day of the transaction -
 * the same match bb_cart_complete_paypal_transaction() requires. Outside it, PayPal records the payment
 * as a separate new transaction, so completing the original as well would count the gift twice.
 * @param object $row
 * @return boolean
 */
function bb_cart_recovery_is_safe($row) {
	$transaction_day = new DateTime(substr($row->post_date, 0, 10), wp_timezone());
	$payment_day = new DateTime(bb_cart_recovery_payment_date($row)->format('Y-m-d'), wp_timezone());
	return $transaction_day->diff($payment_day)->days <= 1;
}

/**
 * The fields kept for each row: IDs, dates, method and amount, and no donor details
 * @param object $row
 * @param string $result
 * @return array
 */
function bb_cart_recovery_log_row($row, $result) {
	return array(
			'transaction_id' => (int)$row->transaction_id,
			'entry_id' => (int)$row->entry_id,
			'form_id' => (int)$row->form_id,
			'transaction_date' => $row->post_date,
			'payment_date' => bb_cart_recovery_payment_date($row)->format('Y-m-d H:i:s'),
			'payment_method' => strlen((string)$row->payment_method) ? $row->payment_method : '(none)',
			'amount' => (float)$row->payment_amount,
			'gateway_transaction_id' => $row->gateway_transaction_id,
			'result' => $result,
	);
}

/**
 * Complete one found row with its original payment date
 * @param object $row
 * @return string recovered, skipped, already_complete, failed, or error: <message>
 */
function bb_cart_recovery_complete($row) {
	if ('draft' != get_post_status($row->transaction_id)) {
		return 'already_complete';
	}
	if (!bb_cart_recovery_is_safe($row)) {
		return 'skipped';
	}
	try {
		bb_cart_complete_pending_transaction($row->transaction_id, bb_cart_recovery_payment_date($row)->format('Y-m-d H:i:s'));
	} catch (Throwable $e) {
		// Most likely a listener on bb_cart_complete_pending_transaction. The transaction may already be
		// published, so record it rather than let one row stop the run and drop out of the log.
		return 'error: '.get_class($e).' - '.$e->getMessage();
	}
	clean_post_cache($row->transaction_id);
	return 'publish' == get_post_status($row->transaction_id) ? 'recovered' : 'failed';
}

/**
 * Stop emails going out while old transactions are completed - receipts or thank-yous sent by anything
 * listening to the completion would reach donors weeks after they gave. Counted for the log.
 */
function bb_cart_recovery_suppress_mail($return) {
	$GLOBALS['bb_cart_recovery_emails_suppressed']++;
	return false;
}

/**
 * Take the lock that keeps two requests from processing at once. A lock older than 10 minutes is
 * treated as left behind by a request that died.
 * @return boolean Whether the lock was taken
 */
function bb_cart_recovery_lock() {
	global $wpdb;
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value < %d", BB_CART_RECOVERY_LOCK, time()-10*MINUTE_IN_SECONDS));
	return 1 === $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", BB_CART_RECOVERY_LOCK, time()));
}

function bb_cart_recovery_unlock() {
	global $wpdb;
	$wpdb->delete($wpdb->options, array('option_name' => BB_CART_RECOVERY_LOCK));
}

/**
 * Read the log fresh from the database, so a persistent object cache can't hand back a stale copy
 * @return array|false
 */
function bb_cart_recovery_get_log() {
	wp_cache_delete(BB_CART_RECOVERY_OPTION, 'options');
	return get_option(BB_CART_RECOVERY_OPTION);
}

add_action(BB_CART_RECOVERY_CRON, 'bb_cart_recovery_process');
/**
 * Process the queued recovery for up to $time_limit seconds, then hand the rest to WP-Cron
 * @param int $time_limit
 */
function bb_cart_recovery_process($time_limit = 20) {
	if (!bb_cart_recovery_lock()) {
		return;
	}
	$log = bb_cart_recovery_get_log();
	if (empty($log) || !in_array($log['status'], array('queued', 'running'))) {
		bb_cart_recovery_unlock();
		return;
	}

	if ('queued' == $log['status']) {
		$log['started'] = current_time('mysql');
		// If the same signature exists from before 3.8.28, something other than 3.8.28 produces it on
		// this site, so stop and leave the decision to a person.
		$log['earlier_matches'] = count(bb_cart_recovery_find('1970-01-01 00:00:00', $log['window_start']));
		if ($log['earlier_matches'] > 0 && !$log['override']) {
			$log['status'] = 'needs_review';
			$log['finished'] = current_time('mysql');
			update_option(BB_CART_RECOVERY_OPTION, $log, false);
			wp_clear_scheduled_hook(BB_CART_RECOVERY_CRON);
			bb_cart_recovery_unlock();
			return;
		}
		$log['status'] = 'running';
		update_option(BB_CART_RECOVERY_OPTION, $log, false);
	}

	$started = time();
	$GLOBALS['bb_cart_recovery_emails_suppressed'] = 0;
	add_filter('pre_wp_mail', 'bb_cart_recovery_suppress_mail', 999);
	do {
		$rows = bb_cart_recovery_find($log['window_start'], $log['window_end'], BB_CART_RECOVERY_BATCH_SIZE, $log['cursor']);
		foreach ($rows as $row) {
			$log['rows'][] = bb_cart_recovery_log_row($row, bb_cart_recovery_complete($row));
			$log['cursor'] = (int)$row->transaction_id;
			$log['emails_suppressed'] += $GLOBALS['bb_cart_recovery_emails_suppressed'];
			$GLOBALS['bb_cart_recovery_emails_suppressed'] = 0;
			update_option(BB_CART_RECOVERY_OPTION, $log, false); // Saved per row so a timeout loses nothing
		}
	} while (count($rows) == BB_CART_RECOVERY_BATCH_SIZE && time()-$started < $time_limit);
	remove_filter('pre_wp_mail', 'bb_cart_recovery_suppress_mail', 999);

	if (count($rows) < BB_CART_RECOVERY_BATCH_SIZE) {
		$log['status'] = 'complete';
		$log['finished'] = current_time('mysql');
		wp_clear_scheduled_hook(BB_CART_RECOVERY_CRON);
	} else {
		wp_schedule_single_event(time()+30, BB_CART_RECOVERY_CRON);
	}
	update_option(BB_CART_RECOVERY_OPTION, $log, false);
	bb_cart_recovery_unlock();
}

/**
 * Totals for a set of log rows
 * @param array $rows
 * @return array
 */
function bb_cart_recovery_summarise(array $rows) {
	$summary = array(
			'recovered' => 0,
			'amount' => 0,
			'by_method' => array(),
			'by_month' => array(),
			'other' => array(),
	);
	foreach ($rows as $row) {
		if ('recovered' != $row['result']) {
			if (!isset($summary['other'][$row['result']])) {
				$summary['other'][$row['result']] = 0;
			}
			$summary['other'][$row['result']]++;
			continue;
		}
		$summary['recovered']++;
		$summary['amount'] += $row['amount'];
		$month = substr($row['transaction_date'], 0, 7);
		foreach (array('by_method' => $row['payment_method'], 'by_month' => $month) as $group => $key) {
			if (!isset($summary[$group][$key])) {
				$summary[$group][$key] = array('count' => 0, 'amount' => 0);
			}
			$summary[$group][$key]['count']++;
			$summary[$group][$key]['amount'] += $row['amount'];
		}
	}
	ksort($summary['by_month']);
	return $summary;
}

/**
 * What a run would do now, without changing anything. Rows a run would complete are marked 'recovered'.
 * @param array $log
 * @return array
 */
function bb_cart_recovery_preview_rows(array $log) {
	$rows = array();
	foreach (bb_cart_recovery_find($log['window_start'], current_time('mysql')) as $row) {
		$rows[] = bb_cart_recovery_log_row($row, bb_cart_recovery_is_safe($row) ? 'recovered' : 'skipped');
	}
	return $rows;
}

add_action('admin_menu', 'bb_cart_recovery_menu');
function bb_cart_recovery_menu() {
	add_submenu_page('bb_cart_settings', 'Transaction Recovery', 'Transaction Recovery', 'manage_options', 'bb_cart_recovery', 'bb_cart_recovery_page');
}

add_action('admin_post_bb_cart_recovery_process', 'bb_cart_recovery_process_action');
/**
 * "Process now" (cron may not run on a quiet site) and "Recover anyway" (after a needs-review stop)
 */
function bb_cart_recovery_process_action() {
	if (!current_user_can('manage_options')) {
		wp_die('You do not have permission to do this.');
	}
	check_admin_referer('bb_cart_recovery_process');
	$log = bb_cart_recovery_get_log();
	if (!empty($log) && 'needs_review' == $log['status'] && !empty($_POST['override'])) {
		$log['status'] = 'queued';
		$log['override'] = true;
		$log['finished'] = '';
		update_option(BB_CART_RECOVERY_OPTION, $log, false);
	}
	bb_cart_recovery_process();
	wp_safe_redirect(admin_url('admin.php?page=bb_cart_recovery'));
	exit;
}

add_action('admin_post_bb_cart_recovery_csv', 'bb_cart_recovery_csv_action');
function bb_cart_recovery_csv_action() {
	if (!current_user_can('manage_options')) {
		wp_die('You do not have permission to do this.');
	}
	check_admin_referer('bb_cart_recovery_csv');
	$log = bb_cart_recovery_get_log();
	if (empty($log)) {
		wp_die('The recovery has not been queued on this site.');
	}
	$preview = isset($_GET['preview']);
	$rows = $preview ? bb_cart_recovery_preview_rows($log) : $log['rows'];
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="bb-cart-recovery-'.($preview ? 'preview' : 'log').'-'.current_time('Ymd-His').'.csv"');
	$out = fopen('php://output', 'w');
	fputcsv($out, array('transaction_id', 'entry_id', 'form_id', 'transaction_date', 'payment_date', 'payment_method', 'amount', 'gateway_transaction_id', 'result'));
	foreach ($rows as $row) {
		fputcsv($out, $row);
	}
	fclose($out);
	exit;
}

add_action('admin_post_bb_cart_recovery_dismiss', 'bb_cart_recovery_dismiss_action');
function bb_cart_recovery_dismiss_action() {
	if (!current_user_can('manage_options')) {
		wp_die('You do not have permission to do this.');
	}
	check_admin_referer('bb_cart_recovery_dismiss');
	update_option(BB_CART_RECOVERY_NOTICE, 1, false);
	wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
	exit;
}

add_action('admin_notices', 'bb_cart_recovery_notice');
/**
 * Tell admins once the recovery has done something, or stopped for review. Silent where it found nothing.
 */
function bb_cart_recovery_notice() {
	if (!current_user_can('manage_options') || get_option(BB_CART_RECOVERY_NOTICE)) {
		return;
	}
	$log = get_option(BB_CART_RECOVERY_OPTION);
	if (empty($log)) {
		return;
	}
	$summary = bb_cart_recovery_summarise($log['rows']);
	if ('needs_review' == $log['status']) {
		$class = 'notice-warning';
		$message = 'BB Cart 3.8.29 found draft transactions with paid entries but stopped without changing anything, because the same pattern exists from before the fault it repairs. Please review.';
	} elseif ('complete' == $log['status'] && count($log['rows']) > 0) {
		$class = 'notice-info';
		$message = sprintf('BB Cart 3.8.29 completed %d transaction(s) that an earlier version had left in draft.', $summary['recovered']);
	} else {
		return;
	}
	$log_url = admin_url('admin.php?page=bb_cart_recovery');
	$dismiss_url = wp_nonce_url(admin_url('admin-post.php?action=bb_cart_recovery_dismiss'), 'bb_cart_recovery_dismiss');
	echo '<div class="notice '.esc_attr($class).'"><p>'.esc_html($message).' <a href="'.esc_url($log_url).'">View the recovery log</a> | <a href="'.esc_url($dismiss_url).'">Dismiss</a></p></div>'."\n";
}

/**
 * Print a summary as tables of counts by payment method and by transaction month
 * @param array $summary
 */
function bb_cart_recovery_print_summary(array $summary) {
	foreach (array('by_method' => 'Payment method', 'by_month' => 'Transaction month') as $group => $label) {
		if (empty($summary[$group])) {
			continue;
		}
		echo '<table class="widefat striped" style="max-width: 40em; margin-bottom: 1em;">'."\n";
		echo '<thead><tr><th>'.esc_html($label).'</th><th>Transactions</th><th>Amount</th></tr></thead>'."\n";
		echo '<tbody>'."\n";
		foreach ($summary[$group] as $key => $totals) {
			echo '<tr><td>'.esc_html($key).'</td><td>'.esc_html($totals['count']).'</td><td>'.esc_html(number_format($totals['amount'], 2)).'</td></tr>'."\n";
		}
		echo '</tbody></table>'."\n";
	}
	foreach ($summary['other'] as $result => $count) {
		echo '<p>'.esc_html(ucfirst(str_replace('_', ' ', $result)).' (left unchanged): '.$count).'</p>'."\n";
	}
}

function bb_cart_recovery_page() {
	if (!current_user_can('manage_options')) {
		return;
	}
	$log = bb_cart_recovery_get_log();
	echo '<div class="wrap">'."\n";
	echo '<h2>Transaction Recovery</h2>'."\n";
	echo '<p>BB Cart 3.8.28 (29 September 2026) left some paid one-off transactions in draft: the payment was taken and the Gravity Forms entry is marked Paid, but the transaction was never completed, so it is missing from exports and reports that only count completed transactions. 3.8.29 fixes the cause and, once, completes those transactions with their original dates. Emails are not sent while it does so.</p>'."\n";
	if (empty($log)) {
		echo '<p>The recovery has not been queued on this site.</p></div>'."\n";
		return;
	}
	$summary = bb_cart_recovery_summarise($log['rows']);
	$csv_url = wp_nonce_url(admin_url('admin-post.php?action=bb_cart_recovery_csv'), 'bb_cart_recovery_csv');
	$statuses = array(
			'queued' => 'Queued - waiting for WP-Cron',
			'running' => 'Running',
			'complete' => 'Complete',
			'needs_review' => 'Stopped for review - nothing was changed',
	);
	$fields = array(
			'Status' => $statuses[$log['status']],
			'Queued' => $log['queued'],
			'Started' => $log['started'],
			'Finished' => $log['finished'],
			'Transactions dated' => $log['window_start'].' to '.$log['window_end'],
			'Transactions completed' => $summary['recovered'].' totalling '.number_format($summary['amount'], 2),
			'Emails suppressed' => $log['emails_suppressed'],
	);
	echo '<h3>Log</h3>'."\n";
	echo '<table class="form-table">'."\n";
	foreach ($fields as $label => $value) {
		echo '<tr><th scope="row">'.esc_html($label).'</th><td>'.esc_html($value).'</td></tr>'."\n";
	}
	echo '</table>'."\n";
	bb_cart_recovery_print_summary($summary);
	if (count($log['rows']) > 0) {
		echo '<p><a class="button" href="'.esc_url($csv_url).'">Download the log as CSV (IDs, dates, method, amount)</a></p>'."\n";
	}
	if ('needs_review' == $log['status']) {
		echo '<p>'.esc_html(sprintf('%d transaction(s) dated before %s match the same pattern, so on this site it may have another cause. Check the preview below before choosing to go ahead.', $log['earlier_matches'], $log['window_start'])).'</p>'."\n";
	}
	if (in_array($log['status'], array('queued', 'running', 'needs_review'))) {
		echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'."\n";
		echo '<input type="hidden" name="action" value="bb_cart_recovery_process">'."\n";
		wp_nonce_field('bb_cart_recovery_process');
		if ('needs_review' == $log['status']) {
			echo '<input type="hidden" name="override" value="1">'."\n";
			submit_button('Recover anyway', 'secondary', 'submit', false);
		} else {
			submit_button('Process now', 'primary', 'submit', false);
		}
		echo '</form>'."\n";
	}
	$preview = bb_cart_recovery_preview_rows($log);
	$preview_summary = bb_cart_recovery_summarise($preview);
	echo '<h3>Preview (changes nothing)</h3>'."\n";
	echo '<p>'.esc_html(sprintf('Draft transactions dated from %s whose entry is Paid, right now: %d would be completed.', $log['window_start'], $preview_summary['recovered'])).'</p>'."\n";
	bb_cart_recovery_print_summary($preview_summary);
	if (count($preview) > 0) {
		echo '<p><a class="button" href="'.esc_url(add_query_arg('preview', 1, $csv_url)).'">Download the preview as CSV</a></p>'."\n";
	}
	echo '</div>'."\n";
}
