<?php
add_filter('gform_confirmation', 'bb_cart_form_confirmation', 10, 3);
function bb_cart_form_confirmation($confirmation, $form, $entry) {
    $url = '';
    if (in_array('bb_cart_checkout', explode(' ', $form['cssClass'])) && class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$checkout_form_confirmation_url)) {
        $url = site_url(Brownbox\Config\BB_Cart::$checkout_form_confirmation_url);
    } elseif (in_array('bb_cart_donations', explode(' ', $form['cssClass'])) && class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$donate_form_confirmation_url)) {
        $url = site_url(Brownbox\Config\BB_Cart::$donate_form_confirmation_url);
    }
    if (!empty($url)) {
        if (!empty($confirmation['redirect'])) {
            $query = parse_url($confirmation['redirect'], PHP_URL_QUERY);
            $url .= '?'.$query;
        }
        $confirmation = array(
                'redirect' => $url,
        );
    }
    return $confirmation;
}
