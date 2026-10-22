<?php
namespace Atomora\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crash reporting: catches errors from Atomora's own try/catch blocks
 * plus true uncaught PHP fatals originating from this plugin's own files,
 * stores the last 50 as an admin-viewable log, and emails the plugin
 * owner — throttled per unique error (1/hour) so a repeat fatal on every
 * page load doesn't flood the inbox.
 */
class Crash_Reporter {

	const OPTION_REPORTS         = 'atomora_crash_reports';
	const OPTION_EMAIL_THROTTLE  = 'atomora_crash_email_throttle';
	const PAGE_SLUG              = 'atomora-crash-reports';

	// Opt-in: reports are only emailed to an address the site owner enters
	// on the Crash Reports screen. Empty (the default) means nothing is sent.
	const OPTION_EMAIL_TO = 'atomora_crash_email_to';

	const MAX_REPORTS             = 50;
	const EMAIL_THROTTLE_SECONDS  = HOUR_IN_SECONDS;

	/** @var self|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'shutdown', [ $this, 'catch_fatal_error' ] );
		add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
		add_action( 'admin_post_atomora_clear_crash_reports', [ $this, 'handle_clear_reports' ] );
		add_action( 'admin_post_atomora_save_crash_email', [ $this, 'handle_save_email' ] );
	}

	/**
	 * Called from existing try/catch blocks throughout the plugin.
	 */
	public function report( string $context, \Throwable $e ): void {
		$this->store_report( [
			'time'    => current_time( 'mysql' ),
			'context' => $context,
			'message' => $e->getMessage(),
			'file'    => $e->getFile(),
			'line'    => $e->getLine(),
			'trace'   => $this->short_trace( $e ),
		] );
	}

	/**
	 * Hooked on 'shutdown' — catches true uncaught fatals (the kind
	 * try/catch can never see). Filtered to only report errors that
	 * actually originate from this plugin's own files, so a fatal in an
	 * unrelated theme/plugin on the same site is never misattributed to
	 * Atomora.
	 */
	public function catch_fatal_error(): void {
		$error = error_get_last();

		if ( ! $error ) {
			return;
		}

		$fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ];
		if ( ! in_array( $error['type'], $fatal_types, true ) ) {
			return;
		}

		if ( ! defined( 'ATOMORA_PATH' ) || false === strpos( $error['file'], ATOMORA_PATH ) ) {
			return;
		}

		$this->store_report( [
			'time'    => current_time( 'mysql' ),
			'context' => 'Fatal error (uncaught)',
			'message' => $error['message'],
			'file'    => $error['file'],
			'line'    => $error['line'],
			'trace'   => '',
		] );
	}

	private function short_trace( \Throwable $e ): string {
		$lines = explode( "\n", $e->getTraceAsString() );
		return implode( "\n", array_slice( $lines, 0, 3 ) );
	}

	private function store_report( array $report ): void {
		$reports = get_option( self::OPTION_REPORTS, [] );
		if ( ! is_array( $reports ) ) {
			$reports = [];
		}

		array_unshift( $reports, $report );
		$reports = array_slice( $reports, 0, self::MAX_REPORTS );

		update_option( self::OPTION_REPORTS, $reports, false );

		$this->maybe_email_report( $report );
	}

	private function maybe_email_report( array $report ): void {
		$to = get_option( self::OPTION_EMAIL_TO, '' );
		if ( ! is_string( $to ) || ! is_email( $to ) ) {
			return;
		}

		$hash = md5( $report['context'] . '|' . $report['message'] . '|' . $report['file'] . '|' . $report['line'] );

		$throttle = get_option( self::OPTION_EMAIL_THROTTLE, [] );
		if ( ! is_array( $throttle ) ) {
			$throttle = [];
		}

		$now = time();

		// Prune stale entries on every write so this option never grows
		// unbounded across the life of the site.
		foreach ( $throttle as $key => $timestamp ) {
			if ( $now - $timestamp > self::EMAIL_THROTTLE_SECONDS ) {
				unset( $throttle[ $key ] );
			}
		}

		if ( isset( $throttle[ $hash ] ) ) {
			update_option( self::OPTION_EMAIL_THROTTLE, $throttle, false );
			return;
		}

		$throttle[ $hash ] = $now;
		update_option( self::OPTION_EMAIL_THROTTLE, $throttle, false );

		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		$subject = sprintf( '[Atomora] Crash report — %s — %s', $report['context'], $site_name );

		$body  = "A crash was reported by Atomora.\n\n";
		$body .= 'Site: ' . $site_name . ' (' . $site_url . ")\n";
		$body .= 'Time: ' . $report['time'] . "\n";
		$body .= 'Context: ' . $report['context'] . "\n";
		$body .= 'Message: ' . $report['message'] . "\n";
		$body .= 'File: ' . $report['file'] . ':' . $report['line'] . "\n";
		if ( ! empty( $report['trace'] ) ) {
			$body .= "\nTrace (first 3 lines):\n" . $report['trace'] . "\n";
		}
		$body .= "\nView all reports: " . admin_url( 'options-general.php?page=' . self::PAGE_SLUG );

		wp_mail( $to, $subject, $body );
	}

	public function handle_save_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'atomora' ) );
		}

		check_admin_referer( 'atomora_save_crash_email' );

		$email = isset( $_POST['crash_email'] ) ? sanitize_email( wp_unslash( $_POST['crash_email'] ) ) : '';
		if ( '' !== $email && is_email( $email ) ) {
			update_option( self::OPTION_EMAIL_TO, $email, false );
		} else {
			delete_option( self::OPTION_EMAIL_TO );
		}

		wp_safe_redirect( add_query_arg( 'email_saved', '1', admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	public function add_admin_page() {
		add_options_page(
			__( 'Atomora Crash Reports', 'atomora' ),
			__( 'Atomora Crash Reports', 'atomora' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * "Delete reports from the database, clean" — full deletion of both
	 * the reports list and the email-throttle option, not just clearing
	 * the display. Nonce-protected, capability-checked.
	 */
	public function handle_clear_reports() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'atomora' ) );
		}

		check_admin_referer( 'atomora_clear_crash_reports' );

		delete_option( self::OPTION_REPORTS );
		delete_option( self::OPTION_EMAIL_THROTTLE );

		wp_safe_redirect( add_query_arg( 'cleared', '1', admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$reports = get_option( self::OPTION_REPORTS, [] );
		if ( ! is_array( $reports ) ) {
			$reports = [];
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Atomora — Crash Reports', 'atomora' ); ?></h1>

			<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Crash reports cleared from the database.', 'atomora' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['email_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Crash report email setting saved.', 'atomora' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="atomora_save_crash_email">
				<?php wp_nonce_field( 'atomora_save_crash_email' ); ?>
				<p>
					<label for="atomora-crash-email"><?php esc_html_e( 'Email crash reports to (optional)', 'atomora' ); ?></label><br>
					<input type="email" id="atomora-crash-email" name="crash_email" class="regular-text" value="<?php echo esc_attr( (string) get_option( self::OPTION_EMAIL_TO, '' ) ); ?>">
				</p>
				<p class="description"><?php esc_html_e( 'Reports include error messages and file paths. Nothing is emailed unless you enter an address here.', 'atomora' ); ?></p>
				<?php submit_button( __( 'Save', 'atomora' ), 'secondary', 'submit', false ); ?>
			</form>

			<p><?php esc_html_e( 'Errors caught by Atomora (widget render errors, registration failures, and uncaught fatals originating from this plugin) are logged here and emailed to the plugin author\'s inbox (throttled to 1 email per unique error per hour).', 'atomora' ); ?></p>

			<hr/>

			<h2><?php
				printf(
					/* translators: %d: number of stored crash reports */
					esc_html__( 'Recent reports (%d)', 'atomora' ),
					count( $reports )
				);
			?></h2>

			<?php if ( empty( $reports ) ) : ?>
				<p><?php esc_html_e( 'No crashes reported yet.', 'atomora' ); ?></p>
			<?php else : ?>
				<form
					method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete all crash reports from the database? This cannot be undone.', 'atomora' ) ); ?>');"
					style="margin-bottom: 12px;"
				>
					<?php wp_nonce_field( 'atomora_clear_crash_reports' ); ?>
					<input type="hidden" name="action" value="atomora_clear_crash_reports" />
					<?php submit_button( __( 'Clear all reports (delete from database)', 'atomora' ), 'delete', 'submit', false ); ?>
				</form>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'atomora' ); ?></th>
							<th><?php esc_html_e( 'Context', 'atomora' ); ?></th>
							<th><?php esc_html_e( 'Message', 'atomora' ); ?></th>
							<th><?php esc_html_e( 'Location', 'atomora' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $reports as $report ) : ?>
							<tr>
								<td><?php echo esc_html( $report['time'] ?? '' ); ?></td>
								<td><?php echo esc_html( $report['context'] ?? '' ); ?></td>
								<td><?php echo esc_html( $report['message'] ?? '' ); ?></td>
								<td><code><?php echo esc_html( ( $report['file'] ?? '' ) . ':' . ( $report['line'] ?? '' ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}