<?php
add_action('admin_menu', 'bb_cart_pledges_menu');
function bb_cart_pledges_menu() {
    //create new top-level menu
    add_submenu_page('bb_cart_settings', 'Pledges', 'Pledges', 'manage_options', 'bb_cart_pledges', 'bb_cart_pledges');
}

function bb_cart_pledges() {
?>
<div class="wrap">
    <h2>Pledge Management</h2>
<?php
    if (!empty($_GET['pledge_id']) && !empty($_GET['pledge_action'])) {
        switch ($_GET['pledge_action']) {
            case 'complete':
                $date = current_time('mysql'); // @todo let user specify date
                bb_cart_complete_pending_transaction($_GET['pledge_id'], $date);
                echo '<div class="notice notice-success"><p>Transaction successfully marked as complete.</p></div>';
                break;
            case 'cancel':
                $message = 'Cancelled'; // @todo let user specify message
                bb_cart_cancel_pending_transaction($_GET['pledge_id'], $message);
                echo '<div class="notice notice-success"><p>Transaction successfully marked as cancelled.</p></div>';
                break;
        }
    }
    $args = array(
            'post_type' => 'transaction',
            'post_status' => 'draft',
            'posts_per_page' => -1,
    );
    $pledges = get_posts($args);
    if (count($pledges)) {
?>
    <table class="bb_cart_table widefat striped">
        <tr>
            <th>Pledge Date</th>
            <th>Name</th>
            <th>Email</th>
            <th>Amount</th>
            <th>Payment Method</th>
            <th>Actions</th>
        </tr>
<?php
    foreach ($pledges as $pledge) {
        if (!empty($pledge->post_author)) {
            $donor = new WP_User($pledge->post_author);
            $name = $donor->display_name;
            $email = $donor->user_email;
        } else {
			$entry = GFAPI::get_entry(get_post_meta($pledge->ID, 'gf_entry_id', true));
			if (!$entry) {
				continue;
			}
            $form = GFAPI::get_form($entry['form_id']);
            foreach ($form['fields'] as $field) {
                switch ($field->type) {
                    case 'name':
                        $name = $entry[$field['id'].'.3'].' '.$entry[$field['id'].'.6'];
                        break;
                    case 'email':
                        $email = $entry[$field['id']];
                        break;
                }
            }
        }
?>
        <tr>
            <td><?php echo $pledge->post_date; ?></td>
            <td><?php echo $name; ?></td>
            <td><?php echo $email; ?></td>
            <td style="text-align: right;">$<?php echo number_format((float)get_post_meta($pledge->ID, 'total_amount', true), 2); ?></td>
            <td><?php echo get_post_meta($pledge->ID, 'payment_method', true); ?></td>
            <td><a onclick="return confirm('Are you sure you want to manually mark this transaction as complete?');" href="admin.php?page=bb_cart_pledges&pledge_id=<?php echo $pledge->ID; ?>&pledge_action=complete" class="dashicons dashicons-yes" title="Mark as won"></a> <a onclick="return confirm('Are you sure you want to manually mark this transaction as failed?');" href="admin.php?page=bb_cart_pledges&pledge_id=<?php echo $pledge->ID; ?>&pledge_action=cancel" class="dashicons dashicons-no-alt" title="Mark as lost"></a></td>
        </tr>
<?php
    }
?>
    </table>
<?php
    } else {
?>
    <p>There are no pledges currently pending.</p>
<?php
    }
?>
</div>
<?php
}
