<?php
/**
 * Plugin Name: Comment Rating
 * Plugin URI: https://github.com/shestopalovpro/comment-rating
 * Description: Добавляет систему голосования (плюс/минус) для комментариев с современным дизайном
 * Version: 1.0.2
 * Author: Sergey Shestopalov
 * Author URI: https://shestopalovpro.ru
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: comment-rating
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CR_VERSION', '1.0.2');
define('CR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CR_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Create database table on plugin activation
 */
function cr_activate_plugin()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'comment_votes';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        comment_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL DEFAULT 0,
        user_ip varchar(100) NOT NULL,
        vote_type tinyint(1) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY comment_id (comment_id),
        KEY user_id (user_id),
        KEY user_ip (user_ip)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option('cr_version', CR_VERSION);
}
register_activation_hook(__FILE__, 'cr_activate_plugin');

/**
 * Enqueue CSS and JavaScript
 */
function cr_enqueue_scripts()
{
    if (!is_singular() || !comments_open()) {
        return;
    }

    wp_enqueue_style(
        'comment-rating-css',
        CR_PLUGIN_URL . 'assets/css/comment-rating.css',
        array(),
        CR_VERSION
    );

    wp_enqueue_script(
        'comment-rating-js',
        CR_PLUGIN_URL . 'assets/js/comment-rating.js',
        array('jquery'),
        CR_VERSION,
        true
    );

    // Pass AJAX URL and nonce to JavaScript
    wp_localize_script('comment-rating-js', 'crAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cr_vote_nonce'),
        'errorMessage' => __('Произошла ошибка. Попробуйте еще раз.', 'comment-rating'),
        'alreadyVoted' => __('Вы уже проголосовали за этот комментарий.', 'comment-rating')
    ));
}
add_action('wp_enqueue_scripts', 'cr_enqueue_scripts');

/**
 * Get vote counts for a comment
 */
function cr_get_vote_counts($comment_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'comment_votes';

    $upvotes = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE comment_id = %d AND vote_type = 1",
        $comment_id
    ));

    $downvotes = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE comment_id = %d AND vote_type = -1",
        $comment_id
    ));

    return array(
        'upvotes' => intval($upvotes),
        'downvotes' => intval($downvotes),
        'total' => intval($upvotes) - intval($downvotes)
    );
}

/**
 * Check if user has already voted
 */
function cr_user_has_voted($comment_id, $user_id = 0, $user_ip = '')
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'comment_votes';

    if ($user_id > 0) {
        $vote = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE comment_id = %d AND user_id = %d",
            $comment_id,
            $user_id
        ));
    } else {
        $vote = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE comment_id = %d AND user_ip = %s AND user_id = 0",
            $comment_id,
            $user_ip
        ));
    }

    return $vote ? intval($vote->vote_type) : 0;
}

/**
 * Add voting UI to comment text
 */
function cr_add_voting_ui($comment_text, $comment = null)
{
    if (!$comment) {
        return $comment_text;
    }

    $comment_id = $comment->comment_ID;
    $votes = cr_get_vote_counts($comment_id);
    $user_id = get_current_user_id();
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $user_vote = cr_user_has_voted($comment_id, $user_id, $user_ip);

    $upvote_class = $user_vote === 1 ? 'cr-active' : '';
    $downvote_class = $user_vote === -1 ? 'cr-active' : '';

    $voting_html = '
    <span class="cr-voting-wrapper" data-comment-id="' . esc_attr($comment_id) . '">
        <button class="cr-vote-btn cr-upvote ' . $upvote_class . '" data-vote="1" aria-label="' . esc_attr__('Плюс', 'comment-rating') . '">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                <path d="M7.493 18.75c-.425 0-.82-.236-.975-.632A7.48 7.48 0 0 1 6 15.375c0-1.75.599-3.358 1.602-4.634.151-.192.373-.309.6-.397.473-.183.89-.514 1.212-.924a9.042 9.042 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23h-.777ZM2.331 10.727a11.969 11.969 0 0 0-.831 4.398 12 12 0 0 0 .52 3.507C2.28 19.482 3.105 20 3.994 20H4.9c.445 0 .72-.498.523-.898a8.963 8.963 0 0 1-.924-3.977c0-1.708.476-3.305 1.302-4.666.245-.403-.028-.959-.5-.959H4.25c-.832 0-1.612.453-1.918 1.227Z"/>
            </svg>
        </button>
        <span class="cr-vote-count">' . esc_html($votes['total']) . '</span>
        <button class="cr-vote-btn cr-downvote ' . $downvote_class . '" data-vote="-1" aria-label="' . esc_attr__('Минус', 'comment-rating') . '">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                <path d="M15.73 5.25h1.035A7.465 7.465 0 0 1 18 9.375a7.465 7.465 0 0 1-1.235 4.125h-.148c-.806 0-1.534.446-2.031 1.08a9.04 9.04 0 0 1-2.861 2.4c-.723.384-1.35.956-1.653 1.715a4.499 4.499 0 0 0-.322 1.672v.633A.75.75 0 0 1 9 21a2.25 2.25 0 0 1-2.25-2.25c0-1.152.26-2.243.723-3.218.266-.558-.107-1.282-.725-1.282H3.622c-1.026 0-1.945-.694-2.054-1.715A12.137 12.137 0 0 1 1.5 12.25c0-2.848.992-5.464 2.649-7.521C4.537 4.247 5.136 4 5.754 4H9.77a4.5 4.5 0 0 1 1.423.23l3.114 1.04a4.5 4.5 0 0 0 1.423.23ZM21.669 14.023c.536-1.362.831-2.845.831-4.398 0-1.22-.182-2.398-.52-3.507-.26-.85-1.084-1.368-1.973-1.368H19.1c-.445 0-.72.498-.523.898.591 1.2.924 2.55.924 3.977a8.958 8.958 0 0 1-1.302 4.666c-.245.403.028.959.5.959h1.053c.832 0 1.612-.453 1.918-1.227Z"/>
            </svg>
        </button>
    </span>';

    return $comment_text . $voting_html;
}
add_filter('comment_text', 'cr_add_voting_ui', 10, 2);

/**
 * Handle AJAX vote submission
 */
function cr_handle_vote()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cr_vote_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    $comment_id = intval($_POST['comment_id']);
    $vote_type = intval($_POST['vote_type']);

    // Validate vote type
    if (!in_array($vote_type, array(1, -1))) {
        wp_send_json_error(array('message' => 'Invalid vote type'));
        return;
    }

    // Validate comment exists
    $comment = get_comment($comment_id);
    if (!$comment) {
        wp_send_json_error(array('message' => 'Comment not found'));
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'comment_votes';
    $user_id = get_current_user_id();
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // Check if user has already voted
    $existing_vote = cr_user_has_voted($comment_id, $user_id, $user_ip);

    if ($existing_vote !== 0) {
        // User has voted before
        if ($existing_vote === $vote_type) {
            // Same vote - remove it (toggle off)
            if ($user_id > 0) {
                $wpdb->delete(
                    $table_name,
                    array('comment_id' => $comment_id, 'user_id' => $user_id),
                    array('%d', '%d')
                );
            } else {
                $wpdb->delete(
                    $table_name,
                    array('comment_id' => $comment_id, 'user_ip' => $user_ip, 'user_id' => 0),
                    array('%d', '%s', '%d')
                );
            }
        } else {
            // Different vote - update it
            if ($user_id > 0) {
                $wpdb->update(
                    $table_name,
                    array('vote_type' => $vote_type),
                    array('comment_id' => $comment_id, 'user_id' => $user_id),
                    array('%d'),
                    array('%d', '%d')
                );
            } else {
                $wpdb->update(
                    $table_name,
                    array('vote_type' => $vote_type),
                    array('comment_id' => $comment_id, 'user_ip' => $user_ip, 'user_id' => 0),
                    array('%d'),
                    array('%d', '%s', '%d')
                );
            }
        }
    } else {
        // New vote
        $wpdb->insert(
            $table_name,
            array(
                'comment_id' => $comment_id,
                'user_id' => $user_id,
                'user_ip' => $user_ip,
                'vote_type' => $vote_type
            ),
            array('%d', '%d', '%s', '%d')
        );
    }

    // Get updated vote counts
    $votes = cr_get_vote_counts($comment_id);
    $new_user_vote = cr_user_has_voted($comment_id, $user_id, $user_ip);

    wp_send_json_success(array(
        'votes' => $votes,
        'user_vote' => $new_user_vote
    ));
}
add_action('wp_ajax_cr_vote', 'cr_handle_vote');
add_action('wp_ajax_nopriv_cr_vote', 'cr_handle_vote');
