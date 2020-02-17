<?php
require_once("../../../wp-load.php");

$data = json_decode(file_get_contents('php://input'), true);

usleep(rand(1000000, 5000000)); // Sleep for random interval between 1 and 5 seconds in an attempt to prevent duplicate requests from being processed simultaneously

// @todo check for live vs sandbox gateway
switch($data['event']) {
    case 'transaction_success':
        // Mark Direct Debit transactions as complete
        if ($data['data']['customer']['payment_source']['type'] == 'bsb') {
            // PayDock have changed the data structure at some point, but some customers still seem to get the old format
            if (!empty($data['data']['_id'])) { // So we have to cater to both new...
                $pd_id = $data['data']['_id'];
            } else { // ...and old
                $pd_id = $data['_id'];
            }
            $search_criteria = array();
            $search_criteria['field_filters'][] = array('key' => 'transaction_id', 'value' => $pd_id);
            $entries = GFAPI::get_entries(0, $search_criteria);
            if ($entries) {
                $now = date('Y-m-d H:i:s');
                foreach ($entries as $entry) {
                    $transaction = bb_cart_get_transaction_from_entry($entry['id']);
                    if ($transaction) {
                        bb_cart_complete_pending_transaction($transaction->ID, $now, $entry);

                        // Send notifications configured to go on "Payment Completed" event
                        $action = array();
                        $action['id']               = $pd_id;
                        $action['type']             = 'complete_payment';
                        $action['transaction_id']   = $transaction->ID;
                        $action['amount']           = $data['data']['amount'];
                        $action['entry_id']         = $entry['id'];
                        $action['payment_date']     = gmdate('y-m-d H:i:s');
                        $action['payment_method']	= 'Direct Debit';
                        $action['ready_to_fulfill'] = !$entry['is_fulfilled'] ? true : false;
                        GFAPI::send_notifications(GFAPI::get_form($entry['form_id']), $entry, rgar($action, 'type'), array('payment_action' => $action));
                    }
                }
            }
        }

        // Handle subscription payments
        if ($data['data']['one_off'] == false) {
            $subscription_id = $data['data']['subscription_id'];
            $amount = $data['data']['amount'];
            $email = $data['data']['customer']['email'];
            $currency = $data['data']['currency'];
            if (email_exists($email)) {
                $user = get_user_by('email', $email);
                $author_id = $user->ID;
                $firstname = get_user_meta($author_id, 'first_name', true);
                $lastname = get_user_meta($author_id, 'last_name', true);
            } else {
                $firstname = $data['data']['customer']['first_name'];
                $lastname = $data['data']['customer']['last_name'];
                $userdata = array(
                        'user_login' => $email,
                        'user_nicename' => $firstname.' '.$lastname,
                        'display_name' => $firstname.' '.$lastname,
                        'user_email' => $email,
                        'first_name' => $firstname,
                        'nickname' => $firstname,
                        'last_name' => $lastname,
                        'role' => 'subscriber',
                );
                $author_id = wp_insert_user($userdata);
                if (is_wp_error($author_id)) {
                    unset($author_id);
                } else {
                    $user = new WP_User($author_id);
                }
            }


            // Make sure we haven't already tracked this transaction
            $transaction_details = bb_cart_get_transaction_for_subscription($subscription_id);
            $duplicate = false;
            if ($transaction_details) {
                $previous_date = bb_cart_get_datetime($transaction_details->post_date);
                $duplicate = $previous_date->format('Ymd') >= $pd_date->format('Ymd');
            }
            if (!$duplicate) {
                $frequency = 'recurring';
                $transaction_date = date('Y-m-d H:i:s', strtotime($data['data']['transactions'][0]['created_at']));

                // Create transaction record
                $transaction = array(
                        'post_title' => $firstname . '-' . $lastname . '-' . $amount,
                        'post_content' => serialize($data),
                        'post_status' => 'publish',
                        'post_author' => $author_id,
                        'post_type' => 'transaction',
                        'post_date' => $transaction_date,
                );

                // Insert the post into the database
                $transaction_id = wp_insert_post($transaction);

                update_post_meta($transaction_id, 'frequency', $frequency);
                update_post_meta($transaction_id, 'donation_amount', $amount); // Virtually all subscriptions will be donations - we update it below based on the previous transaction (if found) though just in case
                update_post_meta($transaction_id, 'total_amount', $amount);
                update_post_meta($transaction_id, 'currency', $currency);
                update_post_meta($transaction_id, 'transaction_type', 'online');
                update_post_meta($transaction_id, 'is_receipted', 'false');
                update_post_meta($transaction_id, 'subscription_id', $subscription_id);

                $batch_id = bb_cart_get_web_batch($transaction_date);
                update_post_meta($transaction_id, 'batch_id', $batch_id);

                $transaction_term = get_term_by('slug', $transaction_id, 'transaction'); // Have to pass term ID rather than slug

                $line_item = array(
                        'post_title' => 'PayDock Subscription Payment',
                        'post_status' => 'publish',
                        'post_author' => $author_id,
                        'post_type' => 'transactionlineitem',
                        'post_date' => $transaction['post_date'],
                );
                if ($transaction_details) {
                    $prev_amount = get_post_meta($transaction_details->ID, 'total_amount');
                    if ($prev_amount == $amount) {
                        update_post_meta($transaction_id, 'donation_amount', get_post_meta($transaction_details->ID, 'donation_amount', true)); // Subscriptions should generally be donations but just to be safe...
                    }

                    $line_items = bb_cart_get_transaction_line_items($transaction_details->ID);
                    if ($prev_amount == $amount || count($line_items) == 1) {
                        foreach ($line_items as $previous_line_item) {
                            $previous_meta = get_post_meta($previous_line_item->ID);
                            $line_item_id = wp_insert_post($line_item);
                            $price = $previous_meta['price'][0];
                            if ($prev_amount != $amount) { // Amount has changed, use new amount
                                $price = $amount;
                            }
                            update_post_meta($line_item_id, 'fund_code', $previous_meta['fund_code'][0]);
                            update_post_meta($line_item_id, 'price', $price);
                            update_post_meta($line_item_id, 'quantity', $previous_meta['quantity'][0]);

                            wp_set_post_terms($line_item_id, $transaction_term->term_id, 'transaction');
                            $previous_fund_codes = wp_get_object_terms($previous_line_item->ID, 'fundcode');
                            foreach ($previous_fund_codes as $fund_code_term) {
                                wp_set_post_terms($line_item_id, $fund_code_term->term_id, 'fundcode');
                            }
                        }
                    } else { // Amount has changed but we have multiple line items - just create one line item with default fund code
                        $fund_code = bb_cart_get_default_fund_code();
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
                } else { // No previous tranaction found
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

                do_action('bb_cart_webhook_paydock_recurring_success', $user, $amount, $transaction_id);
            }
        }
        break;
        // @todo handle other events
}

echo 'Thanks!';
