<?php
class BbCartUpdates {
    private $slug;
    private $pluginData;
    private $username;
    private $repo;
    private $pluginFile;
    private $githubAPIResult;
    private $accessToken;
    private $pluginActivated;

    /**
     * Class constructor.
     * @param string $pluginFile
     * @param string $gitHubUsername
     * @param string $gitHubProjectName
     * @param string $accessToken
     * @return null
     */
    function __construct($pluginFile, $gitHubUsername, $gitHubProjectName, $accessToken = '') {
        add_filter("pre_set_site_transient_update_plugins", array($this, "setTransient"));
        add_filter("plugins_api", array($this, "setPluginInfo"), 10, 3);
        add_filter("upgrader_pre_install", array($this, "preInstall"), 10, 3);
        add_filter("upgrader_post_install", array($this, "postInstall"), 10, 3);

        $this->pluginFile = $pluginFile;
        $this->username = $gitHubUsername;
        $this->repo = $gitHubProjectName;
        $this->accessToken = $accessToken;
    }

    /**
     * Get information regarding our plugin from WordPress
     * @return null
     */
    private function initPluginData() {
        $this->slug = plugin_basename($this->pluginFile);

        $this->pluginData = get_plugin_data($this->pluginFile);
    }

    /**
     * Get information regarding our plugin from GitHub
     * @return null
     */
    private function getRepoReleaseInfo() {
        if (!empty($this->githubAPIResult)) {
            return;
        }

        // Query the GitHub API
        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases";

        if (!empty($this->accessToken)) {
            $url = add_query_arg(array("access_token" => $this->accessToken), $url);
        }

        // Get the results
        $this->githubAPIResult = wp_remote_retrieve_body(wp_remote_get($url));

        if (!empty($this->githubAPIResult)) {
            $this->githubAPIResult = @json_decode($this->githubAPIResult);
        }

        // Use only the latest release
        if (is_array($this->githubAPIResult)) {
            $this->githubAPIResult = $this->githubAPIResult[0];
        }
    }

    /**
     * Push in plugin version information to get the update notification
     * @param object $transient
     * @return object
     */
    public function setTransient($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get plugin & GitHub release information
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        $doUpdate = version_compare($this->githubAPIResult->tag_name, $transient->checked[$this->slug], '>');

        if ($doUpdate) {
            $package = $this->githubAPIResult->zipball_url;

            if (!empty($this->accessToken)) {
                $package = add_query_arg(array("access_token" => $this->accessToken), $package);
            }

            // Plugin object
            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $this->githubAPIResult->tag_name;
            $obj->url = $this->pluginData["PluginURI"];
            $obj->package = $package;

            $transient->response[$this->slug] = $obj;
        }

        return $transient;
    }

    /**
     * Push in plugin version information to display in the details lightbox
     * @param boolean $false
     * @param string $action
     * @param object $response
     * @return object
     */
    public function setPluginInfo($false, $action, $response) {
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        if (empty($response->slug) || $response->slug != $this->slug) {
            return $false;
        }

        // Add our plugin information
        $response->last_updated = $this->githubAPIResult->created_at;
        $response->slug = $this->slug;
        $response->plugin_name = $this->pluginData["Name"];
        $response->version = $this->githubAPIResult->tag_name;
        $response->author = $this->pluginData["AuthorName"];
        $response->homepage = $this->pluginData["PluginURI"];

        // This is our release download zip file
        $downloadLink = $this->githubAPIResult->zipball_url;

        if (!empty($this->accessToken)) {
            $downloadLink = add_query_arg(array("access_token" => $this->accessToken), $downloadLink);
        }

        $response->download_link = $downloadLink;

        // Load Parsedown
//         require_once __DIR__.DIRECTORY_SEPARATOR.'Parsedown.php';

        // Create tabs in the lightbox
        $response->sections = array(
                'Description' => $this->pluginData["Description"],
                'Changelog' => class_exists("Parsedown") ? Parsedown::instance()->parse($this->githubAPIResult->body) : nl2br($this->githubAPIResult->body),
        );

        // Gets the required version of WP if available
        $matches = null;
        preg_match("/requires:\s([\d\.]+)/i", $this->githubAPIResult->body, $matches);
        if (!empty($matches)) {
            if (is_array($matches)) {
                if (count($matches) > 1) {
                    $response->requires = $matches[1];
                }
            }
        }

        // Gets the tested version of WP if available
        $matches = null;
        preg_match("/tested:\s([\d\.]+)/i", $this->githubAPIResult->body, $matches);
        if (!empty($matches)) {
            if (is_array($matches)) {
                if (count($matches) > 1) {
                    $response->tested = $matches[1];
                }
            }
        }

        return $response;
    }

    /**
     * Perform check before installation starts.
     * @param boolean $true
     * @param array $args
     * @return null
     */
    public function preInstall($true, $args) {
        // Get plugin information
        $this->initPluginData();

        // Check if the plugin was installed before...
        $this->pluginActivated = is_plugin_active($this->slug);
    }

    /**
     * Perform additional actions to successfully install our plugin
     * @param boolean $true
     * @param string $hook_extra
     * @param object $result
     * @return object
     */
    public function postInstall($true, $hook_extra, $result) {
        global $wp_filesystem;

        // Since we are hosted in GitHub, our plugin folder would have a dirname of
        // reponame-tagname change it to our original one:
        $pluginFolder = WP_PLUGIN_DIR.DIRECTORY_SEPARATOR.dirname($this->slug);
        $wp_filesystem->move($result['destination'], $pluginFolder);
        $result['destination'] = $pluginFolder;

        // Re-activate plugin if needed
        if ($this->pluginActivated) {
            $activate = activate_plugin($this->slug);
        }

        return $result;
    }
}

add_action('plugins_loaded', 'bb_cart_db_updates');
function bb_cart_db_updates() {
    // Get current version
    $dbv = get_option('bb_cart_db_version', 0);

    // If it's not the latest, run our updates
    if (version_compare($dbv, BB_CART_VERSION, '<')) {
        // List of versions that involved a DB update - each one must have a corresponding function below
        $db_versions = array(
                '3.8.16',
        );

        foreach ($db_versions as $version) {
            if (version_compare($version, $dbv, '>')) {
                call_user_func('bb_cart_db_update_'.str_replace('.', '_', $version));
                update_option('bb_cart_db_version', $version);
            }
        }
        update_option('bb_cart_db_version', BB_CART_VERSION);
    }
}

function bb_cart_db_update_3_8_16() {
	if (defined('BBCONNECT_VER')) {
		// Special Fund Code search field
		$field = array(
				array('source' => 'bbconnect', 'meta_key' => 'fund_code', 'tag' => '', 'name' => __('Fund Code', 'bbconnect'), 'options' => array('admin' => false, 'user' => false, 'signup' => false, 'reports' => true, 'public' => false, 'req' => false, 'field_type' => 'select', 'choices' => 'bb_cart_bbconnect_helper_fund_code'), 'help' => false, 'column' => 'section_account_information', 'section' => 'account_information'),
		);
		$field_keys = array();

		foreach ($field as $key => $value) {
			if (false != get_option('bbconnect_'.$value['meta_key'])) {
				continue;
			}

			$field_keys[] = $value['meta_key'];
			add_option('bbconnect_'.$value['meta_key'], $value);
		}

		$umo = get_option('_bbconnect_user_meta');
		if (!empty($field_keys)) {
			foreach ($umo as $uk => $uv) {
				// Add to the account info section
				foreach ($uv as $suk => $suv) {
					if ('bbconnect_account_information' == $suv) {
						$acct = get_option($suv);
						foreach ($field_keys as $fk => $fv) {
							$acct['options']['choices'][] = $fv;
						}
						update_option($suv, $acct);
						$aok = true;
					}
				}
			}
			// If we couldn't find the account info section just add to column 3
			if (!isset($aok)) {
				foreach ($field_keys as $fk => $fv) {
					$umo['column_3'][] = 'bbconnect_' . $fv;
				}

				update_option('_bbconnect_user_meta', $umo);
			}
		}
	}
}
