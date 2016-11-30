<?php
/**
 * Plugin Name: BB Cart Evolution
 * Plugin URI: n/a
 * Description: A cart to provide some simple session and checkout facilities for GF
 * Version: 2.1.1
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
        } else {
            unset($_SESSION[BB_CART_SESSION_ITEM][$item]);
        }
    }
}

// THIS IS OUR FUNCTION FOR CLEANING UP THE PRICING AMOUNTS THAT GF SPITS OUT
function clean_amount($entry) {
    $entry = preg_replace("/\|(.*)/", '',$entry); // replace everything from the pipe symbol forward
    if (strpos($entry,'.')===false) {
        $entry .= ".00";
    }
    if (strpos($entry,'$')!==false) {
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
add_filter('gform_pre_form_settings_save', 'save_bb_cart_form_setting');
function save_bb_cart_form_setting($form) {
    $form['bb_cart_enable'] = rgpost('bb_cart_enable');
    $form['bb_checkout_enable'] = rgpost('bb_checkout_enable');
    $form['custom_flash_message'] = rgpost('custom_flash_message');
    return $form;
}

add_filter("gform_field_value_bb_cart_total_name", "bb_add_total_name");
function bb_add_total_name($value = '') {
    return "Donation";
}

add_filter("gform_field_value_bb_cart_total_price", "bb_cart_total_price");
function bb_cart_total_price($value = '') {
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
function bb_cart_total_quantity($value = '') {
    if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
        return count($_SESSION[BB_CART_SESSION_ITEM]);
    }
    return 0;
}

add_filter('gform_field_value_bb_campaign', 'bb_cart_primary_campaign');
function bb_cart_primary_campaign($value = '') {
    if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
        foreach ($_SESSION[BB_CART_SESSION_ITEM] as $item) {
            if (!empty($item['campaign_id'])) {
                return $item['campaign_id'];
            }
        }
    }
    return $value;
}

add_filter("gform_field_value_bb_donation_frequency", "bb_cart_frequency");
function bb_cart_frequency($value = '') {
    if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
        foreach ($_SESSION[BB_CART_SESSION_ITEM] as $item) {
            if (!empty($item['frequency'])) {
                return $item['frequency'];
            }
        }
    }
    return $value;
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

    global $post;
    $frequency = 'one-off';
    $deductible = false;
    $campaign = $post->ID;
    $quantity = 1;
    $variations = array();
    $sku = '';
    if (!empty($form['bb_cart_enable']) && $form['bb_cart_enable']=="cart_enabled") {
        // ANNOYINGLY HAVE TO RUN THIS ALL THROUGH ONCE TO SET THE FIELD LABEL IN CASE THERE'S A CUSTOM LABEL SET
        foreach ($form['fields'] as $field) {
            if ($field['inputName']=='bb_cart_custom_item_label') {
                $label = $entry[$field['id']];
            } elseif ($field['adminLabel'] == 'bb_donation_frequency') {
                $frequency = $entry[$field['id']];
            } elseif ($field['inputName'] == 'bb_tax_status') {
                $deductible = (boolean)$entry[$field['id']];
            } elseif ($field['inputName'] == 'bb_campaign') {
                $campaign = $entry[$field['id']];
            } elseif ($field['type'] == 'quantity' && !empty($entry[$field['id']])) {
                $quantity = $entry[$field['id']];
            } elseif (strpos($field->inputName, 'variation') !== false) {
                $variations[] = $entry[$field['id']];
            } elseif ($field['inputName'] == 'bb_sku') {
                $sku = $entry[$field['id']];
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
                            'variation' => $variations,
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
            $payment_method = 'Credit Card';
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
                } else if ($field->inputName == 'payment_method') {
                    $payment_method = $entry[$field->id];
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
            add_post_meta($post_id, 'payment_method', $payment_method);

            if(isset($deductible)) {
                add_post_meta($post_id, 'is_tax_deductible', (string)$deductible);
            }
            do_action('bb_cart_post_purchase', $cart_items, $entry, $form, $post_id);
            $_SESSION['last_checkout'] = $entry['id'];

            $gf_line_items = array(
                    'products' => array(),
                    'shipping' => array(
                            'name' => 'Shipping',
                            'price' => 0,
                    ),
            );
            foreach ($cart_items as $item) {
                $gf_line_items['products'][] = array(
                        'name' => $item['label'],
                        'price' => $item['price']/100,
                        'quantity' => $item['quantity'],
                );
                if (!empty($item['entry_id'])) {
            	    $switched = false;
            	    if (!empty($item['blog_id']) && $item['blog_id'] != $blog_id) {
            	        switch_to_blog($item['blog_id']);
            	        $switched = true;
            	    }
                    GFAPI::update_entry_property($item['entry_id'], "payment_status", $transaction_status);
                    GFAPI::update_entry_property($item['entry_id'], "payment_amount", $item['price']/100);
                    GFAPI::update_entry_property($item['entry_id'], "payment_date", $entry['date_created']);
                    GFAPI::update_entry_property($item['entry_id'], "payment_method", $payment_method);
            		if ($switched) {
                        restore_current_blog();
            		}
                }
            }
            gform_update_meta($entry['id'], 'gform_product_info__', $gf_line_items);
        }
        bb_cart_end_session();
    }
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

add_filter('gform_paypal_query', 'bb_cart_paypal_line_items', 10, 5);
function bb_cart_paypal_line_items($query_string, $form, $entry, $feed, $submission_data) {
    parse_str(ltrim($query_string, '&'), $query);
    $i = 1;
    foreach ($_SESSION[BB_CART_SESSION_ITEM] as $cart_item) {
        $query['item_name_'.$i] = $cart_item['label'];
        $query['amount_'.$i] = $cart_item['price']/100;
        $query['quantity_'.$i] = $cart_item['quantity'];
        $i++;
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
    wp_publish_post($transaction_id);
    GFAPI::update_entry_property($entry['id'], "payment_status", 'Approved');
    GFAPI::update_entry_property($entry['id'], "payment_date", $date);
    $form = GFAPI::get_form($entry['form_id']);
    foreach ($form['fields'] as $field) {
        if ($field->inputName == 'bb_cart_checkout_items_array') {
            $item_entries = explode(',', $entry[$field->id]);
            foreach ($item_entries as $item_entry) {
                GFAPI::update_entry_property($item_entry['id'], "payment_status", 'Approved');
                GFAPI::update_entry_property($item_entry['id'], "payment_date", $date);
            }
        }
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
function bb_cart_configure_notifications($notification, $form, $entry) {
        // Get the products - this will work if the products are IN the actual credit card form
        // However if we're using CART and sessions, we'll need to run a secondary process
    $items = array();
    foreach ($form['fields'] as $field) {
        if ($field['type'] == "product") {
            $clean_price = clean_amount($entry[$field["id"]]); // this will now be the a correctly formatted amount in cents
            if ($clean_price > 0) {
                if ($label == '') {
                    $label = $field['label'];
                }

                $items[] = array(
                        'label' => $label,
                        'price' => $clean_price,
                        'form_id' => $form['id'],
                        'entry_id' => $entry['id'],
                );
            }
        }
    }

    // Now we need to ADD the session ones
    if (bb_cart_total_quantity() > 0) {
        $cart_items = $_SESSION[BB_CART_SESSION_ITEM];
    } else {
        $cart_items = bb_cart_get_cart_from_entry($entry);
    }
    foreach ($cart_items as $item) {
        $items[] = array(
            'label' => $item['label'],
            'price' => $item['price'],
        );
    }

    foreach ($items as $item) {
        $item_string .= "<tr><td>".$item['label']."</td><td align='right'>".GFCommon::to_money($item['price']/100)."</td></tr>";
    }

    $notification['message'] = str_replace("!!!items!!!", $item_string, $notification['message']);
    return $notification;
}

add_action('admin_menu', 'bb_cart_create_menu');
function bb_cart_create_menu() {
    //create new top-level menu
    add_menu_page('BB Cart', 'BB Cart', 'administrator', 'bb_cart_settings', 'bb_cart_settings_page', 'dashicons-cart');
    add_submenu_page('bb_cart_settings', 'Settings', 'Settings', 'administrator', 'bb_cart_settings', 'bb_cart_settings_page', 'dashicons-cart');
    add_submenu_page('bb_cart_settings', 'Pledges', 'Pledges', 'administrator', 'bb_cart_pledges', 'bb_cart_pledges');
}

//call register settings function
add_action('admin_init', 'register_bb_cart_settings');
function register_bb_cart_settings() {
    //register our settings
    register_setting('bb-cart-settings-group', 'bb_cart_checkout_form_id');
    register_setting('bb-cart-settings-group', 'bb_advanced_form_notification_addresses');
    register_setting('bb-cart-settings-group', 'bb_cart_envoyrelate_endpoint');
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
            <th scope="row">Checkout Form ID<br>
            <em>This won't likely need to be changed and is used for order-details matching</em></th>
            <td><input type="text" name="bb_cart_checkout_form_id" value="<?php echo get_option('bb_cart_checkout_form_id'); ?>"></td>
        </tr>
    </table>
    <table class="form-table" width="50%">
        <tr valign="top">
            <th scope="row">Advanced Form Notification Emails</th>
            <td><input type="text" name="bb_advanced_form_notification_addresses" value="<?php echo get_option('bb_advanced_form_notification_addresses'); ?>"></td>
        </tr>
        <tr valign="top">
            <th scope="row" width="20%">EnvoyRelate API Endpoint</th>
            <td><input type="text" name="bb_cart_envoyrelate_endpoint" value="<?php echo get_option('bb_cart_envoyrelate_endpoint'); ?>" size="50"></td>
        </tr>
    </table>
    <?php submit_button(); ?>
</form>
</div>
<?php
}

function bb_cart_pledges() {
?>
<div class="wrap">
    <h2>Pledge Management</h2>
<?php
    if (!empty($_GET['pledge_id']) && !empty($_GET['pledge_action'])) {
        switch ($_GET['pledge_action']) {
            case 'complete':
                $date = date('Y-m-d H:i:s'); // @todo let user specify date
                bb_cart_complete_pending_transaction($_GET['pledge_id'], $date);
                echo '<div class="notice notice-success"><p>Transaction successfully marked as complete.</p></div>';
                break;
            case 'cancel':
                $message = 'Cancelled'; // @todo let user specify message
                bb_cart_cancel_pending_transaction($_GET['pledge_id'], $message);
                echo '<div class="notice notice-success"><p>Transaction successfully marked as cancelled.</p></div>';
                break;
        }
    }
    $args = array(
            'post_type' => 'transaction',
            'post_status' => 'draft',
            'posts_per_page' => -1,
    );
    $pledges = get_posts($args);
    if (count($pledges)) {
?>
    <table class="bb_cart_table widefat striped">
        <tr>
            <th>Pledge Date</th>
            <th>Name</th>
            <th>Email</th>
            <th>Amount</th>
            <th>Payment Method</th>
            <th>Actions</th>
        </tr>
<?php
    foreach ($pledges as $pledge) {
        if (!empty($pledge->post_author)) {
            $name = get_user_meta($pledge->post_author, 'display_name', true);
            $email = get_user_meta($pledge->post_author, 'user_email', true);
        } else {
            $entry = GFAPI::get_entry(get_post_meta($pledge->ID, 'gf_entry_id', true));
            $form = GFAPI::get_form($entry['form_id']);
            foreach ($form['fields'] as $field) {
                switch ($field->type) {
                    case 'name':
                        $name = $entry[$field['id'].'.3'].' '.$entry[$field['id'].'.6'];
                        break;
                    case 'email':
                        $email = $entry[$field['id']];
                        break;
                }
            }
        }
?>
        <tr>
            <td><?php echo $pledge->post_date; ?></td>
            <td><?php echo $name; ?></td>
            <td><?php echo $email; ?></td>
            <td style="text-align: right;">$<?php echo number_format(get_post_meta($pledge->ID, 'total_amount', true), 2); ?></td>
            <td><?php echo get_post_meta($pledge->ID, 'payment_method', true); ?></td>
            <td><a onclick="return confirm('Are you sure you want to manually mark this transaction as complete?');" href="admin.php?page=bb_cart_pledges&pledge_id=<?php echo $pledge->ID; ?>&pledge_action=complete" class="dashicons dashicons-yes" title="Mark as won"></a> <a onclick="return confirm('Are you sure you want to manually mark this transaction as failed?');" href="admin.php?page=bb_cart_pledges&pledge_id=<?php echo $pledge->ID; ?>&pledge_action=cancel" class="dashicons dashicons-no-alt" title="Mark as lost"></a></td>
        </tr>
<?php
    }
?>
    </table>
<?php
    } else {
?>
    <p>There are no pledges currently pending.</p>
<?php
    }
?>
</div>
<?php
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
            $html .=  '<td>'.GFCommon::to_money($item_price*$item['quantity']).$frequency.'</td>'."\n";
            $html .=  '<td><a href="?remove_item='.$idx.'" title="Remove" onclick="return confirm(\'Are you sure you want to remove this item?\');">x</a></td>'."\n";
            $html .=  '</tr>'."\n";
        }
        $html .=  '</table>'."\n";
        $html .=  '  <h5>Total: $'.number_format(bb_cart_total_price(), 2).'</h5>'."\n";
    }
    return $html;
}
add_shortcode('bb_cart_table', 'bb_cart_shortcode' );
