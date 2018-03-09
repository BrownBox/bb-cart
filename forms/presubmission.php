<?php
add_action('gform_pre_submission', 'bb_cart_pre_submission_handler');
function bb_cart_pre_submission_handler($form) {
    if ($form['id'] == bb_cart_get_donate_form()) {
        global $post;
        $label = 'My Donation';
        $fund_code = bb_cart_get_default_fund_code();
        $target = rgpost('input_2', true);
        if ($target == 'sponsorship') {
            list($fund_code, $id) = explode(':', rgpost('input_5', true));
            if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$member)) {
                foreach (Brownbox\Config\BB_Cart::$member as $type => $value) {
                    if ($type == 'post_type') {
                        $label = get_the_title($id);
                        $email = get_post_meta($member,'bb_email_notification', true);
                        $deductible = get_post_meta($id, 'bb_give_tax_deductible', true);
                    } elseif($type == 'user') {
                        $user = get_userdata($id);
                        $label = $user->first_name.' '.$user->last_name;
                        $email = $user->user_email;
                        $deductible = get_user_meta($id, 'bb_give_tax_deductible', true);
                    }
                }
                $label = 'Donation to '.$label;
            }
        } elseif ($target == 'campaign') {
            list($fund_code, $id) = explode(':',rgpost('input_6',true));
            $label = 'Donation to '.get_the_title($id);
            $email = get_post_meta($project,'bb_email_notification', true);
            $deductible = get_post_meta($id, 'bb_give_tax_deductible', true);
        }

        foreach ($form['fields'] as $field) {
            if ($field->inputName == 'bb_cart_custom_item_label') {
                $_POST['input_'.$field->id] = $label;
            } elseif ($field->inputName == 'bb_cart_notification_email' && !empty($email)) {
                $_POST['input_'.$field->id] = $email;
            } elseif ($field->inputName == 'bb_cart_fund_code') {
                $_POST['input_'.$field->id] = $fund_code;
            } elseif ($field->inputName == 'bb_cart_page_id') {
                $_POST['input_'.$field->id] = $post->ID;
            } elseif ($field->inputName == 'bb_cart_campaign_id') {
                $_POST['input_'.$field->id] = $id;
            } elseif ($field->inputName == 'bb_cart_gift_type') {
                if(!empty(rgpost('input_7.2'))){
                    $_POST['input_'.$field->id] = rgpost('input_7.2');
                } else {
                    $_POST['input_'.$field->id] = $target;
                }
            } elseif ($field->inputName == 'bb_cart_tax_deductible') {
                $_POST['input_'.$field->id] = $deductible;
            }
        }
    }
}
