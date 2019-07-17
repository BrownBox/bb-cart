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

    echo '    <table class="wp-list-table widefat fixed striped">'."\n";
    echo '        <thead>'."\n";
    echo '            <tr>'."\n";
    echo '                <th style="" class="manage-column column-date column-primary" id="date" scope="col">Date</th>'."\n";
    echo '                <th style="" class="manage-column" id="fundcode" scope="col">Fund Code</th>'."\n";
    echo '                <th style="" class="manage-column" id="comments" scope="col">Description</th>'."\n";
    echo '                <th style="text-align: right;" class="manage-column" id="amount" scope="col">Amount</th>'."\n";
    echo '                <th style="text-align: center;" class="manage-column" id="tax_deductible" scope="col">Tax Deductible</th>'."\n";
    if ($can_edit) {
        echo '                <th style="" colspan="2" class="manage-column" id="actions" scope="col">&nbsp;</th>'."\n";
    }
    echo '            </tr>'."\n";
    echo '        </thead>'."\n";
    echo '        <tbody id="the-list">'."\n";
    foreach ($transactions as $transaction) {
        $deductible = get_post_meta($transaction->ID, 'is_tax_deductible', true) == 'true' ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>';
        $args = array(
                'post_type' => 'transactionlineitem',
                'posts_per_page' => -1,
                'tax_query' => array(
                        array(
                                'taxonomy' => 'transaction',
                                'field' => 'slug',
                                'terms' => $transaction->ID,
                        ),
                ),
        );
        $line_items = get_posts($args);
        if (count($line_items) > 0) {
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
                echo '                <td class="date">'.$transaction->post_date.'</td>'."\n";
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
                    echo '                <td style="text-align: center;">'.bb_cart_ajax_modal_link('Edit', $edit_args).'</td>'."\n";
                    echo '                <td style="text-align: center;">'.bb_cart_ajax_modal_link('Split', $split_args).'</td>'."\n";
                }
                echo '            </tr>'."\n";
            }
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
