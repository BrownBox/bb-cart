<?php
add_action('gform_post_process', 'bb_cart_post_process', 10, 3);
function bb_cart_post_process($form, $page_number, $source_page_number) {
	if ($form['id'] == bb_cart_get_shipping_form()) {
		$shipping_address = array(
				'address_line_1' => rgpost('input_5_1'),
				'address_line_2' => rgpost('input_5_2'),
				'address_city' => rgpost('input_5_3'),
				'address_state' => rgpost('input_5_4'),
				'address_postcode' => rgpost('input_5_5'),
				'address_country' => rgpost('input_5_6'),
		);

		// Hack to get country code
		foreach ($form['fields'] as $field) {
			if ($field->type == 'address') {
				$shipping_address['address_country_code'] = $field->get_country_code($shipping_address['address_country']);
				break;
			}
		}

		$_SESSION[BB_CART_SESSION_SHIPPING_ADDRESS] = $shipping_address;

		// Backwards compatibility
		$_SESSION[BB_CART_SESSION_SHIPPING_POSTCODE] = $shipping_address['address_postcode'];
		$_SESSION[BB_CART_SESSION_SHIPPING_SUBURB] = $shipping_address['address_city'];
	}
}
