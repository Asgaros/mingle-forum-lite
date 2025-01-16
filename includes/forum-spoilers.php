<?php
if (!defined('ABSPATH')) {
    exit;
}

class AsgarosForumSpoilers {
    private $asgarosforum = null;
    private $tables = null;

    public function __construct($asgarosForumObject) {
        $this->asgarosforum = $asgarosForumObject;

        // Assuming tables are accessible through a property or method.
        $this->tables = $this->asgarosforum->tables;

        add_action('init', array($this, 'initialize'));
    }

    public function initialize() {
        if ($this->asgarosforum->options['enable_spoilers']) {
            add_shortcode('spoiler', array($this, 'render_spoiler'));
        }
    }

    public function get_parent_id($post_id) {
        global $wpdb;
        
        // Query the wp_forum_posts table for the parent_id of the post.
        $parent_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT parent_id FROM {$this->tables->posts} WHERE id = %d",
                $post_id
            )
        );
    
        return $parent_id;
    }
    
    public function render_spoiler($atts = false, $content = false) {
        $atts = shortcode_atts(
            array(
                'title' => esc_html__('Spoiler', 'asgaros-forum'),
                'post_id' => $this->get_forum_post_id(), // Fetch post ID from wp_forum_posts table.
            ),
            $atts,
            'spoiler'
        );
    
        $atts['title'] = (!empty($atts['title'])) ? $atts['title'] : esc_html__('Spoiler', 'asgaros-forum');
    
        $post_id = $atts['post_id'];
    
        $user_has_replied = $this->asgarosforum_user_has_replied($post_id);
    
        $output  = '<div class="spoiler">';
        $output .= '<div class="spoiler-head closed"><span>' . esc_html($atts['title']) . '</span></div>';
        $output .= '<div class="spoiler-body">';
    
        if (current_user_can('manage_options') || $user_has_replied) {
            $output .= do_shortcode($content); // Allow nested shortcodes within the spoiler.
        } else {
            $output .= esc_html__('Sorry, you need to reply to see spoilers.', 'asgaros-forum');
        }
    
        $output .= '</div>';
        $output .= '</div>';
    
        return $output;
    }
    
    private function get_forum_post_id() {
        // Get the current user ID
        $current_user_id = get_current_user_id();
    
        // Query the wp_forum_posts table to get the post ID.
        global $wpdb;
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tables->posts} WHERE author_id = %d",
                $current_user_id
            )
        );
        return $post_id;
    }
    
        
    public function asgarosforum_user_has_replied($post_id) {
        global $wpdb;
    
        if (!empty($post_id) && is_user_logged_in()) {
            $current_user_id = get_current_user_id();
    
            // Check if the post with the given ID exists
            $post_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->tables->posts} WHERE id = %d", $post_id));
    
            if ($post_exists) {
                // Get the parent_id of the post.
                $parent_id = $this->get_parent_id($post_id);
    
                // Check if the user has authored the post or has replied to it.
                $user_has_replied = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) 
                         FROM {$this->tables->posts} 
                         WHERE (id = %d AND author_id = %d) OR (parent_id = %d AND author_id = %d)",
                        $post_id,
                        $current_user_id,
                        $parent_id,
                        $current_user_id
                    )
                );
    
                // Count the number of new posts/replies within the same parent_id.
                $reply_count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) 
                         FROM {$this->tables->posts} 
                         WHERE parent_id = %d AND author_id = %d",
                        $parent_id,
                        $current_user_id
                    )
                );
    
                // Log the SQL queries and results for debugging.
                error_log('Post ID: ' . $post_id);
                error_log('SQL Query (user_has_replied): ' . $wpdb->last_query);
                error_log('User has replied: ' . $user_has_replied);
                error_log('SQL Query (reply_count): ' . $wpdb->last_query);
                error_log('Reply Count: ' . $reply_count);
    
                return ($user_has_replied + $reply_count > 0);
            } else {
                // Debug information
                error_log('Post does not exist for ID: ' . $post_id);
                error_log('SQL Query (reply_count): ' . $wpdb->last_query);
            }
        }
    
        return false;
    }
}