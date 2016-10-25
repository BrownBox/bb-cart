<?php
/**
 * Plugin Name: BB Cart Evolution
 * Plugin URI: n/a
 * Description: A cart to provide some simple session and checkout facilities for GF
 * Version: 2.0.1
 * Author: Brown Box
 * Author URI: http://brownbox.net.au
 * License: Proprietary Brown Box
 */

require_once(dirname(__FILE__).'/cpt_.php');
require_once(dirname(__FILE__).'/tax_.php');
require_once(dirname(__FILE__).'/transaction_meta.php');

define('BB_CART_SESSION_ITEM', 'bb_cart_item');

new \bb_cart\cptClass('Transaction', 'Transactions', array(
        'rewrite' => array(
                'with_front' => false,
                'slug' => 'transaction',
        ),
        'labels' => array(
                'name' => 'Transactions',
        ),
        'public' => false,
        'has_archive' => false,
        'query_var' => false,
        'show_ui' => true,
));

// JUST DO SOME SESSION STUFF HERE TO KEEP IT CLEAN + NOT CREATE ANY SESSION PROBLEMS
add_action('init', 'bb_cart_start_session', 1);
function bb_cart_start_session() {
    if(!session_id()) {
        if (is_multisite()) {
            $domain = network_site_url();
            $domain = substr($domain, strpos($domain, '//')+2);
            $domain = substr($domain, 0, strrpos($domain, '/'));
            session_set_cookie_params(0, '/', '.'.$domain);
        }
        session_start();
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

add_action('init', 'bb_remove_item_from_cart');
function bb_remove_item_from_cart() {
    if(isset($_GET['remove_item'])) {
        $item = $_GET['remove_item'];
        if (strpos($item, ':') !== false) {
            list($section, $item) = explode(':', $item);
            unset($_SESSION[BB_CART_SESSION_ITEM][$section][$item]);
        } else
            unset($_SESSION[BB_CART_SESSION_ITEM][$item]);
    }
}

// THIS IS OUR FUNCTION FOR CLEANING UP THE PRICING AMOUNTS THAT GF SPITS OUT
function clean_amount($entry) {
    $entry = preg_replace("/\|(.*)/", '',$entry); // replace everything from the pipe symbol forward
    if(strpos($entry,'.')===false){
        $entry .= ".00";
    }
    if(strpos($entry,'$')!==false){
        $startsAt = strpos($entry, "$") + strlen("$");
        $endsAt=strlen($entry);
        $amount = substr($entry, 0, $endsAt);
        $amount = preg_replace("/[^0-9,.]/", "", $amount);
    }else{
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
add_filter('gform_pre_form_settings_save', 'save_bb_cart_form_setting');
function save_bb_cart_form_setting($form) {
    $form['bb_cart_enable'] = rgpost('bb_cart_enable');
    $form['bb_checkout_enable'] = rgpost('bb_checkout_enable');
    $form['custom_flash_message'] = rgpost('custom_flash_message');
    return $form;
}


add_filter("gform_field_value_bb_cart_total_name", "bb_add_total_name");
function bb_add_total_name($value){
    return "Donation";
}
add_filter("gform_field_value_bb_cart_total_price", "bb_cart_total_price");
function bb_cart_total_price($value){
    $total = 0;
    if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
        foreach ($_SESSION[BB_CART_SESSION_ITEM] as $item ) {
            $total += $item['price']*$item['quantity'];
        }
    }
    $total = $total/100;
    return $total;
}
add_filter("gform_field_value_bb_cart_total_quantity", "bb_cart_total_quantity");
function bb_cart_total_quantity($value){
    return "1";
}

add_filter("gform_field_value_bb_cart_checkout_items_array", "bb_cart_checkout_items_array");
function bb_cart_checkout_items_array($value){
    $array_string = '';
    if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
        foreach ($_SESSION[BB_CART_SESSION_ITEM] as $item ) {
            $array_string .= $item['entry_id'] . ",";
        }
        $array_string = substr($array_string, 0, -1);
    }
    return $array_string;
}

add_filter("gform_field_value_bb_cart_custom_item_label", "add_custom_label");
function add_custom_label($value){
    global $post;
    return $post->post_title;
}

// OK NOW WE NEED A POST-SUBMISSION HOOK TO CATCH ANY SUBMISSIONS FROM FORMS WITH 'bb_cart_enable' CHECKED
// MUST happen before the post_purchase function below
add_action("gform_after_submission", "check_for_cart_additions", 5, 2);
function check_for_cart_additions($entry, $form){
    if(!empty($form['custom_flash_message'])){
        $_SESSION['flash_message'] = $form['custom_flash_message'];
    }

    $frequency = 'one-off';
    $deductible = false;
    $campaign = '';
    $quantity = 1;
    $variation = '';
    $sku = '';
    if(!empty($form['bb_cart_enable']) && $form['bb_cart_enable']=="cart_enabled"){
        // ANNOYINGLY HAVE TO RUN THIS ALL THROUGH ONCE TO SET THE FIELD LABEL IN CASE THERE'S A CUSTOM LABEL SET
        foreach ($form['fields'] as $field) {
            if ($field['inputName']=='bb_cart_custom_item_label')
                $label = $entry[$field['id']];
            elseif ($field['adminLabel'] == 'bb_donation_frequency')
                $frequency = $entry[$field['id']];
            elseif ($field['inputName'] == 'bb_tax_status')
                $deductible = (boolean)$entry[$field['id']];
            elseif ($field['inputName'] == 'bb_campaign')
                $campaign = $entry[$field['id']];
            elseif ($field['type'] == 'quantity' && !empty($entry[$field['id']]))
                $quantity = $entry[$field['id']];
            elseif ($field['inputName'] == 'bb_variations')
                $variation = $entry[$field['id']];
            elseif ($field['inputName'] == 'bb_sku')
                $sku = $entry[$field['id']];
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
                $clean_price = clean_amount($amount); // this will now be the correctly formatted amount in cents
                if ($clean_price>0) {
                    if ($label == '')
                        $label = $field['label'];

                    global $blog_id;
                    $_SESSION[BB_CART_SESSION_ITEM][] = array(
                            'label' => $label,
                            'price' => $clean_price,
                            'form_id' => $form['id'],
                            'entry_id' => $entry['id'],
                            'frequency' => $frequency,
    			            'deductible' => $deductible,
    			            'campaign' => $campaign,
    			            'blog_id' => $blog_id,
                            'quantity' => $quantity,
                            'variation' => $variation,
                            'sku' => $sku,
                    );
                }
            }
            $quantity = $old_quantity;
            $label = $old_label;
        }
    }
}

// WE'LL DO ANOTHER AFTER SUBMISSION ONE TO CORRECTLY UPDATE THE ORIGINAL FORMS WITH THE PAYMENT SUCCESS
add_action("gform_after_submission", "bb_cart_post_purchase_actions", 99, 2);
function bb_cart_post_purchase_actions($entry, $form){
    global $blog_id;

    if (is_checkout_form($form)) {
        $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
        if (!empty($cart_items)) {
            $donation_amount = 0;
            $total_amount = 0;
            $first_item = reset($cart_items);

            foreach ($form['fields'] as $field) {
                if ($field['type'] == 'email') {
                    $email_field_id = $field['id'];
                } else if ($field['uniquenameField'] == 'firstname') {
                    $firstname_field_id = $field['id'];
                } else if ($field['uniquenameField'] == 'lastname') {
                    $lastname_field_id = $field['id'];
                } else if($field['type']== 'date' && !empty($entry[$field['id']])) {
                    $transaction_date = $entry[$field['id']];
                    $transaction_date = date('Y-m-d',strtotime($transaction_date));
                }
            }

            if (!empty($first_item['frequency'])) {
                $frequency = $first_item['frequency'];
            } else {
                $frequency = 'one-off';
            }

            $campaign = $first_item['campaign'];
            $deductible = $first_item['deductible'];

            foreach ($cart_items as $key => $value) {
                $total_amount = $total_amount + $value['price'];
            }
            $total_amount = $total_amount / 100;
            $donation_amount = $total_amount;

            $email = $entry[$email_field_id];

            if (isset($_GET['user_id'])) {
                $author_id = $_GET['user_id'];
            } elseif (is_user_logged_in()) {
                $author_id = get_current_user_id();
            }

            //determine if the payment will be processed via ECH or Via Paypal
            if (isset($first_item['paymentby']) && $first_item['paymentby']=='Paypal') {
                $post_status = 'draft';
            } else {
                $post_status = 'publish';
            }

            // Create post object
            $transaction = array(
                    'post_title' => $firstname . '-' . $lastname . '-' . $total_amount,
                    'post_content' => serialize($entry),
                    'post_status' => $post_status,
                    'post_author' => $author_id,
                    'post_type' => 'transaction',
            );

            if (!empty($author_id)) {
                $firstname = get_user_meta($author_id, 'first_name', true);
                $lastname = get_user_meta($author_id, 'last_name', true);
            }

            //check if transaction date exists
            if (isset($transaction_date) && !empty($transaction_date)) {
                $transaction['post_date'] = $transaction_date;
            } else {
                $transaction['post_date'] = date('Y-m-d');
            }

            // Insert the post into the database
            $post_id = wp_insert_post($transaction);

            add_post_meta($post_id, 'frequency', $frequency);
            add_post_meta($post_id, 'gf_entry_id', $entry['id']);
            add_post_meta($post_id, 'donation_amount', $donation_amount);
            add_post_meta($post_id, 'total_amount', $total_amount);
            add_post_meta($post_id, 'cart', serialize($cart_items));

            if(isset($deductible)) {
                add_post_meta($post_id, 'is_tax_deductible', (string)$deductible);
            }
            do_action('bb_cart_post_purchase', $cart_items, $entry, $form, $post_id);
            foreach ($cart_items as $item) {
        	    $switched = false;
        	    if (!empty($item['blog_id']) && $item['blog_id'] != $blog_id) {
        	        switch_to_blog($item['blog_id']);
        	        $switched = true;
        	    }
                RGFormsModel::update_lead_property($item['entry_id'], "payment_status", 'Approved');
                RGFormsModel::update_lead_property($item['entry_id'], "payment_amount", $item['price']/100);
                RGFormsModel::update_lead_property($item['entry_id'], "payment_date",   $entry['date_created']);
                RGFormsModel::update_lead_property($item['entry_id'], "payment_method", 'Credit Card');
        		if ($switched)
                    restore_current_blog();
            }
        }
        bb_cart_end_session();
    }
}

function is_checkout_form($form) {
    $is_checkout = false;
    if ((!empty($form['bb_checkout_enable']) && $form['bb_checkout_enable']=="checkout_enabled"))
        $is_checkout = true;
    else {
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
function bb_cart_configure_notifications( $notification, $form, $entry ) {
    // Get the products - this will work if the products are IN the actual credit card form
    // However if we're using CART and sessions, we'll need to run a secondary process
    $items = array();
    foreach ($form['fields'] as $field) {
        if($field['type']=="product"){
        $clean_price = clean_amount($entry[$field["id"]]); // this will now be the a correctly formatted amount in cents
            if($clean_price>0){

                if($label==''){
                    $label = $field['label'];
                }

                $items[] = array(
                    'label' => $label,
                    'price' => $clean_price,
                    'form_id' => $form['id'],
                    'entry_id' => $entry['id']
                );
            };

        }
    }

    // Now we need to ADD the session ones
    foreach ($_SESSION[BB_CART_SESSION_ITEM] as $item ) {
        $items[] = array(
            'label' => $item['label'],
            'price' => $item['price']
        );
    }

    foreach ($items as $item) {
        $item_string .= "<tr><td>" . $item['label'] . "</td><td align='right'>&#8364;" . $item['price']/100 . "</td></tr>";
    }

    $notification['message'] = str_replace("!!!items!!!", $item_string, $notification['message']);
    return $notification;
}

// OK SO NOW THAT WE'VE GOT ITEMS SUCCESSFULLY IN THE SESSION, WE NEED TO BUILD THAT CART EXPERIENCE AND HEADER

// CREATE CUSTOM OPTIONS PANELS
function add_menu_icons_styles(){
?>

<style>
#toplevel_page_bb_cart-bb_cart div.wp-menu-image:before {content: "\f174";}
</style>

<?php
}
// add_action( 'admin_head', 'add_menu_icons_styles' );

add_action('admin_menu', 'bb_cart_create_menu');
function bb_cart_create_menu() {
    //create new top-level menu
    add_menu_page('CART Settings', 'CART Settings', 'administrator', 'bb_cart_settings', 'bb_cart_settings_page');
    add_menu_page('CART Orders', 'CART Orders', 'administrator', 'bb_cart_orders', 'bb_cart_orders');
}

//call register settings function
add_action( 'admin_init', 'register_bb_cart_settings' );
function register_bb_cart_settings() {
    //register our settings
    register_setting( 'bb-cart-settings-group', 'bb_cart_checkout_form_id' );
    register_setting( 'bb-cart-settings-group', 'bb_advanced_form_notification_addresses' );
    register_setting( 'bb-cart-settings-group', 'bb_cart_envoyrelate_endpoint' );
}

function bb_cart_settings_page() {
?>
<div class="wrap">
<h2>CART Settings</h2>
<form method="post" action="options.php">
    <?php settings_fields( 'bb-cart-settings-group' ); ?>
    <?php do_settings_sections( 'bb-cart-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Checkout Form ID<br/><em>This won't likely need to be changed and is used for order-details matching</em></th>
        <td><input type="text" name="bb_cart_checkout_form_id" value="<?php echo get_option('bb_cart_checkout_form_id'); ?>" /></td>
        </tr>
    </table>
    <table class="form-table" width="50%">
        <tr valign="top">
        <th scope="row">Advanced Form Notification Emails</th>
        <td><input type="text" name="bb_advanced_form_notification_addresses" value="<?php echo get_option('bb_advanced_form_notification_addresses'); ?>" /></td>
        </tr>
        <tr valign="top">
        <th scope="row" width="20%">EnvoyRelate API Endpoint</th>
        <td><input type="text" name="bb_cart_envoyrelate_endpoint" value="<?php echo get_option('bb_cart_envoyrelate_endpoint'); ?>" size="50"/></td>
        </tr>
      </table>
    <?php submit_button(); ?>
</form>
</div>
<?php
}

function bb_cart_orders() {
?>
<div class="wrap">
<h2>CART Orders</h2>
    <form action="/wp-admin/admin.php" method='get'>
        <label for='order_id'>Enter order ID for details</label>
        <input type='text' size='10' name='order_id' id='order_id' value="<?php echo $_GET['order_id']; ?>"/>
        <input type='hidden' value='bb_cart_orders' name='page' id='page'/>
        <input type='hidden' value='<?php  echo get_option('bb_cart_checkout_form_id'); ?>' name='form_id' id='form_id'/>
        <br/>
        <input type='Submit' value='Retrieve Details' class='button button-primary button-large'>
    </form>
    <a href="/wp-admin/admin.php?page=gf_entries&id=<?php echo $_GET['form_id']; ?>">All orders</a>
</div>
<?php
    if($_GET['order_id']) {
        $lead = RGFormsModel::get_lead($_GET['order_id']);
        $order_meta = RGFormsModel::get_form_meta($lead['form_id']);

        if (!empty($_GET['action']) && $_GET['action'] == 'reissueEmail') {
            post_to_ocr($lead, $order_meta);
            echo '<div class="updated">'."\n";
            echo '    <p>Emails have been resent</p>'."\n";
            echo '</div>'."\n";
        }

        echo "<h3>Purchaser Information</h3>";
        echo "" . $lead["1.3"] . " " . $lead["1.6"];
        echo "<br/>" . $lead["5.1"] . "<br/>" . $lead["5.3"] . " " . $lead["5.4"] . " " . $lead["5.5"] . "<br/>" . $lead["5.6"];
        echo "<br/>" . $lead[2];
        echo "<br/>Payment via: " . $lead["7.4"];
        echo "<br/>";
        foreach ($order_meta['fields'] as $field) {
            if($field['inputName']=="bb_cart_checkout_items_array"){
                $items = $lead[$field['id']];
            }
        }
        $items_array = explode(",",$items);
        foreach ($items_array as $item) {
            $item_detail = RGFormsModel::get_lead($item);
            $numerickeys = array_filter(array_keys($item_detail), 'is_int');
?>
<h3>Order Item Details</h3>
<table>
<thead>
    <tr>
        <th align='left'>Date</th>
        <th align='left'>Details</th>
        <th align='left'>Full Record</th>
        <th align='left'>Actions</th>
    </tr>
</thead>
<tbody>
    <tr>
        <td><?php
        echo date('d F Y', strtotime($item_detail["date_created"])) . "&nbsp;&nbsp;&nbsp;";
        ?></td>
        <td><?php
            foreach ($numerickeys as $key) {
                if(strpos($item_detail[$key], '$')!==false){
                    echo substr($item_detail[$key], 0, strpos($item_detail[$key], "|")) . " " ;
                }else{
                    echo $item_detail[$key] . " ";
                }
            }
            ?>
        </td>
        <td>
            <a href="/wp-admin/admin.php?page=gf_entries&view=entry&id=&lid=<?php echo $item_detail['id']; ?>&filter=&paged=1&pos=0&field_id=&operator=">Full Entry</a>
        </td>
        <td>
            <a href="/wp-admin/admin.php?page=bb_cart_orders&order_id=<?php echo $_GET['order_id'] ?>&form_id=<?php echo $item_detail['form_id']; ?>&action=reissueEmail">Reissue Ezescan Email</a>
        </td>
    </tr>
</tbody>
</table>
<?php
        }
    }
}

function bb_cart_shortcode() {
    $total_price = 0;
    $html = '';
    if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
        $html = '<h2>Cart</h2>'."\n";
        $html .= '<table class="bb-table">'."\n";
        $html .= '<tr>'."\n";
        $html .=  '<th>Type</th>'."\n";
        $html .=  '<th>Amount</th>'."\n";
        $html .=  '<th></th>'."\n";
        $html .=  '</tr>'."\n";
        foreach ($_SESSION[BB_CART_SESSION_ITEM] as $idx => $item) {
            $html .=  '<tr>'."\n";
            $label = $item['quantity'].'x '.$item['label'];
            if (!empty($item['variation'])) {
                $label .= ' ('.$item['variation'].')';
            }
            $html .=  '<td>'.$label.'</td>'."\n";
            $item_price = $item['price']/100;
            $frequency = $item['frequency'] == 'one-off' ? '' : '/'.ucfirst($item['frequency']);
            $html .=  '<td>$'.number_format($item_price*$item['quantity'], 2).$frequency.'</td>'."\n";
            $html .=  '<td><a href="?remove_item='.$idx.'" title="Remove" onclick="return confirm(\'Are you sure you want to remove this item?\');">x</a></td>'."\n";
            $html .=  '</tr>'."\n";
        }
        $html .=  '</table>'."\n";
        $html .=  '  <h5>Total: $'.number_format(bb_cart_total_price(), 2).'</h5>'."\n";
    }
    return $html;
}
add_shortcode('bb_cart_table', 'bb_cart_shortcode' );
