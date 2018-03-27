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

// @todo rebuild using metaClass
function transaction_metabox() {
	add_meta_box( 'cpt_transaction_box', __( 'Transaction Meta', '' ), 'transaction_metabox_content', 'transaction', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'transaction_metabox' );

function transaction_metabox_content($post) {
	if(!is_array($transaction_fields)) {
	    $transaction_fields = array();
	}
	wp_nonce_field(plugin_basename(__FILE__), 'transaction_metabox_content_nonce');

	array_push($transaction_fields, bbcart_new_field('title=Frequency&field_name=frequency&size=100%&type=text'));
	array_push($transaction_fields, bbcart_new_field('title=GF Entry ID&field_name=gf_entry_id&size=100%&type=text'));
	array_push($transaction_fields, bbcart_new_field('title=Donation Amount&field_name=donation_amount&size=100%&type=text'));
	array_push($transaction_fields, bbcart_new_field('title=Total Amount&field_name=total_amount&size=100%&type=text'));
	array_push($transaction_fields, bbcart_new_field('title=Cart&field_name=cart&size=100%,10rem&type=textarea'));
	array_push($transaction_fields, bbcart_new_field('title=Gateway Response&field_name=gateway_response&size=100%&type=text'));
	array_push($transaction_fields, bbcart_new_field('title=Tax Deductible&field_name=is_tax_deductible&type=checkbox'));
	array_push($transaction_fields, bbcart_new_field('title=Batch ID&field_name=batch_id&size=100%&type=number'));
	array_push($transaction_fields, bbcart_new_field('title=Subscription ID&field_name=subscription_id&size=100%&type=text'));
	array_push($transaction_fields, bbcart_new_field('title=Receipted&field_name=is_receipted&type=checkbox'));

	set_transient('transaction_fields', serialize($transaction_fields), 3600);
}

function transaction_metabox_save($post_id) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
	    return;
	}
	if (!wp_verify_nonce($_POST['transaction_metabox_content_nonce'], plugin_basename(__FILE__))) {
	    return;
	}
	if ('page' == $_POST['post_type'] && (!current_user_can('edit_page', $post_id) || !current_user_can('edit_post', $post_id))) {
	    return;
	}
	$transaction_fields = unserialize(get_transient('transaction_fields'));
	foreach($transaction_fields as $meta_field) {
	    update_post_meta($post_id, $meta_field, sanitize_text_field( $_POST[$meta_field]));
	}
}
add_action('save_post', 'transaction_metabox_save');

function bbcart_new_field( $args ){
	// last updated 19/07/2014

	is_array( $args ) ? extract( $args ) : parse_str( $args );
	// $args example: "url="http://techn.com.au&target=_blank&output=echo"

	//set defaults
	if( !$title && !$field_name) return;
	if( !$title && $field_name) $title = $field_name; $title = ucfirst( strtolower( $title ) );
	if( !$field_name && $title ) $field_name = $title; $field_name = strtolower( str_replace( ' ', '_', $field_name ) );
	if( !$size ) $size = '100%'; // accepts valid css for all types expect textarea. Text array expects an array where [0] is width and [1] is height
	if( !$max_width ) $max_width = '100%';
	if( !$type ) $type = 'text'; // accepts 'text', 'textarea', 'checkox', 'select'

	$script = substr( $_SERVER['PHP_SELF'], strrpos( $_SERVER['PHP_SELF'], '/')+1 );
	$source = ( $script == 'post.php' || $script == 'post-new.php' ) ? 'meta' : 'option';
	if ( !$source == 'option' && !$group ) return;
	$field_name = ( $source == 'option' ) ? $group."[".$name."]" : $field_name;

	echo ' <div style="display:block;width:100%;padding-bottom:5px;">'."\n";
	switch ($type) {

		case 'checkbox':
			$checked = ( $source == 'meta' && get_post_meta( $_GET[post], $field_name, true ) == 'true') ? 'checked="checked"' : '' ;
			if( $source == 'option' ) {
				$option = get_option( $group );
			 	$value = $option[$name];
			 	$checked = ( $value == 'true' ) ? 'checked="checked"' : '';
			 }
			echo '   <input type="checkbox" name="'.$field_name.'" value="true" '.$checked.' style="margin: 0 5px 0px 0;"/><label style="color:rgba(0,0,0,0.75);">'.$title.'</label>'."\n";
			break;

		case 'textarea':
			if( $source == 'meta' ) $value = get_post_meta( $_GET[post], $field_name, true );
			if( $source == 'option' ) {
				$option = get_option( $group );
			 	$value = $option[$name];
			 }
		 	if( $default && !$value ) $value = $default;
			echo '	<label for="'.$field_name.'">'."\n";
			echo '   	<sub style="color:rgba(0,0,0,0.75);display:block;">'.$title.'</sub>'."\n";
			echo '   </label>'."\n";
			if( !is_array( $size ) ) $size = explode(',', $size);
			$style = 'width:'.$size[0].';height:'.$size[1].';max-width:'.$max_width.';';
			echo '   <textarea id="'.$field_name.'" name="'.$field_name.'" style="'.$style.';" placeholder="'.$placeholder.'" >'.esc_attr( $value ).'</textarea>'."\n";
			break;

		case 'select':
		// expects an $options array of arrays as follows
		// $options = array (
		//		array ( 'label' => 'aaa', 'value' => '1' ),
		//		array ( 'label' => 'aaa', 'value' => '1' ),
		//		);
			$current = get_post_meta( $_GET[post], $field_name, true ) ;
			echo '	<sub style="color:rgba(0,0,0,0.75);display:block;width:100%;max-width:'.$max_width.';">'.$title.'</sub>'."\n";
			echo '  	<select name="'.$field_name.'" id="'.$field_name.'">'."\n";
			foreach( $options as $option ) echo '		<option value="'.$option['value'].'" '.selected( $option['value'], $current, false ).'>'.$option['label'].'</option>'."\n";
			echo '	</select>'."\n";
			break;

		case 'color-picker':
			echo '	<label for="meta-color" class="prfx-row-title" style="display:block;width:100%;max-width:'.$max_width.';">'.$title.'</label>'."\n";
    		echo '	<input name="'.$field_name.'" type="text" value="'.get_post_meta( $_GET[post], $field_name, true ).'" class="meta-color" />'."\n";
			break;

		case 'wp-editor':
			if( $source == 'meta' ) $value = get_post_meta( $_GET[post], $field_name, true );
			if( $source == 'option' ) {
				$option = get_option( $group );
			 	$value = $option[$name];
			 }
		 	if( $default && !$value ) $value = $default;
			wp_editor( $value, $field_name, $settings );
			break;

		case 'text':
		default:
			if( $source == 'meta' ) $value = get_post_meta( $_GET[post], $field_name, true );
			if( $source == 'option' ) {
				$option = get_option( $group );
			 	$value = $option[$name];
			 }
		 	if( $default && !$value ) $value = $default;
			echo '	<label for="'.$field_name.'">'."\n";
			echo '		<sub style="color:rgba(0,0,0,0.75);display:block;">'.$title.'</sub>'."\n";
			echo '	</label>'."\n";
			echo '   <input type="'.$type.'" id="'.$field_name.'" name="'.$field_name.'" style="display:block;max-width:'.$max_width.';width:'.$size.';" placeholder="'.$placeholder.'" value="'.esc_attr( $value ).'" />'."\n";
			break;

	}
	if( $description ) echo '   <div style="position:relative;top:-3px;display:block;width:100%;color:#ddd;font-size:0.8em;">'.$description.'</div>'."\n";
	echo ' </div>'."\n";
	return $field_name;

}