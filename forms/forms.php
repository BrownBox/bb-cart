<?php
function bb_cart_get_donate_form() {
    // Sometimes GF doesn't load all the files we need automatically...
    require_once(GFCommon::get_base_path().'/currency.php');

    $donate_form_id = get_option('bb_cart_donate_form_id');
    $donate_form = array(
            'title' => '[BB Cart] Donations',
            'description' => 'Version '.BB_CART_VERSION,
            'is_active' => true,
            'cssClass' => 'bb_cart_donations',
            'enableHoneypot' => true,
            'button' => array(
                    'type' => 'text',
                    'text' => 'Give Now',
                    'imageUrl' => ''
            ),
            'bb_cart_enable' => 'cart_enabled',
            'confirmations' => array(
                    '597697bdb19ee' => array(
                            'id' => '597697bdb19ee',
                            'name' => 'Default Confirmation',
                            'isDefault' => true,
                            'type' => 'redirect',
                            'url' => site_url('/payment/'),
                            'queryString' => 'payment_method={Payment method:17}&gift_type={Gift type:15}',
                    ),
            ),
            'fields' => array( // Next field ID: 25
                    array(
                            'type' => 'checkbox',
                            'id' => 7,
                            'label' => 'Form Setup',
                            'isRequired' => false,
                            'choices' => array(
                                    array(
                                            'text' => 'Simple Giving',
                                            'value' => 'simple giving',
                                            'isSelected' => true,
                                    ),
                                    array(
                                            'text' => 'Peer to Peer Campaign',
                                            'value' => 'peer to peer campaign',
                                            'isSelected' => false,
                                    ),
                                    array(
                                            'text' => 'Supporting Message',
                                            'value' => 'supporting message',
                                            'isSelected' => false,
                                    ),
                                    array(
                                            'text' => 'Donation Target',
                                            'value' => 'donation target',
                                            'isSelected' => false,
                                            'price' => ''
                                    ),
                            ),
                            'inputs' => array(
                                    0 => array(
                                            'id' => '7.1',
                                            'label' => 'Simple Giving',
                                            'name' => '',
                                    ),
                                    1 => array(
                                            'id' => '7.2',
                                            'label' => 'Peer to Peer Campaign',
                                            'name' => '',
                                    ),
                                    2 => array(
                                            'id' => '7.3',
                                            'label' => 'Supporting Message',
                                            'name' => '',
                                    ),
                                    3 => array(
                                            'id' => '7.4',
                                            'label' => 'Donation Target',
                                            'name' => '',
                                    ),
                            ),
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_form_setup',
                            'visibility' => 'hidden',
                    ),
                    array(
                            'type' => 'section',
                            'id' => 1,
                            'label' => 'Gift Details',
                            'isRequired' => false,
                            'visibility' => 'administrative',
                    ),
                    array(
                            'type' => 'radio',
                            'id' => 2,
                            'label' => 'Donation for',
                            'isRequired' => true,
                            'choices' => array(
                                    0 => array(
                                            'text' => 'Give to where it is needed most',
                                            'value' => 'default',
                                            'isSelected' => true,
                                    ),
                                    1 => array(
                                            'text' => 'Sponsor a member',
                                            'value' => 'sponsorship',
                                            'isSelected' => false,
                                    ),
                                    2 => array(
                                            'text' => 'Support a project/campaign/appeal',
                                            'value' => 'campaign',
                                            'isSelected' => false,
                                    ),
                            ),
                            'allowsPrepopulate' => true,
                            'cssClass' => 'tabs',
                            'inputName' => 'bb_cart_donation_for',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '7',
                                                    'operator' => 'isnot',
                                                    'value' => 'peer to peer campaign',
                                            ),
                                            1 => array(
                                                    'fieldId' => '7',
                                                    'operator' => 'isnot',
                                                    'value' => 'simple giving',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'select',
                            'id' => 5,
                            'label' => 'Sponsor a member',
                            'isRequired' => false,
                            'choices' => array(),
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_donation_member',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '2',
                                                    'operator' => 'is',
                                                    'value' => 'sponsorship',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'select',
                            'id' => 6,
                            'label' => 'Support project/campaign/appeal',
                            'isRequired' => false,
                            'choices' => array(),
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_donation_campaign',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '2',
                                                    'operator' => 'is',
                                                    'value' => 'campaign',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'radio',
                            'id' => 3,
                            'label' => 'Gift Frequency',
                            'isRequired' => true,
                            'choices' => array(
                                    0 => array(
                                            'text' => 'once only',
                                            'value' => 'one-off',
                                            'isSelected' => true,
                                    ),
                                    1 => array(
                                            'text' => 'monthly',
                                            'value' => 'month',
                                            'isSelected' => false,
                                    ),
                            ),
                            'description' => '',
                            'allowsPrepopulate' => true,
                            'cssClass' => 'frequency horizontal',
                            'inputName' => 'bb_cart_interval',
                    ),
                    array(
                            'type' => 'text',
                            'id' => 23,
                            'label' => 'My Donation is for',
                            'description' => 'Leave blank to let us decide',
                            'placeholder' => 'program, volunteer',
                            'cssClass' => 'donation_for',
                            'allowsPrepopulate' => true,
                            'inputName' => 'donation_target',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '7',
                                                    'operator' => 'is',
                                                    'value' => 'donation target'
                                            )
                                    )
                            ),
                    ),
                    array(
                            'type' => 'select',
                            'id' => 24,
                            'label' => 'Currency',
                            'isRequired' => false,
                            'choices' => array(
                                    0 => array(
                                            'text' => RGCurrency::get_currency(bb_cart_get_default_currency())['name'],
                                            'value' => bb_cart_get_default_currency(),
                                            'isSelected' => true,
                                    ),
                            ),
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_currency',
                    ),
                    array(
                            'id' => 4,
                            'label' => 'My donation',
                            'type' => 'bb_click_array',
                            'isRequired' => true,
                            'choices' => array(
                                    0 => array(
                                            'text' => '',
                                            'value' => '$35',
                                            'isSelected' => false,
                                    ),
                                    1 => array(
                                            'text' => '',
                                            'value' => '$50',
                                            'isSelected' => true,
                                    ),
                                    2 => array(
                                            'text' => '',
                                            'value' => '$100',
                                            'isSelected' => false,
                                    ),
                                    3 => array(
                                            'text' => '',
                                            'value' => '$250',
                                            'isSelected' => false,
                                    ),
                                    4 => array(
                                            'text' => '',
                                            'value' => '$500',
                                            'isSelected' => false,
                                    ),
                                    5 => array(
                                            'text' => '',
                                            'value' => '$1000',
                                            'isSelected' => false,
                                    ),
                            ),
                            'inputs' => array(
                                    0 => array(
                                            'id' => '4.1',
                                            'label' => 'Value',
                                            'name' => '',
                                    ),
                                    1 => array(
                                            'id' => '4.5',
                                            'label' => 'Clicked',
                                            'name' => '',
                                    )
                            ),
                            'inputName' => 'bb_donation_amounts',
                            'enableOtherChoice' => true,
                            'field_bb_click_array_other_label' => 'My Best Gift',
                            'field_bb_click_array_is_product' => true,
                    ),
                    array(
                            'type' => 'textarea',
                            'id' => 8,
                            'label' => 'Supporting message',
                            'adminLabel' => '',
                            'isRequired' => false,
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '7',
                                                    'operator' => 'is',
                                                    'value' => 'supporting message',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'checkbox',
                            'id' => 19,
                            'label' => '',
                            'adminLabel' => '',
                            'isRequired' => false,
                            'choices' => array(
                                    0 => array(
                                            'text' => 'I\'d like to appear anonymous',
                                            'value' => 'I\'d like to appear anonymous',
                                            'isSelected' => false,
                                    ),
                            ),
                            'inputs' => array(
                                    0 => array(
                                            'id' => '19.1',
                                            'label' => 'I\'d like to appear anonymous',
                                    ),
                            ),
                            'description' => 'Note: your details are always recorded for use internally but will not be displayed publically without your permission. Please see our privacy policy for more details.',
                            'cssClass' => 'anonymous',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '7',
                                                    'operator' => 'is',
                                                    'value' => 'supporting message',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'section',
                            'id' => 9,
                            'label' => 'Admin use only',
                            'isRequired' => false,
                            'visibility' => 'administrative',
                    ),
                    array(
                            'type' => 'text',
                            'id' => 10,
                            'label' => 'Fund code',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_fund_code',
                            'visibility' => 'administrative',
                    ),
                    array(
                            'type' => 'text',
                            'id' => 13,
                            'label' => 'Gift label',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_custom_item_label',
                            'visibility' => 'administrative',
                    ),
                    array(
                            'type' => 'text',
                            'id' => 14,
                            'label' => 'Page ID',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_page_id',
                            'visibility' => 'administrative',
                    ),
                    array(
                            'type' => 'email',
                            'id' => 12,
                            'label' => 'Notification email',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_notification_email',
                            'visibility' => 'administrative',
                    ),
                    array(
                            'type' => 'radio',
                            'id' => 15,
                            'label' => 'Gift type',
                            'isRequired' => false,
                            'inputs' => NULL,
                            'choices' => array(
                                    0 => array(
                                            'text' => 'Everyday Giving',
                                            'value' => 'default',
                                            'isSelected' => false,
                                    ),
                                    1 => array(
                                            'text' => 'Campaign',
                                            'value' => 'campaign',
                                            'isSelected' => false,
                                    ),
                                    2 => array(
                                            'text' => 'Sponsorship',
                                            'value' => 'sponsorship',
                                            'isSelected' => false,
                                    ),
                                    3 => array(
                                            'text' => 'P2P',
                                            'value' => 'peer to peer campaign',
                                            'isSelected' => false,
                                    )
                            ),
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_gift_type',
                            'visibility' => 'administrative',
                    ),
                    array(
                            'type' => 'text',
                            'id' => 20,
                            'label' => 'Tax Deductible',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_tax_deductible',
                            'visibility' => 'administrative',
                    ),
                    array(
                            'type' => 'text',
                            'id' => 21,
                            'label' => 'Member/Campaign ID',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_campaign_id',
                            'visibility' => 'administrative',
                    ),
                    array(
                            'type' => 'section',
                            'id' => 16,
                            'label' => 'Payment Details',
                            'isRequired' => false,
                            'visibility' => 'administrative',
                    ),
                    array(
                            'type' => 'radio',
                            'id' => 17,
                            'label' => 'Payment method',
                            'isRequired' => false,
                            'choices' => array(
                                    0 => array(
                                            'text' => 'Credit Card',
                                            'value' => 'Credit Card',
                                            'isSelected' => false,
                                    ),
                                    1 => array(
                                            'text' => 'Direct Debit',
                                            'value' => 'Direct Debit',
                                            'isSelected' => false,
                                    ),
                                    2 => array(
                                            'text' => 'PayPal',
                                            'value' => 'PayPal',
                                            'isSelected' => false,
                                    )
                            ),
                            'allowsPrepopulate' => true,
                            'cssClass' => 'horizontal hide',
                            'inputName' => 'bb_cart_payment_method',
                    ),
            ),
    );

    if (!$donate_form_id || !GFAPI::form_id_exists($donate_form_id)) { // If form doesn't exist, create it
        $donate_form_id = GFAPI::add_form($donate_form);
        if (is_int($donate_form_id)) {
        	update_option('bb_cart_donate_form_id', $donate_form_id);
        }
    } else { // Otherwise if we've created it previously, just update it to make sure it hasn't been modified and is the latest version
    	$donate_form['id'] = $donate_form_id;
    	$donate_form = wp_parse_args($donate_form, GFAPI::get_form($donate_form_id)); // Make sure we don't lose additional third-party settings etc
        GFAPI::update_form($donate_form);
    	GFAPI::update_form_property($donate_form_id, 'is_trash', '0'); // Make sure it's not in the trash
    }

    return $donate_form_id;
}

function bb_cart_get_checkout_form(){
    $checkout_form_id = get_option('bb_cart_checkout_form_id');
    $checkout_form = array(
            'title' => '[BB Cart] Checkout',
            'description' => 'Version '.BB_CART_VERSION,
            'labelPlacement' => 'top_label',
            'descriptionPlacement' => 'below',
            'subLabelPlacement' => 'above',
            'is_active' => true,
            'cssClass' => 'bb_cart_checkout bb-processing',
            'enableHoneypot' => true,
            'button' =>  array(
                    'type' => 'text',
                    'text' => 'Submit',
            ),
            'confirmations' => array(
                    '56ce55c57ef2e' => array(
                            'id' => '56ce55c57ef2e',
                            'name' => 'Default Confirmation',
                            'isDefault' => true,
                            'type' => 'redirect',
                            'url' => site_url('/thankyou/'),
                            'queryString' => 'n={Name (First):1.3}&e={entry_id}&f={form_id}',
                    ),
            ),
            'fields' => array( // Next field ID: 53
                    array(
                            'type' => 'checkbox',
                            'id' => 28,
                            'label' => 'Checkout Form Setup',
                            'isRequired' => false,
                            'choices' => array(
                                    array(
                                            'text' => 'Show Shipping Address',
                                            'value' => 'shipping_address',
                                            'isSelected' => false,
                                    ),
                                    array(
                                            'text' => 'Show Billing Address',
                                            'value' => 'address',
                                            'isSelected' => false,
                                    ),
                                    array(
                                            'text' => 'Show Phone Number',
                                            'value' => 'phone',
                                            'isSelected' => false,
                                    ),
                                    array(
                                            'text' => 'Show Company',
                                            'value' => 'company',
                                            'isSelected' => false,
                                    ),
                                    array(
                                            'text' => 'Show Subscribe',
                                            'value' => 'subscribe',
                                            'isSelected' => false,
                                    ),
                                    array(
                                            'text' => 'Offer Scheduled Payments',
                                            'value' => 'schedule payment',
                                            'isSelected' => false,
                                    ),
                                    array(
                                            'text' => 'Show Anonymous',
                                            'value' => 'anonymous',
                                            'isSelected' => false,
                                    ),
                            ),
                            'inputs' => array(
                                    array(
                                            'id' => '28.1',
                                            'label' => 'Form Setup: Show Shipping Address',
                                    ),
                                    array(
                                            'id' => '28.2',
                                            'label' => 'Form Setup: Show Billing Address',
                                    ),
                                    array(
                                            'id' => '28.3',
                                            'label' => 'Form Setup: Show Phone',
                                    ),
                                    array(
                                            'id' => '28.4',
                                            'label' => 'Form Setup: Show Company',
                                    ),
                                    array(
                                            'id' => '28.5',
                                            'label' => 'Form Setup: Show Subscribe',
                                    ),
                                    array(
                                            'id' => '28.6',
                                            'label' => 'Form Setup: Offer Scheduled Payments',
                                    ),
                                    array(
                                            'id' => '28.7',
                                            'label' => 'Form Setup: Show Anonymous',
                                    ),
                            ),
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_checkout_form_setup',
                            'visibility' => 'hidden',
                    ),
                    array(
                            'type' => 'section',
                            'id' => 6,
                            'label' => 'Payment Details',
                            'isRequired' => false,
                            'cssClass' => 'gform_column',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'radio',
                            'id' => 12,
                            'label' => 'Payment Options',
                            'isRequired' => true,
                            'inputs' => NULL,
                            'choices' => array(
                                    array(
                                            'text' => 'Pay with Credit Card',
                                            'value' => 'Credit Card',
                                            'isSelected' => true,
                                    ),
                                    array(
                                            'text' => 'Pay with Direct Debit',
                                            'value' => 'Direct Debit',
                                            'isSelected' => false,
                                    ),
                                    array(
                                            'text' => 'Pay with PayPal',
                                            'value' => 'PayPal',
                                            'isSelected' => false,
                                    ),
                                    array(
                                            'text' => 'Bank Deposit',
                                            'value' => 'Bank Deposit',
                                            'isSelected' => false,
                                    ),
                            ),
                            'allowsPrepopulate' => true,
                            'cssClass' => 'gf_list_inline payment_options',
                            'inputName' => 'payment_method',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'creditcard',
                            'id' => 4,
                            'label' => 'Credit Card',
                            'isRequired' => true,
                            'inputs' => array(
                                    0 =>  array(
                                            'id' => '4.1',
                                            'label' => 'Card Number',
                                    ),
                                    1 =>  array(
                                            'id' => '4.2_month',
                                            'label' => 'Expiration Month',
                                            'defaultLabel' => 'Expiration Date',
                                    ),
                                    2 =>  array(
                                            'id' => '4.2_year',
                                            'label' => 'Expiration Year',
                                    ),
                                    3 =>  array(
                                            'id' => '4.3',
                                            'label' => 'Security Code',
                                    ),
                                    4 =>  array(
                                            'id' => '4.4',
                                            'label' => 'Card Type',
                                    ),
                                    5 =>  array(
                                            'id' => '4.5',
                                            'label' => 'Cardholder Name',
                                    ),
                            ),
                            'conditionalLogic' =>  array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 =>  array(
                                                    'fieldId' => '12',
                                                    'operator' => 'is',
                                                    'value' => 'Credit Card',
                                            ),
                                    ),
                            ),
                            'creditCards' => array(
                                    0 => 'visa',
                                    1 => 'mastercard',
                                    2 => 'amex',
                            ),
                            'useRichTextEditor' => false,
                            'creditCardFundingTypes' => array(
                                    0 => 'credit',
                                    1 => 'debit',
                                    2 => 'prepaid',
                                    3 => 'unknown',
                            ),
                            'displayAllCurrencies' => false,
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'text',
                            'id' => 13,
                            'label' => 'Account BSB',
                            'isRequired' => true,
                            'conditionalLogic' =>  array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 =>  array(
                                                    'fieldId' => '12',
                                                    'operator' => 'is',
                                                    'value' => 'Direct Debit',
                                            ),
                                    ),
                            ),
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'text',
                            'id' => 14,
                            'label' => 'Account Number',
                            'isRequired' => true,
                            'conditionalLogic' =>  array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 =>  array(
                                                    'fieldId' => '12',
                                                    'operator' => 'is',
                                                    'value' => 'Direct Debit',
                                            ),
                                    ),
                            ),
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'html',
                            'id' => 27,
                            'label' => 'PayPal Blurb',
                            'isRequired' => false,
                            'visibility' => 'visible',
                            'conditionalLogic' =>  array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 =>  array(
                                                    'fieldId' => '12',
                                                    'operator' => 'is',
                                                    'value' => 'PayPal',
                                            ),
                                    ),
                            ),
                            'content' => 'When you click "Submit" you will be directed to PayPal to finalise your donation. Once complete, please follow the link and return to our site so that we know your donation went through successfully and we can thank you. Please note that PayPal is not able to process donations that have both one-off and recurring amounts in the one transaction. In this case please use an alternate payment method.',
                    ),
                    array(
                            'type' => 'text',
                            'id' => 15,
                            'label' => 'Account Name',
                            'isRequired' => true,
                            'conditionalLogic' =>  array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 =>  array(
                                                    'fieldId' => '12',
                                                    'operator' => 'is',
                                                    'value' => 'Direct Debit',
                                            ),
                                    ),
                            ),
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'html',
                            'id' => 25,
                            'label' => 'Secure Seals',
                            'isRequired' => false,
                            'content' => '<img src="'.BB_CART_URL.'assets/images/trusted-site-seal.png" alt="This site is secured by Comodo for your protection">',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'html',
                            'id' => 44,
                            'label' => 'Pledge Instructions',
                            'isRequired' => false,
                            'visibility' => 'visible',
                            'conditionalLogic' =>  array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 =>  array(
                                                    'fieldId' => '12',
                                                    'operator' => 'is',
                                                    'value' => 'Bank Deposit',
                                            ),
                                    ),
                            ),
                            'content' => 'Thank you for your pledge of support. Please complete the form below so that we can send you our bank account details and thank you for your generosity.
Your details will also allow us to give you a personal reference number to include with your payment so that we can make sure your gift is allocated according to your selection.',
                    ),
                    array(
                            'type' => 'checkbox',
                            'id' => 45,
                            'label' => '',
                            'isRequired' => false,
                            'cssClass' => 'schedule_payment',
                            'choices' => array(
                                    0 => array(
                                            'text' => 'Schedule my payment',
                                            'value' => 'schedule_payment',
                                            'isSelected' => false,
                                    ),
                            ),
                            'inputs' => array(
                                    0 => array(
                                            'id' => '45.1',
                                            'label' => 'Schedule my payment',
                                            'name' => '',
                                    ),
                            ),
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            array(
                                                    'fieldId' => '28',
                                                    'operator' => 'is',
                                                    'value' => 'schedule payment',
                                            ),
                                            array(
                                                    'fieldId' => '22',
                                                    'operator' => 'isnot',
                                                    'value' => 'one-off',
                                            ),
                                            array(
                                                    'fieldId' => '12',
                                                    'operator' => 'isnot',
                                                    'value' => 'Bank Deposit',
                                            ),
                                            array(
                                                    'fieldId' => '12',
                                                    'operator' => 'isnot',
                                                    'value' => 'PayPal',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'id' => 46,
                            'type' => 'date',
                            'label' => 'Payment Date',
                            'dateType' => 'datepicker',
                            'calendarIconType' => 'calendar',
                            'dateFormat' => 'dmy',
                            'cssClass' => 'payment_date',
                            'allowsPrepopulate' => true,
                            'inputName' => 'payment_date',
                            'isRequired' => false,
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'any',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '45',
                                                    'operator' => 'is',
                                                    'value' => 'schedule_payment',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'section',
                            'id' => 7,
                            'label' => 'Personal Details',
                            'isRequired' => false,
                            'cssClass' => 'gform_column',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'name',
                            'id' => 1,
                            'label' => 'Name',
                            'isRequired' => true,
                            'nameFormat' => 'advanced',
                            'inputs' => array(
                                    0 =>  array(
                                            'id' => '1.2',
                                            'label' => 'Prefix',
                                            'name' => '',
                                            'choices' => array(
                                                    0 =>  array(
                                                            'text' => 'Mr.',
                                                            'value' => 'Mr.',
                                                            'isSelected' => false,
                                                    ),
                                                    1 =>  array(
                                                            'text' => 'Mrs.',
                                                            'value' => 'Mrs.',
                                                            'isSelected' => false,
                                                    ),
                                                    2 =>  array(
                                                            'text' => 'Miss',
                                                            'value' => 'Miss',
                                                            'isSelected' => false,
                                                    ),
                                                    3 =>  array(
                                                            'text' => 'Ms.',
                                                            'value' => 'Ms.',
                                                            'isSelected' => false,
                                                    ),
                                                    4 =>  array(
                                                            'text' => 'Dr.',
                                                            'value' => 'Dr.',
                                                            'isSelected' => false,
                                                    ),
                                                    5 =>  array(
                                                            'text' => 'Prof.',
                                                            'value' => 'Prof.',
                                                            'isSelected' => false,
                                                    ),
                                                    6 =>  array(
                                                            'text' => 'Rev.',
                                                            'value' => 'Rev.',
                                                            'isSelected' => false,
                                                    ),
                                            ),
                                            'isHidden' => true,
                                            'inputType' => 'radio',
                                    ),
                                    1 =>  array(
                                            'id' => '1.3',
                                            'label' => 'First',
                                            'name' => 'fname',
                                            'customLabel' => 'First',
                                    ),
                                    2 =>  array(
                                            'id' => '1.4',
                                            'label' => 'Middle',
                                            'name' => '',
                                            'isHidden' => true,
                                    ),
                                    3 =>  array(
                                            'id' => '1.6',
                                            'label' => 'Last',
                                            'name' => 'lname',
                                    ),
                                    4 =>  array(
                                            'id' => '1.8',
                                            'label' => 'Suffix',
                                            'name' => '',
                                            'isHidden' => true,
                                    ),
                            ),
                            'allowsPrepopulate' => true,
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'email',
                            'id' => 3,
                            'label' => 'Email',
                            'isRequired' => true,
                            'allowsPrepopulate' => true,
                            'inputName' => 'email',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'text',
                            'id' => 34,
                            'label' => 'Company/Organisation',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'company',
                            'visibility' => 'visible',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '28',
                                                    'operator' => 'is',
                                                    'value' => 'company',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'address',
                            'id' => 50,
                            'label' => 'Shipping Address',
                            'isRequired' => false,
                            'inputs' => array(
                                    0 =>  array(
                                            'id' => '50.1',
                                            'label' => 'Street Address',
                                    ),
                                    1 =>  array(
                                            'id' => '50.2',
                                            'label' => 'Address Line 2',
                                            'isHidden' => true,
                                    ),
                                    2 =>  array(
                                            'id' => '50.3',
                                            'label' => 'City',
                                    ),
                                    3 =>  array(
                                            'id' => '50.4',
                                            'label' => 'State / Province',
                                    ),
                                    4 =>  array(
                                            'id' => '50.5',
                                            'label' => 'ZIP / Postal Code',
                                    ),
                                    5 =>  array(
                                            'id' => '50.6',
                                            'label' => 'Country',
                                            'name' => '',
                                    ),
                            ),
                            'description' => '',
                            'descriptionPlacement' => 'above',
                            'addressType' => 'international',
                            'visibility' => 'visible',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '28',
                                                    'operator' => 'is',
                                                    'value' => 'shipping_address',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'checkbox',
                            'id' => 51,
                            'label' => '',
                            'isRequired' => false,
                            'choices' => array(
                                    array(
                                            'text' => 'Billing address is the same as shipping address',
                                            'value' => 'same_address',
                                            'isSelected' => false,
                                    ),
                            ),
                            'inputs' => array(
                                    array(
                                            'id' => '51.1',
                                            'label' => 'Billing address is the same as shipping address',
                                    ),
                            ),
                            'allowsPrepopulate' => true,
                            'visibility' => 'visible',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            array(
                                                    'fieldId' => '28',
                                                    'operator' => 'is',
                                                    'value' => 'shipping_address',
                                            ),
                                            array(
                                                    'fieldId' => '28',
                                                    'operator' => 'is',
                                                    'value' => 'address',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'address',
                            'id' => 2,
                            'label' => 'Billing Address',
                            'isRequired' => false,
                            'inputs' => array(
                                    0 =>  array(
                                            'id' => '2.1',
                                            'label' => 'Street Address',
                                    ),
                                    1 =>  array(
                                            'id' => '2.2',
                                            'label' => 'Address Line 2',
                                            'isHidden' => true,
                                    ),
                                    2 =>  array(
                                            'id' => '2.3',
                                            'label' => 'City',
                                    ),
                                    3 =>  array(
                                            'id' => '2.4',
                                            'label' => 'State / Province',
                                    ),
                                    4 =>  array(
                                            'id' => '2.5',
                                            'label' => 'ZIP / Postal Code',
                                    ),
                                    5 =>  array(
                                            'id' => '2.6',
                                            'label' => 'Country',
                                            'name' => '',
                                    ),
                            ),
                            'description' => '',
                            'descriptionPlacement' => 'above',
                            'addressType' => 'international',
                            'visibility' => 'visible',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            array(
                                                    'fieldId' => '28',
                                                    'operator' => 'is',
                                                    'value' => 'address',
                                            ),
                                            array(
                                                    'fieldId' => '51',
                                                    'operator' => 'isnot',
                                                    'value' => 'same_address',
                                            ),
                                    ),
                            ),
                    ),
            		array(
            				'type' => 'phone',
            				'id' => 19,
            				'label' => 'Phone Number',
            				'isRequired' => false,
            				'allowsPrepopulate' => true,
            				'cssClass' => 'phone-number',
            				'inputName' => 'phone',
            				'phoneFormat' => 'international',
            				'visibility' => 'visible',
            				'conditionalLogic' => array(
            						'actionType' => 'show',
            						'logicType' => 'all',
            						'rules' => array(
            								0 => array(
            										'fieldId' => '28',
            										'operator' => 'is',
            										'value' => 'phone',
            								),
            						),
            				),
            		),
                    array(
                            'type' => 'checkbox',
                            'id' => 43,
                            'label' => 'Subscribe',
                            'isRequired' => false,
                            'choices' => array(
                                    0 => array(
                                            'text' => 'Yes, please send me news and updates',
                                            'value' => 'subscribe',
                                            'isSelected' => true,
                                    ),
                            ),
                            'inputs' => array(
                                    0 => array(
                                            'id' => '43.1',
                                            'label' => 'Yes, please send me news and updates',
                                            'name' => 'subscribe',
                                    ),
                            ),
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_subscribe',
                            'visibility' => 'visible',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '28',
                                                    'operator' => 'is',
                                                    'value' => 'subscribe',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'checkbox',
                            'id' => 49,
                            'label' => 'Anonymous Donation',
                            'isRequired' => false,
                            'choices' => array(
                                    0 => array(
                                            'text' => 'Anonymous Gift: Your name and contact details will not be shared with the member',
                                            'value' => 'anonymous',
                                            'isSelected' => false,
                                    ),
                            ),
                            'inputs' => array(
                                    0 => array(
                                            'id' => '49.1',
                                            'label' => 'Anonymous Gift: Your name and contact details will not be shared with the member',
                                            'name' => 'anonymous',
                                    ),
                            ),
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_anonymous',
                            'visibility' => 'visible',
                            'conditionalLogic' => array(
                                    'actionType' => 'show',
                                    'logicType' => 'all',
                                    'rules' => array(
                                            0 => array(
                                                    'fieldId' => '28',
                                                    'operator' => 'is',
                                                    'value' => 'anonymous',
                                            ),
                                    ),
                            ),
                    ),
                    array(
                            'type' => 'product',
                            'id' => 9,
                            'label' => 'Product Name',
                            'isRequired' => false,
                            'inputs' => array(
                                    0 =>  array(
                                            'id' => '9.1',
                                            'label' => 'Name',
                                            'name' => 'bb_cart_total_name',
                                    ),
                                    1 =>  array(
                                            'id' => '9.2',
                                            'label' => 'Price',
                                            'name' => 'bb_cart_total_price',
                                    ),
                                    2 =>  array(
                                            'id' => '9.3',
                                            'label' => 'Quantity',
                                            'name' => 'bb_cart_total_quantity',
                                    ),
                            ),
                            'inputType' => 'hiddenproduct',
                            'allowsPrepopulate' => true,
                            'inputName' => '',
                            'visibility' => 'visible',
                    ),
            		array(
            				'type' => 'total',
            				'id' => 52,
            				'label' => 'Total',
            				'isRequired' => false,
            				'allowsPrepopulate' => true,
            				'inputName' => 'bb_cart_total',
            				'visibility' => 'visible',
            		),
                    array(
                            'type' => 'hidden',
                            'id' => 10,
                            'label' => 'Cart Entries',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_checkout_items_array',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'hidden',
                            'id' => 11,
                            'label' => 'Fund Code',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_fund_code',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'hidden',
                            'id' => 22,
                            'label' => 'frequency',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_donation_frequency',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'hidden',
                            'id' => 26,
                            'label' => 'Item Types',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_item_types',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'hidden',
                            'id' => 32,
                            'label' => 'Campaign ID',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'campaign_id',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'hidden',
                            'id' => 33,
                            'label' => 'target',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'donation_target',
                            'visibility' => 'visible',
                    ),
                    array(
                            'type' => 'number',
                            'id' => 35,
                            'label' => 'Deductible Donations',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_deductible_donation_total',
                            'visibility' => 'hidden',
                    ),
                    array(
                            'type' => 'number',
                            'id' => 36,
                            'label' => 'Non-Deductible Donations',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_non_deductible_donation_total',
                            'visibility' => 'hidden',
                    ),
                    array(
                            'type' => 'number',
                            'id' => 37,
                            'label' => 'Deductible Purchases',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_deductible_purchase_total',
                            'visibility' => 'hidden',
                    ),
                    array(
                            'type' => 'number',
                            'id' => 38,
                            'label' => 'Non-Deductible Purchases',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_non_deductible_purchase_total',
                            'visibility' => 'hidden',
                    ),
                    array(
                            'type' => 'hidden',
                            'id' => 39,
                            'label' => 'New Contact',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_new_contact',
                            'visibility' => 'hidden',
                    ),
                    array(
                            'type' => 'hidden',
                            'id' => 40,
                            'label' => 'Currency',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_currency',
                            'visibility' => 'hidden',
                    ),
                    array(
                            'type' => 'hidden',
                            'id' => 41,
                            'label' => 'External Reference - Contact',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_external_reference_contact',
                            'visibility' => 'hidden',
                    ),
                    array(
                            'type' => 'hidden',
                            'id' => 42,
                            'label' => 'External Reference - Entry',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_external_reference_entry',
                            'visibility' => 'hidden',
                    ),
                    array(
                            'type' => 'hidden',
                            'id' => 48,
                            'label' => 'Transaction Type',
                            'isRequired' => false,
                            'allowsPrepopulate' => true,
                            'inputName' => 'bb_cart_transaction_type',
                            'defaultValue' => 'online',
                            'visibility' => 'hidden',
                    ),
            )
    );

    // If they have configured reCAPTCHA add it to the form
    if (!empty(get_option('rg_gforms_captcha_public_key')) && !empty(get_option('rg_gforms_captcha_private_key'))) {
        $checkout_form['fields'][] = array(
                'type' => 'captcha',
        		'id' => 47,
        		'label' => 'invisible' == get_option('gforms_captcha_type') ? '' : 'Security Check',
                'captchaLanguage' => 'en-GB',
                'visibility' => 'hidden',
        );
    }

    if (!$checkout_form_id || !GFAPI::form_id_exists($checkout_form_id)) { // If form doesn't exist, create it
    	$checkout_form_id = GFAPI::add_form($checkout_form);
    	if (is_int($checkout_form_id)) {
        	update_option('bb_cart_checkout_form_id', $checkout_form_id);
    	}
    } else { // Otherwise if we've created it previously, just update it to make sure it hasn't been modified and is the latest version
    	$checkout_form['id'] = $checkout_form_id;
    	$checkout_form = wp_parse_args($checkout_form, GFAPI::get_form($checkout_form_id)); // Make sure we don't lose additional third-party settings etc
        GFAPI::update_form($checkout_form);
    	GFAPI::update_form_property($checkout_form_id, 'is_trash', '0'); // Make sure it's not in the trash
    }

    return $checkout_form_id;
}

function bb_cart_get_shipping_form() {
    $shipping_form_id = get_option('bbcart_shipping_form_id');
    $shipping_form = array(
            'title' => '[BB Cart] Shipping',
            'description' => '',
            'labelPlacement' => 'top_label',
            'descriptionPlacement' => 'below',
            'is_active' => true,
            'cssClass' => 'bb_cart_shipping',
            'enableHoneypot' => true,
            'button' =>  array(
                    'type' => 'text',
                    'text' => 'Proceed',
            ),
            'confirmations' => array(
                    '597237bdb19ee' => array(
                            'id' => '597237bdb19ee',
                            'name' => 'Default Confirmation',
                            'isDefault' => true,
                            'type' => 'redirect',
                            'url' => site_url('/payment/'),
                            'queryString' => '',
                    ),
            ),
            'pagination' => array(
                    'type' => 'none',
            ),
    		'fields' => array( // Next field ID: 6
    				array( /** @deprecated */
    						'type' => 'text',
    						'id' => 4,
    						'label' => 'Suburb',
    						'isRequired' => true,
    						'allowsPrepopulate' => true,
    						'inputName' => 'suburb',
    						'visibility' => 'administrative',
    						'inputMask' => true,
    						'description' => 'Please enter your suburb so we can calculate your shipping costs',
    				),
    				array( /** @deprecated */
    						'type' => 'text',
    						'id' => 1,
    						'label' => 'Postcode',
    						'isRequired' => true,
    						'allowsPrepopulate' => true,
    						'inputName' => 'postcode',
    						'visibility' => 'administrative',
    						'inputMask' => true,
    						'maskText' => '9999',
    						'inputMaskValue' => '9999',
    						'description' => 'Please enter your postcode so we can calculate your shipping costs',
    				),
    				array(
    						'type' => 'address',
    						'id' => 5,
    						'label' => 'Shipping Address',
    						'isRequired' => false,
    						'inputs' => array(
    								0 =>  array(
    										'id' => '5.1',
    										'label' => 'Street Address',
    								),
    								1 =>  array(
    										'id' => '5.2',
    										'label' => 'Address Line 2',
    										'isHidden' => true,
    								),
    								2 =>  array(
    										'id' => '5.3',
    										'label' => 'City',
    										'cssClass' => 'gf_left_half',
    								),
    								3 =>  array(
    										'id' => '5.4',
    										'label' => 'State / Province',
    										'cssClass' => 'gf_right_half',
    								),
    								4 =>  array(
    										'id' => '5.5',
    										'label' => 'ZIP / Postal Code',
    										'cssClass' => 'gf_left_half',
    								),
    								5 =>  array(
    										'id' => '5.6',
    										'label' => 'Country',
    										'name' => '',
    										'cssClass' => 'gf_right_half',
    								),
    						),
    						'description' => 'Please enter your address so we can calculate your shipping costs',
    						'descriptionPlacement' => 'above',
    						'addressType' => 'international',
    						'visibility' => 'visible',
    				),
    				array(
    						'type' => 'page',
    						'id' => 2,
    						'visibility' => 'visible',
    						'displayOnly' => true,
    						'inputs' => null,
    						'previousButton' => array(
    								'type' => 'text',
    								'text' => 'Previous',
    								'imageUrl' => '',
    						),
    						'nextButton' => array(
    								'type' => 'text',
    								'text' => 'Next',
    								'imageUrl' => '',
    						),
    				),
    				array(
    						'type' => 'radio',
    						'id' => 3,
    						'label' => 'Shipping Method',
    						'isRequired' => false,
    						'inputs' => NULL,
    						'choices' => array(
    								0 =>  array(
    										'text' => 'No Shipping',
    										'value' => '',
    										'isSelected' => true,
    										'price' => '',
    								),
    						),
    						'allowsPrepopulate' => true,
    						'cssClass' => 'gf_list_inline shipping_method',
    						'inputName' => 'shipping_method',
    				),
    		),
    );
    if (!$shipping_form_id || !GFAPI::form_id_exists($shipping_form_id)) { // If form doesn't exist, create it
    	$shipping_form_id = GFAPI::add_form($shipping_form);
    	if (is_int($shipping_form_id)) {
        	update_option('bbcart_shipping_form_id', $shipping_form_id);
    	}
    } else { // Otherwise if we've created it previously, just update it to make sure it hasn't been modified and is the latest version
    	$shipping_form['id'] = $shipping_form_id;
    	$shipping_form = wp_parse_args($shipping_form, GFAPI::get_form($shipping_form_id)); // Make sure we don't lose additional third-party settings etc
        GFAPI::update_form($shipping_form);
    	GFAPI::update_form_property($shipping_form_id, 'is_trash', '0'); // Make sure it's not in the trash
    }

    return $shipping_form_id;
}

function bb_cart_get_cart_user() {
    $user = get_user_by('login', 'bbcart');
    if (!$user) {
        $user = new WP_User();
        $user->user_login = 'bbcart';
        $user->user_email = 'dev+bbcart@brownbox.net.au';
        $user->first_name = 'BB Cart';
        $user->last_name = 'System User';
        $user->user_pass = wp_generate_password();
        $user->ID = wp_insert_user($user);
    }
    return $user;
}

function bb_cart_get_protected_forms() {
	$bb_cart_forms = apply_filters('bb_cart_protected_forms', array());

	// Add our forms after the filter so they can't be removed
	$bb_cart_forms[] = bb_cart_get_donate_form();
	$bb_cart_forms[] = bb_cart_get_checkout_form();
	$bb_cart_forms[] = bb_cart_get_shipping_form();
	return $bb_cart_forms;
}

add_action('init', 'bb_cart_form_locking', 9999); // Run as late as possible to make sure GF has inited first
function bb_cart_form_locking() {
    if (class_exists('GFFormLocking')) {
        class BBCartGFFormLocking extends GFFormLocking {
            private $bb_cart_forms = array();

            public function __construct() {
            	$this->bb_cart_forms = bb_cart_get_protected_forms();
                $this->_redirect_url = admin_url('admin.php?page=gf_edit_forms');

//                 add_action('gform_form_list_column_title', array($this, 'form_list_form_title'));  @todo this conflicts with Connexions' corresponding logic, resulting in 2 form titles being displayed
                add_filter('gform_form_actions', array($this, 'form_list_lock_message'), 999, 2);
                parent::__construct();
            }

            protected function check_lock($object_id) {
                if (in_array($object_id, $this->bb_cart_forms)) {
                    return bb_cart_get_cart_user()->ID;
                }
                return parent::check_lock($object_id);
            }

            public function get_strings() {
                if (in_array($this->get_object_id(), $this->bb_cart_forms)) {
                    $strings = array(
                            'currently_locked'  => __('This form is managed by BB Cart. You cannot edit this form.', 'bbcart'),
                            'currently_editing' => __('This form is managed by BB Cart. You cannot edit this form.', 'bbcart'),
                    );

                    return array_merge(parent::get_strings(), $strings);
                }
                return parent::get_strings();
            }

            public function get_lock_ui($user_id) {
                if (in_array($this->get_object_id(), $this->bb_cart_forms)) {
                    $html = '<div id="gform-lock-dialog" class="notification-dialog-wrap">
                            <div class="notification-dialog-background"></div>
                            <div class="notification-dialog">
                                <div class="gform-locked-message">
                                    <div class="gform-locked-avatar"><!--img src="'.trailingslashit(BB_CART_URL).'assets/brand.png" alt=""--></div>
                                    <p class="currently-editing" tabindex="0">'.$this->get_string('currently_locked').'</p>
                                    <p><a class="button" href="'.esc_url($this->_redirect_url).'">'.$this->get_string('cancel').'</a></p>
                                </div>
                            </div>
                         </div>';
                    return $html;
                }
                return parent::get_lock_ui($user_id);
            }

            public function form_list_form_title($form) {
                if (in_array($form->id, $this->bb_cart_forms)) {
                    echo '<strong>'.esc_html($form->title).'</strong>';
                } else {
                    echo '<strong><a href="?page=gf_edit_forms&id='.absint($form->id).'">'.esc_html($form->title).'</a></strong>';
                }
            }

            public function form_list_lock_message($form_actions, $form_id) {
                if (in_array($form_id, $this->bb_cart_forms)) {
                    echo __('This form is managed by BB Cart. You cannot edit this form.<br>', 'bbcart');
                    // Block access to edit/delete functions
                    unset($form_actions['edit'], $form_actions['trash']);
                    // Rewrite settings link to point to notifications
                    $form_actions['settings']['url'] = add_query_arg('subview', 'notification', $form_actions['settings']['url']);
                }
                return $form_actions;
            }
        }

        class BBCartGFFormSettingsLocking extends GFFormSettingsLocking {
            private $bb_cart_forms = array();
            private $locked_subviews = array(
                    'settings',
                    'confirmation',
            );

            public function __construct() {
            	$this->bb_cart_forms = bb_cart_get_protected_forms();
                $this->_redirect_url = admin_url('admin.php?page=gf_edit_forms&view=settings&id='.rgget('id').'&subview=notification');

                add_filter('gform_form_settings_menu', array($this, 'form_settings_hide_locked_subviews'), 10, 2);

                parent::__construct();
            }

            public function form_settings_hide_locked_subviews($setting_tabs, $form_id) {
                if (in_array($form_id, $this->bb_cart_forms)) {
                    foreach ($setting_tabs as $pos => $tab) {
                        if (in_array($tab['name'], $this->locked_subviews)) {
                            unset($setting_tabs[$pos]);
                        }
                    }
                }
                return $setting_tabs;
            }

            protected function check_lock($object_id) {
            	list($subview, $form_id) = explode('-', $object_id);
            	if ($subview == 'settings' && isset($_GET['subview'])) { // If subview contains hyphen GF doesn't pass it through properly
            		$subview = $_GET['subview'];
            	}
                if (in_array($form_id, $this->bb_cart_forms) && (empty($subview) || in_array($subview, $this->locked_subviews))) {
                    return bb_cart_get_cart_user()->ID;
                }
                return parent::check_lock($object_id);
            }

            public function get_strings() {
                $object_id = $this->get_object_id();
                list($subview, $form_id) = explode('-', $object_id);
                if ($subview == 'settings' && isset($_GET['subview'])) { // If subview contains hyphen GF doesn't pass it through properly
                	$subview = $_GET['subview'];
                }
                if (in_array($form_id, $this->bb_cart_forms) && (empty($subview) || in_array($subview, $this->locked_subviews))) {
                    $strings = array(
                            'currently_locked'  => __( 'This form is managed by BB Cart. You cannot edit this form.', 'gravityforms' ),
                            'currently_editing' => 'This form is managed by BB Cart. You cannot edit this form.',
                    );

                    return array_merge(parent::get_strings(), $strings);
                }
                return parent::get_strings();
            }

            public function get_lock_ui($user_id) {
                $object_id = $this->get_object_id();
                list($subview, $form_id) = explode('-', $object_id);
                if ($subview == 'settings' && isset($_GET['subview'])) { // If subview contains hyphen GF doesn't pass it through properly
                	$subview = $_GET['subview'];
                }
                if (in_array($form_id, $this->bb_cart_forms) && (empty($subview) || in_array($subview, $this->locked_subviews))) {
                    $html = '<div id="gform-lock-dialog" class="notification-dialog-wrap">
                            <div class="notification-dialog-background"></div>
                            <div class="notification-dialog">
                                <div class="gform-locked-message">
                                    <div class="gform-locked-avatar"><!--img src="'.trailingslashit(BB_CART_URL).'assets/brand.png" alt=""--></div>
                                    <p class="currently-editing" tabindex="0">'.$this->get_string('currently_locked').'</p>
                                    <p><a class="button" href="'.esc_url($this->_redirect_url).'">'.$this->get_string('cancel').'</a></p>
                                </div>
                            </div>
                         </div>';
                    return $html;
                }
                return parent::get_lock_ui($user_id);
            }
        }
        $form_lock = new BBCartGFFormLocking();
        $form_settings_lock = new BBCartGFFormSettingsLocking();
    }
}
