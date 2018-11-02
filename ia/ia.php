<?php
new \bb_cart\cptTaxClass('Transaction', 'Transactions', array('transactionlineitem'), array(
        'rewrite' => array(
                'with_front' => false,
                'slug' => 'transaction',
        ),
        'labels' => array(
                'name' => 'Transactions',
        ),
        'menu_icon' => 'dashicons-cart',
        'public' => false,
        'has_archive' => false,
        'query_var' => false,
        'show_ui' => true,
        'hierarchical' => false,
        'supports' => array(
                'title',
                'editor',
                'author',
                'revisions',
                'page-attributes',
        ),
));

$transaction_meta = array(
        array(
                'title' => 'Frequency',
                'description' => '',
                'field_name' => 'frequency',
                'type' => 'text',
        ),
        array(
                'title' => 'GF Entry ID',
                'description' => '',
                'field_name' => 'gf_entry_id',
                'type' => 'number',
        ),
        array(
                'title' => 'Donation Amount',
                'description' => '',
                'field_name' => 'donation_amount',
                'type' => 'number',
        ),
        array(
                'title' => 'Total Amount',
                'description' => '',
                'field_name' => 'total_amount',
                'type' => 'number',
        ),
        array(
                'title' => 'Currency',
                'description' => '',
                'field_name' => 'currency',
                'type' => 'text',
        ),
        array(
                'title' => 'Cart',
                'description' => '',
                'field_name' => 'cart',
                'type' => 'textarea',
        ),
        array(
                'title' => 'Gateway Response',
                'description' => '',
                'field_name' => 'gateway_response',
                'type' => 'textarea',
        ),
        array(
                'title' => 'Tax Deductible',
                'description' => '',
                'field_name' => 'is_tax_deductible',
                'type' => 'checkbox',
        ),
        array(
                'title' => 'Batch ID',
                'description' => '',
                'field_name' => 'batch_id',
                'type' => 'number',
        ),
        array(
                'title' => 'Subscription ID',
                'description' => '',
                'field_name' => 'subscription_id',
                'type' => 'text',
        ),
        array(
                'title' => 'Receipted',
                'description' => '',
                'field_name' => 'is_receipted',
                'type' => 'checkbox',
        ),
        array(
                'title' => 'Transaction Type',
                'description' => '',
                'field_name' => 'transaction_type',
                'type' => 'text',
        ),
);
new \bb_cart\metaClass('Transaction Details', array('transaction'), $transaction_meta);

new \bb_cart\cptClass('Transaction Line Item', 'Transaction Line Items', array(
        'rewrite' => array(
                'with_front' => false,
                'slug' => 'line-item',
        ),
        'menu_icon' => 'dashicons-feedback',
        'public' => false,
        'has_archive' => false,
        'query_var' => false,
        'show_ui' => true,
        'hierarchical' => false,
        'supports' => array(
                'title',
                'editor',
                'author',
                'revisions',
        ),
));

$line_item_meta = array(
        array(
                'title' => 'Transaction ID',
                'description' => '',
                'field_name' => 'transaction_id',
                'type' => 'number',
        ),
        array(
                'title' => "Price",
                'description' => '',
                'field_name' => 'price',
                'type' => 'number',
        ),
        array(
                'title' => "Quantity",
                'description' => '',
                'field_name' => 'quantity',
                'type' => 'number',
        ),
);
new \bb_cart\metaClass('Line Item Details', array('transactionlineitem'), $line_item_meta);

new \bb_cart\cptClass('Transaction Batch', 'Transaction Batches', array(
        'rewrite' => array(
                'with_front' => false,
                'slug' => 'batch',
        ),
        'menu_icon' => 'dashicons-archive',
        'public' => false,
        'has_archive' => false,
        'query_var' => false,
        'show_ui' => true,
        'hierarchical' => false,
));

$batch_meta = array(
        array(
                'title' => 'Batch Type',
                'description' => '',
                'field_name' => 'batch_type',
                'type' => 'text',
        ),
        array(
                'title' => 'Banking Details',
                'description' => '',
                'field_name' => 'banking_details',
                'type' => 'textarea',
        ),
);
new \bb_cart\metaClass('Batch Details', array('transactionbatch'), $batch_meta);

new \bb_cart\cptTaxClass('Fund Code', 'Fund Codes', array('transactionlineitem', 'product', 'give', 'person'), array(
        'rewrite' => array(
                'with_front' => false,
        ),
        'menu_icon' => 'dashicons-portfolio',
        'public' => false,
        'has_archive' => false,
        'query_var' => false,
        'show_ui' => true,
        'hierarchical' => true,
        'supports' => array(
                'title',
                'author',
                'editor',
                'page-attributes',
        ),
));

$fund_code_meta = array(
        array(
                'title' => 'Transaction Type',
                'description' => '',
                'field_name' => 'transaction_type',
                'type' => 'select',
                'options' => array(
                        array(
                                'value' => 'donation',
                                'label' => 'Donation',
                        ),
                        array(
                                'value' => 'purchase',
                                'label' => 'Purchase',
                        ),
                ),
                'show_in_admin' => true,
        ),
        array(
                'title' => 'Tax Deductible',
                'description' => '',
                'field_name' => 'deductible',
                'type' => 'checkbox',
                'show_in_admin' => true,
        ),
);
new \bb_cart\metaClass('Fund Code Details', array('fundcode'), $fund_code_meta);

// Create default fund code if none exists
add_action('admin_init', 'bb_cart_create_default_fund_code');
function bb_cart_create_default_fund_code() {
    $fund_code_count = wp_count_posts('fundcode');
    if ($fund_code_count->publish == 0) {
        $default_fund_code = get_page_by_title('WMN', OBJECT, 'fundcode');
        if ($default_fund_code) {
            wp_publish_post($default_fund_code);
            $fund_code_id = $default_fund_code->ID;
        } else {
            $fund_code = array(
                    'post_title' => 'WMN',
                    'post_type' => 'fundcode',
            );
            $fund_code_id = wp_insert_post($fund_code);
            update_post_meta($fund_code_id, 'transaction_type', 'donation');
        }
        update_option('bb_cart_default_fund_code', $fund_code_id);
    }
}
