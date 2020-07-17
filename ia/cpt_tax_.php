<?php
namespace bb_cart;
class cptTaxClass extends taxClass {
	function __construct($singular, $plural, array $posttypes, array $post_args = array(), array $tax_args = array(), $slug = '') {
	    $this->cpt = new cptClass($singular, $plural, $post_args, $slug);
	    $default_tax_args = array(
	            'public' => false,
	            'show_ui' => true,
	            'show_in_menu' => false,
	            'show_tagcloud' => false,
	            'show_in_nav_menus' => false,
	    );
	    $this->tax = new taxClass($singular, $plural, $posttypes, wp_parse_args($tax_args, $default_tax_args), $slug);
        add_action('save_post', array($this, 'cpt_as_category'));
        add_action('before_delete_post', array($this, 'refresh_cpt_hierarchy'));
        add_action('deleted_post', array($this, 'delete_cpt_as_category'));
	}

    function cpt_as_category($post_id) {
        // We don't want to do anything when autosaving a draft
        $post = get_post($post_id);
        if (wp_is_post_autosave($post_id) || $post->post_status == 'auto-draft') {
            return;
        }

        // Make sure it's the right post type
        if (get_post_type($post_id) == $this->cpt->slug) {
            // Now let's make sure we have the right ID
            $revision = wp_is_post_revision($post_id);
            if ($revision) {
                $post_id = $revision;
                $post = get_post($post_id);
            }

            // Need to mirror the hierarchy
            $parent_id = $post->post_parent;
            $parent_cat_id = 0;
            if ($parent_id > 0) {
                $parent_category = get_term_by('slug', $parent_id, $this->tax->taxonomy);
                if ($parent_category)
                    $parent_cat_id = (int)$parent_category->term_id;
            }

            $category = get_term_by('slug', $post_id, $this->tax->taxonomy);
            if ($category) { // Update
                wp_update_term((int)$category->term_id, $this->tax->taxonomy, array(
                        'name' => $post->post_title,
                        'slug' => $post_id,
                        'parent'=> $parent_cat_id,
                ));
            } else { // Create
                wp_insert_term($post->post_title, $this->tax->taxonomy, array(
                        'slug' => $post_id,
                        'parent'=> $parent_cat_id,
                ));
            }
        }
    }

    function refresh_cpt_hierarchy($post_id) {
        // Update child posts (which will in turn update their terms)
        $args = array(
                'post_parent' => $post_id,
                'post_type' => $this->cpt->slug,
        );
        $children = get_children($args);
        foreach (array_keys($children) as $child_id) {
            wp_update_post(array('ID' => $child_id));
        }

        return true;
    }

    function delete_cpt_as_category($post_id) {
        // If it's only a revision, ignore
        if (wp_is_post_revision($post_id)) {
            return true;
        }

        // Make sure it's the right post type
        if (get_post_type($post_id) == $this->cpt->slug) {
            $category = get_term_by('slug', $post_id, $this->tax->taxonomy);
            if ($category) {
                // Delete term relationships
                global $wpdb;
                $wpdb->query($wpdb->prepare( 'DELETE FROM '.$wpdb->term_relationships.' WHERE term_taxonomy_id = %d', $category->term_id));

                // Delete from users
                $args = array(
                		'meta_query' => array(
                				array(
                						'key' => $this->tax->taxonomy,
                						'value' => $category->term_id,
                						'compare' => 'LIKE',
                				),
                		),
                );
                $users = get_users($args);
                foreach ($users as $user) {
                    $posts = get_user_meta($user->ID, $this->tax->taxonomy, true);
                    $postArr = explode(',',$posts);
                    $idx = array_search($category->term_id, $postArr);
                    if ($idx !== false) {
                        unset($postArr[$idx]);
                        update_user_meta($user->ID, $this->tax->taxonomy, implode(',',$postArr));
                    }
                }

                // Delete term
                wp_delete_term($category->term_id, $this->tax->taxonomy);
            }
        }

        return true;
    }
}
