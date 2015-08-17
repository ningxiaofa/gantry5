<?php

/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2015 RocketTheme, LLC
 * @license   GNU/GPLv2 and later
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Gantry\WordPress\Assignments;

class AssignmentsPost implements AssignmentsInterface
{
    public $type = 'post';
    public $priority = 6;

    /**
     * Returns list of rules which apply to the current page.
     *
     * @return array
     */
    public function getRules()
    {
        global $wp_query;

        $rules = [];

        $queried_object = get_queried_object();

        if(is_singular() && $queried_object !== null) {
            $post_type = $queried_object->post_type;
            $id = $queried_object->ID;

            $rules[$post_type][$id] = 1;

            // Get current post type taxonomies and its terms
            $taxonomies = get_object_taxonomies($queried_object);
            if(!empty($taxonomies)) {
                foreach($taxonomies as $tax) {
                    $args = [
                        'orderby' => 'name',
                        'order' => 'ASC',
                        'fields' => 'all'
                    ];

                    $terms = wp_get_post_terms($id, $tax, $args);

                    foreach($terms as $term) {
                        $rules[$post_type . '-terms'][$tax . '-' . $term->term_id] = $this->priority;
                    }
                }
            }
        }

        return $rules;
    }

    /**
     * List all the rules available.
     *
     * @return array
     */
    public function listRules()
    {
        // Get all defined post types
        $post_types = $this->getPostTypes();

        // Break if there are no post types defined
        if (!$post_types) {
            return [];
        }

        // Get label and items for each post types
        $list = [];
        foreach($post_types as $post_type) {
            $post_type = apply_filters('g5_assignments_' . $post_type->name . '_object', $post_type);

            if($post_type) {
                $list[$post_type->name]['label'] = $post_type->labels->name;
                $list[$post_type->name]['items'] = $this->getItems($post_type);

                // Get current post type taxonomies and its terms
                $taxonomies = get_object_taxonomies($post_type->name);
                if(!empty($taxonomies)) {
                    $list[$post_type->name . '-terms']['label'] = $post_type->labels->name . ': Terms';
                    $list[$post_type->name . '-terms']['items'] = $this->getTerms($taxonomies, $post_type);
                }
            }
        }

        return $list;
    }

    protected function getPostTypes($args = [])
    {
        $defaults = [
            'show_ui' => true
        ];

        $args = wp_parse_args($args, $defaults);

        $post_types = get_post_types(apply_filters('g5_assignments_get_post_types_args', $args), 'object');

        return $post_types;
    }

    protected function getItems($post_type, $args = [])
    {
        $items = [];

        $defaults = [
            'order'                  => 'ASC',
            'orderby'                => 'title',
            'post_type'              => $post_type->name,
            'suppress_filters'       => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'posts_per_page'         => -1
        ];

        $args = wp_parse_args($args, $defaults);

        $wp_query = new \WP_Query;
        $posts = $wp_query->query($args);

        // Check if there are any posts
        if(!$wp_query->post_count) {

            $items[] = [
                'name'     => '',
                'label'    => 'No items',
                'disabled' => true
            ];

        } else {

            $walker = new AssignmentsWalker;

            $new_posts = [];
            foreach($posts as $new_post) {
                $new_post->id           = $new_post->ID;
                $new_post->parent_id    = $new_post->post_parent;
                $new_posts[] = $new_post;
            }

            $posts = $walker->walk($new_posts, 0);

            foreach($posts as $post) {
                $post->post_title != '' ? $post_title = $post->post_title : $post_title = $post_type->labels->singular_name . ' #' . $post->ID;

                $items[] = [
                    'name'     => $post->ID,
                    'label'    => $post->level > 0 ? str_repeat('—', $post->level) . ' ' . $post_title : $post_title,
                    'disabled' => false
                ];
            }

        }

        return apply_filters('g5_assignments_' . $post_type->name . '_list_items', $items, $post_type, $this->type);

    }

    protected function getTerms($taxonomies, $post_type, $args = [])
    {
        $items = [];

        $defaults = [
            'child_of'                 => 0,
            'exclude'                  => '',
            'hide_empty'               => false,
            'hierarchical'             => 1,
            'include'                  => '',
            'include_last_update_time' => false,
            'order'                    => 'ASC',
            'orderby'                  => 'name',
            'pad_counts'               => false,
        ];

        $args = wp_parse_args($args, $defaults);

        foreach($taxonomies as $tax) {
            $taxonomy = get_taxonomy($tax);
            $terms    = get_terms($tax, $args);

            $items[] = [
                'name'     => $taxonomy->name,
                'label'    => $taxonomy->label,
                'section'  => true,
                'disabled' => true
            ];

            if(empty($terms)) {
                $items[] = [
                    'name'     => '',
                    'label'    => 'No items',
                    'disabled' => true
                ];
            } else {
                $walker = new AssignmentsWalker;

                $new_terms = [];
                foreach($terms as $new_term) {
                    $new_term->id        = $new_term->term_id;
                    $new_term->parent_id = $new_term->parent;
                    $new_terms[]         = $new_term;
                }

                $terms = $walker->walk($new_terms, 0);

                foreach($terms as $term) {
                    $items[] = [
                        'name'     => $term->taxonomy . '-' . $term->term_id,
                        'label'    => $term->level > 0 ? str_repeat('—', $term->level + 1) . ' ' . $term->name : '— ' . $term->name,
                        'taxonomy' => $term->taxonomy,
                        'disabled' => false
                    ];
                }
            }
        }

        return apply_filters('g5_assignments_' . $post_type->name . '_terms_list_items', $items, $taxonomies, $post_type, $this->type);
    }
}
