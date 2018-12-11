<?php
add_action('gform_after_update_entry', 'bb_cart_update_entry', 10, 3);
function bb_cart_update_entry($form, $entry_id, $original_entry) {
    if (($transaction = bb_cart_get_transaction_from_entry($entry_id)) !== false) {
        $entry = GFAPI::get_entry($entry_id);
        foreach ($form['fields'] as $field) {
            if ($original_entry[$field->id] !== $entry[$field->id]) {
                switch ($field->type) {
                    case 'email':
                        $user = get_user_by('email', $entry[$field->id]);
                        if ($user instanceof WP_User) {
                            $transaction->post_author = $user->ID;
                            wp_update_post($transaction);
                            $line_items = bb_cart_get_transaction_line_items($transaction->ID);
                            foreach ($line_items as $line_item) {
                                $line_item->post_author = $user->ID;
                                wp_update_post($line_item);
                            }
                            GFAPI::update_entry_property($entry_id, 'created_by', $user->ID);
                        }
                        break;
                    default:
                        switch ($field->inputName) {
                            case 'transaction_date':
                                $transaction->post_date = date('Y-m-d', strtotime($entry[$field->id]));
                                wp_update_post($transaction);
                                break;
                            case 'bb_cart_fund_code':
                                $original_fund_code = bb_cart_load_fund_code($original_entry[$field->id]);
                                $fund_code = bb_cart_load_fund_code($entry[$field->id]);
                                $line_items = bb_cart_get_transaction_line_items($transaction->ID);
                                foreach ($line_items as $line_item) {
                                    $current_fund_code = array_shift(wp_get_post_terms($line_item->ID, 'fundcode'));
                                    if ($current_fund_code instanceof WP_Term && $current_fund_code->slug == $original_fund_code->ID) {
                                        $fund_code_term = get_term_by('slug', $fund_code->ID, 'fundcode'); // Have to pass term ID rather than slug
                                        wp_set_post_terms($line_item->ID, $fund_code_term->term_id, 'fundcode');
                                    }
                                }
                                break;
                        }
                        break;
                }
            }
        }
    }
}
