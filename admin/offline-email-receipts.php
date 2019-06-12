<?php
class bb_cart_offline_email_receipts {
    static $merge_tags = array(
            '{{today_date}}',
            '{{transaction_date}}',
            '{{donor_name}}',
            '{{donor_nickname}}',
            '{{organisation_name}}',
            '{{donor_address}}',
            '{{donor_id}}',
            '{{transaction_amount}}',
            '{{fund_code}}',
            '{{receipt_number}}',
    );

    var $demo_content = array();

    public function __construct() {
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'), 15);
            add_action('admin_init', array($this, 'init'));
        }
        $current_user = wp_get_current_user();
        $this->demo_content = array(
                '{{today_date}}' => current_time(get_option('date_format')),
                '{{transaction_date}}' => current_time(get_option('date_format')),
                '{{donor_name}}' => $current_user->display_name,
                '{{donor_nickname}}' => $current_user->nickname,
                '{{organisation_name}}' => 'An Organisation',
                '{{donor_address}}' => '123 A Street<br>City State 9999<br>Country',
                '{{donor_id}}' => '1234',
                '{{transaction_amount}}' => '123.45',
                '{{fund_code}}' => 'Where Most Needed',
                '{{receipt_number}}' => '98765',
        );
    }

    public function add_plugin_page() {
        add_submenu_page('bb_cart_settings', 'Offline Email Receipts', 'Offline Receipts', 'add_users', 'bb_cart_offline_email_receipts', array($this, 'create_admin_page'));
    }

    public function init() {
        // Register settings
        register_setting('bb-cart-offline-receipt-settings-group', 'bb_cart_offline_receipt_subject');
        register_setting('bb-cart-offline-receipt-settings-group', 'bb_cart_offline_receipt_sender_name');
        register_setting('bb-cart-offline-receipt-settings-group', 'bb_cart_offline_receipt_sender_email');
        register_setting('bb-cart-offline-receipt-settings-group', 'bb_cart_offline_receipt_template');
        register_setting('bb-cart-offline-receipt-settings-group', 'bb_cart_offline_receipt_send_immediately');
        register_setting('bb-cart-offline-receipt-settings-group', 'bb_cart_offline_receipt_bcc_recipient');
        register_setting('bb-cart-offline-receipt-settings-group', 'bb_cart_offline_receipt_send_to_online_recurring_donors');

        // Additional hooks
        if (get_option('bb_cart_offline_receipt_send_to_online_recurring_donors') == 1) {
            add_action('bb_cart_webhook_paydock_recurring_success', array($this, 'after_transaction_import'), 10, 3);
        }
        if (get_option('bb_cart_offline_receipt_send_immediately') == 1) {
            add_action('bb_cart_post_import', array($this, 'after_transaction_import'), 10, 3);
        }
    }

    public function create_admin_page() {
        $user_email = wp_get_current_user()->user_email;
        if ($_GET['test_email'] == 'true') {
            $subject = str_replace(array_keys($this->demo_content), $this->demo_content, get_option('bb_cart_offline_receipt_subject'));
            $message = str_replace(array_keys($this->demo_content), $this->demo_content, get_option('bb_cart_offline_receipt_template'));
            $headers = array(
                    'From: '.get_option('bb_cart_offline_receipt_sender_name').' <'.get_option('bb_cart_offline_receipt_sender_email').'>',
                    'Content-Type: text/html; charset=UTF-8',
            );
            if (is_email(get_option('bb_cart_offline_receipt_bcc_recipient'))) {
                $headers[] = 'Bcc: '.get_option('bb_cart_offline_receipt_bcc_recipient');
            }
            if (wp_mail($user_email, $subject, wpautop($message), $headers)) {
                echo '<div class="notice notice-success"><p>Test email sent successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Test email sending failed.</p></div>';
            }
        }
        ?>
<div class="wrap">
    <h2>Offline Email Receipts</h2>
    <p><a href="<?php echo add_query_arg('test_email', 'true'); ?>" class="button-primary" onclick="return confirm('Send sample receipt email to <?php echo $user_email; ?> now? Please ensure you have saved your changes first.');">Send Test Email</a></p>
    <form method="post" action="options.php">
<?php
    settings_fields('bb-cart-offline-receipt-settings-group');
    do_settings_sections('bb-cart-offline-receipt-settings-group');
?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Sender Name</th>
            <td><input type="text" size="50" name="bb_cart_offline_receipt_sender_name" value="<?php echo get_option('bb_cart_offline_receipt_sender_name', get_option('blogname')); ?>"></td>
        </tr>
        <tr valign="top">
            <th scope="row">Sender Email</th>
            <td><input type="text" size="75" name="bb_cart_offline_receipt_sender_email" value="<?php echo get_option('bb_cart_offline_receipt_sender_email', get_option('admin_email')); ?>"></td>
        </tr>
        <tr valign="top">
            <th scope="row">Email Subject</th>
            <td><input type="text" size="75" name="bb_cart_offline_receipt_subject" value="<?php echo get_option('bb_cart_offline_receipt_subject'); ?>"></td>
        </tr>
        <tr valign="top">
            <th scope="row">Email Content<br>
            <p>The following merge fields are available:</p>
            <p><em><?php echo implode('<br>', self::$merge_tags); ?></em></p>
            </th>
            <td><?php wp_editor(get_option('bb_cart_offline_receipt_template'), 'bb_cart_offline_receipt_template'); ?></td>
        </tr>
        <tr valign="top">
            <th scope="row">Send Immediately</th>
            <td><label><input type="checkbox" name="bb_cart_offline_receipt_send_immediately" value="1" <?php checked(1, get_option('bb_cart_offline_receipt_send_immediately')); ?>>
            Send offline email receipts to all eligible recipients during transaction import.</label><br>
            Only applies to imported transactions. For all other offline transactions (or if this option is not ticked), the receipts must be triggered manually.</td>
        </tr>
        <tr valign="top">
            <th scope="row">Copy Offline Receipts To</th>
            <td><input type="email" size="75" name="bb_cart_offline_receipt_bcc_recipient" value="<?php echo get_option('bb_cart_offline_receipt_bcc_recipient'); ?>"><br>
            Enter an email address here to have a copy of all offline receipts sent to that address.</td>
        </tr>
        <tr valign="top">
            <th scope="row">Send to Online Recurring Donors</th>
            <td><label><input type="checkbox" name="bb_cart_offline_receipt_send_to_online_recurring_donors" value="1" <?php checked(1, get_option('bb_cart_offline_receipt_send_to_online_recurring_donors')); ?>>
            Send email receipts using the template above when a recurring online donation is received.</label></td>
        </tr>
    </table>
    <?php submit_button(); ?>
</form>
</div>
<?php
    }

    public function after_transaction_import($user, $amount, $transaction_id) {
        bb_cart_offline_email_receipts::send_email_receipt(get_post($transaction_id));
    }

    /**
     * Send email receipt to donor for selected transaction. Assumes all necessary checking has been done in terms of context based on the above settings (send immediately, send to online recurring, etc).
     * @param WP_Post $transaction
     * @return boolean
     */
    public static function send_email_receipt(WP_Post $transaction) {
        $user = new WP_User($transaction->post_author);
        if (!empty($user->user_email) && strpos($user->user_email, '@example.com') === false && substr($user->user_email, -8) != '.invalid') {
            $subject = self::replace_merge_tags(get_option('bb_cart_offline_receipt_subject'), $transaction);
            $message = self::replace_merge_tags(get_option('bb_cart_offline_receipt_template'), $transaction);
            $headers = array(
                    'From: '.get_option('bb_cart_offline_receipt_sender_name').' <'.get_option('bb_cart_offline_receipt_sender_email').'>',
                    'Content-Type: text/html; charset=UTF-8',
            );
            if (is_email(get_option('bb_cart_offline_receipt_bcc_recipient'))) {
                $headers[] = 'Bcc: '.get_option('bb_cart_offline_receipt_bcc_recipient');
            }
            if (wp_mail($user->user_email, $subject, wpautop($message), $headers)) {
                update_post_meta($transaction->ID, 'is_receipted', 'true');
                return true;
            }
        }
        return false;
    }

    /**
     * Replace all merge tags in $content based on data in $transaction
     * @param string $content
     * @param WP_Post $transaction
     * @return mixed
     */
    public static function replace_merge_tags($content, WP_Post $transaction) {
        foreach (self::$merge_tags as $merge_tag) {
            $content = self::replace_merge_tag($content, $transaction, $merge_tag);
        }
        return $content;
    }

    /**
     * Replace a single merge tag in $content based on the data in $transaction
     * @param string $content
     * @param WP_Post $transaction
     * @param string $merge_tag
     * @return mixed
     */
    public static function replace_merge_tag($content, WP_Post $transaction, $merge_tag) {
        $meta = get_user_meta($transaction->post_author);
        switch ($merge_tag) {
            case '{{today_date}}':
                $replace = current_time(get_option('date_format'));
                break;
            case '{{transaction_date}}':
                $transaction_date = new DateTime($transaction->post_date);
                $replace = $transaction_date->format(get_option('date_format'));
                break;
            case '{{donor_name}}':
                $donor = new WP_User($transaction->post_author);
                $replace = $donor->display_name;
                break;
            case '{{donor_nickname}}':
                $donor = new WP_User($transaction->post_author);
                $replace = $donor->nickname;
                break;
            case '{{organisation_name}}':
                $donor = new WP_User($transaction->post_author);
                $replace = $meta['organization'][0];
                break;
            case '{{donor_address}}':
                $replace = <<<EOR
{$meta['bbconnect_address_one_1'][0]}
{$meta['bbconnect_address_two_1'][0]}
{$meta['bbconnect_address_city_1'][0]} {$meta['bbconnect_address_state_1'][0]} {$meta['bbconnect_address_postal_code_1'][0]}
{$meta['bbconnect_address_country_1'][0]}
EOR;
                break;
            case '{{donor_id}}':
                $replace = $transaction->post_author;
                break;
            case '{{transaction_amount}}':
                $replace = '$'.number_format(get_post_meta($transaction->ID, 'total_amount', true), 2);
                break;
            case '{{fund_code}}':
                $line_items = bb_cart_get_transaction_line_items($transaction->ID);
                $fund_codes = array();
                foreach ($line_items as $line_item) {
                    $fund_code_id = bb_cart_get_fund_code($line_item->ID);
                    if ($fund_code_id) {
                        $fund_codes[] = get_the_title((int)$fund_code_id);
                    }
                }
                if (count($fund_codes) > 1) {
                    $last_code = array_pop($fund_codes);
                    $replace = implode(', ', $fund_codes).' and '.$last_code;
                } else {
                    $replace = $fund_codes[0];
                }
                break;
            case '{{receipt_number}}':
                $replace = $transaction->ID;
                break;
        }
        return str_replace($merge_tag, $replace, $content);
    }
}

add_action('init', function() {new bb_cart_offline_email_receipts();});
