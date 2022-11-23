<?php
/**
 * @deprecated Use wp_timezone_string() instead
 *
 * Returns the timezone string for a site, even if it's set to a UTC offset
 * Taken from https://www.skyverge.com/blog/down-the-rabbit-hole-wordpress-and-timezones/
 * Adapted from http://www.php.net/manual/en/function.timezone-name-from-abbr.php#89155
 * @return string valid PHP timezone string
 */
function bb_cart_get_timezone_string() {
    return wp_timezone_string();
}

/**
 * @deprecated Use wp_timezone() instead
 *
 * Get DateTimeZone object for current site
 * @param string $timezone_str
 * @return DateTimeZone
 */
function bb_cart_get_timezone($timezone_str = '') {
	return wp_timezone();
}

function bb_cart_get_current_datetime(DateTimeZone $timezone = null) {
    if (is_null($timezone)) {
        $timezone = bb_cart_get_timezone();
    }
    return new DateTime('now', $timezone);
}

function bb_cart_get_datetime($datetime = '', DateTimeZone $timezone = null) {
    if (empty($datetime)) {
        return bb_cart_get_current_datetime($timezone);
    }

    if (is_int($datetime)) {
        $datetime = '@'.$datetime;
    }

    if (is_null($timezone)) {
        $timezone = bb_cart_get_timezone();
    }
    return new DateTime($datetime, $timezone);
}
