<?php
class bb_cart_import {
    public function __construct() {
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'));
        }
    }

    public function add_plugin_page() {
        add_submenu_page('users.php', 'Import Transactions', 'Import Transactions', 'add_users', 'bb_cart_import', array($this, 'create_admin_page'));
    }

    public function create_admin_page() {
?>
<div class="wrap">
    <h2>Import CSV Data</h2>
<?php
        if (!empty($_FILES['uploadedfile']['tmp_name'])) {
            $batch = array(
                    'post_title' => !empty($_POST['batch_name']) ? $_POST['batch_name'] : basename($_FILES['uploadedfile']['name']),
                    'post_content' => $_POST['comments'],
                    'post_status' => 'pending',
                    'post_author' => get_current_user_id(),
                    'post_type' => 'transactionbatch',
                    'post_date' => $_POST['date'],
            );

            // Insert the post into the database
            $batch_id = wp_insert_post($batch);
            update_post_meta($batch_id, 'batch_type', $_POST['batch_type']);
            update_post_meta($batch_id, 'banking_details', $_POST['banking_details']);

            $data = $this->csv_to_array($_FILES['uploadedfile']['tmp_name']);
            $data_type = $_POST['data_type'];

            unset($errors);
            $errors = array();

            // show imported data
            $plural = (count($data) != 1) ? 's' : '';
            $headers = false;
            echo '<p>' . count($data) . ' record' . $plural . ' available for processing.</p>';
            $data_list = '<table class="widefat striped">';
            foreach ($data as $d) {
                set_time_limit(300);
                if (!$headers) {
                    $data_list .= '<tr>';
                    foreach ($d as $h => $v) {
                        $data_list .= '<th>'.$h.'</th>';
                    }
                    $data_list .= '</tr>';
                    $headers = true;
                }
                $data_list .= '<tr>';
                foreach ($d as $v) {
                    $data_list .= '<td>'.$v.'</td>';
                }
                $data_list .= '</tr>';
                $e = $this->{'import_'.$data_type}($d, $batch_id); // The magic happens here [TM]
                if (is_string($e) && strlen($e) > 0) {
                    array_push($errors, $e);
                }
            }
            $data_list .= '</table>';

            $done = count($data) - count($errors);
            $plural = ($done != 1) ? 's' : '';
            echo '<p><strong>' . $done . ' record' . $plural . ' imported.</strong></p>';

            if (count($errors) > 0) {
                $plural = (count($errors) > 1) ? 's' : '';
                echo '<p>' . count($errors) . ' record' . $plural . ' could not be imported.</p>';
                echo '<textarea rows="5" cols="100">';
                print_r($errors);
                echo '</textarea>';
            }
            echo $data_list;
            echo '<a class="button" href="">Process another file</a>';
        } else {
            if (!empty($_FILES['uploadedfile']['error'])) {
                $upload_error_strings = array(
                        false,
                        __('The uploaded file exceeds the upload_max_filesize directive in php.ini.'),
                        __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.'),
                        __('The uploaded file was only partially uploaded.'),
                        __('No file was uploaded.'),
                        '',
                        __('Missing a temporary folder.'),
                        __('Failed to write file to disk.'),
                        __('File upload stopped by extension.'),
                );
?>
    <div class="error"><p><strong>There was an error uploading your file:</strong> <?php echo $upload_error_strings[$_FILES['uploadedfile']['error']]; ?></p></div>
<?php
            }
?>
    <p>Upload a CSV file containing your transaction data from another system to import it. Your CSV file must contain one record per line, with the first line of the file containing the field names.</p>
    <form enctype="multipart/form-data" action="" method="POST">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo wp_max_upload_size(); ?>">
        <table class="widefat striped">
            <tr>
                <th><label for="input_uploadedfile">Choose a file to upload</label></th>
                <td><input required name="uploadedfile" id="input_uploadedfile" type="file"></td>
            </tr>
            <tr>
                <th><label for="input_data_type">File Format</label></th>
                <td>
                    <select required name="data_type" id="input_data_type">
                        <option value="ezescan">EzeScan</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="input_batch_name">Batch Name (if left blank will use file name)</label></th>
                <td><input name="batch_name" id="input_batch_name" type="text"></td>
            </tr>
            <tr>
                <th><label for="input_batch_type">Batch Type</label></th>
                <td><input required name="batch_type" id="input_batch_type" type="text"></td>
            </tr>
            <tr>
                <th><label for="input_banking_details">Banking Details</label></th>
                <td><input required name="banking_details" id="input_banking_details" type="text"></td>
            </tr>
            <tr>
                <th><label for="input_date">Date</label></th>
                <td><input required name="date" id="input_date" type="date" value="<?php echo current_time('Y-m-d'); ?>"></td>
            </tr>
            <tr>
                <th><label for="input_amount">Batch Total</label></th>
                <td>$<input required name="amount" id="input_amount" type="number" step="0.01"></td>
            </tr>
            <tr>
                <th><label for="input_comments">Comments (optional)</label></th>
                <td><textarea name="comments" id="input_comments" rows="6" cols="50"></textarea></td>
            </tr>
        </table>
        <p><strong>Please be patient after clicking "Import". Depending on the size of your file the process can take quite some time. Please do not click the button multiple times or navigate away from this page.</strong></p>
        <input type="submit" class="button" id="input_import_submit" value="Import CSV File">
    </form>
    <script>
        jQuery(document).ready(function() {
            jQuery('#input_import_submit').click(function(e) {
                var form = jQuery(this).parent('form');
                var errors = 0;
                jQuery(form).find('input[required], select[required], textarea[required]').each(function() {
                    if (jQuery(this).val() == '') {
                        errors++;
                        jQuery(this).parent('td').css('background-color', 'rgba(255,0,0,0.25)');
                    } else {
                        jQuery(this).parent('td').css('background-color', '');
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
    }

    private function csv_to_array($filename = '', $delimiter = ',') {
        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }

        $header = null;
        $data = array();
        $line_endings = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', true);
        if (($file = fopen($filename, 'r')) !== false) {
            while (($row = fgetcsv($file)) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($file);
        }
        ini_set('auto_detect_line_endings', $line_endings);

        return $data;
    }

    private function import_ezescan($data, $batch_id) {
        // Try and rationalise some data
        if (empty($data['Name'])) {
            $data['Name'] = $data['First Name'];
        }
        if (empty($data['Surname'])) {
            $data['Surname'] = $data['Last Name'];
        }
        if (empty($data['Email'])) {
            $email = preg_replace('/[^0-9a-z_-]/i', '', $data['Name'].'_'.$data['Surname'].'_');
            if (!empty($data['ID'])) {
                $email .= $data['ID'];
            } else {
                $email .= wp_generate_password(6, false);
            }
            $email .= '@example.com';
            $data['Email'] = strtolower($email);
        }
        $user = get_user_by('email', $data['Email']);
        if (!$user instanceof WP_User) {
            $args = array(
                    'meta_query' => array(
                            array(
                                    'key' => 'bbconnect_address_postal_code_1',
                                    'value' => $data['Postcode'],
                            ),
                    ),
            );
            if (!empty($data['Name'])) {
                $args['meta_query'][] = array(
                        'key' => 'first_name',
                        'value' => $data['Name'],
                );
            }
            if (!empty($data['Surname'])) {
                $args['meta_query'][] = array(
                        'key' => 'last_name',
                        'value' => $data['Surname'],
                );
            }
            if (!empty($data['Organization'])) {
                $args['meta_query'][] = array(
                        'key' => 'organization',
                        'value' => $data['Organization'],
                );
            }
            $users = get_users($args);
            if (count($users) == 1) {
                $user = array_shift($users);
            } else { // No single match - create user
                if (empty($data['Name'])) {
                    $data['Name'] = 'Unknown';
                }
                if (empty($data['Surname'])) {
                    $data['Surname'] = 'Unknown';
                }

                $user_name = wp_generate_password(8, false);
                $random_password = wp_generate_password(12, false);

                $userdata = array(
                        'user_login' => $user_name,
                        'first_name' => $data['Name'],
                        'last_name' => $data['Surname'],
                        'user_pass' => $random_password,
                        'user_email' => $data['Email'],
                        'user_nicename' => $data['Name'],
                        'display_name' => $data['Name'].' '.$data['Surname'],
                );
                $author_id = wp_insert_user($userdata);

                // On fail
                if (is_wp_error($author_id)) {
                    return $data['Name'].' '.$data['Surname'].': Error creating user - '.$author_id->get_error_message();
                }

                update_user_meta($author_id, 'bbconnect_bbc_primary', 'address_1');
                update_user_meta($author_id, 'title', $data['Title']);
                update_user_meta($author_id, 'organization', $data['Organization']);
                update_user_meta($author_id, 'bbconnect_address_one_1', $data['Address']);
                update_user_meta($author_id, 'bbconnect_address_two_1', $data['Address Line 2']);
                update_user_meta($author_id, 'bbconnect_address_city_1', $data['Suburb']);
                update_user_meta($author_id, 'bbconnect_address_state_1', $data['State']);
                update_user_meta($author_id, 'bbconnect_address_postal_code_1', $data['Postcode']);
                update_user_meta($author_id, 'bbconnect_address_country_1', $data['Country']);
                $user = new WP_User($author_id);
            }
        }

        $author_id = $user->ID;
        $firstname = get_user_meta($author_id, 'first_name', true);
        $lastname = get_user_meta($author_id, 'last_name', true);

        list($day, $month, $year) = explode('/', $data['Payment Date']);
        $transaction_date = implode('-', array($year, $month, $day));

        $transaction_details = array(
                'date' => $transaction_date,
                'amount' => $data['Amount'],
                'user' => $user,
                'fund_code' => $data['Source'],
        );
        if (!bb_cart_transaction_exists($transaction_details)) {
            // Create transaction record
            $transaction = array(
                    'post_title' => $firstname . '-' . $lastname . '-' . $data['Amount'],
                    'post_content' => $data['Comment'],
                    'post_status' => 'publish',
                    'post_author' => $author_id,
                    'post_type' => 'transaction',
                    'post_date' => $transaction_date,
            );

            // Insert the post into the database
            $transaction_id = wp_insert_post($transaction);

            $fund_code = get_term_by('name', $data['Source'], 'fundcode');
            if (!$fund_code) {
                return 'Invalid Fund Code: '.$data['Source'];
            }

            update_post_meta($transaction_id, 'frequency', 'one-off');
            update_post_meta($transaction_id, 'donation_amount', get_post_meta($fund_code->slug, 'transaction_type', true) == 'donation' ? $data['Amount'] : 0);
            update_post_meta($transaction_id, 'total_amount', $data['Amount']);
            update_post_meta($transaction_id, 'is_tax_deductible', '0');
            update_post_meta($transaction_id, 'batch_id', $batch_id);
            update_post_meta($transaction_id, 'raw', $data);

            $line_item = array(
                    'post_title' => 'EzeScan Import',
                    'post_status' => 'publish',
                    'post_author' => $author_id,
                    'post_type' => 'transactionlineitem',
                    'post_date' => $transaction['post_date'],
            );
            $line_item_id = wp_insert_post($line_item);
            update_post_meta($line_item_id, 'transaction_id', $transaction_id);
            update_post_meta($line_item_id, 'price', $data['Amount']);
            update_post_meta($line_item_id, 'quantity', 1);

            $transaction_term = get_term_by('slug', $transaction_id, 'transaction');
            wp_set_post_terms($line_item_id, $transaction_term->term_id, 'transaction');
            wp_set_post_terms($line_item_id, $fund_code->term_id, 'fundcode');

            do_action('bb_cart_post_import', $user, $data['Amount'], $transaction_id);
            return true;
        }
        return 'Matching transaction found';
    }

    public function __call($method, $args) {
        return 'Invalid file format.';
    }
}

$bb_cart_import = new bb_cart_import();
