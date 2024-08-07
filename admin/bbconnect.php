<?php
function bb_cart_bbconnect_helper_fund_code() {
	$fund_codes = array();
	$args = array(
		'post_type' => 'fundcode',
		'posts_per_page' => -1,
		'orderby' => 'title',
		'order' => 'ASC',
	);
    $fund_code_posts = get_posts($args);
    foreach ($fund_code_posts as $fund_code) {
		$fund_codes[$fund_code->ID] = $fund_code->post_title;
    }
    return $fund_codes;
}

add_filter('bbconnect_meta_options_exclude', 'bb_cart_bbconnect_meta_options_exclude', 10, 2);
function bb_cart_bbconnect_meta_options_exclude($exclude, $value) {
    if ('fund_code' == $value['meta_key']) {
        $exclude = true;
    }
    return $exclude;
}

add_filter('bbconnect_restricted_field', 'bb_cart_bbconnect_restricted_field', 10, 3);
function bb_cart_bbconnect_restricted_field($restricted_field, $meta_key, $field_type) {
    if ('fund_code' == $meta_key) {
        $restricted_field = true;
    }
    return $restricted_field;
}

add_filter('bbconnect_restricted_choices', 'bb_cart_bbconnect_restricted_choices', 10, 3);
function bb_cart_bbconnect_restricted_choices($restricted_choices, $meta_key, $field_type) {
    if ('fund_code' == $meta_key) {
        $restricted_choices = true;
    }
    return $restricted_choices;
}

add_filter('bbconnect_meta_multi_op', 'bb_cart_bbconnect_meta_multi_op', 10, 2);
function bb_cart_bbconnect_meta_multi_op($multi_op, $user_meta) {
    if ('fund_code' == $user_meta['meta_key']) {
        $multi_op = true;
    }
    return $multi_op;
}

add_filter('bbconnect_filter_process_wp_col', 'bb_cart_bbconnect_filter_process_wp_col', 10, 3);
function bb_cart_bbconnect_filter_process_wp_col($wp_col, $user_meta, $value) {
    if ('fund_code' == $user_meta['meta_key']) {
        $wp_col = 'ID';
    }
    return $wp_col;
}

add_filter('bbconnect_field_value', 'bb_cart_bbconnect_field_value', 10, 3);
function bb_cart_bbconnect_field_value($value, $key, $user) {
	if ($key == 'bbconnect_fund_code') {
		$user_fund_codes = array();
		$transactions = bb_cart_get_user_transactions($user->ID);
		foreach ($transactions as $transaction) {
			$line_items = bb_cart_get_transaction_line_items($transaction->ID);
			if ($line_items && count($line_items) > 0) {
				foreach ($line_items as $line_item) {
					$txn_fund_codes = wp_get_object_terms($line_item->ID, 'fundcode');
					if (!empty($txn_fund_codes)) {
						$fund_code_term = array_shift($txn_fund_codes);
						$fund_code = $fund_code_term->name;
					} else {
						$fund_code = get_post_meta($line_item->ID, 'fund_code', true);
					}
					if (empty($fund_code)) {
						$fund_code = 'Blank/Unknown';
					}
				}
			} else {
				$fund_code = get_post_meta($transaction->ID, 'fund_code', true);
			}
			$user_fund_codes[$fund_code] = $fund_code;
		}
        ksort($user_fund_codes);
        $value = implode('; ', $user_fund_codes);
	}
	return $value;
}

add_filter('bbconnect_filter_process_op', 'bb_cart_bbconnect_filter_process_op', 10, 3);
function bb_cart_bbconnect_filter_process_op($op, $user_meta, $value) {
    if ('fund_code' == $user_meta['meta_key']) {
        if ('=' == $op && isset($value['query'])) {
            $op = 'IN';
        } else if ('!=' == $op && isset($value['query'])) {
            $op = 'NOT IN';
        }
    }
    return $op;
}

add_filter('bbconnect_filter_process_q_val', 'bb_cart_bbconnect_filter_process_q_val', 10, 3);
function bb_cart_bbconnect_filter_process_q_val($q_val, $user_meta, $subvalue) {
    if ('fund_code' == $user_meta['meta_key']) {
		$fund_code_users = array();
		$page_size = 100;
		$offset = 0;
		do {
			$args = array(
				'posts_per_page' => $page_size,
				'offset' => $offset,
				'post_type' => 'transactionlineitem',
				'tax_query' => array(
					array(
						'taxonomy' => 'fundcode',
						'field' => 'slug',
						'terms' => $subvalue,
					),
				),
			);
			$args = apply_filters('bb_cart_bbconnect_fund_code_search_args', $args, $subvalue);
			$transactions = get_posts($args);
			foreach ($transactions as $transaction) {
				$fund_code_users[$transaction->post_author] = $transaction->post_author;
			}
			$offset += $page_size;
		} while (count($transactions) > 0);
        $q_val = '('.implode(',', $fund_code_users).')';
    }
    return $q_val;
}
