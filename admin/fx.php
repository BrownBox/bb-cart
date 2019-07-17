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
    );
    $args = wp_parse_args($args, $default_args);
    return sprintf('<a class="thickbox" href="%s&width=%d&height=%d">%s</a>', $args['url'], $args['width'], $args['height'], $text);
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
        <p><label for="edit_amount">Amount: </label> <input id="edit_amount" name="edit_amount" type="number" value="<?php echo get_post_meta($line_item->ID, 'price', true); ?>"></p>
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

    // We can't use wp_update_post() here as it will clear all the meta values, so we'll do a custom query instead
    global $wpdb;
    $posts_to_update = array($transaction->ID);
    $line_items = bb_cart_get_transaction_line_items($transaction->ID);
    foreach ($line_items as $line_item) {
        $posts_to_update[] = $line_item->ID;
    }
    $format = array_fill(0, count($posts_to_update), '%d');
    $update_data = $posts_to_update;
    array_unshift($update_data, $date);

    $query = $wpdb->prepare('UPDATE '.$wpdb->posts.' SET post_date = %s WHERE ID in ('.implode(',', $format).')', $update_data);
    $wpdb->query($query);

    die('Updated Successfully');
}
