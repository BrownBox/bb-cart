<?php
class bb_cart_batch_management {
    public function __construct() {
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'));
            add_action('admin_init', array($this, 'download_summary'));
        }
    }

    public function add_plugin_page() {
        add_submenu_page('bb_cart_settings', 'Transaction Batches', 'Transaction Batches', 'add_users', 'bb_cart_batch_management', array($this, 'create_admin_page'));
    }

    public function create_admin_page() {
        $clean_url = remove_query_arg(array('_wpnonce', 'action', 'sub_action', 'batch', 'paged', 'item'));
        if (!empty($_GET['action'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'bb_cart_batches')) {
                switch ($_GET['action']) {
                    case 'confirm':
                        $this->confirm_batches($_GET['batch']);
                        echo '<div class="notice notice-success"><p>Confirmed successfully.</p></div>';
                        break;
                    case 'trash':
                        $this->delete_batches($_GET['batch']);
                        echo '<div class="notice notice-success"><p>Deleted successfully.</p></div>';
                        break;
                    case 'email_receipts':
                        $this->email_receipts($_GET['batch']);
                    case 'edit':
                        return $this->edit_batch_page($clean_url);
                        break;
                }
            } else {
                echo '<div class="notice notice-error"><p>Invalid action. Please <a href="'.$clean_url.'">reload the page</a> and try again.</p></div>';
            }
        }

        $date_format = get_option('date_format');
        $orderby = 'date';
        $order = 'asc';
        $statuses = array(
                'pending' => 'Pending',
                'publish' => 'Confirmed',
                'all' => 'All',
        );
        $selected_status = 'pending';
        $page_size = 50;
        $paged = max(1, (int)$_GET['paged']);
        $nonce = wp_create_nonce('bb_cart_batches');
        if (!empty($_GET['orderby'])) {
            $orderby = $_GET['orderby'];
        }
        if (!empty($_GET['order']) && in_array($_GET['order'], array('asc', 'desc'))) {
            $order = $_GET['order'];
        }
        if (!empty($_GET['post_status']) && in_array($_GET['post_status'], array_keys($statuses))) {
            $selected_status = $_GET['post_status'];
        }
        $args = array(
                'post_type' => 'transactionbatch',
                'posts_per_page' => -1,
                'post_status' => $selected_status,
                'orderby' => $orderby,
                'order' => $order,
        );
        $batches = get_posts($args);
        $total_pages = ceil(count($batches)/$page_size);
        echo '<div class="wrap">'."\n";
        echo '<h2>Transaction Batches</h2>'."\n";
        echo '<ul class="subsubsub">'."\n";
        $s = 1;
        foreach ($statuses as $status => $status_name) {
            $class = $count = '';
            if ($selected_status == $status) {
                $class = 'current';
                $count = ' <span class="count">('.count($batches).')</span>';
            }
            echo '    <li class="'.$status.'"><a href="'.add_query_arg(array('post_status' => $status), $clean_url).'" class="'.$class.'">'.$status_name.$count.'</a>';
            if ($s < count($statuses)) {
                echo ' |';
            }
            echo '</li>'."\n";
            $s++;
        }
        echo '</ul>'."\n";
        echo '<form id="posts-filter" method="get">'."\n";
        echo '    <input type="hidden" name="page" value="'.$_GET['page'].'">'."\n";
        echo '    <input type="hidden" name="orderby" value="'.$orderby.'">'."\n";
        echo '    <input type="hidden" name="order" value="'.$order.'">'."\n";
        echo '    <input type="hidden" name="_wpnonce" value="'.$nonce.'">'."\n";
        echo '    <div class="tablenav top">'."\n";
        echo '        <div class="alignleft actions bulkactions">'."\n";
        echo '            <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>'."\n";
        echo '            <select name="action" id="bulk-action-selector-top">'."\n";
        echo '                <option value="-1">Bulk Actions</option>'."\n";
        echo '                <option value="confirm">Confirm</option>'."\n";
        echo '            </select>'."\n";
        echo '            <input type="hidden" name="selected_batches" id="selected_batches" value="">'."\n";
        echo '            <input id="doaction" class="button action" value="Apply" type="submit">'."\n";
        echo '        </div>'."\n";
        echo '        <div class="alignleft actions">'."\n";
        echo '        </div>'."\n";
        echo '        <h2 class="screen-reader-text">Batches list navigation</h2>'."\n";
        echo '        <div class="tablenav-pages">'."\n";
        echo '            <span class="displaying-num">'.count($batches).' items</span>'."\n";
        $big = 99999999;
        echo paginate_links(array(
                'base' => str_replace(array($big, '#038;'), array('%#%', '&amp;'), get_pagenum_link($big)),
                'format' => '?paged=%#%',
                'current' => $paged,
                'total' => $total_pages,
                'before_page_number' => '<span class="screen-reader-text">Page </span>'
        ));
        echo '        </div>'."\n";
        echo '    </div>'."\n";

        $col_count = 0;
        ob_start();
        echo '            <tr>'."\n";
        $col_count++;
        echo '                <th id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></th>'."\n";
        $url = add_query_arg($this->get_sorting_args('batch', 'asc', $sort_class), $clean_url);
        if ($orderby == 'batch') {
            $sort_class .= ' sorted';
        }
        $col_count++;
        echo '                <th style="" class="manage-column column-title column-primary sortable '.$sort_class.' ui-sortable" id="title" scope="col">'."\n";
        echo '                    <a href="'.$url.'"><span>Batch Name</span><span class="sorting-indicator"></span></a>'."\n";
        echo '                </th>'."\n";
        $col_count++;
        echo '                <th style="" class="manage-column" id="transactions" scope="col">Batch Summary</th>'."\n";
        $col_count++;
        echo '                <th style="" class="manage-column" id="comments" scope="col">Description</th>'."\n";
        $url = add_query_arg($this->get_sorting_args('date', 'desc', $sort_class), $clean_url);
        if ($orderby == 'date') {
            $sort_class .= ' sorted';
        }
        $col_count++;
        echo '                <th style="" class="manage-column column-date sortable '.$sort_class.' ui-sortable" id="date" scope="col">'."\n";
        echo '                    <a href="'.$url.'"><span>Date</span><span class="sorting-indicator"></span></a>'."\n";
        echo '                </th>'."\n";
        echo '            </tr>'."\n";
        $table_headers = ob_get_clean();

        echo '    <table class="wp-list-table widefat fixed striped action_items">'."\n";
        echo '        <thead>'."\n";
        echo $table_headers;
        echo '        </thead>'."\n";
        echo '        <tbody id="the-list">'."\n";
        if (count($batches) == 0) {
            echo '            <tr class="no-items">'."\n";
            echo '                <td class="colspanchange" colspan="'.$col_count.'">No items found</td>'."\n";
            echo '            </tr>'."\n";
        } else {
            $pages = array_chunk($batches, $page_size, true);
            foreach ($pages[$paged-1] as $batch) {
                echo '            <tr class="type-page status-publish hentry iedit author-other level-0" id="note-'.$batch->ID.'">'."\n";
                $batch_date = bb_cart_get_datetime($batch->post_date);
                echo '                <th scope="row" class="check-column">';
                if ($batch->post_status == 'pending') {
                    echo '                    <input id="cb-select-'.$batch->ID.'" name="batch[]" value="'.$batch->ID.'" type="checkbox">';
                }
                echo '                </th>'."\n";
                $edit_url = add_query_arg(array('batch' => urlencode($batch->ID), 'action' => 'edit', '_wpnonce' => $nonce), $clean_url);
                echo '                <td class="post-title has-row-actions page-title column-title"><a href="'.$edit_url.'"><strong>'.$batch->post_title.'</strong></a>'."\n";
                echo '                    <div class="row-actions">'."\n";
                echo '                        <span class="edit"><a href="'.$edit_url.'" data-batch="'.$batch->ID.'" target="_blank">View Details</a> | </span>'."\n";
                if ($batch->post_status == 'pending') {
                    $confirm_url = add_query_arg(array('batch[]' => urlencode($batch->ID), 'action' => 'confirm', '_wpnonce' => $nonce), $clean_url);
                    echo '                        <span class="publish"><a href="'.$confirm_url.'" class="submitpublish" data-batch="'.$batch->ID.'">Confirm</a> | </span>'."\n";
                }
                $dl_url = add_query_arg(array('batch[]' => urlencode($batch->ID), 'action' => 'download', '_wpnonce' => $nonce), $clean_url);
                echo '                        <span class="view"><a href="'.$dl_url.'" data-batch="'.$batch->ID.'">Download Summary</a> | </span>'."\n";
                $trash_url = add_query_arg(array('batch[]' => urlencode($batch->ID), 'action' => 'trash', '_wpnonce' => $nonce), $clean_url);
                echo '                        <span class="delete"><a href="'.$trash_url.'" class="submitdelete" data-batch="'.$batch->ID.'" onclick="return confirm(\'Are you sure you want to delete this batch and all associated transactions? This cannot be undone!\');">Delete</a></span>'."\n";
                echo '                    </div>'."\n";
                echo '                </td>'."\n";
                echo '                <td class="">'.$this->get_batch_summary_html($batch->ID).'</td>'."\n";
                echo '                <td class="">'.$batch->post_content.'</td>'."\n";
                echo '                <td class="post-date page-date column-date">'.$batch_date->format($date_format).'</td>'."\n";
                echo '            </tr>'."\n";
            }
        }
        echo '        </tbody>'."\n";
        echo '        <tfoot>'."\n";
        echo $table_headers;
        echo '        </tfoot>'."\n";
        echo '    </table>'."\n";
        echo '</form>'."\n";
        echo '</div>'."\n";
    }

    private function edit_batch_page($back_url) {
        $batch_id = (int)$_GET['batch'];
        $batch = get_post($batch_id);
        $can_edit = current_user_can('manage_options') && $batch->post_status == 'pending';
        $ajax_url = admin_url('admin-ajax.php');

        if (!$batch instanceof WP_Post || $batch->post_type != 'transactionbatch') {
            echo '<div class="notice notice-error"><p>Invalid action. Please <a href="'.$back_url.'">reload the page</a> and try again.</p></div>';
            return;
        }

        $clean_url = remove_query_arg(array('sub_action', 'item'));
        if (!empty($_GET['sub_action'])) {
            switch ($_GET['sub_action']) {
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

        echo '<div class="wrap">'."\n";
        $edit_args = array(
                'class' => 'button',
                'url' => add_query_arg(array('action' => 'bb_cart_load_edit_batch', 'id' => $batch->ID), $ajax_url),
        );
        echo '<p style="float: right;">'."\n";
        echo '<a href="'.add_query_arg(array('action' => 'email_receipts')).'" class="button" onclick="return confirm(\'This will email receipts for all transactions in this batch which have not yet been receipted and where the donor has a valid email address. Are you sure you wish to continue?\');">Email Receipts</a>'."\n";
        if ($can_edit) {
            echo bb_cart_ajax_modal_link('Edit Batch Details', $edit_args)."\n";
        }
        echo '</p>'."\n";
        echo '<h2>Batch Details: '.$batch->post_title.'</h2>'."\n";
        $transactions = bb_cart_get_batch_transactions($batch_id);
        if ($can_edit) {
            echo '    <div class="tablenav top">'."\n";
            echo '        <div class="alignleft actions bulkactions">'."\n";
            echo '            <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>'."\n";
            echo '            <select name="action" id="bulk-action-selector-top">'."\n";
            echo '                <option value="-1">Bulk Actions</option>'."\n";
            echo '                <option value="movetransactions">Move</option>'."\n";
            echo '                <option value="newbatch">Create New Batch</option>'."\n";
            echo '            </select>'."\n";
            echo '            <input type="hidden" name="selected_line_items" id="selected_line_items" value="">'."\n";
            echo '            <input id="doaction" class="button action" value="Apply" type="submit" onclick="bb_cart_batch_details_bulkaction();">'."\n";
            echo '        </div>'."\n";
            echo '    </div>'."\n";
?>
    <script>
    function bb_cart_batch_details_bulkaction() {
        var action = jQuery('#bulk-action-selector-top').val();
        if (action != '-1') {
            var url = '<?php echo $ajax_url; ?>?action=bb_cart_load_'+action;
            jQuery('input[type=checkbox][name=line_item]').each(function() {
                if (jQuery(this).prop('checked')) {
                    url += '&items[]='+jQuery(this).val();
                }
            });
            jQuery('body').append('<a id="bb_cart_templink" style="display: none;" class="thickbox" href="'+url+'&width=600&height=400"></a>');
            jQuery('#bb_cart_templink').click().remove();
        }
    }
    </script>
<?php
        }
        echo '    <table class="wp-list-table widefat fixed striped action_items">'."\n";
        echo '        <thead>'."\n";
        echo '            <tr>'."\n";
        echo '                <th id="cb" class="manage-column column-cb check-column">'."\n";
        if ($can_edit) {
            echo '                    <input id="cb-select-all-1" type="checkbox">'."\n";
        }
        echo '                </th>'."\n";
        echo '                <th style="" class="manage-column column-postdate column-primary" id="date" scope="col">Date</th>'."\n";
        echo '                <th style="" class="manage-column column-author" id="author" scope="col">Donor Name</th>'."\n";
        echo '                <th style="" class="manage-column" id="fundcode" scope="col">Fund Code</th>'."\n";
        echo '                <th style="" class="manage-column" id="comments" scope="col">Description</th>'."\n";
        echo '                <th style="text-align: center;" class="manage-column" id="receipted" scope="col">Receipted</th>'."\n";
        echo '                <th style="text-align: right;" class="manage-column" id="amount" scope="col">Amount</th>'."\n";
        echo '            </tr>'."\n";
        echo '        </thead>'."\n";
        echo '        <tbody id="the-list">'."\n";
        $total = 0;
        foreach ($transactions as $transaction) {
            $author = new WP_User($transaction->post_author);
            $can_delete = strtolower(get_post_meta($transaction->ID, 'transaction_type', true)) == 'offline';
            $receipted = get_post_meta($transaction->ID, 'is_receipted', true) == 'true' ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>';
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
                    $total += $amount;
                    echo '            <tr class="type-page status-publish hentry iedit author-other level-0" id="lineitem-'.$line_item->ID.'">'."\n";
                    echo '                <th scope="row" class="check-column">';
                    if ($can_edit) {
                        echo '                    <input id="cb-select-'.$line_item->ID.'" name="line_item" value="'.$line_item->ID.'" type="checkbox">';
                    }
                    echo '                </th>'."\n";
                    echo '                <td class="post-date has-row-actions page-date column-date"><strong>'.date_i18n(get_option('date_format'), strtotime($line_item->post_date)).'</strong>'."\n";
                    if ($can_edit) {
                        $edit_args = array(
                                'url' => add_query_arg(array('action' => 'bb_cart_load_edit_transaction_line', 'id' => $line_item->ID), $ajax_url),
                        );
                        $split_args = array(
                                'url' => add_query_arg(array('action' => 'bb_cart_load_split_transaction_line', 'id' => $line_item->ID), $ajax_url),
                        );
                        echo '                    <div class="row-actions">'."\n";
                        if ($can_delete) {
                            $trash_url = add_query_arg(array('item[]' => urlencode($line_item->ID), 'sub_action' => 'trash'), $clean_url);
                            echo '                        <span class="delete"><a href="'.$trash_url.'" class="submitdelete" data-item="'.$line_item->ID.'" onclick="return confirm(\'Are you sure you want to delete this item? This cannot be undone!\');">Delete</a> | </span>'."\n";
                        }
                        echo '                        <span class="edit">'.bb_cart_ajax_modal_link('Edit', $edit_args).' | </span>'."\n";
                        echo '                        <span class="edit">'.bb_cart_ajax_modal_link('Split', $split_args).'</span>'."\n";
                        echo '                    </div>'."\n";
                    }
                    echo '                <td class="post-author page-author column-author">'.$author->display_name.'</td>'."\n";
                    echo '                <td class="">'.$fund_code.'</td>'."\n";
                    echo '                <td class="">'.apply_filters('the_content', $line_item->post_content).'</td>'."\n";
                    echo '                <td style="text-align: center;">'.$receipted.'</td>'."\n";
                    echo '                <td style="text-align: right;">$'.number_format($amount, 2).'</td>'."\n";
                    echo '            </tr>'."\n";
                }
            }
        }
        echo '        </tbody>'."\n";
        echo '        <tfoot>'."\n";
        echo '            <tr>'."\n";
        echo '                <th colspan="7" style="text-align: right;" class="manage-column" id="amount" scope="col"><span style="border-top: 1px solid black;">Total: $'.number_format($total, 2).'</span></th>'."\n";
        echo '            </tr>'."\n";
        echo '        </tfoot>'."\n";
        echo '    </table>'."\n";
        echo '</div>'."\n";
    }

    private function get_sorting_args($field, $order = 'asc', &$current_sort) {
        $sorting = array(
                'orderby' => $field,
        );
        if ($order != 'desc') { // Sanity check
            $order = 'asc';
        }
        $current_sort = $order == 'asc' ? 'desc' : 'asc';
        if (!empty($_GET['order'])) {
            if ($_GET['orderby'] == $field && $_GET['order'] == $order) {
                $current_sort = $order;
                $order = $order == 'asc' ? 'desc' : 'asc';
            }
        }
        $sorting['order'] = $order;
        return $sorting;
    }

    private function get_batch_summary_html($batch_id) {
        $transactions = bb_cart_get_batch_transactions($batch_id);
        $groups = $this->group_transactions_by_fund_code($transactions);
        $output = '<p>';
        $total = 0;
        foreach ($groups as $fund_code => $details) {
            $output .= '<strong>'.$fund_code.'</strong> ('.$details['transaction_count'].' transactions): $'.number_format($details['total'], 2).'<br>'."\n";
            $total += $details['total'];
        }
        $output .= '</p>';
        $output .= '<p><strong>Total: $'.number_format($total, 2).'</strong></p>';
        return $output;
    }

    public function download_summary() {
        global $pagenow;
        if ($pagenow == 'users.php' && $_GET['page'] == 'bb_cart_batch_management' && !empty($_GET['batch']) && $_GET['action'] == 'download' && wp_verify_nonce($_GET['_wpnonce'], 'bb_cart_batches')) {
            $batch_id = array_shift($_GET['batch']);
            $output = get_the_date('', $batch_id)."\n\n";
            $transactions = bb_cart_get_batch_transactions($batch_id);
            $groups = $this->group_transactions_by_fund_code($transactions);
            $total = 0;
            $output .= str_pad('Code', 50).str_pad('# of Transactions', 20, ' ', STR_PAD_LEFT).str_pad('Amount', 20, ' ', STR_PAD_LEFT)."\n";
            $output .= str_pad('', 90, '-')."\n";
            foreach ($groups as $fund_code => $details) {
                $output .= str_pad($fund_code, 50).str_pad($details['transaction_count'], 20, ' ', STR_PAD_LEFT).str_pad('$'.number_format($details['total'], 2), 20, ' ', STR_PAD_LEFT)."\n";
                $total += $details['total'];
            }
            $output .= str_pad(str_pad('', 15, '-'), 90, ' ', STR_PAD_LEFT)."\n";
            $output .= str_pad('Total', 80, ' ', STR_PAD_LEFT).str_pad('$'.number_format($total, 2), 10, ' ', STR_PAD_LEFT);

            header("Content-type: text/plain");
            header("Content-Length: ".strlen($output));
            header("Content-Disposition: attachment; filename=".get_the_title($batch_id).'.txt');
            header("Pragma: no-cache");
            header("Expires: 0");
            print $output;
            exit;
        }
    }

    private function confirm_batches($batch_ids) {
        if (!is_array($batch_ids)) {
            $batch_ids = explode(',', $batch_ids);
        }
        foreach ($batch_ids as $batch_id) {
            wp_publish_post($batch_id);
        }
    }

    private function delete_batches($batch_ids) {
        if (!is_array($batch_ids)) {
            $batch_ids = explode(',', $batch_ids);
        }
        foreach ($batch_ids as $batch_id) {
            $transactions = bb_cart_get_batch_transactions($batch_id);
            bb_cart_delete_transactions($transactions);
            wp_trash_post($batch_id);
        }
    }

    private function email_receipts($batch_id) {
        $transactions = bb_cart_get_batch_transactions($batch_id);
        $count = 0;
        foreach ($transactions as $transaction) {
            if (get_post_meta($transaction->ID, 'is_receipted', true) != 'true') {
                if (bb_cart_offline_email_receipts::send_email_receipt($transaction)) {
                    $count++;
                }
            }
        }
        echo '<div class="notice notice-success"><p>'.$count.' emails sent.</p></div>';
    }

    private function group_transactions_by_fund_code($transactions) {
        $totals = array();
        $fund_code_template = array(
                'transaction_count' => 0,
                'total' => 0,
        );
        foreach ($transactions as $transaction) {
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
                    if (!isset($totals[$fund_code])) {
                        $totals[$fund_code] = $fund_code_template;
                    }
                    $totals[$fund_code]['total'] += $amount;
                    $totals[$fund_code]['transaction_count']++;
                }
            } else { // No line items, just pull data from the transaction itself
                $fund_code = get_post_meta($transaction->ID, 'fund_code', true);
                if (empty($fund_code)) {
                    $fund_code = 'Blank/Unknown';
                }
                $amount = get_post_meta($transaction->ID, 'total_amount', true);
                if (!isset($totals[$fund_code])) {
                    $totals[$fund_code] = $fund_code_template;
                }
                $totals[$fund_code]['total'] += $amount;
                $totals[$fund_code]['transaction_count']++;
            }
        }
        return $totals;
    }
}
new bb_cart_batch_management();
