<?php

/**
 * Asking users for their experience with this plugin.
 */
class UserFeedback {

	/**
	 * The wp option for notice dismissal data.
	 */
	const OPTION_NAME = 'easy_wp_smtp_user_feedback_notice';

	/**
	 * How many days after activation it should display the user feedback notice.
	 */
	const DELAY_NOTICE = 14;

	/**
	 * Initialize user feedback notice functionality.
	 */
	public function init() {

		add_action( 'admin_notices', [ $this, 'maybe_display' ] );
		add_action( 'wp_ajax_easy_wp_smtp_feedback_notice_dismiss', [ $this, 'feedback_notice_dismiss' ] );
	}

	/**
	 * Maybe display the user feedback notice.
	 */
	public function maybe_display() {

		// Only admin users should see the feedback notice.
		if ( ! is_super_admin() ) {
			return;
		}

		$options = get_option( self::OPTION_NAME );

		// Set default options.
		if ( empty( $options ) ) {
			$options = [
				'time'      => time(),
				'dismissed' => false,
			];
			update_option( self::OPTION_NAME, $options );
		}

		// Check if the feedback notice was not dismissed already.
		if ( isset( $options['dismissed'] ) && ! $options['dismissed'] ) {
			$this->display();
		}
	}

	/**
	 * Display the user feedback notice.
	 */
	private function display() {

		// Skip if SMTP settings are not configured.
		if ( ! $this->is_smtp_configured() ) {
			return;
		}

		// Fetch when plugin was initially activated.
		$activated = get_option( 'easy_wp_smtp_activated_time' );

		// Skip if the plugin is active for less than a defined number of days.
		if ( empty( $activated ) || ( $activated + ( DAY_IN_SECONDS * self::DELAY_NOTICE ) ) > time() ) {
			return;
		}

		?>
		<script type="text/javascript">
			jQuery( document ).ready( function ( $ ) {
				$( document ).on( 'click', '.easy-wp-smtp-dismiss-review-notice, .easy-wp-smtp-review-notice button', function( e ) {
					if ( ! $( this ).hasClass( 'easy-wp-smtp-review-out' ) ) {
						e.preventDefault();
					}
					$.post( ajaxurl, { action: 'easy_wp_smtp_feedback_notice_dismiss' } );
					$( '.easy-wp-smtp-review-notice' ).remove();
				} );

				$( document ).on( 'click', '.easy-wp-smtp-review-switch-step', function( e ) {
					e.preventDefault();
					var target = parseInt( $( this ).attr( 'data-step' ), 10 );

					if ( target ) {
						var $notice = $( this ).closest( '.easy-wp-smtp-review-notice' );
						var $review_step = $notice.find( '.easy-wp-smtp-review-step-' + target );

						if ( $review_step.length > 0 ) {
							$notice.find( '.easy-wp-smtp-review-step:visible' ).fadeOut( function() {
								$review_step.fadeIn();
							} );
						}
					}
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Check if the crucial SMTP setting are configured.
	 *
	 * @return bool
	 */
	public function is_smtp_configured() {

		$plugin_options = get_option( 'swpsmtp_options', [] );

		return
			! empty( $plugin_options['from_email_field'] ) &&
			! empty( $plugin_options['smtp_settings']['host'] );
	}

	/**
	 * Dismiss the user feedback admin notice.
	 */
	public function feedback_notice_dismiss() {

		$options              = get_option( self::OPTION_NAME, [] );
		$options['time']      = time();
		$options['dismissed'] = true;

		update_option( self::OPTION_NAME, $options );

		if ( is_super_admin() && is_multisite() ) {
			$site_list = get_sites();
			foreach ( (array) $site_list as $site ) {
				switch_to_blog( $site->blog_id );

				update_option( self::OPTION_NAME, $options );

				restore_current_blog();
			}
		}

		wp_send_json_success();
	}
}
