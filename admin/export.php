<?php
class bb_cart_export {
    private $slug = 'bb_cart_export';

    public function __construct() {
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'));
            if ($_GET['page'] == $this->slug && $_GET['export'] == 'csv') {
                add_action('init', array($this, 'export_csv'));
            }
        }
    }

    public function add_plugin_page() {
        add_submenu_page('users.php', 'Export Transactions', 'Export Transactions', 'add_users', $this->slug, array($this, 'create_admin_page'));
    }

    public function create_admin_page() {
?>
<div class="wrap">
    <h2>Export Transactions</h2>
    <p>Export a CSV file containing your transaction data for use in another system, e.g. mail merge for printed receipts.</p>
    <form enctype="multipart/form-data" action="?page=<?php echo $this->slug; ?>&export=csv" method="POST">
        <table class="widefat striped">
            <tr>
                <th><label for="input_start_date">Start Date</label></th>
                <td><input required name="start_date" id="input_start_date" type="date" value="<?php echo current_time('Y-m-d'); ?>"></td>
            </tr>
            <tr>
                <th><label for="input_end_date">End Date</label></th>
                <td><input required name="end_date" id="input_end_date" type="date" value="<?php echo current_time('Y-m-d'); ?>"></td>
            </tr>
            <tr>
                <th><label>Which transactions do you want to export?</label></th>
                <td data-required>
                    <label><input name="input_filters[]" value="online" id="input_filters_online" type="checkbox"> Online</label><br>
                    <label><input name="input_filters[]" value="offline_receipted" id="input_filters_offline_receipted" type="checkbox"> Offline (receipted)</label><br>
                    <label><input name="input_filters[]" value="offline_unreceipted" id="input_filters_offline_unreceipted" type="checkbox" checked> Offline (not receipted)</label>
                </td>
            </tr>
            <tr>
                <th><label for="input_mark_receipted">Mark as receipted (optional)?</label></th>
                <td>
                    <label><input name="input_mark_receipted" value="yes" id="input_mark_receipted" type="checkbox"> Yes, mark previously unreceipted transactions as receipted</label>
                </td>
            </tr>
        </table>
        <p><strong>Please be patient after clicking "Generate". Depending on the number of transactions in the selected date range the process can take quite some time. Please do not click the button multiple times or navigate away from this page.</strong></p>
        <input type="submit" class="button" id="input_export_submit" value="Generate CSV File">
    </form>
    <script>
        jQuery(document).ready(function() {
            jQuery('#input_export_submit').click(function(e) {
                var form = jQuery(this).parent('form');
                var errors = 0;
                jQuery(form).find('input[required], select[required], textarea[required]').each(function() {
                    var is_error = false;
                    if (jQuery(this).attr('type') == 'radio') {
                        var input_name = jQuery(this).attr('name').replace(/\[|\]/g, function(m) {return '\\'+m;});
                        is_error = true;
                        jQuery(form).find('input[name='+input_name+']').each(function() {
                            if (jQuery(this).prop('checked')) {
                                is_error = false;
                            }
                        });
                    } else {
                        if (jQuery(this).val() == '') {
                            is_error = true;
                        }
                    }
                    if (is_error) {
                        errors++;
                        jQuery(this).parents('td').css('background-color', 'rgba(255,0,0,0.25)');
                    } else {
                        jQuery(this).parents('td').css('background-color', '');
                    }
                });
                jQuery(form).find('td[data-required]').each(function() {
                    var is_error = true;
                    jQuery(this).find('input[type=checkbox]').each(function() {
                        if (jQuery(this).prop('checked')) {
                            is_error = false;
                        }
                    });
                    if (is_error) {
                        errors++;
                        jQuery(this).css('background-color', 'rgba(255,0,0,0.25)');
                    } else {
                        jQuery(this).css('background-color', '');
                    }
                });
                if (errors > 0) {
                    alert('All fields are required unless otherwise indicated');
                    e.preventDefault();
                }
            });
        });
    </script>
</div>
<?php
    }

    public function export_csv() {
        // Check for required variables
        if (empty($_POST['start_date']) || empty($_POST['end_date']) || empty($_POST['input_filters'])) {
            wp_die('Invalid or missing parameters.');
        }
        $transactions = $this->load_matching_transactions($_POST);

        if (count($transactions) == 0) {
            wp_die('No matching transactions found.');
        }

        $csv = array(
                array(
                        'Date',
                        'Donor ID',
                        'Receipt Number',
                        'First Name',
                        'Last Name',
                        'Address Line 1',
                        'Address Line 2',
                        'Suburb',
                        'Postcode',
                        'State',
                        'Country',
                        'Fund Code',
                        'Amount',
                        'Receipted',
                        'Channel',
                ),
        );

        foreach ($transactions as $transaction) {
            $user = new WP_User($transaction->post_author);
            $user_meta = get_user_meta($user->ID);
            $line_items = bb_cart_get_transaction_line_items($transaction->ID);
            foreach ($line_items as $line_item) {
                $fund_code = wp_get_post_terms($line_item->ID, 'fundcode');
                $first_name = $user->first_name;
                if ($first_name == 'Unknown') {
                    if (!empty($user_meta['organization'][0])) {
                        // Temporary hack for badly imported organisations
                        $first_name = $user_meta['organization'][0];
                    } else {
                        $first_name = 'Friend';
                    }
                }
                $last_name = $user->last_name == 'Unknown' ? '' : $user->last_name;
                $csv[] = array(
                        'Date' => $transaction->post_date,
                        'Donor ID' => $user->ID,
                        'Receipt Number' => $transaction->ID,
                        'First Name' => $first_name,
                        'Last Name' => $last_name,
                        'Address Line 1' => $user_meta['bbconnect_address_one_1'][0],
                        'Address Line 2' => $user_meta['bbconnect_address_two_1'][0],
                        'Suburb' => $user_meta['bbconnect_address_city_1'][0],
                        'Postcode' => $user_meta['bbconnect_address_postal_code_1'][0],
                        'State' => $user_meta['bbconnect_address_state_1'][0],
                        'Country' => $user_meta['bbconnect_address_country_1'][0],
                        'Fund Code' => $fund_code[0]->name,
                        'Amount' => get_post_meta($line_item->ID, 'price', true),
                        'Receipted' => get_post_meta($transaction->ID, 'is_receipted', true) == 'true' ? 'Y' : 'N',
                        'Channel' => get_post_meta($transaction->ID, 'gf_entry_id', true) != '' ? 'Online' : 'Offline',
                );
            }
            if ($_POST['input_mark_receipted'] == 'yes') {
                update_post_meta($transaction->ID, 'is_receipted', 'true');
            }
        }

        $fp = fopen('php://output', 'w+');
        header('Content-type: application/octet-stream');
        header('Content-disposition: attachment; filename="transactions.csv"');
        foreach ($csv as $line) {
            fputcsv($fp, $line);
        }
        fclose($output);
        exit;
    }

    private function load_matching_transactions($search) {
        $from = $search['start_date'];
        $to = $search['end_date'];
        list($from_year, $from_month, $from_day) = explode('-', $from);
        list($to_year, $to_month, $to_day) = explode('-', $to);
        $args = array(
                'posts_per_page' => -1,
                'post_type' => 'transaction',
                'date_query' => array(
                        array(
                                'after' => array(
                                        'year' => (int)$from_year,
                                        'month' => (int)$from_month,
                                        'day' => (int)$from_day,
                                ),
                                'before' => array(
                                        'year' => (int)$to_year,
                                        'month' => (int)$to_month,
                                        'day' => (int)$to_day,
                                ),
                                'inclusive' => true,
                        ),
                ),
        );

        if (count($search['input_filters']) < 3) { // If all 3 options are selected we don't need to worry about meta queries
            $meta_query = array(
                    'relation' => 'OR',
            );
            if (in_array('online', $search['input_filters'])) {
                $meta_query[] = array(
                        'key' => 'transaction_type',
                        'value' => 'online',
                );
                $meta_query[] = array(
                        'key' => 'transaction_type',
                        'compare' => 'NOT EXISTS',
                );
            }
            if (in_array('offline_receipted', $search['input_filters']) || in_array('offline_unreceipted', $search['input_filters'])) {
                $offline_query = array(
                        array(
                                'key' => 'transaction_type',
                                'value' => 'offline',
                        ),
                        'relation' => 'AND',
                );
                if (!in_array('offline_unreceipted', $search['input_filters'])) { // Only receipted
                    $offline_query[] = array(
                            'key' => 'is_receipted',
                            'value' => 'true',
                    );
                } elseif (!in_array('offline_receipted', $search['input_filters'])) { // Only unreceipted
                    $offline_query[] = array(
                            array(
                                    'key' => 'is_receipted',
                                    'value' => 'true',
                                    'compare' => '!=',
                            ),
                            array(
                                    'key' => 'is_receipted',
                                    'compare' => 'NOT EXISTS',
                            ),
                            'relation' => 'OR',
                    );
                }
                $meta_query[] = $offline_query;
            }
            $args['meta_query'] = $meta_query;
        }

        return get_posts($args);
    }
}

$bb_cart_export = new bb_cart_export();
