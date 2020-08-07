<?php
class bb_cart_fund_code_report {
	private $slug = 'bb_cart_fund_code_report';
	private $hook = '';
	private $selected_month;
	private $selected_fund_code;

	public function __construct() {
		if (is_admin()) {
			add_action('admin_menu', array($this, 'add_plugin_page'));
			add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
		}
	}

	public function add_plugin_page() {
		$this->hook = add_submenu_page('bb_cart_settings', 'Fund Code Report', 'Fund Code Report', 'add_users', $this->slug, array($this, 'create_admin_page'));
	}

	public function enqueue_scripts($hook) {
		if ($hook == $this->hook) {
			// Scripts from Connexions used for table export
			wp_enqueue_script('Blob');
			wp_enqueue_script('FileSaver');
			wp_enqueue_script('tableExport');
			wp_enqueue_script('tableExportBase64');
		}
	}

	public function create_admin_page() {
		$this->setup_data();
		$title = 'Fund Code Report - '.date('F Y', strtotime($this->selected_month));
		echo '<div class="wrap">'."\n";
		echo '    <h2>'.$title.'</h2>'."\n";
		if (!empty($this->selected_fund_code)) {
			$title .= ' - '.$this->selected_fund_code;
			echo '<h3>'.$this->selected_fund_code.'</h3>'."\n";
			echo '<p><a href="'.remove_query_arg('fund_code').'">&larr; Return to summary</a></p>'."\n";
		} else {
			echo '<p>Click an amount for a breakdown of donations for that code.</p>'."\n";
		}
		echo '<button name="report-download" id="fund_code_report_download" class="button action" style="float: right;"><img src="'.BBCONNECT_URL.'assets/g/excel.png"> Export to Excel</button>'."\n";
		echo $this->get_date_filter();
		$this->show_report();
		echo '<script>'."\n";
		echo 'jQuery(document).ready(function () {'."\n";
		echo '    // Apply filters on change'."\n";
		echo '    jQuery("#fund_code_report_filter_month").change(function() {'."\n";
		echo '        jQuery("#fund_code_report_filter_month").parent("form").trigger("submit");'."\n";
		echo '    });'."\n";
		echo "\n";
		echo '    // Export to CSV'."\n";
		echo '    jQuery("#fund_code_report_download").click(function() {'."\n";
		echo '        var tableExport = jQuery("#table_fund_code_report").tableExport({'."\n";
		echo '            formats: ["csv"],'."\n";
		echo '            filename: "'.$title.'",'."\n";
		echo '            exportButtons: false'."\n";
		echo '        });'."\n";
		echo '        exportData = tableExport.getExportData()["table_fund_code_report"]["csv"];'."\n";
		echo '        tableExport.export2file(exportData.data, exportData.mimeType, exportData.filename, exportData.fileExtension);'."\n";
		echo '    });'."\n";
		echo '});'."\n";
		echo '</script>'."\n";
		echo '</div>'."\n";
	}

	private function show_report() {
		$class = '';
		if (!empty($this->selected_fund_code)) {
			$class .= ' fund_code_detail';
		}
		echo '    <table class="widefat striped'.$class.'" id="table_fund_code_report">'."\n";
		if (empty($this->selected_fund_code)) {
			$data = $this->get_summary();
			$total = 0;
			foreach ($data as $fund_code => $stats) {
				$subtotal = $stats['total'];
				echo '        <tr>'."\n";
				echo '            <td style="padding-top: 2rem;"><strong>'.$fund_code.'</strong></td>'."\n";
				echo '            <td style="padding-top: 2rem; text-align: right;">';
				if (!empty($stats['total']) || empty($stats['children'])) {
					echo '<a href="'.add_query_arg('fund_code', urlencode($fund_code)).'">$'.number_format($stats['total'], 2).'</a>';
				}
				echo '</td>'."\n";
				echo '        </tr>'."\n";
				if (!empty($stats['children'])) {
					foreach ($stats['children'] as $child_code => $child_total) {
						$subtotal += $child_total;
						echo '        <tr>'."\n";
						echo '            <td style="padding-left: 2rem;">'.$child_code.'</td>'."\n";
						echo '            <td style="text-align: right;"><a href="'.add_query_arg('fund_code', urlencode($child_code)).'">$'.number_format($child_total, 2).'</a></td>'."\n";
						echo '        </tr>'."\n";
					}
					echo '        <tr>'."\n";
					echo '            <td>&nbsp;</td>'."\n";
					echo '            <td style="text-align: right;"><span style="border-top: 1px solid black;">Subtotal: $'.number_format($subtotal, 2).'</span></td>'."\n";
					echo '        </tr>'."\n";
				}
				$total += $subtotal;
			}
			echo '        <tr>'."\n";
			echo '            <td style="padding-top: 1rem;">&nbsp;</td>'."\n";
			echo '            <td style="padding-top: 1rem; text-align: right;"><span style="border-top: 1px solid black;">Total: $'.number_format($total, 2).'</span></td>'."\n";
			echo '        </tr>'."\n";
		} else {
			$data = $this->get_detail();
			$headers = array_shift($data);
			echo '        <tr>'."\n";
			foreach ($headers as $header) {
				echo '            <th>'.$header.'</th>'."\n";
			}
			echo '        </tr>'."\n";
			foreach ($data as $row) {
				echo '        <tr>'."\n";
				foreach ($row as $col) {
					echo '            <td>'.$col.'</td>'."\n";
				}
				echo '        </tr>'."\n";
			}
		}
		echo '    </table>'."\n";
	}

	private function get_summary() {
		$results = $totals = array();
		$transactions = $this->get_transactions();
		foreach ($transactions as $transaction) {
			$line_items = bb_cart_get_transaction_line_items($transaction->ID);
			if (count($line_items) > 0) {
				foreach ($line_items as $line_item) {
					$fund_code = $this->get_line_item_fund_code($line_item);
					$amount = get_post_meta($line_item->ID, 'price', true)*get_post_meta($line_item->ID, 'quantity', true);
					if (!isset($totals[$fund_code])) {
						$totals[$fund_code] = 0;
					}
					$totals[$fund_code] += $amount;
				}
			} else { // No line items, just pull data from the transaction itself
				$fund_code = get_post_meta($transaction->ID, 'fund_code', true);
				if (empty($fund_code)) {
					$fund_code = 'Blank/Unknown';
				}
				$amount = get_post_meta($transaction->ID, 'total_amount', true);
				if (!isset($totals[$fund_code])) {
					$totals[$fund_code] = 0;
				}
				$totals[$fund_code] += $amount;
			}
		}

		// Get all active fund codes and set up results array
		$args = array(
				'taxonomy' => 'fundcode',
				'hide_empty' => false,
				'orderby' => 'parent',
				'order' => 'ASC',
		);
		$active_fund_codes = get_terms('fundcode', $args);
		$fund_code_ref = array();
		foreach ($active_fund_codes as $term) {
			$fund_code_ref[$term->term_id] = $term;
		}
		foreach ($active_fund_codes as $term) {
			if (array_key_exists($term->name, $totals)) {
				$total = $totals[$term->name];
				unset($totals[$term->name]);
				if (empty($term->parent)) {
					$results[$term->name] = array(
							'total' => $total,
							'children' => array(),
					);
				} else {
					$parent_name = $fund_code_ref[$term->parent]->name;
					$results[$parent_name]['children'][$term->name] = $total;
				}
			}
		}

		// Add in any fund codes that either aren't active or don't exist as posts
		foreach ($totals as $term_name => $total) {
			$results[$term_name] = array(
					'total' => $total,
			);
		}

		$this->recursive_ksort($results);

		return $results;
	}

	private function get_detail() {
		$results = array(
				array( // Headers
						'User ID',
						'Organisation',
						'Title',
						'First Name',
						'Last Name',
						'Address',
						'Suburb',
						'State',
						'Postcode',
						'Country',
						'Date',
						'Fund Code',
						'Description',
						'Amount',
						'Batch',
				),
		);
		$transactions = $this->get_transactions();
		$nonce = wp_create_nonce('bb_cart_batches');
		foreach ($transactions as $transaction) {
			$batch_id = get_post_meta($transaction->ID, 'batch_id', true);
			$batch = '<a href="?page=bb_cart_batch_management&amp;batch='.urlencode($batch_id).'&amp;action=edit&amp;_wpnonce='.$nonce.'">'.get_the_title($batch_id).'</a>';
			$line_items = bb_cart_get_transaction_line_items($transaction->ID);
			if (count($line_items) > 0) {
				foreach ($line_items as $line_item) {
					$fund_code = $this->get_line_item_fund_code($line_item);
					if ($fund_code == $this->selected_fund_code) {
						$amount = get_post_meta($line_item->ID, 'price', true)*get_post_meta($line_item->ID, 'quantity', true);
						$donor = new WP_User($transaction->post_author);
						$country = $donor->bbconnect_address_country_1;
						if (function_exists('bbconnect_helper_country')) {
							$countries = bbconnect_helper_country();
							if (array_key_exists($country, $countries)) {
								$country = $countries[$country];
							}
						}
						$results[] = array(
								$donor->ID,
								$donor->organization,
								$donor->title,
								$donor->user_firstname,
								$donor->user_lastname,
								$donor->bbconnect_address_one_1.'<br>'.$donor->bbconnect_address_two_1,
								$donor->bbconnect_address_city_1,
								$donor->bbconnect_address_state_1,
								$donor->bbconnect_address_postal_code_1,
								$country,
								$transaction->post_date,
								$fund_code,
								$line_item->post_content,
								'$'.number_format($amount, 2),
								$batch,
						);
					}
				}
			} else { // No line items, just pull data from the transaction itself
				$fund_code = get_post_meta($transaction->ID, 'fund_code', true);
				if (empty($fund_code)) {
					$fund_code = 'Blank/Unknown';
				}
				if ($fund_code == $this->selected_fund_code) {
					$amount = get_post_meta($transaction->ID, 'total_amount', true);
					$donor = new WP_User($transaction->post_author);
					$country = $donor->bbconnect_address_country_1;
					if (function_exists('bbconnect_helper_country')) {
						$countries = bbconnect_helper_country();
						if (array_key_exists($country, $countries)) {
							$country = $countries[$country];
						}
					}
					$results[] = array(
							$donor->ID,
							$donor->organization,
							$donor->title,
							$donor->user_firstname,
							$donor->user_lastname,
							$donor->bbconnect_address_one_1.'<br>'.$donor->bbconnect_address_two_1,
							$donor->bbconnect_address_city_1,
							$donor->bbconnect_address_state_1,
							$donor->bbconnect_address_postal_code_1,
							$country,
							$transaction->post_date,
							$fund_code,
							'',
							'$'.number_format($amount, 2),
							$batch,
					);
				}
			}
		}
		return $results;
	}

	private function get_transactions() {
		$start = DateTime::createFromFormat('Y-m-d', $this->selected_month.'-01');
		$end = clone $start;
		$end->add(new DateInterval('P1M'));
		$end->sub(new DateInterval('P1D'));

		// Get transactions for selected month
		$args = array(
				'post_type' => 'transaction',
				'posts_per_page' => -1,
				'date_query' => array(
						array(
								'after' => array(
										'year'  => $start->format('Y'),
										'month' => $start->format('n'),
										'day'   => 1,
								),
								'before' => array(
										'year'  => $end->format('Y'),
										'month' => $end->format('n'),
										'day'   => $end->format('d'),
								),
								'inclusive' => true,
						),
				),
		);
		return get_posts($args);
	}

	private function get_line_item_fund_code(WP_Post $line_item) {
		$txn_fund_codes = wp_get_object_terms($line_item->ID, 'fundcode');
		if (!empty($txn_fund_codes)) {
			$fund_code_term = array_shift($txn_fund_codes);
			$fund_code = $fund_code_term->name;
		} else {
			$fund_code = get_post_meta($line_item->ID, 'fund_code', true);
		}
		if (empty($fund_code)) {
			$fund_code = 'Blank/Unknown';
		}
		return $fund_code;
	}

	private function get_date_filter() {
		// Get earliest transaction
		$args = array(
				'post_type' => 'transaction',
				'posts_per_page' => 1,
				'orderby' => 'date',
				'order' => 'ASC',
		);
		$transactions = get_posts($args);
		$earliest_transaction = array_shift($transactions);
		if ($earliest_transaction instanceof WP_Post) {
			$months = array();
			$report_date = new DateTime($earliest_transaction->post_date);
			$report_date->setDate($report_date->format('Y'), $report_date->format('m'), 1);
			$report_date->setTime(0, 0, 0);
			while ($report_date->getTimestamp() <= current_time('timestamp')) {
				$months[$report_date->format('Y-m')] = $report_date->format('F Y');
				$report_date->add(new DateInterval('P1M'));
			}
			$months = array_reverse($months);
			echo '<div class="filters">'."\n";
			echo '<form action="" method="get">'."\n";
			echo '<input type="hidden" name="page" value="'.$this->slug.'">'."\n";
			echo '<input type="hidden" name="fund_code" value="'.urlencode($this->selected_fund_code).'">'."\n";
			echo 'Select Month: <select id="fund_code_report_filter_month" name="month">'."\n";
			foreach ($months as $month => $label) {
				echo '<option value="'.$month.'" '.selected($month, $this->selected_month, false).'>'.$label.'</option>'."\n";
			}
			echo '</select>'."\n";
			echo '</form>'."\n";
			echo '</div>'."\n";
		}
	}

	private function setup_data() {
		$now = current_time('timestamp');
		$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m', $now);
		$this->selected_month = $selected_month;
		$this->selected_fund_code = isset($_GET['fund_code']) ? urldecode($_GET['fund_code']) : '';
	}

	private function recursive_ksort(&$array) {
		foreach ($array as &$value) {
			if (is_array($value)) {
				$this->recursive_ksort($value);
			}
		}
		ksort($array);
	}
}
new bb_cart_fund_code_report();
