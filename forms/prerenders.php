<?php
add_filter('gform_pre_render', 'bb_cart_setup_filters_to_populate_field');
function bb_cart_setup_filters_to_populate_field($form) {
    global $post;
    if (in_array($form['id'], array(bb_cart_get_donate_form(), bb_cart_get_checkout_form()))) {
        foreach($form['fields'] as &$field) {
            if ($field->inputName == 'bb_cart_form_setup') {
                $field->choices = apply_filters('bb_cart_form_setup_choices', $field->choices, $form, $field);
            } elseif ($field->inputName == 'bb_cart_checkout_form_setup') {
                $field->choices = apply_filters('bb_cart_checkout_form_setup_choices', $field->choices, $form, $field);
            } elseif ($field->inputName == 'bb_cart_donation_for') {
                $field->choices = apply_filters('bb_cart_donation_for_choices', $field->choices, $form, $field);
            } elseif ($field->inputName == 'bb_cart_interval') {
                $field->choices = apply_filters('bb_cart_interval_choices', $field->choices, $form, $field);
            } elseif ($field->inputName == 'bb_cart_donation_member') {
                $field->choices = apply_filters('bb_cart_donation_member_choices', $field->choices, $form, $field);
                if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$donation_for_choices['member'])) {
                    $field->label = Brownbox\Config\BB_Cart::$donation_for_choices['member'];
                }
            } elseif ($field->inputName == 'bb_cart_donation_campaign') {
                $field->choices = apply_filters('bb_cart_donation_campaign_choices', $field->choices, $form, $field);
                if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$donation_for_choices['campaign'])) {
                    $field->label = Brownbox\Config\BB_Cart::$donation_for_choices['campaign'];
                }
            } elseif ($field->inputName == 'bb_cart_payment_method') {
                $field->choices = apply_filters('bb_cart_payment_method_choices', $field->choices, $form, $field);
            } elseif ($field->inputName == 'bb_cart_currency') {
                $field->choices = apply_filters('bb_cart_currency_choices', $field->choices, $form, $field);
                $field->visibility = count($field->choices) > 1 ? 'visible' : 'hidden';
            } elseif ($field->inputName == 'bb_donation_amounts') {
                $field->choices = apply_filters('bb_cart_donation_amount_choices', $field->choices, $form, $field);
                $field->enableOtherChoice = apply_filters('bb_cart_donation_amount_enable_other', $field->enableOtherChoice, $form, $field);
                if ($field->enableOtherChoice) {
                    $field->field_bb_click_array_other_label = apply_filters('bb_cart_donation_amount_other_label', $field->field_bb_click_array_other_label, $form, $field);
                }
            } elseif ($field->inputName == 'payment_method') {
                $field->choices = apply_filters('bb_cart_checkout_payment_method_choices', $field->choices, $form, $field);
            } elseif ($field->inputName == 'page_id') {
                $field->defaultValue = $post->ID;
            }
        }
    }
    return $form;
}

add_filter('bb_cart_form_setup_choices','bb_cart_populate_form_setup_choices', 1, 3);
function bb_cart_populate_form_setup_choices($choices, $form, $field) {
    if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$form_setup_choices)) {
        foreach ($choices as &$choice) {
            $choice['isSelected'] = false;
            foreach (Brownbox\Config\BB_Cart::$form_setup_choices as $value) {
                if ($choice['value'] == $value) {
                    $choice['isSelected'] = true;
                    continue(2);
                }
            }
        }
    }
    return $choices;
}

add_filter('bb_cart_checkout_form_setup_choices','bb_cart_populate_checkout_form_setup_choices', 1, 3);
function bb_cart_populate_checkout_form_setup_choices($choices, $form, $field) {
    if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$checkout_form_setup_choices)) {
        foreach ($choices as &$choice) {
            $choice['isSelected'] = false;
            foreach (Brownbox\Config\BB_Cart::$checkout_form_setup_choices as $value) {
                if ($choice['value'] == $value) {
                    $choice['isSelected'] = true;
                    continue(2);
                }
            }
        }
    }
    return $choices;
}

/*
 * Populate "donation for" field in the donation form
 */
add_filter('bb_cart_donation_for_choices', 'bb_cart_populate_donation_for_choices', 1, 3);
function bb_cart_populate_donation_for_choices($choices, $form, $field) {
    if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$donation_for_choices)) {
        $choices = array();
        global $post;
        if (isset(Brownbox\Config\BB_Cart::$member) && !empty(Brownbox\Config\BB_Cart::$member['post_type']) && Brownbox\Config\BB_Cart::$member['post_type'] == $post->post_type) {
            $default = 'sponsorship';
        } elseif (isset(Brownbox\Config\BB_Cart::$member) && !empty(Brownbox\Config\BB_Cart::$member['user'])) {
            $default = 'sponsorship';
        } elseif (isset(Brownbox\Config\BB_Cart::$project) && in_array($post->post_type, Brownbox\Config\BB_Cart::$project)) {
            $default = 'campaign';
        } else {
            $default = 'default';
        }
        foreach (Brownbox\Config\BB_Cart::$donation_for_choices as $value => $text) {
            $choices[] = array(
                    'text' => $text,
                    'value' => $value,
                    'isSelected' => $value == $default,
            );
        }
    }
    return $choices;
}

/*
 * Populate donation interval field
 */
add_filter('bb_cart_interval_choices', 'bb_cart_populate_interval_choices', 1, 3);
function bb_cart_populate_interval_choices($choices, $form, $field) {
    if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$intervals)) {
        $choices = array();
        foreach (Brownbox\Config\BB_Cart::$intervals as $value => $text) {
            $choices[] = array(
                    'text' => $text,
                    'value' => $value,
                    'isSelected' => $value == 'one-off',
            );
        }
    }
    return $choices;
}

/*
 * Populate currency field
 */
add_filter('bb_cart_currency_choices', 'bb_cart_populate_currency_choices', 1, 3);
function bb_cart_populate_currency_choices($choices, $form, $field) {
    if (!empty($_SESSION[BB_CART_SESSION_ITEM])) {
        // If cart not empty, always use the previously selected currency
        $choices = array(
                array(
                        'text' => bb_cart_get_default_currency(),
                        'value' => bb_cart_get_default_currency(),
                        'isSelected' => $value == bb_cart_get_default_currency(),
                ),
        );
    } elseif (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$currencies)) {
        $choices = array();
        foreach (Brownbox\Config\BB_Cart::$currencies as $value => $text) {
            $choices[] = array(
                    'text' => $text,
                    'value' => $value,
                    'isSelected' => $value == bb_cart_get_default_currency(),
            );
        }
    }
    return $choices;
}

/*
 * Populate click array values
 */
add_filter('bb_cart_donation_amount_choices', 'bb_cart_populate_donation_amount_choices', 1, 3);
function bb_cart_populate_donation_amount_choices($choices, $form, $field) {
    if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$donation_amounts)) {
        $choices = Brownbox\Config\BB_Cart::$donation_amounts;
    }

    return $choices;
}

/*
 * Enable/disable "other" amount field
 */
add_filter('bb_cart_donation_amount_enable_other', 'bb_cart_populate_donation_amount_enable_other', 1, 3);
function bb_cart_populate_donation_amount_enable_other($enable, $form, $field) {
    if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$donation_amount_enable_other)) {
        $enable = Brownbox\Config\BB_Cart::$donation_amount_enable_other;
    }

    return $enable;
}

/*
 * Label for "other" amount field
 */
add_filter('bb_cart_donation_amount_other_label', 'bb_cart_populate_donation_amount_other_label', 1, 3);
function bb_cart_populate_donation_amount_other_label($label, $form, $field) {
    if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$donation_amount_other_label)) {
        $label = Brownbox\Config\BB_Cart::$donation_amount_other_label;
    }

    return $label;
}

/*
 * Populate memmber list in donation form
 */
add_filter('bb_cart_donation_member_choices', 'bb_cart_populate_donation_member_choices', 1, 3);
function bb_cart_populate_donation_member_choices($choices, $form, $field){
    if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$member)) {
        $choices = array(
                array(
                        'text' => '-- Please select --',
                        'value' => ''
                ),
        );
        foreach (Brownbox\Config\BB_Cart::$member as $type => $value) {
            if ($type == 'post_type') {
                $args = array(
                        'post_type' => $value,
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC'
                );
                $members = get_posts($args);
                foreach ($members as $member) {
                    $label = $member->post_title;
                    $fund_code = get_post_meta($member->ID, 'bb_give_fund_code', true);
                    $choices[] = array('text' => $label, 'value' => $member->ID);
                }

            } else if($type == 'user'){
                $args = array(
                        'role' => $value,
                        'orderby' => 'first_name',
                        'order' => 'ASC',
                );

                $members = get_users($args);
                foreach ($members as $member) {
                    $label = $member->display_name;
                    $choices[] = array('text' => $label, 'value' => $member->ID);
                }
            }
        }
    }
    return $choices;
}

/*
 * Populate project list in donation form
 */
add_filter('bb_cart_donation_campaign_choices', 'bb_cart_populate_donation_campaign_choices', 1, 3);
function bb_cart_populate_donation_campaign_choices($choices, $form, $field) {
    if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$project)) {
        $choices = array(
                array(
                        'text' => '-- Please select --',
                        'value' => ''
                ),
        );
        $project_args = array(
                'post_type' => Brownbox\Config\BB_Cart::$project,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
        );

        $projects = get_posts($project_args);

        global $post;
        foreach ($projects as $project) {
            $label = $project->post_title;
            $choices[] = array(
                    'text' => $label,
                    'value' => $project->ID,
                    'isSelected' => $post->ID == $project->ID,
            );
        }
    }
    return $choices;
}

/*
 * Populate payment method field
 */
add_filter('bb_cart_payment_method_choices', 'bb_cart_populate_payment_method_choices', 1, 3);
add_filter('bb_cart_checkout_payment_method_choices', 'bb_cart_populate_payment_method_choices', 1, 3);
function bb_cart_populate_payment_method_choices($choices, $form, $field) {
    if (class_exists('Brownbox\Config\BB_Cart') && isset(Brownbox\Config\BB_Cart::$payment_methods)) {
        $choices = array();
        foreach (Brownbox\Config\BB_Cart::$payment_methods as $choice) {
            $choices[] = $choice;
        }
    }
    return $choices;
}

add_filter("gform_submit_button", "bb_cart_form_submit_button", 1, 2);
function bb_cart_form_submit_button($button, $form) {
    $cssClasses = explode(" ", $form['cssClass']);
    foreach ($cssClasses as $cssClass) {
        $content = '<div class="hide">' . $button . '</div>';
        if ($cssClass == 'bb_cart_donations') {
            $content .= '<p>Your payment method</p>';
            foreach ($form['fields'] as &$field){
                if($field->inputName == 'bb_cart_payment_method'){
                    foreach ($field->choices as &$choice){
                        $content .= '<a href="#" data-paymentmethod="'.$choice['value'].'" class="pseudo-submit payment-method button">'.$choice['text'].'</a> ';
                    }
                }
            }

            $js = <<<MULTI
				<script type="text/javascript">

				jQuery(".pseudo-submit").on("click", function() {
				    var payment_method = jQuery(this).attr("data-paymentmethod");
				    jQuery("input[value='" + payment_method + "']").trigger("click")
				    jQuery(this).parent().find(".gform_button.button").trigger("click");
				    return false;
				});

				</script>
MULTI;
            return $content . $js;
        }
        return $button;
    }
}
