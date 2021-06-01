<?php
class bb_cart_import {
	private $upload_dir;
	private $option_name = 'bb_cart_import_stats';

	const STATUS_WAITING = 1; // Uploaded but not yet processed
	const STATUS_IN_PROGRESS = 2; // Partially processed
	const STATUS_PROCESSING = 3; // Actively being processed
	const STATUS_PENDING = 4; // Processed but not yet reported to the user
	const STATUS_COMPLETE = 5; // All done!

    public function __construct() {
        // Make sure our upload directory exists
        $wp_uploads = wp_get_upload_dir();
        $this->upload_dir = trailingslashit($wp_uploads['basedir']).'bb-cart/';
        if (!is_dir($this->upload_dir)) {
        	wp_mkdir_p($this->upload_dir);
        }

        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'));
        }

        if ($this->is_processing_file() && (!wp_doing_ajax() || $_REQUEST['action'] != 'bb_cart_import_do_import')) {
        	add_action('shutdown', array($this, 'process_file'));
        }

        add_action('wp_ajax_bb_cart_import_do_import', array($this, 'ajax_do_import'));
        add_action('wp_ajax_nopriv_bb_cart_import_do_import', array($this, 'ajax_do_import'));
        add_action('wp_ajax_bb_cart_import_get_current_progress', array($this, 'ajax_get_current_progress'));
    }

    public function add_plugin_page() {
        add_submenu_page('bb_cart_settings', 'Import Transactions', 'Import Transactions', 'add_users', 'bb_cart_import', array($this, 'create_admin_page'));
    }

    public function create_admin_page() {
?>
<div class="wrap">
    <h2>Import CSV Data</h2>
<?php
        if ($this->is_processing_file()) {
            $this->progress_page();
        } elseif ($this->is_pending_file()) {
            $this->result_page();
        } else {
            $this->upload_page();
        }
?>
</div>
<?php
    }

    private function upload_page() {
    	if (!empty($_FILES['uploadedfile']['tmp_name'])) {
    		$filename = $_FILES['uploadedfile']['name'];
    		$unique_filename = wp_unique_filename($this->upload_dir, $filename);
    		$file_path = $this->upload_dir.$unique_filename;
    		if (move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $file_path)) {
    			$data = $this->csv_to_array($file_path);
    			$record_count = count($data);
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

    			$this->add_file($filename, $file_path, $record_count, $batch_id, $_POST['data_type']);
    			echo '<p><strong>'.$filename.'</strong> containing '.$record_count.' records uploaded successfully. Processing of this file will begin momentarily.</p>'."\n";
    			echo '<script>window.setTimeout("window.location.reload()", 5000);</script>'."\n";
    		} else {
    			echo '<p>An error occured while attempting to save the uploaded file.</p>'."\n";
    			echo '<p><a href="">Try again?</a></p>';
    		}
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
<?php
        }
    }

    private function progress_page() {
    	$details = $this->get_current_progress();
    	$success_count = $details['result']['success_count'];
    	$fail_count = $details['result']['fail_count'];
    	$processed_count = $success_count+$fail_count;
    	$total_count = $details['result']['total_count'];
    	$fraction = $processed_count/$total_count;
    	$percent = floor($fraction*100);
    	?>
        <p>Processing <strong><?php echo $details['filename']; ?></strong> uploaded on <?php echo $details['added']; ?></p>
        <style>
        .progress {
            width: 100%;
            height: 50px;
        }
        .progress-wrap {
            background: #25AAE1;
            margin: 20px 0;
            overflow: hidden;
            position: relative;
        }
        .progress-bar {
            background: #ddd;
            left: <?php echo $percent; ?>%;
            position: absolute;
            top: 0;
        }
        .progress-text {
            text-align: center;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 5;
        }
        </style>
        <div class="progress-wrap progress">
            <div class="progress-bar progress"></div>
            <p class="progress-text progress"><span class="percent"><?php echo $percent; ?></span>% complete</p>
        </div>
        <p style="text-align: center;" id="progress_message"><span class="processed"><?php echo $processed_count; ?></span> of <span class="total"><?php echo $total_count; ?></span> records processed.</p>
        <p><strong>The import system has been designed to continue processing in the background even if you navigate away from this page. However we have found that on some systems this does not work - if that is the case just leave this page open and the import process will run as expected.</strong></p>
        <script>
            jQuery(document).ready(function() {
                window.setTimeout('bb_cart_import_get_progress()', 1000);
                window.setTimeout('bb_cart_import_do_import()', 1000);
            });
            function bb_cart_import_get_progress() {
                var data = {
                        action: 'bb_cart_import_get_current_progress'
                }
                jQuery.post(ajaxurl, data, function(response) {
                    if (typeof response.result != 'undefined') {
                        // Grab the relevant values
                        var total = response.result.total_count;
                        var success = response.result.success_count;
                        var fail = response.result.fail_count;
                        var processed = success+fail;
                        var fraction = processed/total;
                        var percent = Math.floor(fraction*100);

                        // Update the display
                        var elem_processed = jQuery('.processed');
                        jQuery({Counter: elem_processed.text()}).animate({Counter: processed}, {
                            duration: 500,
                            easing: 'swing',
                            step: function () {
                                elem_processed.text(Math.ceil(this.Counter));
                            }
                        });
                        var elem_percent = jQuery('.percent');
                        jQuery({Counter: elem_percent.text()}).animate({Counter: percent}, {
                            duration: 500,
                            easing: 'swing',
                            step: function () {
                                elem_percent.text(Math.ceil(this.Counter));
                            }
                        });
                        jQuery('.progress-bar').animate({left: percent+'%'}, 500);
                        if (processed >= total) {
                            jQuery('#progress_message').text('Import complete. Please wait a moment...');
                            window.location.reload();
                        }
                    }
                    window.setTimeout('bb_cart_import_get_progress()', 1000);
                });
            }
            function bb_cart_import_do_import() {
                data = {
                        action: 'bb_cart_import_do_import'
                }
                jQuery.post(ajaxurl, data).always(function () {
                    window.setTimeout('bb_cart_import_do_import()', 10000);
                });
            }
        </script>
<?php
    }

    private function result_page() {
        $details = $this->get_current_progress();
        $success_count = $details['result']['success_count'];
        $fail_count = $details['result']['fail_count'];
        $total_count = $details['result']['total_count'];
        $errors = $details['result']['errors'];
?>
        <p><strong><?php echo $details['filename']; ?></strong> uploaded on <?php echo $details['added']; ?> has been imported.</p>
        <ul>
            <li>Total Records: <strong><?php echo $total_count; ?></strong></li>
            <li>Successully Imported: <strong><?php echo $success_count; ?></strong></li>
            <li>Not Imported: <strong><?php echo $fail_count; ?></strong></li>
        </ul>
<?php
        if (!empty($errors)) {
            echo '<h3>Errors</h3>'."\n";
            echo '<ul>'."\n";
            foreach ($errors as $error) {
                echo '<li>'.$error.'</li>'."\n";
            }
            echo '</ul>'."\n";
        }

        echo '<p><a href="" class="button">Continue</a>'."\n";

        $details['status'] = self::STATUS_COMPLETE;
        $this->update_progress($details);
    }

    private function add_file($filename, $file_path, $count, $batch_id, $data_type) {
        $details = array(
                'filename' => $filename,
                'path' => $file_path,
        		'batch_id' => $batch_id,
        		'data_type' => $data_type,
                'added' => current_time('mysql'),
                'updated' => current_time('mysql'),
                'status' => self::STATUS_WAITING,
                'pos' => 0,
                'result' => array(
                        'total_count' => $count,
                        'success_count' => 0,
                        'fail_count' => 0,
                        'errors' => array(),
                ),
        );
        $files = get_option($this->option_name);
        $files[] = $details;
        update_option($this->option_name, $files);
    }

    public function process_file() {
        if ($this->is_processing_file()) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, admin_url('admin-ajax.php?action=bb_cart_import_do_import'));
            curl_setopt($ch, CURLOPT_REFERER, admin_url('admin.php?page=bb_cart_import'));
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 10);
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Requested-With: XMLHttpRequest', 'Content-Type: application/json; charset=utf-8'));
            curl_exec($ch);
            curl_close($ch);
        }
    }

    public function ajax_do_import() {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            ob_end_clean();
            ignore_user_abort(true);
            if (wp_doing_ajax()) {
                header("Connection: close\r\n");
                header("Content-Encoding: none\r\n");
                header("Content-Length: 0");
            }
            flush();
            if (session_id()) {
                session_write_close();
            }
        }
        if (false !== ($details = $this->get_current_progress())) {
            if ($details['status'] == self::STATUS_IN_PROGRESS || ($details['status'] == self::STATUS_PROCESSING && current_time('timestamp')-strtotime($details['updated']) > MINUTE_IN_SECONDS)) { // If not actively processing or it hasn't been updated in over a minute
                $details['status'] = self::STATUS_PROCESSING;
                $this->update_progress($details);
                $data = $this->csv_to_array($details['path']);

                $n = 0;
                while (array_key_exists($details['pos'], $data) && $n < 100) {
                    $d = $data[$details['pos']];
                    $e = $this->{'import_'.$details['data_type']}($d, $details['batch_id']); // The magic happens here [TM]
                    if (!empty($e)) {
                        array_push($details['result']['errors'], $e);
                        $details['result']['fail_count']++;
                    } else {
                        $details['result']['success_count']++;
                    }
                    $details['pos']++;
                    $n++;
                    $this->update_progress($details);
                }

                if ($details['result']['success_count']+$details['result']['fail_count'] >= $details['result']['total_count']) {
                    $details['status'] = self::STATUS_PENDING;
                } else {
                    $details['status'] = self::STATUS_IN_PROGRESS;
                }
                $this->update_progress($details);
                if (wp_doing_ajax()) {
                    die($n.' records processed');
                }
            }
        }
        if (wp_doing_ajax()) {
            die('Nothing to do');
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
            if (!empty($data['External ID'])) {
                $email .= $data['External ID'];
            } else {
                $email .= wp_generate_password(6, false);
            }
            $email .= '@example.com';
            $data['Email'] = strtolower($email);
        }

        // Attempt to locate user
        if (!empty($data['ID'])) {
            $user = get_user_by('id', $data['ID']);
        }
        if (!$user instanceof WP_User) {
            $user = get_user_by('email', $data['Email']);
        }
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
                        'value' => wp_filter_kses($data['Name']),
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
                        'value' => wp_filter_kses($data['Organization']),
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

        $transaction_date = false;
        $transaction_timestamp = strtotime($data['Payment Date']);
        if ($transaction_timestamp !== false) {
            $transaction_date = date('Y-m-d', $transaction_timestamp);
        }
        if ($transaction_date === false) {
            return 'Error processing transaction: Invalid/unrecognised date. Recommended date format is yyyy-mm-dd.';
        }

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
            $transaction_id = wp_insert_post($transaction, true);
            if (is_wp_error($transaction_id)) {
                return 'Error adding transaction: '.$transaction_id->get_error_message();
            }

            $fund_code = get_term_by('name', $data['Source'], 'fundcode');
            if (!$fund_code) {
                return 'Invalid Fund Code: '.$data['Source'];
            }

            $fund_code_deductible = get_post_meta($fund_code->slug, 'deductible', true);
            $deductible = $fund_code_deductible == 'true';
            update_post_meta($transaction_id, 'frequency', 'one-off');
            update_post_meta($transaction_id, 'donation_amount', get_post_meta($fund_code->slug, 'transaction_type', true) == 'donation' ? $data['Amount'] : 0);
            update_post_meta($transaction_id, 'total_amount', $data['Amount']);
            update_post_meta($transaction_id, 'is_tax_deductible', var_export($deductible, true));
            update_post_meta($transaction_id, 'batch_id', $batch_id);
            update_post_meta($transaction_id, 'raw', $data);
            update_post_meta($transaction_id, 'transaction_type', 'offline');

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
            return ''; // No error
        }
        return $data['Amount'].' for '.$data['Source'].' on '.$transaction_date.' from '.$user->user_email.': Matching transaction found';
    }

    public function __call($method, $args) {
        return 'Invalid file format.';
    }

    private function get_current_progress() {
    	$history = get_option($this->option_name);
    	if (is_array($history)) {
    		foreach ($history as &$details) {
    			if (in_array($details['status'], array(self::STATUS_WAITING, self::STATUS_IN_PROGRESS, self::STATUS_PROCESSING, self::STATUS_PENDING))) {
    				if ($details['status'] == self::STATUS_WAITING) {
    					$details['status'] = self::STATUS_IN_PROGRESS;
    					update_option($this->option_name, $history);
    				}
    				return $details;
    			}
    		}
    	}
    	return false;
    }

    public function ajax_get_current_progress() {
    	header('Content-type: application/json');
    	echo json_encode($this->get_current_progress());
    	die();
    }

    private function update_progress($details) {
    	$history = get_option($this->option_name);
    	if (is_array($history)) {
    		foreach ($history as $idx => $history_details) {
    			if ($details['path'] == $history_details['path']) {
    				$details['updated'] = current_time('mysql');
    				$history[$idx] = $details;
    				update_option($this->option_name, $history);
    			}
    		}
    	}
    }

    private function is_processing_file() {
    	$history = get_option($this->option_name);
    	if (is_array($history)) {
    		foreach ($history as $details) {
    			if (in_array($details['status'], array(self::STATUS_WAITING, self::STATUS_IN_PROGRESS, self::STATUS_PROCESSING))) {
    				return true;
    			}
    		}
    	}
    	return false;
    }

    private function is_pending_file() {
    	$history = get_option($this->option_name);
    	if (is_array($history)) {
    		foreach ($history as $details) {
    			if ($details['status'] == self::STATUS_PENDING) {
    				return true;
    			}
    		}
    	}
    	return false;
    }
}

new bb_cart_import();
