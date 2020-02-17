<?php
add_action('admin_init', 'bb_cart_create_modal');
function bb_cart_create_modal() {
    add_thickbox();
    add_action('admin_footer', function() {
        ?>
<div id="bb_cart_modal" style="display: none;">
    <div style="overflow: scroll;" id="bb_cart_thickbox_contents">Loading, please wait...</div>
</div>
<?php
    });
}

function bb_cart_ajax_modal_link($text, $args = array()) {
    $default_args = array(
            'width' => 600,
            'height' => 400,
            'url' => '',
            'class' => '',
    );
    $args = wp_parse_args($args, $default_args);
    return sprintf('<a class="thickbox %s" href="%s&width=%d&height=%d">%s</a>', $args['class'], $args['url'], $args['width'], $args['height'], $text);
}

add_action('wp_ajax_bb_cart_load_edit_transaction_line', 'bb_cart_load_edit_transaction_line');
function bb_cart_load_edit_transaction_line() {
    $line_item = get_post((int)$_GET['id']);
    if (!$line_item instanceof WP_Post || get_post_type($line_item) != 'transactionlineitem') {
        die('Invalid ID');
    }
    $transaction = bb_cart_get_transaction_from_line_item($line_item);
    $transaction_type = get_post_meta($transaction->ID, 'transaction_type', true);

    $args = array(
            'post_type' => 'fundcode',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
    );
    $fund_codes = get_posts($args);

    $user = new WP_User($line_item->post_author);
?>
    <h3>Updating <?php echo bb_cart_format_currency(get_post_meta($line_item->ID, 'price', true)); ?> from <?php echo $user->display_name; ?> on <?php echo date_i18n(get_option('date_format'), strtotime($line_item->post_date)); ?></h3>
    <form action="" method="post">
        <p><label for="edit_fund_code">Fund Code: </label>
        <select id="edit_fund_code" name="edit_fund_code">
<?php
    $current_code = bb_cart_get_fund_code($line_item->ID);
    foreach ($fund_codes as $fund_code) {
?>
            <option value="<?php echo $fund_code->ID; ?>"<?php selected($fund_code->ID, $current_code); ?>><?php echo $fund_code->post_title; ?></option>
<?php
    }
?>
        </select></p>
        <p><label for="edit_date">Date: </label> <input id="edit_date" name="edit_date" type="date" value="<?php echo date('Y-m-d', strtotime($line_item->post_date)); ?>"></p>
<?php
    if (strtolower($transaction_type) == 'offline') {
?>
        <p><label for="edit_amount">Amount: </label> <input id="edit_amount" name="edit_amount" type="number" min="0.01" value="<?php echo get_post_meta($line_item->ID, 'price', true); ?>"></p>
<?php
    }
?>
        <input type="submit" value="Update" onclick="bb_cart_update_transaction_line(); return false;">
    </form>
    <script>
        function bb_cart_update_transaction_line() {
            var data = {
                    'action': 'bb_cart_update_transaction_line',
                    'id': <?php echo $line_item->ID; ?>,
                    'fund_code': jQuery('#edit_fund_code').val(),
                    'date': jQuery('#edit_date').val(),
                    'amount': jQuery('#edit_amount').val()
            };
            jQuery.post(ajaxurl, data, function(response) {
                alert(response);
                window.location.reload();
            });
        }
    </script>
<?php
    die();
}

add_action('wp_ajax_bb_cart_update_transaction_line', 'bb_cart_update_transaction_line');
function bb_cart_update_transaction_line() {
    $line_item = get_post((int)$_POST['id']);
    if (!$line_item instanceof WP_Post || get_post_type($line_item) != 'transactionlineitem') {
        die('Invalid ID');
    }

    $fund_code = (int)$_POST['fund_code'];
    if (get_post_type($fund_code) != 'fundcode') {
        die('Invalid Fund Code');
    }

    $date = $_POST['date'];
    if (strtotime($date) === false) {
        die('Invalid Date');
    } else {
        $date = date('Y-m-d', strtotime($date));
    }

    // Changes impacting just this item
    $fund_code_term = get_term_by('slug', $fund_code, 'fundcode'); // Have to pass term ID rather than slug
    wp_set_post_terms($line_item->ID, $fund_code_term->term_id, 'fundcode');

    // Changes impacting the whole transaction
    $transaction = bb_cart_get_transaction_from_line_item($line_item);
    $transaction_type = get_post_meta($transaction->ID, 'transaction_type', true);
    if (strtolower($transaction_type) == 'offline') {
        if (!empty($_POST['amount'])) {
            $new_amount = $_POST['amount'];
            $old_amount = get_post_meta($line_item->ID, 'price', true);
            $diff = $old_amount - $new_amount;
            update_post_meta($line_item->ID, 'price', $new_amount);

            $original_amount = get_post_meta($transaction->ID, 'total_amount', true);
            update_post_meta($transaction->ID, 'total_amount', $original_amount-$diff);

            // Now we need to check whether it's a donation so we can update the donation amount too
            $fund_code_type = get_post_meta($fund_code, 'transaction_type', true);
            if ($fund_code_type == 'donation') {
                $original_amount = get_post_meta($transaction->ID, 'donation_amount', true);
                update_post_meta($transaction->ID, 'donation_amount', $original_amount-$diff);
            }
        }
    }

    bb_cart_change_transaction_date($transaction->ID, $date);

    die('Updated Successfully');
}

add_action('wp_ajax_bb_cart_load_split_transaction_line', 'bb_cart_load_split_transaction_line');
function bb_cart_load_split_transaction_line() {
    $line_item = get_post((int)$_GET['id']);
    if (!$line_item instanceof WP_Post || get_post_type($line_item) != 'transactionlineitem') {
        die('Invalid ID');
    }

    $args = array(
            'post_type' => 'fundcode',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
    );
    $fund_codes = get_posts($args);

    $user = new WP_User($line_item->post_author);
?>
    <h3>Splitting <?php echo bb_cart_format_currency(get_post_meta($line_item->ID, 'price', true)); ?> from <?php echo $user->display_name; ?> on <?php echo date_i18n(get_option('date_format'), strtotime($line_item->post_date)); ?></h3>
    <form action="" method="post">
        <p><label for="split_amount">Move Amount: </label> <input id="split_amount" name="split_amount" type="number" min="0.01" max="<?php echo get_post_meta($line_item->ID, 'price', true)-0.01; ?>" value=""></p>
        <p><label for="split_fund_code">To Fund Code: </label>
        <select id="split_fund_code" name="split_fund_code">
<?php
    $current_code = bb_cart_get_fund_code($line_item->ID);
    foreach ($fund_codes as $fund_code) {
?>
            <option value="<?php echo $fund_code->ID; ?>"<?php selected($fund_code->ID, $current_code); ?>><?php echo $fund_code->post_title; ?></option>
<?php
    }
?>
        </select></p>
        <input type="submit" value="Process" onclick="bb_cart_split_transaction_line(); return false;">
    </form>
    <script>
        function bb_cart_split_transaction_line() {
            var data = {
                    'action': 'bb_cart_split_transaction_line',
                    'id': <?php echo $line_item->ID; ?>,
                    'fund_code': jQuery('#split_fund_code').val(),
                    'amount': jQuery('#split_amount').val()
            };
            jQuery.post(ajaxurl, data, function(response) {
                alert(response);
                window.location.reload();
            });
        }
    </script>
<?php
    die();
}

add_action('wp_ajax_bb_cart_split_transaction_line', 'bb_cart_split_transaction_line');
function bb_cart_split_transaction_line() {
    $line_item = get_post((int)$_POST['id']);
    if (!$line_item instanceof WP_Post || get_post_type($line_item) != 'transactionlineitem') {
        die('Invalid ID');
    }

    $fund_code = (int)$_POST['fund_code'];
    if (get_post_type($fund_code) != 'fundcode') {
        die('Invalid Fund Code');
    }

    $amount = (float)$_POST['amount'];
    $current_amount = get_post_meta($line_item->ID, 'price', true);
    if ($amount <= 0) {
        die('Invalid Amount');
    } elseif ($amount >= $current_amount) {
        die('Amount must be less than current total');
    }

    // Create new line item
    $new_line_item = array(
            'post_type' => 'transactionlineitem',
            'post_status' => $line_item->post_status,
            'post_date' => $line_item->post_date,
            'post_author' => $line_item->post_author,
            'post_title' => $line_item->post_title,
            'post_content' => $line_item->post_content,
    );
    $new_item_id = wp_insert_post($new_line_item);

    update_post_meta($new_item_id, 'price', $amount);
    update_post_meta($new_item_id, 'quantity', 1);

    $transaction = bb_cart_get_transaction_from_line_item($line_item);
    $transaction_term = get_term_by('slug', $transaction->ID, 'transaction'); // Have to pass term ID rather than slug
    wp_set_post_terms($new_item_id, $transaction_term->term_id, 'transaction');

    $fund_code_term = get_term_by('slug', $fund_code, 'fundcode'); // Have to pass term ID rather than slug
    wp_set_post_terms($new_item_id, $fund_code_term->term_id, 'fundcode');

    // Update existing item
    update_post_meta($line_item->ID, 'price', $current_amount-$amount);

    die('Split Successfully');
}

add_action('wp_ajax_bb_cart_load_edit_batch', 'bb_cart_load_edit_batch');
function bb_cart_load_edit_batch() {
    $batch = get_post((int)$_GET['id']);
    if (!$batch instanceof WP_Post || get_post_type($batch) != 'transactionbatch') {
        die('Invalid ID');
    }
?>
    <h3>Updating <?php echo get_the_title($batch); ?></h3>
    <form action="" method="post">
        <p><label for="edit_batch_name">Batch Name: </label> <input id="edit_batch_name" name="edit_batch_name" type="text" value="<?php echo get_the_title($batch); ?>"></p>
        <p><label for="edit_date">Date: </label> <input id="edit_date" name="edit_date" type="date" value="<?php echo date('Y-m-d', strtotime($batch->post_date)); ?>"></p>
        <p><label for="update_transactions">Update Transactions: </label> <input id="update_transactions" name="update_transactions" type="checkbox" value="1"> Update all transaction dates to match batch date</p>
        <input type="submit" value="Update" onclick="bb_cart_update_batch(); return false;">
    </form>
    <script>
        function bb_cart_update_batch() {
            var data = {
                    'action': 'bb_cart_update_batch',
                    'id': <?php echo $batch->ID; ?>,
                    'title': jQuery('#edit_batch_name').val(),
                    'date': jQuery('#edit_date').val(),
                    'update_transactions': jQuery('#update_transactions').is(":checked")
            };
            jQuery.post(ajaxurl, data, function(response) {
                alert(response);
                window.location.reload();
            });
        }
    </script>
<?php
    die();
}

add_action('wp_ajax_bb_cart_update_batch', 'bb_cart_update_batch');
function bb_cart_update_batch() {
    $batch = get_post((int)$_POST['id']);
    if (!$batch instanceof WP_Post || get_post_type($batch) != 'transactionbatch') {
        die('Invalid ID');
    }

    $title = $_POST['title'];
    if (empty($title)) {
        die('Batch Name cannot be empty');
    }

    $date = $_POST['date'];
    if (strtotime($date) === false) {
        die('Invalid Date');
    } else {
        $date = date('Y-m-d', strtotime($date));
    }

    $update_transactions = $_POST['update_transactions'] == 'true'; // Boolean value gets posted as a string

    // We can't use wp_update_post() here as it will clear all the meta values, so we'll do a custom query instead
    global $wpdb;
    $query = $wpdb->prepare('UPDATE '.$wpdb->posts.' SET post_title = %s WHERE ID = %d', array($title, $batch->ID));
    $wpdb->query($query);

    bb_cart_change_batch_date($batch->ID, $date, $update_transactions);

    die('Updated Successfully');
}

add_action('wp_ajax_bb_cart_load_newbatch', 'bb_cart_load_newbatch');
function bb_cart_load_newbatch() {
    $items = $_GET['items'];
    if (empty($items)) {
        die('No transactions selected');
    }
?>
    <h3>New Batch Details</h3>
    <form action="" method="post">
        <p><label for="new_batch_name">Batch Name: </label> <input id="new_batch_name" name="new_batch_name" type="text" value=""></p>
        <p><label for="new_date">Date: </label> <input id="new_date" name="new_date" type="date" value="<?php echo date('Y-m-d', current_time('timestamp')); ?>"></p>
        <input type="submit" value="Create" onclick="bb_cart_update_batch(); return false;">
    </form>
    <script>
        function bb_cart_update_batch() {
            var data = {
                    'action': 'bb_cart_new_batch',
                    'items': '<?php echo implode(',', $items); ?>',
                    'title': jQuery('#new_batch_name').val(),
                    'date': jQuery('#new_date').val()
            };
            jQuery.post(ajaxurl, data, function(response) {
                alert(response);
                window.location.reload();
            });
        }
    </script>
<?php
    die();
}

add_action('wp_ajax_bb_cart_new_batch', 'bb_cart_new_batch');
function bb_cart_new_batch() {
    $items = $_POST['items'];
    if (empty($items)) {
        die('No transactions selected');
    }
    $items = explode(',', $items);

    $title = $_POST['title'];
    if (empty($title)) {
        die('Batch Name cannot be empty');
    }

    $date = $_POST['date'];
    if (strtotime($date) === false) {
        die('Invalid Date');
    } else {
        $date = date('Y-m-d', strtotime($date));
    }

    $new_batch = array(
            'post_type' => 'transactionbatch',
            'post_status' => 'pending',
            'post_date' => $date,
            'post_author' => get_current_user_id(),
            'post_title' => $title,
    );
    $new_batch_id = wp_insert_post($new_batch);

    bb_cart_move_line_items_to_batch($items, $new_batch_id);

    die('Updated Successfully');
}

add_action('wp_ajax_bb_cart_load_movetransactions', 'bb_cart_load_movetransactions');
function bb_cart_load_movetransactions() {
    $items = $_GET['items'];
    if (empty($items)) {
        die('No transactions selected');
    }

    $args = array(
            'post_type' => 'transactionbatch',
            'posts_per_page' => -1,
            'post_status' => 'pending',
            'orderby' => 'date',
            'order' => 'ASC',
    );
    $batches = get_posts($args);
?>
    <h3>Select Batch</h3>
    <form action="" method="post">
        <p><label for="move_batch_id">Move To: </label>
        <select id="move_batch_id" name="move_batch_id">
<?php
    foreach ($batches as $batch) {
?>
            <option value="<?php echo $batch->ID; ?>"><?php echo $batch->post_title; ?></option>
<?php
    }
?>
        </select></p>
        <input type="submit" value="Move" onclick="bb_cart_move_transactions(); return false;">
    </form>
    <script>
        function bb_cart_move_transactions() {
            var data = {
                    'action': 'bb_cart_move_transactions',
                    'items': '<?php echo implode(',', $items); ?>',
                    'batch_id': jQuery('#move_batch_id').val()
            };
            jQuery.post(ajaxurl, data, function(response) {
                alert(response);
                window.location.reload();
            });
        }
    </script>
<?php
    die();
}

add_action('wp_ajax_bb_cart_move_transactions', 'bb_cart_move_transactions');
function bb_cart_move_transactions() {
    $items = $_POST['items'];
    if (empty($items)) {
        die('No transactions selected');
    }
    $items = explode(',', $items);

    $batch_id = (int)$_POST['batch_id'];
    $batch = get_post($batch_id);
    if (!$batch instanceof WP_Post || get_post_type($batch) != 'transactionbatch') {
        die('Invalid ID');
    }

    bb_cart_move_line_items_to_batch($items, $batch_id);

    die('Updated Successfully');
}

function bb_cart_change_batch_date($batch_id, $new_date, $update_transactions = false) {
    bb_cart_change_post_dates(array($batch_id), $new_date);
    if ($update_transactions) {
        $transactions = bb_cart_get_batch_transactions($batch_id);
        foreach ($transactions as $transaction) {
            bb_cart_change_transaction_date($transaction->ID, $new_date);
        }
    }
}

function bb_cart_change_transaction_date($transaction_id, $new_date) {
    $posts_to_update = array($transaction_id);
    $line_items = bb_cart_get_transaction_line_items($transaction_id);
    foreach ($line_items as $line_item) {
        $posts_to_update[] = $line_item->ID;
    }
    bb_cart_change_post_dates($posts_to_update, $new_date);
}

function bb_cart_change_post_dates(array $post_ids, $new_date) {
    // We can't use wp_update_post() here as it will clear all the meta values, so we'll do a custom query instead
    global $wpdb;
    $format = array_fill(0, count($post_ids), '%d');
    $update_data = $post_ids;
    array_unshift($update_data, $new_date);
    $query = $wpdb->prepare('UPDATE '.$wpdb->posts.' SET post_date = %s WHERE ID in ('.implode(',', $format).')', $update_data);
    $wpdb->query($query);
}

function bb_cart_move_line_items_to_batch($items, $new_batch_id) {
    $batch = get_post($new_batch_id);
    $date = $batch->post_date;
    $affected_transactions = array();
    foreach ($items as $item_id) {
        $transaction = bb_cart_get_transaction_from_line_item($item_id);
        $line_items = bb_cart_get_transaction_line_items($transaction->ID);
        $line_item_ids = array();
        foreach ($line_items as $line_item) {
            $line_item_ids[] = $line_item->ID;
        }
        $affected_transactions[$transaction->ID] = $line_item_ids;
    }

    foreach ($affected_transactions as $transaction_id => $line_item_ids) {
        $diff = array_diff($line_item_ids, $items);
        if (count($diff) > 0) { // Only some of this transaction's items are being moved, we're going to have to split the transaction
            $original_transaction = get_post($transaction_id);
            $meta = get_post_meta($original_transaction);
            $new_transaction = array(
                    'post_type' => 'transaction',
                    'post_status' => $original_transaction->post_status,
                    'post_author' => $original_transaction->post_author,
                    'post_date' => $date,
                    'post_title' => $original_transaction->post_title,
                    'post_content' => $original_transaction->post_content,
            );
            $new_transaction_id = wp_insert_post($new_transaction);

            update_post_meta($new_transaction_id, 'frequency', $meta['frequency'][0]);
            update_post_meta($new_transaction_id, 'gf_entry_id', $meta['gf_entry_id'][0]);
            update_post_meta($new_transaction_id, 'currency', $meta['currency'][0]);
            update_post_meta($new_transaction_id, 'cart', $meta['cart'][0]);
            update_post_meta($new_transaction_id, 'gateway_response', $meta['gateway_response'][0]);
            update_post_meta($new_transaction_id, 'is_tax_deductible', $meta['is_tax_deductible'][0]);
            update_post_meta($new_transaction_id, 'subscription_id', $meta['subscription_id'][0]);
            update_post_meta($new_transaction_id, 'is_receipted', $meta['is_receipted'][0]);
            update_post_meta($new_transaction_id, 'transaction_type', $meta['transaction_type'][0]);
            update_post_meta($new_transaction_id, 'batch_id', $new_batch_id);

            $old_donation_amount = $old_total_amount = $new_donation_amount = $new_total_amount = 0;
            foreach ($line_item_ids as $line_item_id) {
                $line_amount = get_post_meta($line_item_id, 'price', true);
                $fund_code = bb_cart_get_fund_code($line_item_id);
                $fund_code_type = get_post_meta($fund_code, 'transaction_type', true);
                if (in_array($line_item_id, $items)) { // Moving this one
                    $new_total_amount += $line_amount;
                    if ($fund_code_type == 'donation') {
                        $new_donation_amount += $line_amount;
                    }
                    $transaction_term = get_term_by('slug', $new_transaction_id, 'transaction'); // Have to pass term ID rather than slug
                    wp_set_post_terms($line_item_id, $transaction_term->term_id, 'transaction');
                } else {
                    $old_total_amount += $line_amount;
                    if ($fund_code_type == 'donation') {
                        $old_donation_amount += $line_amount;
                    }
                }
            }

            update_post_meta($transaction_id, 'donation_amount', $old_donation_amount);
            update_post_meta($transaction_id, 'total_amount', $old_total_amount);
            update_post_meta($new_transaction_id, 'donation_amount', $new_donation_amount);
            update_post_meta($new_transaction_id, 'total_amount', $new_total_amount);
        } else { // Moving everything - simple update
            update_post_meta($transaction_id, 'batch_id', $new_batch_id);
            bb_cart_change_transaction_date($transaction_id, $date);
        }
    }
}

function bb_cart_get_batch_transactions($batch_id) {
    $args = array(
            'post_type' => 'transaction',
            'posts_per_page' => -1,
            'meta_query' => array(
                    array(
                            'key' => 'batch_id',
                            'value' => $batch_id,
                    ),
            ),
    );
    return get_posts($args);
}
