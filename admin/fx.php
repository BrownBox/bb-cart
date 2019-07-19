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
        <input type="submit" value="Update" onclick="bb_cart_update_batch(); return false;">
    </form>
    <script>
        function bb_cart_update_batch() {
            var data = {
                    'action': 'bb_cart_update_batch',
                    'id': <?php echo $batch->ID; ?>,
                    'title': jQuery('#edit_batch_name').val(),
                    'date': jQuery('#edit_date').val()
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

    bb_cart_update_transaction_date($transaction->ID, $date);

    die('Updated Successfully');
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

    // We can't use wp_update_post() here as it will clear all the meta values, so we'll do a custom query instead
    global $wpdb;
    $query = $wpdb->prepare('UPDATE '.$wpdb->posts.' SET post_title = %s WHERE ID = %d', array($title, $batch->ID));
    $wpdb->query($query);

    bb_cart_change_batch_date($batch->ID, $date);

    die('Updated Successfully');
}

function bb_cart_change_batch_date($batch_id, $new_date) {
    bb_cart_change_post_dates(array($batch_id), $new_date);
    $transactions = bb_cart_get_batch_transactions($batch_id);
    foreach ($transactions as $transaction) {
        bb_cart_change_transaction_date($transaction->ID, $new_date);
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
