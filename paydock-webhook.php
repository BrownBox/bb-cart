<?php
require_once("../../../wp-load.php");

$data = json_decode(file_get_contents('php://input'), true);

// @todo check for live vs sandbox gateway
switch($data['event']) {
    case 'transaction_success':
        // Mark Direct Debit transactions as complete
        if ($data['data']['customer']['payment_source']['type'] == 'bsb') {
            $pd_id = $data['_id'];
            $search_criteria['field_filters'][] = array('key' => 'transaction_id', 'value' => $pd_id);
            $entries = GFAPI::get_entries(0, $search_criteria);
            if ($entries) {
                $now = date('Y-m-d H:i:s');
                foreach ($entries as $entry) {
                    $transaction = bb_cart_get_transaction_from_entry($entry['id']);
                    if ($transaction) {
                        bb_cart_complete_pending_transaction($transaction->ID, $now, $entry);
                    }
                }
            }
        }

        // Handle subscription payments
        if ($data['data']['one_off'] == false) {
            $subscription_id = $data['data']['subscription_id'];
            $receipt_no = $data['data']['transactions'][0]['_id'];
            $amount = $data['data']['amount'];
            $email = $data['data']['customer']['email'];
            $user = get_user_by('email', $email);

            $transaction_check = array(
                    'date' => $data['data']['transactions'][0]['created_at'],
                    'amount' => $amount,
                    'user' => $user,
            );
            if (!bb_cart_transaction_exists($transaction_check)) {
                // Contact details
                $author_id = $user->ID;
                $firstname = get_user_meta($author_id, 'first_name', true);
                $lastname = get_user_meta($author_id, 'last_name', true);

                // Other details
                $frequency = 'recurring';

                // Create transaction record
                $transaction = array(
                        'post_title' => $firstname . '-' . $lastname . '-' . $amount,
                        'post_content' => serialize($data),
                        'post_status' => 'publish',
                        'post_author' => $author_id,
                        'post_type' => 'transaction',
                        'post_date' => date('Y-m-d H:i:s', strtotime($data['data']['transactions'][0]['created_at'])),
                );

                // Insert the post into the database
                $transaction_id = wp_insert_post($transaction);

                update_post_meta($transaction_id, 'frequency', $frequency);
                update_post_meta($transaction_id, 'donation_amount', $amount);
                update_post_meta($transaction_id, 'total_amount', $amount);

                $batch_id = bb_cart_get_web_batch();
                update_post_meta($transaction_id, 'batch_id', $batch_id);

                $transaction_term = get_term_by('slug', $transaction_id, 'transaction'); // Have to pass term ID rather than slug

                $transaction_details = bb_cart_get_transaction_for_subscription($subscription_id);
                $line_item = array(
                        'post_title' => 'PayDock Subscription Payment',
                        'post_status' => 'publish',
                        'post_author' => $author_id,
                        'post_type' => 'transactionlineitem',
                        'post_date' => $transaction['post_date'],
                );
                if ($transaction_details) {
                    update_post_meta($transaction_id, 'donation_amount', get_post_meta($transaction_details->ID, 'donation_amount', true)); // Subscriptions should generally be donations but just to be safe...

                    $line_items = bb_cart_get_transaction_line_items($transaction_details->ID);
                    foreach ($line_items as $previous_line_item) {
                        $previous_meta = get_post_meta($previous_line_item->ID);
                        $line_item_id = wp_insert_post($line_item);
                        update_post_meta($line_item_id, 'fund_code', $previous_meta['fund_code'][0]);
                        update_post_meta($line_item_id, 'price', $previous_meta['price'][0]);
                        update_post_meta($line_item_id, 'quantity', $previous_meta['quantity'][0]);

                        wp_set_post_terms($line_item_id, $transaction_term->term_id, 'transaction');
                        $previous_fund_codes = wp_get_object_terms($previous_line_item->ID, 'fundcode');
                        foreach ($previous_fund_codes as $fund_code_term) {
                            wp_set_post_terms($line_item_id, $fund_code_term->term_id, 'fundcode');
                        }
                    }
                } else {
                    $fund_code = bb_cart_get_default_fund_code(); // No way to get this directly from PayDock, so if we can't find an existing transaction for this subscription, just use the default fund code
                    $line_item_id = wp_insert_post($line_item);
                    update_post_meta($line_item_id, 'fund_code', $fund_code);
                    update_post_meta($line_item_id, 'price', $amount);
                    update_post_meta($line_item_id, 'quantity', 1);

                    wp_set_post_terms($line_item_id, $transaction_term->term_id, 'transaction');
                    if (!empty($fund_code)) {
                        $fund_code_term = get_term_by('slug', $fund_code, 'fundcode'); // Have to pass term ID rather than slug
                        wp_set_post_terms($line_item_id, $fund_code_term->term_id, 'fundcode');
                    }
                }

                // We do this at the end because we don't want to pick up our new transaction when looking for previous details
                update_post_meta($transaction_id, 'subscription_id', $subscription_id);

                do_action('bb_cart_webhook_paydock_recurring_success', $user, $amount, $transaction_id);
            }
        }
        break;
        // @todo handle other events
}

echo 'Thanks!';
