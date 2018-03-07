<?php
namespace Brownbox\Config;
/**
 * Class for configuring BB Cart donation and checkout forms. Place this in your theme or plugin and customise the below values as required.
 */
class BB_Cart {
    /**
     * Donate form setup. Include the options you wish to enable.
     * @var array
     */
    public static $form_setup_choices = array(
//             'simple giving',
            'supporting message',
            'peer to peer giving',
//             'donation target',
    );

    /**
     * Checkout form setup. Include the options you wish to enable.
     * @var array
     */
    public static $checkout_form_setup_choices = array(
            'address',
            'phone',
            'company',
    );

    /**
     * Available "donation for" options
     * @var array
     */
    public static $donation_for_choices = array(
            'default' => 'Give to where it is needed most',
            'sponsorship' => 'Sponsor a member',
            'campaign' => 'Support a project/campaign/appeal',
    );

    /**
     * If you offer donations to projects, list the post types here
     * @var array
     */
    public static $project = array(
            'stories',
    );

    /**
     * If you offer donations to members, specify the details here.
     * Members can be post types or user roles or a combination of both.
     * @var array
     */
    public static $member = array(
            'post_type' => 'staff',
            'user' => 'member',
    );

    /**
     * Donation interval options. Keys must match PayDock intervals.
     * @var array
     */
    public static $intervals = array(
            'one-off' => 'Give Once',
            'day' => 'Give Daily',
            'week' => 'Give Weekly',
            'month' => 'Give Monthly',
            'year' => 'Give Annually',
    );

    /**
     * Click array options. Text is impact statement, value is amount
     * @var array
     */
    public static $donation_amounts = array(
            array(
                    'text' => 'Impact statement 1',
                    'value' => 20,
                    'isSelected' => false,
            ),
            array(
                    'text' => 'Impact statement 2',
                    'value' => 50,
                    'isSelected' => true,
            ),
            array(
                    'text' => 'Impact statement 3',
                    'value' => 100,
                    'isSelected' => false,
            ),
            array(
                    'text' => 'Impact statement 4',
                    'value' => 250,
                    'isSelected' => false,
            ),
            array(
                    'text' => 'Impact statement 5',
                    'value' => 500,
                    'isSelected' => false,
            ),
            array(
                    'text' => 'Impact statement 6',
                    'value' => 1000,
                    'isSelected' => false,
            ),
    );

    /**
     * Whether to allow the user to enter their own amount
     * @var boolean
     */
    public static $donation_amount_enable_other = true;

    /**
     * Label for "other" amount field. Ignored if self::$donation_amounts_enable_other is false
     * @var string
     */
    public static $donation_amount_other_label = 'Or enter your preferred amount';

    /**
     * URL for checkout page
     * @var string
     */
    public static $donate_form_confirmation_url = '/payment/';

    /**
     * URL for thankyou page
     * @var string
     */
    public static $checkout_form_confirmation_url = '/thankyou/';

    /**
     * Available payment methods
     * @var array
     */
    public static $payment_methods = array(
            0 => array(
                    'text' => 'Credit Card',
                    'value' => 'Credit Card',
            ),
            1 => array(
                    'text' => 'Direct Debit',
                    'value' => 'Direct Debit',
            ),
            2 => array(
                    'text' => 'PayPal',
                    'value' => 'PayPal',
            ),
    );
}
