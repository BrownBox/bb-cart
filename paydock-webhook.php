<?php
require_once("../../../wp-load.php");

$data = json_decode(file_get_contents('php://input'), true);

if ($data['data']['one_off'] == false && class_exists('GFPayDock')) {
    $subscription_id = $data['data']['subscription_id'];
    $receipt_no = $data['data']['transactions'][0]['_id'];

    // If this is not first recurring payment
    $pd = new GFPayDock();
    $result = $pd->get_subscription($subscription_id, true);
    if ($result->resource->data->statistics->successful_transactions > 1) {
        // Contact details
        $email = $data['data']['customer']['email'];
        $user = get_user_by('email', $email);
        $author_id = $user->ID;
        $firstname = get_user_meta($author_id, 'first_name', true);
        $lastname = get_user_meta($author_id, 'last_name', true);

        // Other details
        $amount = $data['data']['amount'];
        $frequency = 'recurring';

        // Create transaction record
        $transaction = array(
                'post_title' => $firstname . '-' . $lastname . '-' . $amount,
                'post_content' => serialize($data),
                'post_status' => 'publish',
                'post_author' => $author_id,
                'post_type' => 'transaction',
        );

        // Insert the post into the database
        $post_id = wp_insert_post($transaction);

        add_post_meta($post_id, 'frequency', $frequency);
        add_post_meta($post_id, 'donation_amount', $amount);
        add_post_meta($post_id, 'total_amount', $amount);

        do_action('bb_cart_webhook_paydock_recurring_success', $user, $amount);
    }
}

echo 'Thanks!';
