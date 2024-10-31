<?php
/**
 * The template for previously sent messages.
 *
 * Override this template by copying it to yourtheme/sensei/sent-messages.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<div id="sent-messages">

    <h3><?php esc_html_e( 'Previous messages', 'sent-messages-sensei-lms' ); ?></h3>

    <?php foreach ( $messages as $message ): ?>

        <div class="sent-message">

            <p class="sent-message__content">
                <?php echo $message['content']; ?>
            </p>

            <p class="sent-message__meta">
                <small>
                    <a href="<?php echo $message['permalink']; ?>"><?php printf( esc_html__( 'Sent %s ago', 'sent-messages-sensei-lms' ), $message['posted_at_human'] ); ?></a>
                    &mdash; <?php echo get_sent_message_last_reply_info( $message ); ?>
                </small>
            </p>

        </div>

    <?php endforeach; ?>

    <?php if ( $display_count !== $total_count ) : ?>
        <p>
            <a href="<?php echo $messages_archive_permalink; ?>">
                <?php esc_html_e( 'View all messages', 'sent-messages-sensei-lms' ); ?>
            </a>
        </p>
    <?php endif; ?>

</div>