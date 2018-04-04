<?php
/**
 * Plugin Name: BB Cart
 * Plugin URI: n/a
 * Description: A cart system to extend Gravity Forms. Includes session and checkout functionality, transaction tracking and reporting.
 * Version: 3.0.2
 * Author: Brown Box
 * Author URI: http://brownbox.net.au
 * License: Proprietary Brown Box
 */

define('BB_CART_DIR', plugin_dir_path(__FILE__));
define('BB_CART_URL', plugin_dir_url(__FILE__));

require_once(BB_CART_DIR.'ia/cpt_.php');
require_once(BB_CART_DIR.'ia/meta_.php');
require_once(BB_CART_DIR.'ia/tax_.php');
require_once(BB_CART_DIR.'ia/cpt_tax_.php');
require_once(BB_CART_DIR.'ia/ia.php');
require_once(BB_CART_DIR.'forms/forms.php');
require_once(BB_CART_DIR.'forms/prerenders.php');
require_once(BB_CART_DIR.'forms/presubmission.php');
require_once(BB_CART_DIR.'admin/settings.php');
require_once(BB_CART_DIR.'admin/pledges.php');
require_once(BB_CART_DIR.'admin/import.php');
require_once(BB_CART_DIR.'admin/export.php');
require_once(BB_CART_DIR.'admin/fund-code-report.php');
require_once(BB_CART_DIR.'admin/batch-management.php');
require_once(BB_CART_DIR.'admin/donor-history.php');
// require_once(BB_CART_DIR.'demo/config.class.php');

require_once(BB_CART_DIR.'admin/updates.php');
if (is_admin()) {
    new BbCartUpdates(__FILE__, 'BrownBox', 'bb-cart');
}

define('BB_CART_SESSION_ITEM', 'bb_cart_item');
define('BB_CART_SESSION_SHIPPING_TYPE', 'bb_cart_shipping_type');
define('BB_CART_SESSION_SHIPPING_POSTCODE', 'bb_cart_shipping_postcode');

// JUST DO SOME SESSION STUFF HERE TO KEEP IT CLEAN + NOT CREATE ANY SESSION PROBLEMS
add_action('init', 'bb_cart_start_session', 1);
function bb_cart_start_session() {
    if(!session_id()) {
        if (is_multisite()) {
            $domain = network_site_url();
            $domain = substr($domain, strpos($domain, '//')+2);
            $domain = substr($domain, 0, strrpos($domain, '/'));
            if (strpos($_SERVER['HTTP_HOST'], $domain) !== false) { // Basic check to avoid issues with domain mapping
                session_set_cookie_params(0, '/', '.'.$domain);
            }
        }
        session_start();
    }

    // WooCommerce support
    if (function_exists('WC')) {
        $wc_session = WC()->session;
        if (!is_object($wc_session)) {
            if (!empty($_SESSION[BB_CART_SESSION_ITEM]['woo'])) {
                unset($_SESSION[BB_CART_SESSION_ITEM]['woo']);
            }
        } else {
            $wc_cart = $wc_session->get('cart', array());
            if (!empty($_SESSION[BB_CART_SESSION_ITEM]['woo'])) {
                // Go through BB Cart Woocommerce items to make sure they're still in the WC cart
                foreach ($_SESSION[BB_CART_SESSION_ITEM]['woo'] as $idx => $cart_item) {
                    if (!array_key_exists($cart_item['cart_item_key'], $wc_cart)) {
                        unset($_SESSION[BB_CART_SESSION_ITEM]['woo'][$idx]);
                    }
                }
                if (empty($_SESSION[BB_CART_SESSION_ITEM]['woo'])) {
                    unset($_SESSION[BB_CART_SESSION_ITEM]['woo']);
                }
            }
        }

        // And now make sure all the WC cart items are also in BB Cart
        if (!empty($wc_cart)) {
            foreach ($wc_cart as $woo_idx => $woo_item) {
                if (!empty($_SESSION[BB_CART_SESSION_ITEM]['woo'])) {
                    foreach ($_SESSION[BB_CART_SESSION_ITEM]['woo'] as &$cart_item) {
                        if ($cart_item['cart_item_key'] == $woo_idx) { // Found it - let's make sure the details are the same
                            $label = !empty($woo_item['variation_id']) ? get_the_title($woo_item['variation_id']) : get_the_title($woo_item['product_id']);
                            $cart_item = array(
                                    'name' => $label,
                                    'cart_item_key' => $woo_idx,
                                    'product_id' => $woo_item['product_id'],
                                    'quantity' => $woo_item['quantity'],
                                    'variation_id' => $woo_item['variation_id'],
                                    'variation' => $woo_item['variation'],
                                    'cart_item_data' => $woo_item['cart_item_data'],
                                    'fund_code' => bb_cart_get_fund_code($woo_item['product_id']),
                            );
                            continue(2); // Found it - go to next $woo_item
                        }
                    }
                }

                // Not found - add it
                $label = !empty($woo_item['variation_id']) ? get_the_title($woo_item['variation_id']) : get_the_title($woo_item['product_id']);
                $_SESSION[BB_CART_SESSION_ITEM]['woo'][] = array(
                        'name' => $label,
                        'cart_item_key' => $woo_idx,
                        'product_id' => $woo_item['product_id'],
                        'quantity' => $woo_item['quantity'],
                        'variation_id' => $woo_item['variation_id'],
                        'variation' => $woo_item['variation'],
                        'cart_item_data' => $woo_item['cart_item_data'],
                        'fund_code' => bb_cart_get_fund_code($woo_item['product_id']),
                );
            }
        }
    }

    // Now we try to work around Event Manager idiocy
    if (is_user_logged_in() && !empty($_SESSION[BB_CART_SESSION_ITEM]['event'])) {
        foreach ($_SESSION[BB_CART_SESSION_ITEM]['event'] as &$event) {
            $event['booking']->person_id = get_current_user_id();
            $event['booking']->save(false);
        }
    }
}

function bb_cart_end_session() {
    unset($_SESSION[BB_CART_SESSION_ITEM]);
}

add_action('init', 'bb_cart_start_session', 1);
add_action('wp_logout', 'bb_cart_end_session');
// add_action('wp_login', 'bb_cart_end_session');

// Enqueue styles
add_action('wp_enqueue_scripts', 'bb_cart_enqueue');
add_action('admin_enqueue_scripts', 'bb_cart_enqueue');
function bb_cart_enqueue() {
    wp_enqueue_style('bb_cart', plugin_dir_url(__FILE__).'/assets/css/bb_cart.css');
}

// ENABLE OUR CREDIT CARD FIELDS
add_action("gform_enable_credit_card_field", "bb_cart_enable_creditcard");
function bb_cart_enable_creditcard($is_enabled){
    return true;
}

// ENABLE PASSWORD FIELDS
add_action("gform_enable_password_field", "bb_cart_enable_password_field");
function bb_cart_enable_password_field($is_enabled){
    return true;
}

add_action('init', 'bb_cart_remove_item_from_cart');
function bb_cart_remove_item_from_cart() {
    if(isset($_GET['remove_item'])) {
        $item = $_GET['remove_item'];
        if (strpos($item, ':') !== false) {
            list($section, $item) = explode(':', $item);
            if ($section == 'woo' && function_exists('WC')) {
                $woo_cart_item = $_SESSION[BB_CART_SESSION_ITEM][$section][$item]['cart_item_key'];
                WC()->cart->set_quantity($woo_cart_item, 0); // We remove items from the WooCommerce cart by setting quantity to zero
            }
            unset($_SESSION[BB_CART_SESSION_ITEM][$section][$item]);

            if (empty($_SESSION[BB_CART_SESSION_ITEM][$section])) {
                unset($_SESSION[BB_CART_SESSION_ITEM][$section]);
            }
        } else {
            unset($_SESSION[BB_CART_SESSION_ITEM][$item]);
        }
        wp_redirect(remove_query_arg('remove_item'));
        exit;
    }
}

// THIS IS OUR FUNCTION FOR CLEANING UP THE PRICING AMOUNTS THAT GF SPITS OUT
function bb_cart_clean_amount($entry) {
    $entry = preg_replace("/\|(.*)/", '',$entry); // replace everything from the pipe symbol forward
    if (strpos($entry,'.') === false) {
        $entry .= ".00";
    }
    if (strpos($entry,'$') !== false) {
        $startsAt = strpos($entry, "$") + strlen("$");
        $endsAt=strlen($entry);
        $amount = substr($entry, 0, $endsAt);
        $amount = preg_replace("/[^0-9,.]/", "", $amount);
    } else {
        $amount = preg_replace("/[^0-9,.]/", "", $entry);
        $amount = sprintf("%.2f", $amount);
    }

    $amount = str_replace('.', '', $amount);
    $amount = str_replace(',', '', $amount);
    return $amount;
}

// WE NEED TO ADD THE OPTION TO GRAVITY FORMS SETTINGS TO SELECT WHICH FORMS WILL ADD ITEMS TO THE CART
add_filter('gform_form_settings', 'bb_cart_custom_form_setting', 10, 2);
function bb_cart_custom_form_setting($settings, $form) {

    if(rgar($form, 'bb_cart_enable')=="cart_enabled"){
        $checked_text = "checked='checked'";
    }else{
        $checked_text = "";
    }

    if(rgar($form, 'bb_checkout_enable')=="checkout_enabled"){
        $checkout_enabled = "checked='checked'";
    }else{
        $checkout_enabled = "";
    }

    $settings['Form Options']['bb_cart_enable'] = '
        <tr>
            <th><label for="bb_cart_enable">Enable CART?</label></th>
            <td><input type="checkbox" value="cart_enabled" '.$checked_text.' name="bb_cart_enable"> When checked, the values from any "Product" or "EnvoyRecharge" fields in this form when submitted will be added to the cart.</td>
        </tr>
        <tr>
            <th><label for="bb_checkout_enable">Enable CHECKOUT?</label></th>
            <td><input type="checkbox" value="checkout_enabled" '.$checkout_enabled.' name="bb_checkout_enable"> If this option is checked OR the form contains a credit card field, this form will be treated as a checkout form.</td>
        </tr>
        <tr>
            <th><label for="custom_flash_message">Flash Message?</label></th>
            <td><input type="text" value="'.rgar($form, 'custom_flash_message').'" name="custom_flash_message" class="fieldwidth-3"> <br/>This custom message will be displayed in the site header when this form is submitted.</td>
        </tr>';
    return $settings;
}

// save your custom form setting
add_filter('gform_pre_form_settings_save', 'bb_cart_save_form_setting');
function bb_cart_save_form_setting($form) {
    $form['bb_cart_enable'] = rgpost('bb_cart_enable');
    $form['bb_checkout_enable'] = rgpost('bb_checkout_enable');
    $form['custom_flash_message'] = rgpost('custom_flash_message');
    return $form;
}

add_filter("gform_field_value_bb_cart_total_price", "bb_cart_total_price");
function bb_cart_total_price($value = '', $include_shipping = true) {
    $total = 0;
    $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
    foreach ($cart_items as $section => $items) {
        $total += bb_cart_section_total($section, $include_shipping);
    }
    return $total;
}

function bb_cart_products_total($include_shipping = true) {
    $woo_total = 0;
    $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
    if (!empty($cart_items['woo']) && function_exists('WC')) {
        $wc_session = WC()->session;
        if (is_object($wc_session)) {
            $cart = $wc_session->get('cart', array());
            foreach ($cart as $product) {
                $woo_total += $product['line_total'];
            }
        }

        // Calculate shipping
        if ($include_shipping) {
            $woo_total += bb_cart_calculate_shipping($woo_total);
        }
    }
    return $woo_total;
}

function bb_cart_events_total() {
    $events_total = 0;
    $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
    if (!empty($cart_items['event'])) {
        foreach ($cart_items['event'] as $event) {
            $events_total += $event['booking']->booking_price;
        }
    }
    return $events_total;
}

function bb_cart_section_total($section = 'donations', $include_shipping = true) {
    switch ($section) {
        case 'woo':
            return bb_cart_products_total($include_shipping);
            break;
        case 'event':
            return bb_cart_events_total();
            break;
        default:
            $section_total = 0;
            $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
            if (!empty($cart_items[$section])) {
                foreach ($cart_items[$section] as $item) {
                    $section_total += $item['price']*$item['quantity'];
                }
            }
            $section_total = $section_total/100;
            return $section_total;
            break;
    }
}

/**
 * @deprecated
 * Use bb_cart_section_total() instead.
 */
function bb_cart_donations_total() {
    return bb_cart_section_total();
}

add_filter("gform_field_value_bb_cart_total_quantity", "bb_cart_total_quantity");
function bb_cart_total_quantity($value = '') {
    $count = 0;
    $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
    if (!empty($cart_items)) {
        foreach ($cart_items as $section => $items) {
            switch ($section) {
                case 'woo':
                    if (function_exists('WC')) {
                        $wc_session = WC()->session;
                        if (is_object($wc_session)) {
                            $cart = $wc_session->get('cart', array());
                            foreach ($cart as $product) {
                                $count += $product['quantity'];
                            }
                        }
                    }
                    break;
                case 'event':
                    foreach ($items as $event) {
                        $count += $event['booking']->tickets; // @todo check property name
                    }
                    break;
                default:
                    foreach ($items as $item) {
                        $count += $item['quantity'];
                    }
                    break;
            }
        }
    }
    return $count;
}

add_filter("gform_field_value_bb_cart_deductible_donation_total", "bb_cart_deductible_donation_total");
function bb_cart_deductible_donation_total($value = '') {
    $value = 0;
    foreach ($_SESSION[BB_CART_SESSION_ITEM] as $section => $cart_items) {
        foreach ($cart_items as $item) {
            if ($item['deductible'] && $item['transaction_type'] == 'donation') {
                $value += ($item['price']/100)*$item['quantity'];
            }
        }
    }
    return $value;
}

add_filter("gform_field_value_bb_cart_non_deductible_donation_total", "bb_cart_non_deductible_donation_total");
function bb_cart_non_deductible_donation_total($value = '') {
    $value = 0;
    foreach ($_SESSION[BB_CART_SESSION_ITEM] as $section => $cart_items) {
        foreach ($cart_items as $item) {
            if (!$item['deductible'] && $item['transaction_type'] == 'donation') {
                $value += ($item['price']/100)*$item['quantity'];
            }
        }
    }
    return $value;
}

add_filter("gform_field_value_bb_cart_deductible_purchase_total", "bb_cart_deductible_purchase_total");
function bb_cart_deductible_purchase_total($value = '') {
    $value = 0;
    foreach ($_SESSION[BB_CART_SESSION_ITEM] as $section => $cart_items) {
        foreach ($cart_items as $item) {
            if ($item['deductible'] && $item['transaction_type'] == 'purchase') {
                $value += ($item['price']/100)*$item['quantity'];
            }
        }
    }
    return $value;
}

add_filter("gform_field_value_bb_cart_non_deductible_purchase_total", "bb_cart_non_deductible_purchase_total");
function bb_cart_non_deductible_purchase_total($value = '') {
    $value = 0;
    foreach ($_SESSION[BB_CART_SESSION_ITEM] as $section => $cart_items) {
        foreach ($cart_items as $item) {
            if (!$item['deductible'] && $item['transaction_type'] == 'purchase') {
                $value += ($item['price']/100)*$item['quantity'];
            }
        }
    }
    return $value;
}

function bb_cart_has_donation() {
    return bb_cart_section_total('donations') > 0;
}

function bb_cart_amounts_by_frequency() {
    $transactions = array();
    $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
    if (!empty($cart_items)) {
        foreach ($cart_items as $section => $items) {
            switch ($section) {
                case 'donations':
                    foreach ($items as $cart_item) {
                        if (!isset($transactions[$cart_item['frequency']])) {
                            $transactions[$cart_item['frequency']] = 0;
                        }
                        $transactions[$cart_item['frequency']] += $cart_item['price']*$cart_item['quantity']/100;
                    }
                    break;
                default:
                    if (!isset($transactions['one-off'])) {
                        $transactions['one-off'] = 0;
                    }
                    $transactions['one-off'] += bb_cart_section_total($section);
                    break;
            }
        }
    }
    return $transactions;
}

add_filter('gform_field_value_bb_campaign', 'bb_cart_primary_campaign');
function bb_cart_primary_campaign($value = '') {
    if (!empty($_SESSION[BB_CART_SESSION_ITEM]['donations'])) {
        foreach ($_SESSION[BB_CART_SESSION_ITEM]['donations'] as $item) {
            if (!empty($item['campaign_id'])) {
                return $item['campaign_id'];
            }
        }
    }
    return $value;
}

add_filter("gform_field_value_bb_donation_frequency", "bb_cart_frequency");
function bb_cart_frequency($value = '') {
    if (!empty($_SESSION[BB_CART_SESSION_ITEM]['donations'])) {
        foreach ($_SESSION[BB_CART_SESSION_ITEM]['donations'] as $item) {
            if (!empty($item['frequency'])) {
                return $item['frequency'];
            }
        }
    }
    return $value;
}

add_filter("gform_field_value_bb_tax_status", "bb_cart_tax_status");
function bb_cart_tax_status($value = '') {
    if (!empty($_SESSION[BB_CART_SESSION_ITEM]['donations'])) {
        foreach ($_SESSION[BB_CART_SESSION_ITEM]['donations'] as $item) {
            return $item['deductible'];
        }
    }
    return $value;
}

add_filter("gform_field_value_bb_item_types", "bb_cart_item_types");
function bb_cart_item_types($value = '') {
    if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
        return implode(',', array_keys($_SESSION[BB_CART_SESSION_ITEM]));
    }
    return $value;
}

add_filter("gform_field_value_bb_cart_checkout_items_array", "bb_cart_checkout_items_array");
function bb_cart_checkout_items_array($value){
    $array_string = '';
    if (!empty($_SESSION[BB_CART_SESSION_ITEM]['donations'])) {
        foreach ($_SESSION[BB_CART_SESSION_ITEM]['donations'] as $item ) {
            $array_string .= $item['entry_id'] . ",";
        }
        $array_string = substr($array_string, 0, -1);
    }
    return $array_string;
}

add_filter("gform_field_value_bb_cart_custom_item_label", "bb_cart_add_custom_label");
function bb_cart_add_custom_label($value) {
    global $post;
    return $post->post_title;
}

// OK NOW WE NEED A POST-SUBMISSION HOOK TO CATCH ANY SUBMISSIONS FROM FORMS WITH 'bb_cart_enable' CHECKED
// MUST happen before the post_purchase function below
add_action("gform_after_submission", "bb_cart_check_for_cart_additions", 5, 2);
function bb_cart_check_for_cart_additions($entry, $form){
    if(!empty($form['custom_flash_message'])){
        $_SESSION['flash_message'] = $form['custom_flash_message'];
    }

    global $post;
    $frequency = 'one-off';
    $section = 'donations';
    $transaction_type = 'donation';
    $deductible = false;
    $campaign = $post->ID;
    $quantity = 1;
    $variations = array();
    $sku = $donation_target = $fund_code = '';
    $label = 'Where Most Needed';
    if (!empty($form['bb_cart_enable']) && $form['bb_cart_enable']=="cart_enabled") {
        // ANNOYINGLY HAVE TO RUN THIS ALL THROUGH ONCE TO SET THE FIELD LABEL IN CASE THERE'S A CUSTOM LABEL SET
        foreach ($form['fields'] as $field) {
            if ($field['inputName']=='bb_cart_custom_item_label' && !empty($entry[$field['id']])) {
                $label = $entry[$field['id']];
            } elseif (($field['adminLabel'] == 'bb_cart_interval' || $field['adminLabel'] == 'bb_donation_frequency') && !empty($entry[$field['id']])) {
                $frequency = $entry[$field['id']];
            } elseif ($field['inputName'] == 'bb_cart_tax_deductible' || $field['inputName'] == 'bb_tax_status') {
                $deductible = (boolean)$entry[$field['id']];
            } elseif ($field['inputName'] == 'bb_campaign' && !empty($entry[$field['id']])) {
                $campaign = $entry[$field['id']];
            } elseif ($field['inputName'] == 'bb_fund_code' && !empty($entry[$field['id']])) {
                $fund_code = $entry[$field['id']];
            } elseif ($field['type'] == 'quantity' && !empty($entry[$field['id']])) {
                $quantity = $entry[$field['id']];
            } elseif (strpos($field->inputName, 'variation') !== false && !empty($entry[$field['id']])) {
                $variations[] = $entry[$field['id']];
            } elseif ($field['inputName'] == 'bb_sku' && !empty($entry[$field['id']])) {
                $sku = $entry[$field['id']];
            } elseif ($field['inputName'] == 'bb_cart_donation_for' && !empty($entry[$field['id']])) {
                $donation_target = $entry[$field['id']];
            } elseif (($field['inputName'] == 'bb_cart_donation_member' || $field['inputName'] == 'bb_cart_donation_campaign') && !empty($entry[$field['id']])) {
                $campaign = $entry[$field['id']];
                list($fund_code, $campaign) = explode(':', $campaign);
            } elseif ($field['inputName'] == 'bb_cart_purchase_type' && !empty($entry[$field['id']])) {
                $section = $entry[$field['id']];
            } elseif ($field['inputName'] == 'page_id') {
                $campaign = $entry[$field['id']];
            } elseif ($field['inputName'] == 'donation_target') {
                $donation_for = $entry[$field['id']];
            }
        }
        foreach ($form['fields'] as $field) {
            $amount = '';
            $old_quantity = $quantity;
            $old_label = $label;

            if ($field['type']=="product") {
                if (!empty($entry[$field["id"]])) {
                    $amount = $entry[$field["id"]];
                } elseif (!empty($field['inputs'])) {
                    foreach ($field['inputs'] as $input) {
                        if ($input['name'] == 'bb_product_price') {
                            $amount = $entry[(string)$input["id"]];
                        } elseif ($input['name'] == 'bb_product_quantity' && !empty($entry[(string)$input["id"]])) {
                            $quantity = $entry[(string)$input["id"]];
                        } elseif ($input['name'] == 'bb_product_name' && !empty($entry[(string)$input["id"]])) {
                            $label = $entry[(string)$input["id"]];
                        }
                    }
                }
            } elseif ($field['type'] == 'envoyrecharge') {
                $amount = $entry[$field["id"].'.1'];
                if ($entry[$field["id"].'.5'] == 'recurring')
                    $frequency = $entry[$field["id"].'.2'];
            } elseif ($field['type'] == 'bb_click_array') {
                $amount = $entry[$field["id"].'.1'];
            }
            if (!empty($amount)) {
                // now we can add products to our session
                // only problem is that the 'price' field is a joke in GF so many different formats.. so we need to clean that
                $clean_price = bb_cart_clean_amount($amount); // this will now be the correctly formatted amount in cents
                if ($clean_price>0) {
                    if ($label == '') {
                        if (is_numeric($campaign)) {
                            $label = get_the_title($campaign);
                        } else {
                            $label = $field['label'];
                        }
                    }
                    if (empty($fund_code) && !empty($campaign)) {
                        if (is_numeric($campaign)) {
                            $fund_code = bb_cart_get_fund_code($campaign);
                        } else {
                            $fund_code = $campaign;
                        }
                    }
                    if (empty($fund_code)) {
                        $fund_code = bb_cart_get_default_fund_code();
                    }
                    $original_fund_code = $fund_code;

                    $fund_code = apply_filters('bb_cart_fund_code', $fund_code, $entry);

                    $fund_code_post = bb_cart_load_fund_code($fund_code);
                    if ($fund_code_post instanceof WP_Post) {
                        $fund_code_deductible = get_post_meta($fund_code_post->ID, 'deductible', true);
                        $deductible = $fund_code_deductible == 'true';
                        $transaction_type = get_post_meta($fund_code_post->ID, 'transaction_type', true);
                    }

                    global $blog_id;
                    $_SESSION[BB_CART_SESSION_ITEM][$section][] = array(
                            'label' => $label,
                            'price' => $clean_price,
                            'form_id' => $form['id'],
                            'entry_id' => $entry['id'],
                            'frequency' => $frequency,
                            'campaign' => $campaign,
                            'fund_code' => $fund_code,
                            'deductible' => $deductible,
                            'transaction_type' => $transaction_type,
                            'original_fund_code' => $original_fund_code,
                            'blog_id' => $blog_id,
                            'quantity' => $quantity,
                            'variation' => $variations,
                            'sku' => $sku,
                            'target' => $donation_target,
                            'donation_for' => $donation_for,
                    );
                }
            }
            $quantity = $old_quantity;
            $label = $old_label;
        }
    }
}

/**
 * Add WooCommerce items to BB Cart
 */
add_action('woocommerce_add_to_cart', 'bb_cart_add_woo_to_bb_cart', 1, 6);
function bb_cart_add_woo_to_bb_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    // Check if it's already in the cart and if so, update
    foreach ($_SESSION[BB_CART_SESSION_ITEM]['woo'] as &$cart_item) {
        if ($cart_item['cart_item_key'] == $cart_item_key) {
            $cart_item['quantity'] += $quantity;
            return;
        }
    }

    // Not already in cart - add new item
    $_SESSION[BB_CART_SESSION_ITEM]['woo'][] = array(
            'name' => get_the_title($product_id),
            'cart_item_key' => $cart_item_key,
            'product_id' => $product_id,
            'quantity' => $quantity,
            'variation_id' => $variation_id,
            'variation' => $variation,
            'cart_item_data' => $cart_item_data,
            'fund_code' => bb_cart_get_fund_code($product_id),
    );
}

function bb_cart_calculate_shipping($total_price = null) {
    if (empty($total_price)) {
        $total_price = bb_cart_total_price(null, false);
    }
    $shipping = 0;

    return apply_filters('bb_cart_calculate_shipping', $shipping, $total_price, $_SESSION[BB_CART_SESSION_ITEM]);
}

function bb_cart_shipping_label() {
    return apply_filters('bb_cart_shipping_label', 'Shipping');
}

/**
 * Add a column to the List of Orders page in WordPress admin
 *
 * @param $columns
 * @return array
 */
function bb_cart_woocommerce_customer_name_add_column($columns) {
    $new_columns = is_array($columns) ? $columns : array();
    unset($new_columns['order_actions']);

    $new_columns['customer_name'] = 'Customer Name';
    $new_columns['order_actions'] = $columns['order_actions'];

    return $new_columns;
}
add_filter( 'manage_edit-shop_order_columns', 'bb_cart_woocommerce_customer_name_add_column' );

/**
 * Place customer name into the column on Orders List page
 *
 * @param string $column
 */
function bb_cart_woocommerce_customer_name_column_value($column) {
    global $post;
    global $woocommerce;

    // Get the order details
    $order = new WC_Order($post->ID);

    // Only do this for "Customer Name" column
    if ($column == 'customer_name') {
        // Get all customer notes of the order
        $customer_notes = $order->get_customer_order_notes();
        $customer_name_found = false;

        foreach($customer_notes as $note) {
            // Put customer's name to the List of order page in WordPress admin
            if (preg_match('/Customer name: /', $note->comment_content)) {
                echo str_replace('Customer name: ', '', $note->comment_content);
                $customer_name_found = true;
                break;
            }
        }
    }
}
add_action('manage_shop_order_posts_custom_column', 'bb_cart_woocommerce_customer_name_column_value', 2);

// WE'LL DO ANOTHER AFTER SUBMISSION ONE TO CORRECTLY UPDATE THE ORIGINAL FORMS WITH THE PAYMENT SUCCESS
add_action("gform_after_submission", "bb_cart_post_purchase_actions", 99, 2);
function bb_cart_post_purchase_actions($entry, $form){
    global $blog_id;

    if (bb_cart_is_checkout_form($form)) {
        $total_amount = bb_cart_total_price();
        if ($total_amount > 0) {
            $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
            $donation_amount = 0;
            $payment_method = 'Credit Card';
            $bb_line_items = array();
            $gf_line_items = array(
                    'products' => array(),
                    'shipping' => array(
                            'name' => bb_cart_shipping_label(),
                            'price' => bb_cart_calculate_shipping(),
                    ),
            );

            foreach ($form['fields'] as $field) {
                if ($field['type'] == 'email') {
                    $email_field_id = $field['id'];
                } else if ($field['uniquenameField'] == 'firstname') {
                    $firstname_field_id = $field['id'];
                } else if ($field['uniquenameField'] == 'lastname') {
                    $lastname_field_id = $field['id'];
                } else if($field['type']== 'date' && !empty($entry[$field['id']])) {
                    $transaction_date = $entry[$field['id']];
                    $transaction_date = date('Y-m-d', strtotime($transaction_date));
                } else if ($field->inputName == 'payment_method') {
                    $payment_method = $entry[$field->id];
                }
            }

            $frequency = bb_cart_frequency();
            $deductible = bb_cart_tax_status();

            $email = $entry[$email_field_id];

            if (isset($_GET['user_id'])) {
                $author_id = $_GET['user_id'];
            } elseif (is_user_logged_in()) {
                $author_id = get_current_user_id();
            } else {
                $user = get_user_by('email', $email);
                if ($user instanceof WP_User) {
                    $author_id = $user->ID;
                }
            }

            if (!empty($author_id)) {
                $firstname = get_user_meta($author_id, 'first_name', true);
                $lastname = get_user_meta($author_id, 'last_name', true);
            }

            if ($payment_method == 'Credit Card') {
                $post_status = 'publish';
                $transaction_status = 'Approved';
            } else {
                $post_status = 'draft';
                $transaction_status = 'Pending';
            }

            // Create post object
            $transaction = array(
                    'post_title' => $firstname . '-' . $lastname . '-' . $total_amount,
                    'post_content' => serialize($entry),
                    'post_status' => $post_status,
                    'post_author' => $author_id,
                    'post_type' => 'transaction',
            );

            //check if transaction date exists
            if (isset($transaction_date) && !empty($transaction_date)) {
                $transaction['post_date'] = $transaction_date;
            } else {
                $transaction['post_date'] = current_time('mysql');
            }

            // Insert the post into the database
            $transaction_id = wp_insert_post($transaction);

            $batch_id = bb_cart_get_web_batch($transaction['post_date']);

            update_post_meta($transaction_id, 'frequency', $frequency);
            update_post_meta($transaction_id, 'gf_entry_id', $entry['id']);
            update_post_meta($transaction_id, 'batch_id', $batch_id);
            update_post_meta($transaction_id, 'donation_amount', $donation_amount);
            update_post_meta($transaction_id, 'total_amount', $total_amount);
            update_post_meta($transaction_id, 'cart', serialize($_SESSION[BB_CART_SESSION_ITEM]));
            update_post_meta($transaction_id, 'payment_method', $payment_method);
            update_post_meta($transaction_id, 'is_receipted', true);

            if (isset($deductible)) {
                update_post_meta($transaction_id, 'is_tax_deductible', (string)$deductible);
            }
            if (!empty($GLOBALS['subscription_id'])) {
                update_post_meta($transaction_id, 'subscription_id', $GLOBALS['subscription_id']);
            }

            foreach ($cart_items as $section => $items) {
                switch ($section) {
                    case 'woo':
                        if (function_exists('WC')) {
                            $wc_session = WC()->session;
                            if (is_object($wc_session)) {
                                define('WOOCOMMERCE_CHECKOUT',true);
                                WC()->cart->calculate_totals();
                                $woo_cart = $wc_session->get('cart', array());
                                $WCCheckout = new WC_Checkout();
                                $order_id = $WCCheckout->create_order();
                                update_post_meta($transaction_id, 'woocommerce_order_id', $order_id);

                                if (!empty($author_id)) {
                                    // Have to hack this as WooCommerce won't set the user unless we go through their checkout process
                                    wp_update_post(array('ID' => $order_id, 'post_author' => $author_id));
                                    update_post_meta($order_id, '_customer_user', $author_id);
                                }

                                $order = wc_get_order($order_id);

                                // Get user address details for WooCommerce order
                                foreach ($form['fields'] as $field) {
                                    if ($field['type'] == 'address') {
                                        $customer_address = array(
                                                'country'    => rgpost("input_".$field["id"]."_6"),
                                                'state'      => rgpost("input_".$field["id"]."_4"),
                                                'postcode'   => rgpost("input_".$field["id"]."_5"),
                                                'city'       => rgpost("input_".$field["id"]."_3"),
                                                'address_1'  => rgpost("input_".$field["id"]."_1"),
                                                'address_2'  => rgpost("input_".$field["id"]."_2"),
                                        );
                                    }
                                }

                                $order->set_address($customer_address, 'shipping');
                                $order->set_address($customer_address); // billing
                                $order->add_order_note('Customer name: ' . $firstname . ' ' . $lastname, true);
                                $total = 0;
                                foreach ($items as $product) {
                                    $price = $woo_cart[$product['cart_item_key']]['line_total'];
                                    $total += $price;
                                    $line_item = array(
                                            'name' => $product['name'],
                                            'price' => $price/$product['quantity'],
                                            'quantity' => $product['quantity'],
                                    );
                                    $gf_line_items['products'][] = $line_item;
                                    $line_item['fund_code'] = $product['fund_code'];
                                    $bb_line_items[] = $line_item;
                                }
                                $shipping = bb_cart_calculate_shipping($total);
                                if ($shipping > 0) {
                                    $bb_line_items[] = array(
                                            'name' => bb_cart_shipping_label(),
                                            'price' => $shipping,
                                            'quantity' => '1',
                                            'fund_code' => 'Postage',
                                    );
                                }
                                $order->set_total($shipping, 'shipping');
                                $grand_total = $total+$shipping;
                                $order->set_total($grand_total);
                                if ($transaction_status == 'Approved') {
                                    $order->payment_complete($transaction_id);
                                }
                            }
                        }
                        break;
                    case 'event':
                        foreach ($items as $event) {
                            $event['booking']->approve();
                            $line_item = array(
                                    'name' => $event['label'],
                                    'price' => $event['booking']->booking_price/$event['booking']->booking_spaces, // @todo is there a way of getting the per-ticket price? Probably not as there could be a combination of different tickets in one booking...
                                    'quantity' => $event['booking']->booking_spaces,
                            );
                            $gf_line_items['products'][] = $line_item;
                            $line_item['fund_code'] = $event['fund_code'];
                            $bb_line_items[] = $line_item;
                        }
                        break;
                    default:
                        foreach ($items as $item) {
                            $line_item = array(
                                    'name' => $item['label'],
                                    'price' => $item['price']/100,
                                    'quantity' => $item['quantity'],
                            );
                            $gf_line_items['products'][] = $line_item;
                            $line_item['fund_code'] = $item['fund_code'];
                            if (get_post_meta($item['fund_code'], 'transaction_type', true) == 'donation') {
                                $donation_amount += $item['price']*$item['quantity']/100;
                            }
                            $bb_line_items[] = $line_item;
                        }
                        break;
                }
            }

            foreach ($bb_line_items as $bb_line_item) {
                $line_item = array(
                        'post_title' => $bb_line_item['name'],
                        'post_status' => 'publish',
                        'post_author' => $author_id,
                        'post_type' => 'transactionlineitem',
                        'post_date' => $transaction['post_date'],
                );
                $line_item_id = wp_insert_post($line_item);
                update_post_meta($line_item_id, 'fund_code', $bb_line_item['fund_code']);
                update_post_meta($line_item_id, 'price', $bb_line_item['price']);
                update_post_meta($line_item_id, 'quantity', $bb_line_item['quantity']);

                $transaction_term = get_term_by('slug', $transaction_id, 'transaction'); // Have to pass term ID rather than slug
                wp_set_post_terms($line_item_id, $transaction_term->term_id, 'transaction');
                if (!empty($bb_line_item['fund_code'])) {
                    $fund_code_term = get_term_by('slug', $bb_line_item['fund_code'], 'fundcode'); // Have to pass term ID rather than slug
                    wp_set_post_terms($line_item_id, $fund_code_term->term_id, 'fundcode');
                }
            }

            foreach ($cart_items as $section => $items) {
                foreach ($items as $item) {
                    if (!empty($item['entry_id'])) {
                        $switched = false;
                        if (!empty($item['blog_id']) && $item['blog_id'] != $blog_id) {
                            switch_to_blog($item['blog_id']);
                            $switched = true;
                        }
                        // Add payment details to the entry of the donate form
                        GFAPI::update_entry_property($item['entry_id'], "payment_status", $transaction_status);
                        GFAPI::update_entry_property($item['entry_id'], "payment_amount", $item['price']/100);
                        GFAPI::update_entry_property($item['entry_id'], "payment_date",   $entry['date_created']);
                        GFAPI::update_entry_property($item['entry_id'], "payment_method", $payment_method);

                        if ($switched) {
                            restore_current_blog();
                        }
                    }
                }
            }

            // Add payment details to the entry of the checkout form
            $entry['payment_amount'] = $total_amount;
            $entry['payment_status'] = $transaction_status;
            $entry['payment_date'] = $entry['date_created'];
            $entry['payment_method'] = $payment_method;
            GFAPI::update_entry_property($entry['id'], "payment_amount", $total_amount);
            GFAPI::update_entry_property($entry['id'], "payment_status", $transaction_status);
            GFAPI::update_entry_property($entry['id'], "payment_date",   $entry['date_created']);
            GFAPI::update_entry_property($entry['id'], "payment_method", $payment_method);
            gform_update_meta($entry['id'], 'gform_product_info__', $gf_line_items);

            do_action('bb_cart_post_purchase', $cart_items, $entry, $form, $transaction_id);
            if (function_exists('WC')) {
                WC()->cart->empty_cart();
            }
            $_SESSION['last_checkout'] = $entry['id'];
        }
        bb_cart_end_session();
    }
}

function bb_cart_get_web_batch($date = null) {
    if (empty($date)) {
        $date = current_time('mysql');
    }

    $date_obj = new DateTime($date);
    $batch_date = $date_obj->format('Y-m-d');
    $batch_name = 'WEB '.$batch_date;

    $existing_batch = get_page_by_title($batch_name, OBJECT, 'transactionbatch');

    if ($existing_batch instanceof WP_Post) {
        return $existing_batch->ID;
    } else {
        // Create batch
        $batch = array(
                'post_title' => $batch_name,
                'post_content' => '',
                'post_status' => 'pending',
                'post_type' => 'transactionbatch',
                'post_date' => $batch_date,
        );

        // Insert the post into the database
        return wp_insert_post($batch);
    }
}

/**
 * Does the transaction already exist?
 * @param array $data
 * @return boolean
 */
function bb_cart_transaction_exists(array $data) {
    $args = array(
            'post_type' => 'transaction',
            'author' => $data['user']->ID,
            'date_query' => array(
                    array(
                            'year'  => date('Y', strtotime($data['date'])),
                            'month' => date('m', strtotime($data['date'])),
                            'day'   => date('d', strtotime($data['date'])),
                    ),
            ),
            'meta_query' => array(
                    array(
                            'key' => 'total_amount',
                            'value' => $data['amount'],
                    ),
            ),
    );
    $transactions = get_posts($args);
    if (count($transactions) > 0) {
        if (!empty($data['fund_code'])) {
            foreach ($transactions as $transaction) {
                $line_args = array(
                        'post_type' => 'transactionlineitem',
                        'meta_query' => array(
                                array(
                                        'key' => 'price',
                                        'value' => $data['amount'],
                                ),
                        ),
                        'tax_query' => array(
                                array(
                                        'taxonomy' => 'fundcode',
                                        'field' => 'name',
                                        'terms' => $data['fund_code'],
                                        'include_children' => false,
                                ),
                                array(
                                        'taxonomy' => 'transaction',
                                        'field' => 'slug',
                                        'terms' => $transaction->ID,
                                ),
                        ),
                );
                $line_items = get_posts($line_args);
                echo '<pre>'; var_dump($line_args, $line_items); echo '</pre>';
                if (count($line_items) > 0) {
                    // Matching transaction and line item found
                    return true;
                }
            }
            // Similar transaction found but different fund code
            return false;
        }
        // Matching transaction found and we're not checking fund code
        return true;
    }
    // No match
    return false;
}

function bb_cart_get_transaction_for_subscription($subscription_id) {
    $args = array(
            'post_type' => 'transaction',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => array(
                    array(
                            'key' => 'subscription_id',
                            'value' => $subscription_id,
                    ),
            ),
    );
    $transactions = get_posts($args);
    if (count($transactions)) {
        return array_shift($transactions);
    }
    return false;
}

/**
 * Find the transaction for the specified entry
 * @param int $entry_id
 * @return mixed
 */
function bb_cart_get_transaction_from_entry($entry_id) {
    $args = array(
            'post_type' => 'transaction',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => array(
                    array(
                            'key' => 'gf_entry_id',
                            'value' => $entry_id,
                    ),
            ),
    );
    $transactions = get_posts($args);
    if (count($transactions)) {
        return array_shift($transactions);
    }
    return false;
}

/**
 * Get cart contents for the specified entry
 * @param array $entry
 * @return mixed|boolean
 */
function bb_cart_get_cart_from_entry($entry) {
    $transaction = bb_cart_get_transaction_from_entry($entry['id']);
    if ($transaction) {
        return maybe_unserialize(get_post_meta($transaction->ID, 'cart', true));
    }
    return false;
}

function bb_cart_get_transaction_line_items($transaction_id) {
    $args = array(
            'post_type' => 'transactionlineitem',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'tax_query' => array(
                    array(
                            'taxonomy' => 'transaction',
                            'field' => 'slug',
                            'terms' => $transaction_id,
                    ),
            ),
    );
    $line_items = get_posts($args);
    if (count($line_items)) {
        return $line_items;
    }
    return false;
}

add_filter('gform_paypal_query', 'bb_cart_paypal_line_items', 10, 5);
function bb_cart_paypal_line_items($query_string, $form, $entry, $feed, $submission_data) {
    parse_str(ltrim($query_string, '&'), $query);
    $i = 1;
    foreach ($_SESSION[BB_CART_SESSION_ITEM] as $section => $items) {
        foreach ($items as $cart_item) {
            $query['item_name_'.$i] = $cart_item['label'];
            $query['amount_'.$i] = $cart_item['price']/100;
            $query['quantity_'.$i] = $cart_item['quantity'];
            $i++;
        }
    }
    $query_string = '&' . http_build_query($query);
    return $query_string;
}

add_action('gform_paypal_post_ipn', 'bb_maf_sf_complete_paypal_transaction', 10, 4);
function bb_cart_complete_paypal_transaction($ipn_post, $entry, $feed, $cancel) {
    $now = date('Y-m-d H:i:s');
    $transaction = bb_cart_get_transaction_from_entry($entry['id']);
    if ($transaction) {
        bb_cart_complete_pending_transaction($transaction->ID, $now, $entry);
    }
}

/**
 * Mark a pending transaction (pledge) as complete/paid
 * @param int $transaction_id Transaction post ID
 * @param string $date Date of payment (Y-m-d H:i:s)
 * @param array $entry Optional GF entry. If not specified entry will be loaded from transaction meta
 */
function bb_cart_complete_pending_transaction($transaction_id, $date, $entry = null) {
    if (is_null($entry)) {
        $entry = GFAPI::get_entry(get_post_meta($transaction_id, 'gf_entry_id', true));
    }
    if ($entry['payment_status'] != 'Approved') {
        wp_publish_post($transaction_id);
        $entry['payment_status'] = 'Approved';
        $entry['payment_date'] = $date;
        GFAPI::update_entry_property($entry['id'], "payment_status", 'Approved');
        GFAPI::update_entry_property($entry['id'], "payment_date", $date);
        $form = GFAPI::get_form($entry['form_id']);
        foreach ($form['fields'] as $field) {
            if ($field->inputName == 'bb_cart_checkout_items_array') {
                $item_entries = explode(',', $entry[$field->id]);
                foreach ($item_entries as $item_entry) {
                    GFAPI::update_entry_property($item_entry, "payment_status", 'Approved');
                    GFAPI::update_entry_property($item_entry, "payment_date", $date);
                }
            }
        }
        $order_id = get_post_meta($transaction_id, 'woocommerce_order_id', true);
        if ($order_id) {
            $order = new WC_Order($order_id);
            $order->payment_complete($transaction_id);
        }
        do_action('bb_cart_complete_pending_transaction', $transaction_id, $date, $entry, $form);
    }
}

/**
 * Mark a pending transaction (pledge) as lost/failed
 * @param int $transaction_id Transaction post ID
 * @param array $entry Optional GF entry. If not specified entry will be loaded from transaction meta
 */
function bb_cart_cancel_pending_transaction($transaction_id, $message, $entry = null) {
    if (is_null($entry)) {
        $entry = GFAPI::get_entry(get_post_meta($transaction_id, 'gf_entry_id', true));
    }
    if ($entry['payment_status'] != 'Failed') {
        $transaction->post_content .= "\n\nTransaction marked as cancelled. Message: ".$message;
        wp_trash_post($transaction_id);
        GFAPI::update_entry_property($entry['id'], "payment_status", 'Failed');
        $form = GFAPI::get_form($entry['form_id']);
        foreach ($form['fields'] as $field) {
            if ($field->inputName == 'bb_cart_checkout_items_array') {
                $item_entries = explode(',', $entry[$field->id]);
                foreach ($item_entries as $item_entry) {
                    GFAPI::update_entry_property($item_entry['id'], "payment_status", 'Failed');
                }
            }
        }
    }
    $order_id = get_post_meta($transaction_id, 'woocommerce_order_id', true);
    if ($order_id) {
        $order = new WC_Order($order_id);
        $order->update_status('failed');
    }
}

function bb_cart_is_checkout_form($form) {
    $is_checkout = false;
    if ((!empty($form['bb_checkout_enable']) && $form['bb_checkout_enable']=="checkout_enabled")) {
        $is_checkout = true;
    } else {
        foreach ($form['fields'] as $field) {
            if ($field['type'] == 'creditcard') {
                $is_checkout = true;
                break;
            }
        }
    }
    return $is_checkout;
}

add_filter("gform_notification", "bb_cart_configure_notifications", 10, 3);
function bb_cart_configure_notifications($notification, $form, $entry) {
    if (bb_cart_total_quantity() > 0) {
        $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
    } else {
        $cart_items = bb_cart_get_cart_from_entry($entry);
    }

    if (!empty($cart_items)) {
        $notification['message'] = str_replace("!!!items!!!", bb_cart_table('email', $cart_items), $notification['message']);
    }
    return $notification;
}

function bb_cart_shortcode() {
    return bb_cart_table();
}
add_shortcode('bb_cart_table', 'bb_cart_shortcode');

function bb_cart_table($purpose = 'table', array $cart_items = array()) {
    if (empty($cart_items)) {
        $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
    }

    switch ($purpose) {
        case 'email':
            $cols = 2;
            break;
        case 'table':
        default:
            $cols = 3;
            break;
    }
    $html = '';
    if (!empty($cart_items)) {
        foreach ($cart_items as $section => $items) {
            $html .= '<table class="bb-table" width="100%">'."\n";
            switch ($section) {
                case 'woo':
                    if (function_exists('WC')) {
                        $wc_session = WC()->session;
                        if (is_object($wc_session)) {
                            $woo_cart = $wc_session->get('cart', array());
                            $html .= '<tr><th colspan="'.$cols.'" style="text-align:left;">Products</th></tr>';
                            $total = 0;
                            foreach ($items as $idx => $product) {
                                $price = $woo_cart[$product['cart_item_key']]['line_total'];
                                $total += $price;
                                $html .= '<tr><td>'.$product['quantity'].'x <a href="'.get_the_permalink($product['product_id']).'">'.$product['name'].'</a></td>'."\n";
                                $html .= '<td class="text-right">$'.number_format($price, 2).'</td>'."\n";
                                if ($purpose != 'email') {
                                    $html .= '<td style="width: 15px;"><a href="'.add_query_arg('remove_item', $section.':'.$idx).'" title="Remove" class="delete" onclick="return confirm(\'Are you sure you want to remove this item?\');">x</a></td>'."\n";
                                }
                                $html .= '</tr>';
                            }
                            $shipping = bb_cart_calculate_shipping($total);
                            if ($shipping > 0) {
                                $html .= '<tr><td>'.bb_cart_shipping_label().'</td><td style="text-align: right;">$'.number_format($shipping, 2).'</td>';
                                if ($purpose != 'email') {
                                    $html .= '<td>&nbsp;</td>';
                                }
                                $html .= '</tr>'."\n";
                            }
                        }
                    }
                    break;
                case 'events':
                    $html .= '<tr><th colspan="'.$cols.'" style="text-align:left;">'.ucwords($section).'</th></tr>';
                    foreach ($items as $idx => $event) {
                        $html .= '<tr><td>'.$event['booking']->booking_spaces.' registration/s for '.$event['event']->event_name.' ('.$event['event']->event_start_date.')</td>'."\n";
                        $html .= '<td style="text-align: right;">$'.number_format($event['booking']->booking_price, 2).'</td>'."\n";
                        if ($purpose != 'email') {
                            $html .= '<td style="width: 15px;"><a href="'.add_query_arg('remove_item', $section.':'.$idx).'" title="Remove" class="delete" onclick="return confirm(\'Are you sure you want to remove this item?\');">x</a></td>'."\n";
                        }
                        $html .= '</tr>';
                    }
                    break;
                default:
                    $html .= '<tr><th colspan="'.$cols.'" style="text-align:left;">'.ucwords($section).'</th></tr>';
                    foreach ($items as $idx => $item) {
                        $html .= '<tr>'."\n";
                        $label = $item['label'];
                        if ($item['quantity'] > 1) {
                            $label = $item['quantity'].'x '.$label;
                        }
                        $html .= '<td>'.$label.'</td>'."\n";
                        $item_price = ($item['price']*$item['quantity'])/100;
                        $total_price += $item_price;
                        $frequency = empty($item['frequency']) || $item['frequency'] == 'one-off' ? '' : '/'.ucfirst($item['frequency']);
                        $html .= '<td style="text-align: right;">$'.number_format($item_price, 2).$frequency.'</td>'."\n";
                        if ($purpose != 'email') {
                            $html .= '<td style="width: 15px;"><a href="'.add_query_arg('remove_item', $section.':'.$idx).'" title="Remove" class="delete" onclick="return confirm(\'Are you sure you want to remove this item?\');">x</a></td>'."\n";
                        }
                        $html .= '</tr>'."\n";
                    }
                    break;
            }
            $html .= '</table>'."\n";
        }
        $html .= '<p class="h5" style="text-align: right; font-weight: bold;">Total: $'.number_format(bb_cart_total_price(), 2).'</p>'."\n";
    }
    return $html;
}

add_filter('gform_validation', 'bb_cart_fraud_detection', 0); // Do this before anything else
function bb_cart_fraud_detection($validation_result) {
    $form = $validation_result['form'];
    if (bb_cart_is_checkout_form($form)) {
        global $bb_cart_fraud_score;
        $bb_cart_fraud_score = 0;
        if (bb_cart_total_price() > 0 && bb_cart_total_price() < 2) {
            $bb_cart_fraud_score += 1;
        }
        $entry = GFFormsModel::get_current_lead();
        foreach ($form['fields'] as &$field) {
            if ($field->type == 'email') {
                $email = rgar($entry, $field->id);
                $dodgy_emails = array(
                        'juanmwilcox@rhyta.com',
                        'brandydpearson@dayrep.com',
                        'mrcyshps.551087354@macr2.com',
                        'love@love.com',
                        'mikemike222@macr2.com',
                        'MarkSHarvey@armyspy.com',
                        'identionevid1945@getapet.net',
                );
                if (in_array(strtolower($email), $dodgy_emails)) {
                    $bb_cart_fraud_score += 2;
                }
            } elseif ($field->type == 'creditcard') {
                $cc_field =& $field;
            }
        }
        if ($bb_cart_fraud_score > 0) {
            $cc_field->failed_validation = true;
            $cc_field->validation_message = 'We were unable to process your payment at this time. Please try again later. If you continue to experience issues, please contact us. ('.$bb_cart_fraud_score.')';
            $validation_result['is_valid'] = false;
            $validation_result['form'] = $form;
        }
    }
    return $validation_result;
}

/**
 * Get selected fund code for the specified post
 * @param integer $id Post ID
 * @return integer Fund Code ID (will return default fund code if none specified on post)
 */
function bb_cart_get_fund_code($id) {
    $fund_codes = wp_get_object_terms($id, 'fundcode');
    if (count($fund_codes)) {
        return $fund_codes[0]->slug;
    }
    return bb_cart_get_default_fund_code();
}

function bb_cart_get_default_fund_code() {
    return get_option('bb_cart_default_fund_code');
}

function bb_cart_load_fund_code($fund_code) {
    if (is_numeric($fund_code)) {
        return get_post($fund_code);
    }
    return get_page_by_title($fund_code, OBJECT, 'fundcode');
}
