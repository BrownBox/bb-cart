<?php
/**
 * Plugin Name: BB Cart
 * Description: A cart system for Gravity Forms. Includes session and checkout functionality, transaction tracking and reporting.
 * Version: 3.8.21
 * Author: Spark Web Solutions
 * Author URI: https://sparkweb.com.au
 */

define('BB_CART_DIR', plugin_dir_path(__FILE__));
define('BB_CART_URL', plugin_dir_url(__FILE__));
define('BB_CART_VERSION', '3.8.21');

require_once(BB_CART_DIR.'time.php');
require_once(BB_CART_DIR.'ia/cpt_.php');
require_once(BB_CART_DIR.'ia/meta_.php');
require_once(BB_CART_DIR.'ia/tax_.php');
require_once(BB_CART_DIR.'ia/cpt_tax_.php');
require_once(BB_CART_DIR.'ia/ia.php');
require_once(BB_CART_DIR.'forms/forms.php');
require_once(BB_CART_DIR.'forms/prerenders.php');
require_once(BB_CART_DIR.'forms/presubmission.php');
require_once(BB_CART_DIR.'forms/post-process.php');
require_once(BB_CART_DIR.'forms/confirmation.php');
require_once(BB_CART_DIR.'forms/update-entry.php');
require_once(BB_CART_DIR.'admin/fx.php');
require_once(BB_CART_DIR.'admin/settings.php');
require_once(BB_CART_DIR.'admin/pledges.php');
require_once(BB_CART_DIR.'admin/import.php');
require_once(BB_CART_DIR.'admin/export.php');
require_once(BB_CART_DIR.'admin/fund-code-report.php');
require_once(BB_CART_DIR.'admin/batch-management.php');
require_once(BB_CART_DIR.'admin/donor-history.php');
require_once(BB_CART_DIR.'admin/offline-email-receipts.php');
require_once(BB_CART_DIR.'admin/bbconnect.php');
// require_once(BB_CART_DIR.'demo/config.class.php');

require_once(BB_CART_DIR.'admin/updates.php');
if (is_admin()) {
	new BbCartUpdates(__FILE__, 'BrownBox', 'bb-cart');
}

// Session variable keys
define('BB_CART_SESSION_ITEM', 'bb_cart_item');
define('BB_CART_SESSION_SHIPPING_TYPE', 'bb_cart_shipping_type');
define('BB_CART_SESSION_SHIPPING_ADDRESS', 'bb_cart_shipping_address');
/**
 * @deprecated Use BB_CART_SESSION_SHIPPING_ADDRESS instead
 */
define('BB_CART_SESSION_SHIPPING_POSTCODE', 'bb_cart_shipping_postcode');
/**
 * @deprecated Use BB_CART_SESSION_SHIPPING_ADDRESS instead
 */
define('BB_CART_SESSION_SHIPPING_SUBURB', 'bb_cart_shipping_suburb');

// JUST DO SOME SESSION STUFF HERE TO KEEP IT CLEAN + NOT CREATE ANY SESSION PROBLEMS
add_action('init', 'bb_cart_start_session');
function bb_cart_start_session() {
	if (!session_id()) {
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
			$wc_cart = WC()->cart->get_cart();
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
			$currency = apply_filters('wcml_get_client_currency', get_option('woocommerce_currency'));
			if (empty($currency)) {
				$currency = bb_cart_get_default_currency();
			}
			foreach ($wc_cart as $woo_idx => $woo_item) {
				$_product = apply_filters('woocommerce_cart_item_product', $woo_item['data'], $woo_item, $woo_idx);
				$label = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $woo_item, $woo_idx);
				$fund_code_id = bb_cart_get_fund_code($woo_item['product_id']);
				$fund_code_deductible = get_post_meta($fund_code_id, 'deductible', true);
				$deductible = $fund_code_deductible == 'true';
				$price = ($woo_item['line_total']+$woo_item['line_tax'])/$woo_item['quantity'];
				$price *= 100;
				if (!empty($_SESSION[BB_CART_SESSION_ITEM]['woo'])) {
					foreach ($_SESSION[BB_CART_SESSION_ITEM]['woo'] as &$cart_item) {
						if ($cart_item['cart_item_key'] == $woo_idx) { // Found it - let's make sure the details are the same
							$cart_item = array(
									'label' => $label,
									'cart_item_key' => $woo_idx,
									'product_id' => $woo_item['product_id'],
									'price' => $price,
									'quantity' => $woo_item['quantity'],
									'variation_id' => $woo_item['variation_id'],
									'variation' => $woo_item['variation'],
									'cart_item_data' => $woo_item['cart_item_data'],
									'fund_code' => $fund_code_id,
									'transaction_type' => 'purchase',
									'deductible' => $deductible,
									'currency' => $currency,
									'tax' => $woo_item['line_tax'] ?: 0,
							);
							continue(2); // Found it - go to next $woo_item
						}
					}
				}

				// Not found - add it
				$_SESSION[BB_CART_SESSION_ITEM]['woo'][] = array(
						'label' => $label,
						'cart_item_key' => $woo_idx,
						'product_id' => $woo_item['product_id'],
						'price' => $price,
						'quantity' => $woo_item['quantity'],
						'variation_id' => $woo_item['variation_id'],
						'variation' => $woo_item['variation'],
						'cart_item_data' => $woo_item['cart_item_data'],
						'fund_code' => $fund_code_id,
						'transaction_type' => 'purchase',
						'deductible' => $deductible,
						'currency' => $currency,
						'tax' => $woo_item['line_tax'] ?: 0,
				);
			}
		}
	}

	// Now we try to work around Event Manager idiocy
	if (is_user_logged_in() && !empty($_SESSION[BB_CART_SESSION_ITEM]['event'])) {
		foreach ($_SESSION[BB_CART_SESSION_ITEM]['event'] as $event) {
			$booking = new EM_Booking($event['booking_id']);
			$booking->person_id = get_current_user_id();
			$booking->manage_override = true; // Allow user to manage their own booking
			$booking->save(false);
		}
	}
}

function bb_cart_end_session() {
	if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
		unset($_SESSION[BB_CART_SESSION_ITEM]);
	}
	bb_cart_reset_shipping(true);
}

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

add_action('template_redirect', 'bb_cart_remove_item_from_cart');
function bb_cart_remove_item_from_cart() {
	if(isset($_GET['remove_item'])) {
		$item = $_GET['remove_item'];
		if (strpos($item, ':') !== false) {
			list($section, $item) = explode(':', $item);
			if ($section == 'woo' && function_exists('WC')) {
				$woo_cart_item = $_SESSION[BB_CART_SESSION_ITEM][$section][$item]['cart_item_key'];
				WC()->cart->remove_cart_item($woo_cart_item);
			}
			$removed_item = $_SESSION[BB_CART_SESSION_ITEM][$section][$item];
			$removed_section = $section;
			unset($_SESSION[BB_CART_SESSION_ITEM][$section][$item]);

			if (empty($_SESSION[BB_CART_SESSION_ITEM][$section])) {
				unset($_SESSION[BB_CART_SESSION_ITEM][$section]);
			}
		} else {
			$removed_item = $_SESSION[BB_CART_SESSION_ITEM][$item];
			$removed_section = null;
			unset($_SESSION[BB_CART_SESSION_ITEM][$item]);
		}
		bb_cart_reset_shipping();
		$redirect = apply_filters('bb_cart_remove_item_from_cart_redirect', remove_query_arg('remove_item'), $removed_item, $removed_section);
		wp_redirect($redirect);
		exit;
	}
}

// THIS IS OUR FUNCTION FOR CLEANING UP THE PRICING AMOUNTS THAT GF SPITS OUT
function bb_cart_clean_amount($amount, $currency_code = null) {
	$amount = preg_replace("/\|(.*)/", '', $amount); // Replace everything from the pipe symbol forward
	if (is_null($currency_code)) {
		$currency_code = bb_cart_get_default_currency();
	}
	return GFCommon::to_number($amount, $currency_code)*100;
}

function bb_cart_format_currency($amount, $currency_code = null) {
	if (is_null($currency_code)) {
		$currency_code = bb_cart_get_default_currency();
	}
	return GFCommon::to_money($amount, $currency_code);
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

add_filter("gform_field_value_bb_cart_total_price", "bb_cart_field_value_total_price");
function bb_cart_field_value_total_price($value = '') {
	return bb_cart_total_price();
}

function bb_cart_total_price($include_shipping = true, array $cart_items = array()) {
	$total = 0;
	if (empty($cart_items) && !empty($_SESSION[BB_CART_SESSION_ITEM])) {
		$cart_items = $_SESSION[BB_CART_SESSION_ITEM];
	}
	foreach ($cart_items as $section => $items) {
		$total += bb_cart_section_total($section, $include_shipping, $cart_items);
	}
	return $total;
}

function bb_cart_products_total($include_shipping = true, array $cart_items = array()) {
	$woo_total = 0;
	if (empty($cart_items)) {
		$cart_items = $_SESSION[BB_CART_SESSION_ITEM];
	}
	if (!empty($cart_items['woo'])) {
		foreach ($cart_items['woo'] as $product) {
			$woo_total += ($product['price']*$product['quantity'])/100;
		}

		// Calculate shipping
		if ($include_shipping) {
			$woo_total += bb_cart_calculate_shipping($woo_total);
		}
	}
	return $woo_total;
}

function bb_cart_events_total(array $cart_items = array()) {
	$events_total = 0;
	if (empty($cart_items)) {
		$cart_items = $_SESSION[BB_CART_SESSION_ITEM];
	}
	if (!empty($cart_items['event'])) {
		foreach ($cart_items['event'] as $event) {
			$events_total += $event['price'];
		}
	}
	return $events_total;
}

function bb_cart_section_total($section = 'donations', $include_shipping = true, $cart_items = array()) {
	switch ($section) {
		case 'woo':
			return bb_cart_products_total($include_shipping, $cart_items);
			break;
		case 'event':
			return bb_cart_events_total($cart_items);
			break;
		default:
			$section_total = 0;
			if (empty($cart_items)) {
				$cart_items = $_SESSION[BB_CART_SESSION_ITEM];
			}
			if (!empty($cart_items[$section])) {
				foreach ($cart_items[$section] as $item) {
					$section_total += $item['price']*$item['quantity'];
				}
			}
			$section_total = $section_total/100;
			if ($include_shipping && apply_filters('bb_cart_section_incurs_shipping', false, $section)) {
				$section_total += bb_cart_calculate_shipping($section_total);
			}
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

add_filter("gform_field_value_bb_cart_total_quantity", function() {return 1;}); // Always set quantity to 1 otherwise total gets multiplied by quantity

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
							$cart = WC()->cart->get_cart();
							foreach ($cart as $product) {
								$count += $product['quantity'];
							}
						}
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
	return round($value, 2);
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
	return round($value, 2);
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
	return round($value, 2);
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
	$value += bb_cart_calculate_shipping();
	return round($value, 2);
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
				case 'other':
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
			if (!empty($item['campaign'])) {
				return $item['campaign'];
			} elseif (!empty($item['campaign_id'])) {
				return $item['campaign_id'];
			}
		}
	}
	return $value;
}

add_filter("gform_field_value_bb_donation_frequency", "bb_cart_frequency");
function bb_cart_frequency($value = '') {
	$value = 'one-off';
	if (!empty($_SESSION[BB_CART_SESSION_ITEM]['donations'])) {
		foreach ($_SESSION[BB_CART_SESSION_ITEM]['donations'] as $item) {
			if (!empty($item['frequency'])) {
				return $item['frequency'];
			}
		}
	}
	return $value;
}

add_filter("gform_field_value_bb_cart_tax_deductible", "bb_cart_tax_status");
function bb_cart_tax_status($value = '') {
	if (!empty($_SESSION[BB_CART_SESSION_ITEM]['donations'])) {
		foreach ($_SESSION[BB_CART_SESSION_ITEM]['donations'] as $item) {
			return $item['deductible'];
		}
	}
	return $value;
}

add_filter("gform_field_value_bb_cart_currency", "bb_cart_currency");
function bb_cart_currency($value = '') {
	if (!empty($_SESSION[BB_CART_SESSION_ITEM]['donations'])) {
		foreach ($_SESSION[BB_CART_SESSION_ITEM]['donations'] as $item) {
			return $item['currency'];
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
	$entry_ids = array();
	if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
		foreach ($_SESSION[BB_CART_SESSION_ITEM] as $section => $items) {
			foreach ($items as $item) {
				if (!empty($item['entry_id'])) {
					$entry_ids[] = $item['entry_id'];
				}
			}
		}
	}
	return implode(',', $entry_ids);
}

function bb_cart_get_default_currency() {
	if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
		foreach ($_SESSION[BB_CART_SESSION_ITEM] as $section => $items) {
			foreach ($items as $item) {
				if (!empty($item['currency'])) {
					return $item['currency'];
				}
			}
		}
	}
	return apply_filters('wcml_get_client_currency', GFCommon::get_currency());
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
	$page_id = $post->ID;
	$campaign = $post->ID;
	$quantity = 1;
	$variations = array();
	$sku = $donation_target = $message = $fund_code = '';
	$label = 'My Donation';
	$currency = bb_cart_get_default_currency();
	if (!empty($form['bb_cart_enable']) && $form['bb_cart_enable']=="cart_enabled") {
		// ANNOYINGLY HAVE TO RUN THIS ALL THROUGH ONCE TO SET THE FIELD LABEL IN CASE THERE'S A CUSTOM LABEL SET
		foreach ($form['fields'] as $field) {
			if ($field['inputName']=='bb_cart_custom_item_label' && !empty($entry[$field['id']])) {
				$label = $entry[$field['id']];
			} elseif (($field['inputName'] == 'bb_cart_interval' || $field['adminLabel'] == 'bb_donation_frequency') && !empty($entry[$field['id']])) {
				$frequency = $entry[$field['id']];
			} elseif ($field['inputName'] == 'bb_cart_tax_deductible' || $field['inputName'] == 'bb_tax_status') {
				$deductible = (boolean)$entry[$field['id']];
			} elseif ($field['inputName'] == 'bb_campaign' && !empty($entry[$field['id']])) {
				$campaign = $entry[$field['id']];
			} elseif ($field['inputName'] == 'bb_cart_fund_code' && !empty($entry[$field['id']])) {
				$fund_code = $entry[$field['id']];
			} elseif ($field['type'] == 'quantity' && !empty($entry[$field['id']])) {
				$quantity = $entry[$field['id']];
			} elseif (strpos($field->inputName, 'variation') !== false && !empty($entry[$field['id']])) {
				$variations[] = $entry[$field['id']];
			} elseif ($field['inputName'] == 'bb_sku' && !empty($entry[$field['id']])) {
				$sku = $entry[$field['id']];
			} elseif ($field['inputName'] == 'bb_cart_donation_for' && !empty($entry[$field['id']])) {
				$donation_target = $entry[$field['id']];
			} elseif ($field['inputName'] == 'bb_cart_donation_message' && !empty($entry[$field['id']])) {
				$message = $entry[$field['id']];
			} elseif (($field['inputName'] == 'bb_cart_donation_member' || $field['inputName'] == 'bb_cart_donation_campaign') && !empty($entry[$field['id']])) {
				$campaign = $entry[$field['id']];
			} elseif ($field['inputName'] == 'bb_cart_purchase_type' && !empty($entry[$field['id']])) {
				$section = $entry[$field['id']];
			} elseif ($field['inputName'] == 'bb_cart_page_id') {
				$page_id = $entry[$field['id']];
			} elseif ($field['inputName'] == 'donation_target') {
				$donation_for = $entry[$field['id']];
			} elseif ($field['inputName'] == 'bb_cart_currency') {
				$currency = $entry[$field['id']];
			}
		}

		global $blog_id;
		$products = GFCommon::get_product_fields($form, $entry);
		foreach ($products['products'] as $product) {
			$old_quantity = $quantity;
			$old_label = $label;

			$label = $product['name'];
			$amount = bb_cart_clean_amount($product['price'], $currency);
			if (!empty($product['options'])) {
				$options_label = '';
				foreach ($product['options'] as $option) {
					$amount += bb_cart_clean_amount($option['price'], $currency);
					if (!empty($options_label)) {
						$options_label .= ', ';
					}
					$options_label .= '+'.$option['option_name'];
				}
				if (!empty($options_label)) {
					$label .= ' ('.$options_label.')';
				}
			}
			if ($amount > 0) {
				$cart_item = array(
						'label' => $label,
						'currency' => $currency,
						'price' => $amount,
						'form_id' => $form['id'],
						'entry_id' => $entry['id'],
						'frequency' => $frequency,
						'page_id' => $page_id,
						'campaign' => $campaign,
						'fund_code' => $fund_code,
						'deductible' => $deductible,
						'transaction_type' => $transaction_type,
						'original_fund_code' => $fund_code,
						'blog_id' => $blog_id,
						'quantity' => $product['quantity'],
						'variation' => $variations,
						'sku' => $sku,
						'target' => $donation_target,
						'donation_for' => $donation_for,
						'comments' => $message,
				);

				$fund_code = apply_filters('bb_cart_fund_code', $fund_code, $entry, $cart_item);

				$fund_code_post = bb_cart_load_fund_code($fund_code);
				if ($fund_code_post instanceof WP_Post) {
					$fund_code_deductible = get_post_meta($fund_code_post->ID, 'deductible', true);
					$deductible = $fund_code_deductible == 'true';
					$transaction_type = get_post_meta($fund_code_post->ID, 'transaction_type', true);
				}

				$cart_item['fund_code'] = $fund_code;
				$cart_item['deductible'] = $deductible;
				$cart_item['transaction_type'] = $transaction_type;

				$_SESSION[BB_CART_SESSION_ITEM][$section][] = apply_filters('bb_cart_new_cart_item', $cart_item, $section, $form, $entry);
			}
			$quantity = $old_quantity;
			$label = $old_label;
		}
		foreach ($form['fields'] as $field) {
			$amount = 0;
			$old_quantity = $quantity;
			$old_label = $label;

			if ($field->type == 'envoyrecharge') { // @deprecated
				$amount = bb_cart_clean_amount($entry[$field["id"].'.1'], $currency);
				if ($entry[$field["id"].'.5'] == 'recurring') {
					$frequency = $entry[$field["id"].'.2'];
				}
			} elseif ($field->type == 'bb_click_array') {
				$amount = bb_cart_clean_amount($entry[$field["id"].'.1'], $currency);
			}
			if ($amount > 0 && $quantity > 0) {
				// Now we can add products to our session
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

				$cart_item = array(
						'label' => $label,
						'currency' => $currency,
						'price' => $amount,
						'form_id' => $form['id'],
						'entry_id' => $entry['id'],
						'frequency' => $frequency,
						'page_id' => $page_id,
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
						'comments' => $message,
				);

				$fund_code = apply_filters('bb_cart_fund_code', $fund_code, $entry, $cart_item);

				$fund_code_post = bb_cart_load_fund_code($fund_code);
				if ($fund_code_post instanceof WP_Post) {
					$fund_code_deductible = get_post_meta($fund_code_post->ID, 'deductible', true);
					$deductible = $fund_code_deductible == 'true';
					$transaction_type = get_post_meta($fund_code_post->ID, 'transaction_type', true);
				}

				$cart_item['fund_code'] = $fund_code;
				$cart_item['deductible'] = $deductible;
				$cart_item['transaction_type'] = $transaction_type;

				$_SESSION[BB_CART_SESSION_ITEM][$section][] = apply_filters('bb_cart_new_cart_item', $cart_item, $section, $form, $entry);
			}
			$quantity = $old_quantity;
			$label = $old_label;
		}
	}
}

add_action('wp', 'bb_cart_add_from_querystring', 99);
function bb_cart_add_from_querystring() {
	if (!empty($_GET['add_to_cart'])) {
		$price = bb_cart_clean_amount($_GET['add_to_cart']);
		if ($price > 0) {
			global $blog_id;

			$section = !empty($_GET['type']) ? $_GET['type'] : 'donations';
			$label = !empty($_GET['label']) ? $_GET['label'] : 'My Donation';
			$frequency = !empty($_GET['frequency']) ? $_GET['frequency'] : 'one-off';
			$sku = !empty($_GET['sku']) ? sanitize_text_field($_GET['sku']) : null;
			$fund_code = !empty($_GET['fund_code']) ? (int)$_GET['fund_code'] : bb_cart_get_default_fund_code();
			$fund_code_post = bb_cart_load_fund_code($fund_code);
			if ($fund_code_post instanceof WP_Post) {
				$fund_code_deductible = get_post_meta($fund_code_post->ID, 'deductible', true);
				$deductible = $fund_code_deductible == 'true';
				$transaction_type = get_post_meta($fund_code_post->ID, 'transaction_type', true);
			}

			$cart_item = array(
					'label' => $label,
					'currency' => bb_cart_get_default_currency(),
					'price' => $price,
					'frequency' => $frequency,
					'blog_id' => $blog_id,
					'fund_code' => $fund_code,
					'deductible' => $deductible,
					'transaction_type' => $transaction_type,
					'quantity' => 1,
					'sku' => $sku,
			);
			$_SESSION[BB_CART_SESSION_ITEM][$section][] = apply_filters('bb_cart_new_cart_item', $cart_item, $section, $form, $entry);
		}
		wp_redirect(remove_query_arg(array('add_to_cart', 'sku', 'frequency', 'label', 'type')));
		exit;
	} elseif (isset($_GET['cover_costs']) && '1' == $_GET['cover_costs']) {
		bb_cart_cover_costs();
		wp_redirect(remove_query_arg(array('cover_costs')));
		exit;
	}
}

add_action('wp', 'bb_cart_recalculate_costs');
function bb_cart_recalculate_costs() {
	// Recalculate costs in case the cart has changed
	if (bb_cart_is_covering_costs()) {
		bb_cart_cover_costs();
	}
}

function bb_cart_is_covering_costs() {
	if (isset($_SESSION[BB_CART_SESSION_ITEM]['other'])) {
		foreach ($_SESSION[BB_CART_SESSION_ITEM]['other'] as $key => $item) {
			if (0 === strpos($key, 'costs-')) {
				return true;
			}
		}
	}
	return false;
}

function bb_cart_calculate_costs() {
	$costs = array();
	$rate = apply_filters('bb_cart_cost_rate', 0.015);
	$totals = bb_cart_amounts_by_frequency();
	foreach ($totals as $freq => $total) {
		if (!empty($_SESSION[BB_CART_SESSION_ITEM]['other']['costs-'.$freq])) {
			$total -= $_SESSION[BB_CART_SESSION_ITEM]['other']['costs-'.$freq]['price']/100;
		}
		$costs[$freq] = round($total * $rate, 2);
	}
	return $costs;
}

function bb_cart_calculate_total_costs() {
	return array_sum(bb_cart_calculate_costs());
}

function bb_cart_cover_costs() {
	$costs = bb_cart_calculate_costs();
	foreach ($costs as $freq => $cost) {
		$_SESSION[BB_CART_SESSION_ITEM]['other']['costs-'.$freq] = array(
				'label' => 'Gift to cover transaction costs',
				'price' => $cost*100,
				'quantity' => 1,
				'frequency' => $freq,
				'fund_code' => apply_filters('bb_cart_cover_costs_fund_code', bb_cart_get_default_fund_code()),
		);
	}
	// Make sure "other" section is always last
	uksort($_SESSION[BB_CART_SESSION_ITEM], function($a, $b) {
		if ('other' == $a) {
			return 1;
		} elseif ('other' == $b) {
			return -1;
		}
		return 0;
	});
}

add_action('em_booking_add', 'bb_cart_set_event_person_id', 10, 3);
function bb_cart_set_event_person_id(EM_Event $EM_Event, EM_Booking $EM_Booking, $post_validation) {
	if ($post_validation && !empty($EM_Booking->get_price())) { // Regular event
		if (is_user_logged_in()) {
			$EM_Booking->person_id = get_current_user_id(); // For some reason Event Manager doesn't set this automatically
			$EM_Booking->manage_override = true; // Allow user to manage their own booking
			$EM_Booking->save(false); // Save to ensure we have an ID
		}
	}
}

/**
 * Add Events to BB Cart
 */
add_filter('em_booking_save', 'bb_cart_add_event_to_cart', 10, 2);
function bb_cart_add_event_to_cart($success, EM_Booking $EM_Booking) {
	if ($success) {
		if (!session_id()) {
			session_start();
		}
		$exists = false;
		if (is_array($_SESSION[BB_CART_SESSION_ITEM]['event'])) {
			foreach ($_SESSION[BB_CART_SESSION_ITEM]['event'] as $event) {
				if ($event['booking_id'] == $EM_Booking->booking_id) {
					$exists = true;
					break;
				}
			}
		}
		if (!$exists) {
			$EM_Event = new EM_Event($EM_Booking->event_id);
			$_SESSION[BB_CART_SESSION_ITEM]['event'][] = array(
					'label' => $EM_Event->event_name,
					'event_id' => $EM_Event->event_id,
					'booking_id' => $EM_Booking->booking_id,
					'price' => $EM_Booking->get_price(),
					'quantity' => $EM_Booking->booking_spaces,
			);
		}
	}
	return $success;
}

/**
 * Set address details for other plugins if user created/updated through Event Manager
 */
add_filter('em_register_new_user', 'bb_cart_user_created_via_event', 10, 1);
function bb_cart_user_created_via_event($user_id) {
	// Now we try to work around Event Manager idiocy
	if(!session_id()) {
		session_start();
	}
	if (!empty($_SESSION[BB_CART_SESSION_ITEM]['event']) && class_exists('EM_Booking')) {
		foreach ($_SESSION[BB_CART_SESSION_ITEM]['event'] as $event) {
			$booking = new EM_Booking($event['booking_id']);
			$booking->person_id = $user_id;
			$booking->manage_override = true; // Allow user to manage their own booking
			$booking->save(false);
		}
	}
	return $user_id;
}

function bb_cart_calculate_shipping($total_price = null) {
	if (empty($total_price)) {
		$total_price = bb_cart_total_price(false);
	}
	$shipping = 0;

	return apply_filters('bb_cart_calculate_shipping', $shipping, $total_price, $_SESSION[BB_CART_SESSION_ITEM]);
}

function bb_cart_calculate_shipping_tax($shipping = null) {
	if (is_null($shipping)) {
		$shipping = bb_cart_calculate_shipping();
	}
	return apply_filters('bb_cart_calculate_shipping_tax', 0, $shipping);
}

add_filter('bb_cart_calculate_shipping', 'bb_cart_woocommerce_shipping', 1, 3);
function bb_cart_woocommerce_shipping($shipping, $total_price, $cart_items) {
	if (!empty($cart_items['woo']) && function_exists('WC')) {
		return WC()->cart->shipping_total;
	}
	return $shipping;
}

function bb_cart_shipping_label($transaction_id = null) {
	$label = 'Shipping';
	if (!empty($transaction_id)) {
		$transaction_label = get_post_meta($transaction_id, 'shipping_label', true);
		if (!empty($transaction_label)) {
			$label = $transaction_label;
		}
	}
	return apply_filters('bb_cart_shipping_label', $label, $transaction_id);
}

add_filter('woocommerce_checkout_redirect_empty_cart', '__return_false');
add_filter('woocommerce_checkout_update_order_review_expired', '__return_false');
add_action('woocommerce_add_to_cart', 'bb_cart_reset_shipping');
add_action('woocommerce_cart_item_removed', 'bb_cart_reset_shipping');
add_action('woocommerce_cart_item_restored', 'bb_cart_reset_shipping');
add_action('woocommerce_after_cart_item_quantity_update', 'bb_cart_reset_shipping');
function bb_cart_reset_shipping($hard = false) {
	if (!empty($_SESSION[BB_CART_SESSION_SHIPPING_TYPE])) {
		unset($_SESSION[BB_CART_SESSION_SHIPPING_TYPE]);
	}
	if ($hard && !empty($_SESSION[BB_CART_SESSION_SHIPPING_ADDRESS])) {
		unset($_SESSION[BB_CART_SESSION_SHIPPING_ADDRESS]);
	}
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
			$transaction_type = 'online';

			$entry_date = new DateTime($entry['date_created'], new DateTimeZone('UTC'));
			$payment_date = $entry_date->format('Y-m-d H:i:s');
			// GF always stores dates in UTC - need to convert to local time for transaction record
			$entry_date->setTimezone(wp_timezone());
			$transaction_date = $entry_date->format('Y-m-d H:i:s');

			$currency = bb_cart_get_default_currency();
			$shipping_label = bb_cart_shipping_label();
			$bb_line_items = array();
			$gf_line_items = array(
					'products' => array(),
					'shipping' => array(
							'name' => $shipping_label,
							'price' => bb_cart_calculate_shipping(),
					),
			);

			foreach ($form['fields'] as $field) {
				if ($field['type'] == 'email') {
					$email_field_id = $field['id'];
				} elseif ($field->type == 'name') {
					foreach ($field->inputs as $input) {
						if ($input['id'] == $field->id.'.3') {
							$firstname = $entry[(string)$input['id']];
						} elseif ($input['id'] == $field->id.'.6') {
							$lastname = $entry[(string)$input['id']];
						}
					}
				} elseif ($field['uniquenameField'] == 'firstname') {
					$firstname = $entry[$field['id']];
				} elseif ($field['uniquenameField'] == 'lastname') {
					$lastname = $entry[$field['id']];
				} elseif ($field['type']== 'date' && !empty($entry[$field['id']])) {
					$transaction_date = $entry[$field['id']];
					$transaction_date = date('Y-m-d', strtotime($transaction_date));
				} elseif ($field->inputName == 'payment_method') {
					$payment_method = $entry[$field->id];
				} elseif ($field->inputName == 'bb_cart_transaction_type' && !empty($entry[$field->id])) {
					$transaction_type = $entry[$field->id];
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
				if (empty($firstname)) {
					$firstname = get_user_meta($author_id, 'first_name', true);
				}
				if (empty($lastname)) {
					$lastname = get_user_meta($author_id, 'last_name', true);
				}
			} else {
				if (empty($firstname)) {
					$firstname = 'Unknown';
				}
				if (empty($lastname)) {
					$lastname = 'Unknown';
				}
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
				}
			}

			$payment_complete = true;
			if (in_array(strtolower($payment_method), array('direct debit', 'paypal')) || strtotime($transaction_date) > strtotime(current_time('mysql'))) {
				$payment_complete = false;
			}
			$payment_complete = apply_filters('bb_cart_payment_complete', $payment_complete, $payment_method, $transaction_date, $cart_items);
			if ($payment_complete) {
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
					'post_date' => $transaction_date,
			);

			// Insert the post into the database
			$transaction_id = wp_insert_post($transaction);

			$batch_id = bb_cart_get_web_batch($transaction['post_date'], $form, $entry);

			update_post_meta($transaction_id, 'frequency', $frequency);
			update_post_meta($transaction_id, 'gf_entry_id', $entry['id']);
			update_post_meta($transaction_id, 'batch_id', $batch_id);
			update_post_meta($transaction_id, 'total_amount', $total_amount);
			update_post_meta($transaction_id, 'cart', serialize($_SESSION[BB_CART_SESSION_ITEM]));
			update_post_meta($transaction_id, 'payment_method', $payment_method);
			update_post_meta($transaction_id, 'transaction_type', $transaction_type);
			update_post_meta($transaction_id, 'is_receipted', 'true');
			update_post_meta($transaction_id, 'shipping_label', $shipping_label);

			if (isset($deductible)) {
				update_post_meta($transaction_id, 'is_tax_deductible', var_export($deductible, true));
			}
			if (!empty($GLOBALS['transaction_id'])) {
				update_post_meta($transaction_id, 'pd_transaction_id', $GLOBALS['transaction_id']);
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
								$woo_cart = WC()->cart->get_cart();
								$WCCheckout = new WC_Checkout();
								$order_id = $WCCheckout->create_order(array());
								update_post_meta($transaction_id, 'woocommerce_order_id', $order_id);

								if (!empty($author_id)) {
									// Have to hack this as WooCommerce won't set the user unless we go through their checkout process
									wp_update_post(array('ID' => $order_id, 'post_author' => $author_id));
									update_post_meta($order_id, '_customer_user', $author_id);
								}

								$order = wc_get_order($order_id);

								// Get user address details for WooCommerce order
								$use_shipping = rgpost('input_28_1') == 'shipping_address';
								$use_billing = rgpost('input_28_2') == 'address';
								if ($use_shipping || $use_billing) {
									$shipping_address = array(
											'first_name' => $firstname,
											'last_name'  => $lastname,
											'company'    => rgpost('input_34'),
											'email'      => $email,
											'phone'      => rgpost('input_19'),
											'address_1'  => rgpost('input_50_1'),
											'address_2'  => rgpost('input_50_2'),
											'city'       => rgpost('input_50_3'),
											'state'      => rgpost('input_50_4'),
											'postcode'   => rgpost('input_50_5'),
											'country'    => rgpost('input_50_6'),
									);
									$billing_address = array(
											'first_name' => $firstname,
											'last_name'  => $lastname,
											'company'    => rgpost('input_34'),
											'email'      => $email,
											'phone'      => rgpost('input_19'),
											'address_1'  => rgpost('input_2_1'),
											'address_2'  => rgpost('input_2_2'),
											'city'       => rgpost('input_2_3'),
											'state'      => rgpost('input_2_4'),
											'postcode'   => rgpost('input_2_5'),
											'country'    => rgpost('input_2_6'),
									);
									if (!$use_shipping) {
										$shipping_address = $billing_address;
									} elseif ($use_shipping && $use_billing && rgpost('input_51_1') == 'same_address') {
										$billing_address = $shipping_address;
									}

									$order->set_address($shipping_address, 'shipping');
									$order->set_address($billing_address); // billing
								}
								$total = 0;
								foreach ($items as $product) {
									$price = $woo_cart[$product['cart_item_key']]['line_total'];
									if (!empty($woo_cart[$product['cart_item_key']]['line_tax'])) {
										$price += $woo_cart[$product['cart_item_key']]['line_tax'];
									}
									$total += $price;
									$line_item = array(
											'name' => $product['label'],
											'price' => $price/$product['quantity'],
											'quantity' => $product['quantity'],
									);
									$gf_line_items['products'][] = $line_item;
									$line_item['fund_code'] = $product['fund_code'];
									$bb_line_items[] = $line_item;
								}
								$shipping = bb_cart_calculate_shipping($total);
								$shipping_tax = bb_cart_calculate_shipping_tax($shipping);
								if ($shipping > 0) {
									$bb_line_items[] = array(
											'name' => $shipping_label,
											'price' => $shipping,
											'quantity' => '1',
											'fund_code' => apply_filters('bb_cart_shipping_fund_code', 'Postage', $bb_line_items),
									);
								}
								$shipping_item = new WC_Order_Item_Shipping();
								$shipping_item->set_props(array(
										'method_title' => $shipping_label,
										'method_id'    => 'flat_rate',
										'total'        => $shipping, // Just show full amount for now as tax doesn't work. @todo $shipping - $shipping_tax, // WooCommerce always assumes shipping is exclusive of tax, even if product prices are inclusive
										'taxes'        => array(
												'total' => array($shipping_tax),
										),
								));
								$order->add_item($shipping_item);
								$order->set_shipping_total($shipping - $shipping_tax);
								$order->set_shipping_tax($shipping_tax);
								$grand_total = $total+$shipping;
								$order->set_total($grand_total);
								$order->save();
								if ($transaction_status == 'Approved') {
									$order->payment_complete($transaction_id);
								}
							}
						}
						break;
					case 'event':
						foreach ($items as $event) {
							$em_booking = new EM_Booking($event['booking_id']);
							$em_booking->approve();
							$total += $event['price'];

							$line_item = array(
									'name' => $event['label'],
									'price' => $event['price']/$event['quantity'], // @todo is there a way of getting the per-ticket price? Probably not as there could be a combination of different tickets in one booking...
									'quantity' => $event['quantity'],
							);
							$gf_line_items['products'][] = $line_item;
							$bb_line_items[] = $line_item;
						}
						break;
					default:
						foreach ($items as $item) {
							$line_item = array(
									'name' => $item['label'],
									'price' => $item['price']/100,
									'quantity' => $item['quantity'],
									'description' => $item['donation_for'],
							);
							$gf_line_items['products'][] = $line_item;
							$line_item['fund_code'] = $item['fund_code'];
							$currency = $item['currency'];
							if (get_post_meta($item['fund_code'], 'transaction_type', true) == 'donation') {
								$donation_amount += $item['price']*$item['quantity']/100;
							}
							$bb_line_items[] = $line_item;
						}
						break;
				}
			}
			update_post_meta($transaction_id, 'donation_amount', $donation_amount);
			update_post_meta($transaction_id, 'currency', $currency);

			foreach ($bb_line_items as $bb_line_item) {
				$line_item = array(
						'post_title' => $bb_line_item['name'],
						'post_status' => 'publish',
						'post_author' => $author_id,
						'post_type' => 'transactionlineitem',
						'post_date' => $transaction['post_date'],
				);
				if (!empty($bb_line_item['description'])) {
					$line_item['post_content'] = $bb_line_item['description'];
				}
				$line_item_id = wp_insert_post($line_item);
				update_post_meta($line_item_id, 'fund_code', $bb_line_item['fund_code']);
				update_post_meta($line_item_id, 'transaction_id', $transaction_id);
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
						GFAPI::update_entry_property($item['entry_id'], "payment_date",   $payment_date);
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
			$entry['payment_date'] = $payment_date;
			$entry['payment_method'] = $payment_method;
			GFAPI::update_entry_property($entry['id'], "payment_amount", $total_amount);
			GFAPI::update_entry_property($entry['id'], "payment_status", $transaction_status);
			GFAPI::update_entry_property($entry['id'], "payment_date",   $payment_date);
			GFAPI::update_entry_property($entry['id'], "payment_method", $payment_method);
			gform_update_meta($entry['id'], 'gform_product_info__', $gf_line_items);
			gform_update_meta($entry['id'], 'gform_product_info_1_', $gf_line_items);
			gform_update_meta($entry['id'], 'gform_product_info__1', $gf_line_items);
			gform_update_meta($entry['id'], 'gform_product_info_1_1', $gf_line_items);

			$send_notifications = $transaction_status == 'Approved';
			$send_notifications = apply_filters('bb_cart_send_payment_complete_notifications', $send_notifications, $transaction_status, $payment_method);
			if ($send_notifications) {
				// Send notifications configured to go on "Payment Completed" event
				$action = array();
				$action['id']               = $transaction_id.'_'.$entry['id'];
				$action['type']             = 'complete_payment';
				$action['transaction_id']   = $transaction_id;
				$action['amount']           = $total_amount;
				$action['entry_id']         = $entry['id'];
				$action['payment_date']     = $transaction_date;
				$action['payment_method']	= $payment_method;
				$action['ready_to_fulfill'] = !$entry['is_fulfilled'] ? true : false;
				GFAPI::send_notifications($form, $entry, rgar($action, 'type'), array('payment_action' => $action));
			}

			do_action('bb_cart_post_purchase', $cart_items, $entry, $form, $transaction_id);
			if (function_exists('WC') && WC()->cart instanceof WC_Cart) {
				WC()->cart->empty_cart();
			}
			$_SESSION['last_checkout'] = $entry['id'];
		}
		bb_cart_end_session();
	}
}

function bb_cart_get_web_batch($date = null, $form = null, $entry = null, $context = null, $frequency = 'one-off') {
	if (empty($date)) {
		$date = current_time('mysql');
	}

	$date_obj = new DateTime($date);
	$batch_date = $date_obj->format('Y-m-d');
	$batch_name = 'WEB '.$batch_date;

	$existing_batch = apply_filters('bb_cart_get_web_batch', get_page_by_title($batch_name, OBJECT, 'transactionbatch'), $batch_date, $form, $entry, $context, $frequency);

	if ($existing_batch instanceof WP_Post && in_array($existing_batch->post_status, array('draft', 'pending', 'future'))) {
		return $existing_batch->ID;
	} else {
		// Create batch
		$batch = array(
				'post_title' => $batch_name,
				'post_content' => '',
				'post_status' => 'draft',
				'post_type' => 'transactionbatch',
				'post_date' => $batch_date,
		);

		// Insert the post into the database
		return wp_insert_post($batch);
	}
}

add_filter('update_post_metadata', 'bb_cart_update_post_metadata', 10, 5);
function bb_cart_update_post_metadata($null, $post_id, $meta_key, $meta_value, $prev_value) {
	if ('batch_id' == $meta_key && 'transaction' == get_post_type($post_id) && 'publish' == get_post_status($post_id)) {
		$batch_post = array(
				'ID' => $meta_value,
				'post_status' => 'pending',
		);
		wp_update_post($batch_post);
	}
	return $null;
}

add_action('transition_post_status', 'bb_cart_transition_post_status', 10, 3);
function bb_cart_transition_post_status($new_status, $old_status, $post) {
	if ('transaction' == get_post_type($post) && 'publish' == $new_status) {
		$batch_id = get_post_meta($post->ID, 'batch_id', true);
		if ($batch_id) {
			$batch_post = array(
					'ID' => $batch_id,
					'post_status' => 'pending',
			);
			wp_update_post($batch_post);
		}
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

function bb_cart_get_transaction_for_paydock_transaction($pd_transaction_id) {
	$args = array(
			'post_type' => 'transaction',
			'post_status' => 'any',
			'posts_per_page' => 1,
			'meta_query' => array(
					array(
							'key' => 'pd_transaction_id',
							'value' => $pd_transaction_id,
					),
			),
	);
	$transactions = get_posts($args);
	if (count($transactions)) {
		return array_shift($transactions);
	}
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
 * Find the transaction for the specified line item
 * @param WP_Post|integer $line_item
 * @return WP_Post|false Transaction object on success, else false
 */
function bb_cart_get_transaction_from_line_item($line_item) {
	$line_item = get_post($line_item);
	if (!($line_item instanceof WP_Post) || get_post_type($line_item) != 'transactionlineitem') {
		return false;
	}

	$transaction_terms = wp_get_post_terms($line_item->ID, 'transaction');
	if (count($transaction_terms)) {
		$transaction = get_post($transaction_terms[0]->slug);
		return $transaction;
	}

	return false;
}

/**
 * Find the transaction for the specified entry
 * @param int $entry_id
 * @return WP_Post|false Matching transaction if found, otherwise false
 */
function bb_cart_get_transaction_from_entry($entry_id) {
	if (!empty($entry_id)) {
		$args = array(
				'post_type' => 'transaction',
				'post_status' => 'any',
				'posts_per_page' => 1,
				'orderby' => 'date',
				'order' => 'ASC',
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
	}
	return false;
}

/**
 * Get cart contents for the specified entry
 * @param array $entry
 * @return mixed|boolean Cart contents if transaction found (may still be empty), otherwise false
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
	if (bb_cart_total_quantity() <= 0) {
		return $query_string;
	}
	parse_str(ltrim($query_string, '&'), $query);
	$i = 1;
	$currency = null;
	$donation = $feed['meta']['transactionType'] == 'donation' || $feed['meta']['transactionType'] == 'subscription';
	$donation_types = apply_filters('bb_cart_donation_types', array('donations'));
	if ($donation) {
		$query['amount'] = 0;
	}
	foreach ($_SESSION[BB_CART_SESSION_ITEM] as $section => $items) {
		if ($donation && in_array($section, $donation_types)) {
			if (count($items) > 1 || count($_SESSION[BB_CART_SESSION_ITEM]) > 1) {
				$query['item_name'] = 'Donation';
				foreach ($items as $cart_item) {
					$query['amount'] += $cart_item['price']/100;
					if (is_null($currency)) {
						$currency = $cart_item['currency'];
					}
				}
			} else {
				foreach ($items as $cart_item) {
					$query['item_name'] = $cart_item['label'];
					$query['amount'] = $cart_item['price']/100;
					if (is_null($currency)) {
						$currency = $cart_item['currency'];
					}
				}
			}
		} elseif (!$donation && !in_array($section, $donation_types)) {
			foreach ($items as $cart_item) {
				$query['item_name_'.$i] = $cart_item['label'];
				$query['amount_'.$i] = $cart_item['price']/100;
				$query['quantity_'.$i] = $cart_item['quantity'];
				if (is_null($currency)) {
					$currency = $cart_item['currency'];
				}
				$i++;
			}
		}
	}
	if (!$donation) {
		$shipping = bb_cart_calculate_shipping();
		if ($shipping > 0) {
			$query['shipping_1'] = $shipping;
		}
	}
	if (!is_null($currency)) {
		$query['currency_code'] = $currency;
	}
	$query_string = '&' . http_build_query($query);
	return $query_string;
}

add_action('gform_stripe_fulfillment', 'bb_cart_complete_stripe_checkout_transaction', 10, 4);
function bb_cart_complete_stripe_checkout_transaction($session, $entry, $feed, $form) {
	$transaction = bb_cart_get_transaction_from_entry($entry['id']);
	bb_cart_complete_pending_transaction($transaction->ID, current_time('mysql'), $entry);
}

add_action('gform_paypal_post_ipn', 'bb_cart_complete_paypal_transaction', 10, 4);
function bb_cart_complete_paypal_transaction($ipn_post, $entry, $feed, $cancel) {
	if ($cancel) {
		return;
	}

	$transaction = bb_cart_get_transaction_from_entry($entry['id']);
	$timezone = bb_cart_get_timezone();
	$ipn_date = new DateTime($ipn_post['payment_date']);
	$ipn_date->setTimezone($timezone);
	if ($transaction) {
		$transaction_date = new DateTime($transaction->post_date, $timezone);
		if (abs($transaction_date->format('Ymd') - $ipn_date->format('Ymd')) <= 1) { // Check if it's within 1 day to allow for timezone issues
			bb_cart_complete_pending_transaction($transaction->ID, current_time('mysql'), $entry);
			return;
		}
	}

	if (in_array(strtolower($ipn_post['txn_type']), array('subscr_payment', 'web_accept'))) {
		// They've made a payment but it doesn't correspond to the original transaction (or we couldn't load the original)
		$amount = $ipn_post['mc_gross'];
		$currency = $ipn_post['mc_currency'];
		$frequency = 'recurring';
		$deductible = false;
		$donor_id = null;

		if ($transaction) {
			$donor_id = $transaction->post_author;
			$donor = new WP_User($donor_id);
			$was_deductible = get_post_meta($transaction->ID, 'is_tax_deductible', true);
			if (strlen($was_deductible) > 0) {
				$deductible = $was_deductible == 'true';
			}
		} else {
			$donor = get_user_by('email', $ipn_post['payer_email']);
			$donor_id = $donor->ID;
		}

		if ($donor instanceof WP_User) {
			$donor_name = $donor->user_firstname.' '.$donor->user_lastname;
		} else {
			$donor_name = $ipn_post['first_name'].' '.$ipn_post['last_name'];
		}

		// Create transaction record
		$new_transaction = array(
				'post_title' => $donor_name.'-'.$amount,
				'post_content' => serialize($ipn_post),
				'post_status' => 'publish',
				'post_author' => $donor_id,
				'post_type' => 'transaction',
				'post_date' => $ipn_date->format('Y-m-d H:i:s'),
				'post_modified' => current_time('mysql'),
		);

		// Insert the post into the database
		$transaction_id = wp_insert_post($new_transaction);

		update_post_meta($transaction_id, 'frequency', $frequency);
		update_post_meta($transaction_id, 'donation_amount', $amount);
		update_post_meta($transaction_id, 'total_amount', $amount);
		update_post_meta($transaction_id, 'is_tax_deductible', var_export($deductible, true));
		update_post_meta($transaction_id, 'payment_method', 'PayPal');
		update_post_meta($transaction_id, 'currency', $currency);
		update_post_meta($transaction_id, 'transaction_type', 'online');
		update_post_meta($transaction_id, 'is_receipted', 'false');
		update_post_meta($transaction_id, 'subscription_id', $ipn_post['subscr_id']);

		$form = GFAPI::get_form($entry['form_id']);
		$batch_id = bb_cart_get_web_batch($new_transaction['post_date'], $form, $entry, 'paypal');
		update_post_meta($transaction_id, 'batch_id', $batch_id);

		$transaction_term = get_term_by('slug', $transaction_id, 'transaction'); // Have to pass term ID rather than slug
		$base_line_item = array(
				'post_title' => 'PayPal Subscription Payment',
				'post_status' => 'publish',
				'post_author' => $donor_id,
				'post_type' => 'transactionlineitem',
				'post_date' => $ipn_date->format('Y-m-d H:i:s'),
				'post_modified' => current_time('mysql'),
		);
		if ($transaction) { // Get details from original transaction
			$prev_amount = get_post_meta($transaction->ID, 'total_amount');
			if ($prev_amount == $amount) {
				update_post_meta($transaction_id, 'donation_amount', get_post_meta($transaction->ID, 'donation_amount', true)); // Subscriptions should generally be donations but just to be safe...
			}

			$line_items = bb_cart_get_transaction_line_items($transaction->ID);
			if ($line_items && ($prev_amount == $amount || count($line_items) == 1)) {
				foreach ($line_items as $previous_line_item) {
					$line_item = $base_line_item;
					$line_item['post_content'] = $previous_line_item->post_content;
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
			} else { // Amount has changed but we have multiple line items, or we couldn't locate the previous line items - just create one line item with default fund code
				$fund_code = bb_cart_get_default_fund_code();
				$line_item = $base_line_item;
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
			$fund_code = bb_cart_get_default_fund_code(); // No way to get this directly from PayPal, so if we can't find an existing transaction for this subscription, just use the default fund code
			$line_item = $base_line_item;
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

		do_action('bb_cart_webhook_paypal_recurring_success', $donor, $amount, $transaction_id);
	}
}

/**
 * Mark a pending transaction (pledge) as complete/paid
 * @param int $transaction_id Transaction post ID
 * @param string $date Date of payment (Y-m-d H:i:s). Expected to be in local timezone.
 * @param array $entry Optional GF entry. If not specified entry will be loaded from transaction meta
 * @param boolean $force Optional Whether to force the update. If false (default) it will only run if the entry has not yet been marked as Approved.
 */
function bb_cart_complete_pending_transaction($transaction_id, $date, $entry = null, $force = false) {
	if (is_null($entry)) {
		$entry = GFAPI::get_entry(get_post_meta($transaction_id, 'gf_entry_id', true));
	}
	if ($force || $entry['payment_status'] != 'Approved') {
		wp_publish_post($transaction_id);

		// GF always stores dates in UTC so we need to convert it from local
		$payment_date = new DateTime($date, wp_timezone());
		$payment_date->setTimezone(new DateTimeZone('UTC'));
		$entry['payment_status'] = 'Approved';
		$entry['payment_date'] = $payment_date->format('Y-m-d H:i:s');
		GFAPI::update_entry_property($entry['id'], "payment_status", 'Approved');
		GFAPI::update_entry_property($entry['id'], "payment_date", $payment_date->format('Y-m-d H:i:s'));
		$form = GFAPI::get_form($entry['form_id']);
		foreach ($form['fields'] as $field) {
			if ($field->inputName == 'bb_cart_checkout_items_array' && !empty($entry[$field->id])) {
				$item_entries = explode(',', $entry[$field->id]);
				foreach ($item_entries as $item_entry) {
					if (!empty($item_entry)) {
						GFAPI::update_entry_property($item_entry, "payment_status", 'Approved');
						GFAPI::update_entry_property($item_entry, "payment_date", $payment_date->format('Y-m-d H:i:s'));
					}
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
		$transaction = get_post($transaction_id);
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
	if ($order_id && class_exists('WC_Order')) {
		$order = new WC_Order($order_id);
		$order->update_status('failed');
	}
}

function bb_cart_delete_transactions($transactions) {
	if (!is_array($transactions)) {
		$transactions = explode(',', $transactions);
	}
	foreach ($transactions as $transaction) {
		if ($transaction instanceof WP_Post) {
			$transaction_id = $transaction->ID;
		} else {
			$transaction_id = (int)$transaction;
		}
		$line_items = bb_cart_get_transaction_line_items($transaction_id);
		bb_cart_delete_line_items($line_items);
		wp_trash_post($transaction_id);
	}
}

function bb_cart_delete_line_items($line_items) {
	if (!is_array($line_items)) {
		$line_items = explode(',', $line_items);
	}
	foreach ($line_items as $line_item) {
		if ($line_item instanceof WP_Post) {
			$line_item_id = $line_item->ID;
		} else {
			$line_item_id = (int)$line_item;
		}
		wp_trash_post($line_item_id);
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
	$cart_items = bb_cart_get_cart_from_entry($entry);
	$shipping = $shipping_label = null;
	if (empty($cart_items) && bb_cart_total_quantity() > 0) {
		$cart_items = $_SESSION[BB_CART_SESSION_ITEM];
	} else {
		$gf_line_items = bb_cart_gf_product_info(array(), $form, $entry);
		$total = $entry['payment_amount'];
		$shipping = $gf_line_items['shipping']['price'];
		$transaction = bb_cart_get_transaction_from_entry($entry['id']);
		if ($transaction instanceof WP_Post) {
			$shipping_label = bb_cart_shipping_label($transaction->ID);
		}
	}

	if (!empty($cart_items)) {
		$notification['message'] = str_replace("!!!items!!!", bb_cart_table('email', $cart_items, $total, $shipping, $shipping_label), $notification['message']);
		if ($form['id'] == bb_cart_get_checkout_form()) {
			$campaign = $fund_code = '';
			$fund_code_id = $entry[11];
			$campaign_id = $entry[32];
			if (!empty($fund_code_id)) {
				$fund_code = get_the_title($fund_code_id);
			}
			if (!empty($campaign_id)) {
				$campaign = get_the_title($campaign_id);
			}
			$notification['subject'] = str_replace('!!!fund!!!', $campaign.' ('.$fund_code.')', $notification['subject']);
			$notification['message'] = str_replace('!!!fund!!!', $campaign.' ('.$fund_code.')', $notification['message']);
		}
	}
	return $notification;
}

add_filter('gfpdf_pdf_html_output', 'bb_cart_gfpdf_pdf_html_output', 10, 5);
function bb_cart_gfpdf_pdf_html_output($html, $form, $entry, $settings, $Helper_PDF) {
	$cart_items = bb_cart_get_cart_from_entry($entry);
	$shipping = null;
	if (empty($cart_items) && bb_cart_total_quantity() > 0) {
		$cart_items = $_SESSION[BB_CART_SESSION_ITEM];
	} else {
		$gf_line_items = bb_cart_gf_product_info(array(), $form, $entry);
		$total = $entry['payment_amount'];
		$shipping = $gf_line_items['shipping']['price'];
	}

	if (!empty($cart_items)) {
		$html = str_replace("!!!items!!!", bb_cart_table('email', $cart_items, $total, $shipping), $html);
		if ($form['id'] == bb_cart_get_checkout_form()) {
			$campaign = $fund_code = '';
			$fund_code_id = $entry[11];
			$campaign_id = $entry[32];
			if (!empty($fund_code_id)) {
				$fund_code = get_the_title($fund_code_id);
			}
			if (!empty($campaign_id)) {
				$campaign = get_the_title($campaign_id);
			}
			$html = str_replace('!!!fund!!!', $campaign.' ('.$fund_code.')', $html);
		}
	}
	return $html;
}

add_filter('gform_product_info', 'bb_cart_gf_product_info', 10, 3);
function bb_cart_gf_product_info($product_info, $form, $entry) {
	if (!bb_cart_is_checkout_form($form)) {
		return $product_info;
	}
	$gf_line_items = array();
	if (!is_admin() && !empty($_SESSION[BB_CART_SESSION_ITEM])) {
		$cart_items = $_SESSION[BB_CART_SESSION_ITEM];
		foreach ($cart_items as $section => $items) {
			switch ($section) {
				case 'woo':
					$total = 0;
					foreach ($items as $product) {
						$gf_line_items['products'][] = array(
								'name' => $product['label'],
								'price' => $product['price']/100,
								'quantity' => $product['quantity'],
						);
					}
					$shipping = bb_cart_calculate_shipping($total);
					if ($shipping > 0) {
						$gf_line_items['shipping'] = array(
								'name' => bb_cart_shipping_label(),
								'price' => $shipping,
						);
					}
					break;
				case 'event':
					foreach ($items as $event) {
						$gf_line_items['products'][] = array(
								'name' => $event['label'],
								'price' => $event['price']/$event['quantity'],
								'quantity' => $event['quantity'],
						);
					}
					break;
				default:
					foreach ($items as $item) {
						$gf_line_items['products'][] = array(
								'name' => $item['label'],
								'price' => $item['price']/100,
								'quantity' => $item['quantity'],
								'description' => $item['donation_for'],
						);
					}
					break;
			}
		}
		return $gf_line_items;
	} elseif (!empty($entry['id'])) {
		$transaction = bb_cart_get_transaction_from_entry($entry['id']);
		if ($transaction instanceof WP_Post) {
			$line_items = bb_cart_get_transaction_line_items($transaction->ID);
			foreach ($line_items as $line_item) {
				$meta = get_post_meta($line_item->ID);
				if ($meta['fund_code'][0] == 'Postage') {
					$gf_line_items['shipping'] = array(
							'name' => bb_cart_shipping_label($transaction->ID),
							'price' => $meta['price'][0],
					);
				} else {
					$gf_line_items['products'][] = array(
							'name' => $line_item->post_title,
							'price' => $meta['price'][0],
							'quantity' => $meta['quantity'][0],
					);
				}
			}
			return $gf_line_items;
		}
	}
	return $product_info;
}

function bb_cart_shortcode() {
	return bb_cart_table();
}
add_shortcode('bb_cart_table', 'bb_cart_shortcode');

function bb_cart_table($purpose = 'table', array $cart_items = array(), $total = null, $shipping = null, $shipping_label = null) {
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
		$total_tax = 0;
		foreach ($cart_items as $section => $items) {
			$html .= '<table class="bb-table" width="100%">'."\n";
			switch ($section) {
				case 'woo':
					$html .= '<tr><th colspan="'.$cols.'" style="text-align:left;">Products</th></tr>';
					$product_total = 0;
					foreach ($items as $idx => $product) {
						$price = ($product['price']*$product['quantity'])/100;
						$product_total += $price;
						$html .= '<tr><td>'.$product['quantity'].'x <a href="'.get_the_permalink($product['product_id']).'">'.apply_filters('bb_cart_table_item_label_display', $product['label'], $purpose, $product, $section, $idx).'</a></td>'."\n";
						$html .= '<td style="text-align: right; white-space: nowrap;">';
						$html .= bb_cart_format_currency($price);
						if (!empty($product['tax'])) {
							$total_tax += $product['tax'];
							$html .= '<br><span class="tax">incl. '.bb_cart_format_currency($product['tax']).' '.__('Tax', 'woocommerce').'</span>';
						}
						$html .= '</td>'."\n";
						if ($purpose != 'email') {
							$html .= '<td style="width: 15px;">'."\n";
							if ($product['removable'] !== false) {
								$html .= '<a href="'.add_query_arg('remove_item', $section.':'.$idx).'" title="Remove" class="delete" onclick="return confirm(\'Are you sure you want to remove this item?\');">x</a>'."\n";
							} else {
								$html .= '&nbsp;';
							}
							$html .= '</td>'."\n";
						}
						$html .= '</tr>';
					}
					if (is_null($shipping)) {
						$shipping = bb_cart_calculate_shipping($product_total);
					}
					$shipping_tax = 0;
					if (is_numeric($shipping)) {
						$shipping_tax = bb_cart_calculate_shipping_tax($shipping);
						$total_tax += $shipping_tax;
						$shipping = bb_cart_format_currency($shipping);
					}
					if (empty($shipping_label)) {
						$shipping_label = bb_cart_shipping_label();
					}
					if (!empty($shipping_tax)) {
						$shipping .= '<br><span class="tax">incl. '.bb_cart_format_currency(bb_cart_calculate_shipping_tax()).' '.__('Tax', 'woocommerce').'</span>';
					}
					$html .= '<tr><td>'.$shipping_label.'</td>'."\n";
					$html .= '<td style="text-align: right;">'.$shipping.'</td>'."\n";
					if ($purpose != 'email') {
						$html .= '<td>&nbsp;</td>'."\n";
					}
					$html .= '</tr>'."\n";
					break;
				case 'event':
					$html .= '<tr><th colspan="'.$cols.'" style="text-align:left;">Events</th></tr>';
					foreach ($items as $idx => $event) {
						$EM_Booking = new EM_Booking($event['booking_id']);
						$EM_Event = new EM_Event($EM_Booking->event_id);
						$html .= '<tr><td>'.apply_filters('bb_cart_table_item_label_display', $EM_Booking->booking_spaces.' registration/s for '.$EM_Event->event_name.' ('.$EM_Event->event_start_date.')', $purpose, $event, $section, $idx).'</td>'."\n";
						$html .= '<td style="text-align: right; white-space: nowrap;">'.bb_cart_format_currency($EM_Booking->booking_price).'</td>'."\n";
						if ($purpose != 'email') {
							$html .= '<td style="width: 15px;">'."\n";
							if ($event['removable'] !== false) {
								$html .= '<a href="'.add_query_arg('remove_item', $section.':'.$idx).'" title="Remove" class="delete" onclick="return confirm(\'Are you sure you want to remove this item?\');">x</a>'."\n";
							} else {
								$html .= '&nbsp;';
							}
							$html .= '</td>'."\n";
						}
						$html .= '</tr>';
					}
					break;
				default:
					$html .= '<tr><th colspan="'.$cols.'" style="text-align:left;">'.ucwords($section).'</th></tr>';
					foreach ($items as $idx => $item) {
						$html .= '<tr>'."\n";
						$label = $item['label'];
						if ($item['quantity'] > 1 || $section != 'donations') {
							$label = $item['quantity'].'x '.$label;
						}
						$html .= '<td>'.apply_filters('bb_cart_table_item_label_display', $label, $purpose, $item, $section, $idx).'</td>'."\n";
						$item_price = ($item['price']*$item['quantity'])/100;
						$frequency = empty($item['frequency']) || $item['frequency'] == 'one-off' ? '' : '/'.ucfirst($item['frequency']);
						$html .= '<td style="text-align: right; white-space: nowrap;">'.bb_cart_format_currency($item_price).$frequency.'</td>'."\n";
						if ($purpose != 'email') {
							$html .= '<td style="width: 15px;">'."\n";
							if ($item['removable'] !== false) {
								$html .= '<a href="'.add_query_arg('remove_item', $section.':'.$idx).'" title="Remove" class="delete" onclick="return confirm(\'Are you sure you want to remove this item?\');">x</a>'."\n";
							} else {
								$html .= '&nbsp;';
							}
							$html .= '</td>'."\n";
						}
						$html .= '</tr>'."\n";
					}
					if (apply_filters('bb_cart_section_incurs_shipping', false, $section)) {
						if (is_null($shipping)) {
							$shipping = bb_cart_calculate_shipping($product_total);
						}
						$shipping_tax = 0;
						if (is_numeric($shipping)) {
							$shipping_tax = bb_cart_calculate_shipping_tax($shipping);
							$total_tax += $shipping_tax;
							$shipping = bb_cart_format_currency($shipping);
						}
						if (empty($shipping_label)) {
							$shipping_label = bb_cart_shipping_label();
						}
						if (!empty($shipping_tax)) {
							$shipping .= '<br><span class="tax">incl. '.bb_cart_format_currency(bb_cart_calculate_shipping_tax()).' '.__('Tax', 'woocommerce').'</span>';
						}
						$html .= '<tr><td>'.$shipping_label.'</td>'."\n";
						$html .= '<td style="text-align: right;">'.$shipping.'</td>'."\n";
						if ($purpose != 'email') {
							$html .= '<td>&nbsp;</td>'."\n";
						}
						$html .= '</tr>'."\n";
					}
					break;
			}
			$html .= '</table>'."\n";
		}
		if (is_null($total)) {
			$total = bb_cart_total_price(true, $cart_items);
		}
		$html .= '<p class="bb_cart_total" style="text-align: right;"><strong>Total: '.bb_cart_format_currency($total).'</strong>';
		if (!empty($total_tax)) {
			$html .= '<br><span class="tax">incl. '.bb_cart_format_currency($total_tax).' '.__('Tax', 'woocommerce').'</span>';
		}
		$html .= '</p>'."\n";
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

add_action('init', 'bb_cart_register_cron');
function bb_cart_register_cron() {
	if (!wp_next_scheduled('bb_cart_hourly_cron')) {
		wp_schedule_event(time(), 'hourly', 'bb_cart_hourly_cron');
	}
}

add_action('bb_cart_hourly_cron', 'bb_cart_hourly_cron');
function bb_cart_hourly_cron() {
	$last_processed = get_option('bb_cart_last_transaction_reviewed');
	if ($last_processed) {
		$date = get_post_datetime($last_processed);
		$start = array(
				'year' => $date->format('Y'),
				'month' => $date->format('m'),
				'day' => $date->format('d'),
				'hour' => $date->format('h'),
				'minute' => $date->format('i'),
				'second' => $date->format('s'),
		);
	} else {
		$start = array(
				'year' => 2019,
				'month' => 1,
				'day' => 1,
		);
	}
	bb_cart_create_missing_transaction_line_items($start);
}

/**
 * (Hopefully) temporary fix for missing line items
 * Will check a maximum of 100 transactions at a time.
 * @param array $from_date Earliest date to find transactions for. Must contain year, month and day keys with relevant values. May also contain hour, minute and second if desired.
 */
function bb_cart_create_missing_transaction_line_items(array $from_date) {
	$args = array(
			'post_type' => 'transaction',
			'posts_per_page' => 100,
			'post_status' => 'all',
			'orderby' => 'date',
			'order' => 'ASC',
			'date_query' => array(
					array(
							'after' => $from_date,
							'inclusive' => true,
					),
			),
	);
	$transactions = get_posts($args);

	foreach ($transactions as $transaction) {
		$amount = get_post_meta($transaction->ID, 'total_amount', true);
		if (empty($amount)) {
			wp_delete_post($transaction->ID, true);
			continue;
		}
		$line_items = bb_cart_get_transaction_line_items($transaction->ID);
		if (is_array($line_items)) {
			foreach ($line_items as $line_item) {
				if ($line_item->post_author == 0) {
					$line_item->post_author = $transaction->post_author;
					wp_update_post($line_item);
				}
			}
		} else {
			$transaction_date = new DateTime($transaction->post_date);
			$transaction_term = get_term_by('slug', $transaction->ID, 'transaction'); // Have to pass term ID rather than slug

			// Look for line item in case it just isn't connected
			$args = array(
					'post_type' => 'transactionlineitem',
					'posts_per_page' => -1,
					'post_status' => 'all',
					'author' => $transaction->post_author,
					'date_query' => array(
							array(
									'year' => $transaction_date->format('Y'),
									'month' => $transaction_date->format('m'),
									'day' => $transaction_date->format('d'),
							),
					),
			);
			$line_items = get_posts($args);
			$line_item_exists = false;
			foreach ($line_items as $line_item) {
				$line_transaction_terms = wp_get_object_terms($line_item->ID, 'transaction');
				if (count($line_transaction_terms) == 0) { // Line item for same donor on date of transaction but not connected to a transaction - let's connect it
					$line_amount = get_post_meta($line_item->ID, 'total_amount', true);
					if (empty($line_amount)) {
						update_post_meta($line_item->ID, 'price', $amount);
						update_post_meta($line_item->ID, 'quantity', 1);
					}
					wp_set_post_terms($line_item->ID, $transaction_term->term_id, 'transaction');
					$line_item_exists = true;
					$fund_codes = wp_get_object_terms($line_item->ID, 'fundcode');
					if (count($fund_codes)) {
						$fund_code_term = $fund_codes[0];
					} else {
						$fund_code_post = get_page_by_title(get_post_meta($line_item->ID, 'fund_code', true), OBJECT, 'fundcode');
						$fund_code_term = get_term_by('slug', $fund_code_post->ID, 'fundcode');
						wp_set_post_terms($line_item->ID, $fund_code_term->term_id, 'fundcode');
					}
				}
			}

			if (!$line_item_exists) { // No existing line item found, we'll have to create it
				$line_item_created = false;
				$line_item = array(
						'post_title' => 'PayDock Subscription Payment',
						'post_status' => 'publish',
						'post_author' => $transaction->post_author,
						'post_type' => 'transactionlineitem',
						'post_date' => $transaction->post_date,
						'post_modified' => current_time('mysql'),
				);

				$subscription_id = get_post_meta($transaction->ID, 'subscription_id', true);
				if (!empty($subscription_id)) {
					$previous_transaction = bb_cart_get_transaction_for_subscription($subscription_id);
					$prev_amount = get_post_meta($previous_transaction->ID, 'total_amount');
					$previous_line_items = bb_cart_get_transaction_line_items($previous_transaction->ID);
					if ($previous_line_items && ($prev_amount == $amount || count($previous_line_items) == 1)) {
						foreach ($previous_line_items as $previous_line_item) {
							$previous_meta = get_post_meta($previous_line_item->ID);
							$line_item_id = wp_insert_post($line_item);
							$line_item_created = true;
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
					}
				}

				if (!$line_item_created) {
					$line_item_id = wp_insert_post($line_item);
					update_post_meta($line_item_id, 'price', $amount);
					update_post_meta($line_item_id, 'quantity', 1);

					wp_set_post_terms($line_item_id, $transaction_term->term_id, 'transaction');
				}
			}
		}
		update_option('bb_cart_last_transaction_reviewed', $transaction->ID);
	}
}

add_action('bb_cart_webhook_paydock_recurring_success', 'bb_cart_paydock_recurring_success', 10, 3);
function bb_cart_paydock_recurring_success($user, $amount, $transaction_id) {
	$subject = get_option('bb_cart_recurring_payment_email_subject');
	$message = get_option('bb_cart_recurring_payment_email_message');
	if (!empty($subject) && !empty($message)) {
		$currency = get_post_meta($transaction_id, 'currency', true);
		$amount = GFCommon::to_money($amount, $currency);
		$replace = array(
			'{first_name}' => $user->user_firstname,
			'{amount}' => $amount,
		);
		$subject = str_replace(array_keys($replace), $replace, $subject);
		$message = wpautop(str_replace(array_keys($replace), $replace, $message));
		add_filter('wp_mail_content_type', function($type) {return 'text/html';});
		wp_mail($user->user_email, $subject, $message);
	}
}
