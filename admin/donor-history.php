<?php
add_filter('bbconnect_user_tabs', 'bb_cart_donor_history_register_profile_tab', 50, 1);
function bb_cart_donor_history_register_profile_tab(array $tabs) {
    $tabs['donor_history'] = array(
            'title' => 'Transaction History',
            'subs' => false,
    );
    return $tabs;
}

add_action('bbconnect_admin_profile_donor_history', 'bb_cart_donor_history_profile_tab');
function bb_cart_donor_history_profile_tab() {
    // Set up a few variables
    global $user_id;
    $can_edit = current_user_can('manage_options');
    $ajax_url = admin_url('admin-ajax.php');
    $transactions = bb_cart_get_user_transactions($user_id);
    $batch_nonce = wp_create_nonce('bb_cart_batches');

    $clean_url = remove_query_arg(array('trans_action', 'item'));
    if (!empty($_GET['trans_action'])) {
        switch ($_GET['trans_action']) {
            case 'trash':
                $items = $_GET['item'];
                // Before we delete the line items we need a list of transactions to update
                $check_transactions = array();
                foreach ($items as $item) {
                    $check_transactions[] = bb_cart_get_transaction_from_line_item($item);
                }
                $check_transactions = array_unique($check_transactions);
                bb_cart_delete_line_items($items);
                foreach ($check_transactions as $check_transaction) {
                    $remaining_line_items = bb_cart_get_transaction_line_items($check_transaction->ID);
                    if (empty($remaining_line_items)) {
                        bb_cart_delete_transactions(array($check_transaction->ID));
                    } else {
                        $donation_amount = $total_amount = 0;
                        foreach ($remaining_line_items as $remaining_line_item) {
                            $price = get_post_meta($remaining_line_item->ID, 'price', true);
                            $total_amount += $price;
                            $fund_code = bb_cart_get_fund_code($remaining_line_item->ID);
                            if (get_post_meta($fund_code, 'transaction_type', true) == 'donation') {
                                $donation_amount += $price;
                            }
                        }
                        update_post_meta($check_transaction->ID, 'total_amount', $total_amount);
                        update_post_meta($check_transaction->ID, 'donation_amount', $donation_amount);
                    }
                }
                echo '<div class="notice notice-success"><p>Deleted successfully.</p></div>';
                break;
        }
    }

    echo '    <table class="wp-list-table widefat fixed striped">'."\n";
    echo '        <thead>'."\n";
    echo '            <tr>'."\n";
    echo '                <th style="" class="manage-column column-date column-primary" id="date" scope="col">Date</th>'."\n";
    echo '                <th style="" class="manage-column" id="fundcode" scope="col">Fund Code</th>'."\n";
    echo '                <th style="" class="manage-column" id="comments" scope="col">Description</th>'."\n";
    echo '                <th style="text-align: right;" class="manage-column" id="amount" scope="col">Amount</th>'."\n";
    echo '                <th style="text-align: center;" class="manage-column" id="tax_deductible" scope="col">Tax Deductible</th>'."\n";
    if ($can_edit) {
        echo '                <th style="" class="manage-column" id="actions" scope="col">&nbsp;</th>'."\n";
    }
    echo '            </tr>'."\n";
    echo '        </thead>'."\n";
    echo '        <tbody id="the-list">'."\n";
    foreach ($transactions as $transaction) {
        $can_delete = strtolower(get_post_meta($transaction->ID, 'transaction_type', true)) == 'offline';
        $deductible = get_post_meta($transaction->ID, 'is_tax_deductible', true) == 'true' ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>';
        $line_items = bb_cart_get_transaction_line_items($transaction->ID);
        if ($line_items && count($line_items) > 0) {
            foreach ($line_items as $line_item) {
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
                $amount = get_post_meta($line_item->ID, 'price', true)*get_post_meta($line_item->ID, 'quantity', true);
                echo '            <tr class="type-page status-publish hentry iedit author-other level-0" id="lineitem-'.$line_item->ID.'">'."\n";
                echo '                <td class="date">'.date_i18n(get_option('date_format'), strtotime($transaction->post_date)).'</td>'."\n";
                echo '                <td class="">'.$fund_code.'</td>'."\n";
                echo '                <td class="">'.apply_filters('the_content', $line_item->post_content).'</td>'."\n";
                echo '                <td style="text-align: right;">'.bb_cart_format_currency($amount).'</td>'."\n";
                echo '                <td style="text-align: center;">'.$deductible.'</td>'."\n";
                if ($can_edit) {
                    $edit_args = array(
                            'url' => add_query_arg(array('action' => 'bb_cart_load_edit_transaction_line', 'id' => $line_item->ID), $ajax_url),
                    );
                    $split_args = array(
                            'url' => add_query_arg(array('action' => 'bb_cart_load_split_transaction_line', 'id' => $line_item->ID), $ajax_url),
                    );
                    echo '                <td style="text-align: center;">'."\n";
                    if (current_user_can('add_users')) {
                        $batch_id = get_post_meta($transaction->ID, 'batch_id', true);
                        if ($batch_id > 0) {
                            $batch_url = add_query_arg(array('page' => 'bb_cart_batch_management', 'batch' => urlencode($batch_id), 'action' => 'edit', '_wpnonce' => $batch_nonce), admin_url('admin.php'));
                            echo '                    <span class="edit"><a href="'.$batch_url.'" class="submit">View Batch</a> | </span>'."\n";
                        }
                    }
                    if ($can_delete) {
                        $trash_url = add_query_arg(array('item[]' => urlencode($line_item->ID), 'trans_action' => 'trash'), $clean_url);
                        echo '                    <span class="edit"><a href="'.$trash_url.'" class="submitdelete" data-item="'.$line_item->ID.'" onclick="return confirm(\'Are you sure you want to delete this item? This cannot be undone!\');">Delete</a> | </span>'."\n";
                    }
                    echo '                    <span class="edit">'.bb_cart_ajax_modal_link('Edit', $edit_args).' | </span>'."\n";
                    echo '                    <span class="edit">'.bb_cart_ajax_modal_link('Split', $split_args).'</span>'."\n";
                    echo '                </td>'."\n";
                }
                echo '            </tr>'."\n";
            }
        } else {
        	$fund_code = get_post_meta($transaction->ID, 'fund_code', true);
        	if (empty($fund_code)) {
        		$fund_code = 'Blank/Unknown';
        	}
        	$amount = get_post_meta($transaction->ID, 'total_amount', true);
        	echo '            <tr class="type-page status-publish hentry iedit author-other level-0" id="transaction-'.$transaction->ID.'">'."\n";
        	echo '                <td class="date">'.date_i18n(get_option('date_format'), strtotime($transaction->post_date))."\n";
        	echo '                <td class="">'.$fund_code.'</td>'."\n";
        	echo '                <td class=""></td>'."\n";
        	echo '                <td style="text-align: right;">'.bb_cart_format_currency($amount).'</td>'."\n";
        	echo '                <td style="text-align: center;">'.$deductible.'</td>'."\n";
        	echo '                <td></td>'."\n";
        	echo '            </tr>'."\n";
        }
    }
    echo '        </tbody>'."\n";
    echo '    </table>'."\n";
}

function bb_cart_get_user_transactions($user_id) {
    $args = array(
            'post_type' => 'transaction',
            'posts_per_page' => -1,
            'author' => $user_id,
    );
    return get_posts($args);
}
