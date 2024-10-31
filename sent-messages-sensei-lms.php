<?php
/*
 * Plugin Name: Sent Messages for Sensei LMS
 * Description: Access previously sent messages from the course, lesson or quiz.
 * Version: 1.0.2
 * Author: implenton
 * Author URI: https://www.implenton.com/
 * Requires at least: 4.9.8
 * Tested up to: 5.9
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: sent-messages-sensei-lms
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sent_Messages_Sensei_LMS {
    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_filter( 'sensei_messages_send_message_link', [ $this, 'append_sent_messages_link' ] );

        add_filter( 'sensei_single_course_content_inside_before', [ $this, 'display_sent_messages_list' ], 36 );
        add_filter( 'sensei_single_lesson_content_inside_before', [ $this, 'display_sent_messages_list' ], 31 );
        add_filter( 'sensei_single_quiz_questions_before', [ $this, 'display_sent_messages_list' ], 11 );
    }

    public function append_sent_messages_link( $html ) {
        global $post;

        if ( $this->is_sent_messages_view() ) {
            return $html;
        }

        if ( ! $this->is_any_sent_messages( $post ) ) {
            return $html;
        }

        $url = add_query_arg( [
            'past-contact' => $post->post_type,
        ], get_permalink( $post->ID ) );
        $url .= '#sent-messages';

        $html .= sprintf(
            '<p><a class="sent-messages-button" href="%s">%s</a></p>',
            esc_url( $url ),
            sprintf( esc_html__( 'View previous %s messages', 'sent-messages-sensei-lms' ), $post->post_type )
        );

        return $html;
    }

    public function display_sent_messages_list() {
        global $post;

        if ( ! $this->is_sent_messages_view() ) {
            return;
        }

        $messages_query = $this->get_sent_messages( $post );

        if ( ! $messages_query->have_posts() ) {
            return;
        }

        $data = $this->get_sent_messages_data( $messages_query );

        list( $messages, $display_count, $total_count ) = [
            $data['messages'],
            $data['display_count'],
            $data['total_count'],
        ];

        $messages_archive_permalink = $this->get_message_archive_permalink();

        if ( ! empty( Sensei_Templates::locate_template( 'sent-messages.php' ) ) ) {
            Sensei_Templates::get_template( 'sent-messages.php', compact( 'messages', 'display_count', 'total_count', 'messages_archive_permalink' ), '' );
        } else {
            include plugin_dir_path( __FILE__ ) . 'template/sent-messages.php';
        }
    }

    protected function is_sent_messages_view() {
        return isset( $_GET["past-contact"] ) ? true : false;
    }

    protected function get_message_archive_permalink() {
        $message_post_type = get_post_type_object( 'sensei_message' );

        return is_null( $message_post_type ) ? '' : home_url( $message_post_type->rewrite['slug'] );
    }

    protected function get_sent_messages_data( \WP_Query $query ) {
        $data = [
            'messages'      => [],
            'display_count' => intval( $query->post_count ),
            'total_count'   => intval( $query->found_posts ),
        ];

        while ( $query->have_posts() ) {
            $query->the_post();

            $post_id = get_the_ID();
            $post    = [
                'content'         => get_the_content(),
                'permalink'       => get_permalink(),
                'posted_at_human' => human_time_diff( get_the_time( 'U' ), current_time( 'U' ) ),
                'posted_at'       => get_the_time( 'U' ),
                'author'          => get_the_author_meta( 'ID' ),
                'reply_count'     => intval( get_comments_number() ),
                'last_reply'      => null,
                'post_object'     => get_post(),
            ];

            if ( $post['reply_count'] !== 0 ) {
                $post['last_reply'] = $this->get_msg_last_reply( $post_id );
            }

            $data['messages'][] = $post;
        }

        wp_reset_postdata();

        return $data;
    }

    protected function get_msg_last_reply( $post_id ) {
        $replies = get_comments( [
            'post_id' => $post_id,
            'number'  => 1,
        ] );

        if ( empty( $replies ) ) {
            return null;
        }

        $last_reply = $replies[0];

        return [
            'content'         => $last_reply->comment_content,
            'permalink'       => get_comment_link( $last_reply ),
            'posted_at_human' => human_time_diff( strtotime( $last_reply->comment_date ), current_time( 'U' ) ),
            'posted_at'       => strtotime( $last_reply->comment_date ),
            'author'          => intval( $last_reply->user_id ),
            'comment_object'  => $last_reply,
        ];
    }

    protected function is_any_sent_messages( \WP_Post $current_post ) {
        $query          = [
            'post_type'              => 'sensei_message',
            'post_status'            => 'publish',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'fields'                 => 'ids',
            'posts_per_page'         => 1,
            'author'                 => get_current_user_id(),
            'meta_query'             => [
                'relation' => ' AND ',
                [
                    'key'     => '_posttype',
                    'value'   => $current_post->post_type,
                    'compare' => ' = ',
                ],
                [
                    'key'     => '_post',
                    'value'   => $current_post->ID,
                    'compare' => ' = ',
                ],
            ],
        ];
        $messages_query = new \WP_Query( $query );

        return $messages_query->post_count === 0 ? false : true;
    }

    protected function get_sent_messages( \WP_Post $current_post ) {
        $query = [
            'post_type'              => 'sensei_message',
            'post_status'            => 'publish',
            'update_post_term_cache' => false,
            'fields'                 => 'ids',
            'posts_per_page'         => 5,
            'author'                 => get_current_user_id(),
            'meta_query'             => [
                'relation' => ' AND ',
                [
                    'key'     => '_posttype',
                    'value'   => $current_post->post_type,
                    'compare' => ' = ',
                ],
                [
                    'key'     => '_post',
                    'value'   => $current_post->ID,
                    'compare' => ' = ',
                ],
            ],
        ];

        return new \WP_Query( $query );
    }
}

$sent_messages_sensei_lms_plugin = Sent_Messages_Sensei_LMS::get_instance();

function get_sent_message_last_reply_info( $message ) {
    if ( ! $message['reply_count'] ) {
        return esc_html__( 'No response', 'sent-messages-sensei-lms' );
    }

    $who = $message['author'] === $message['last_reply']['author'] ? __( 'you', 'sent-messages-sensei-lms' ) : __( 'teacher', 'sent-messages-sensei-lms' );

    return sprintf( esc_html__( 'Last reply %s ago by %s ', 'sent-messages-sensei-lms' ), $message['last_reply']['posted_at_human'], $who );
}
