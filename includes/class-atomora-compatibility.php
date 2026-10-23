<?php
namespace Atomora;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guards every bit of Atomora behind real, checkable conditions so a
 * mismatched environment shows a friendly notice instead of a white screen.
 */
class Compatibility {

	/** @var string[] Collected human-readable problems, if any. */
	private static $errors = [];

	/** @var string[] Specific Atomic Widgets internal-API mismatches, if any. */
	private static $atomic_api_mismatches = [];

	/**
	 * Runs all checks. Returns true only if it is fully safe to load the
	 * rest of the plugin (widgets, admin settings, etc).
	 */
	public static function check() {
		self::$errors = [];

		if ( version_compare( PHP_VERSION, ATOMORA_MIN_PHP, '<' ) ) {
			self::$errors[] = sprintf(
				/* translators: 1: required PHP version 2: current PHP version */
				__( 'Atomora requires PHP %1$s or higher. Your server is running PHP %2$s. Please ask your host to upgrade PHP.', 'atomora' ),
				ATOMORA_MIN_PHP,
				PHP_VERSION
			);
		}

		if ( version_compare( get_bloginfo( 'version' ), ATOMORA_MIN_WP, '<' ) ) {
			self::$errors[] = sprintf(
				/* translators: 1: required WP version 2: current WP version */
				__( 'Atomora requires WordPress %1$s or higher. You are running WordPress %2$s.', 'atomora' ),
				ATOMORA_MIN_WP,
				get_bloginfo( 'version' )
			);
		}

		// Everything past this point needs plugins loaded, so defer via hook
		// if we're too early (e.g. called from muplugins_loaded).
		if ( ! did_action( 'plugins_loaded' ) ) {
			add_action( 'plugins_loaded', [ __CLASS__, 'check_elementor' ], 20 );
		} else {
			self::check_elementor();
		}

		if ( ! empty( self::$errors ) ) {
			add_action( 'admin_notices', [ __CLASS__, 'render_notices' ] );
			return false;
		}

		return true;
	}

	/**
	 * Elementor active + version checks only. Deliberately does NOT touch
	 * anything in the Elementor\Modules\AtomicWidgets\... namespace yet —
	 * see check_atomic_widgets_api() for why that has to wait.
	 */
	public static function check_elementor() {
		if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
			self::$errors[] = __( 'Atomora requires the Elementor plugin to be installed and active.', 'atomora' );
			add_action( 'admin_notices', [ __CLASS__, 'render_notices' ] );
			return;
		}

		if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, ATOMORA_MIN_ELEMENTOR, '<' ) ) {
			self::$errors[] = sprintf(
				/* translators: 1: required Elementor version 2: current Elementor version */
				__( 'Atomora requires Elementor %1$s (Editor V4 / Atomic Editor) or higher. You have Elementor %2$s active. Please update Elementor.', 'atomora' ),
				ATOMORA_MIN_ELEMENTOR,
				ELEMENTOR_VERSION
			);
			add_action( 'admin_notices', [ __CLASS__, 'render_notices' ] );
			return;
		}

		// IMPORTANT: class_exists() on anything under Elementor\Modules\...
		// triggers autoloading of that class right now — and if that class
		// extends \Elementor\Widget_Base, PHP needs Widget_Base to already
		// be defined. Elementor does NOT autoload Widget_Base by class name;
		// it explicitly `require`s includes/base/widget-base.php from
		// inside Widgets_Manager's constructor, which Elementor only builds
		// during its own Plugin::init() — hooked to WordPress's 'init'
		// action, not 'elementor/loaded' (which fires earlier, around
		// 'plugins_loaded'). So Widget_Base isn't safely referenceable
		// until Elementor's Widgets_Manager has actually been built. The
		// 'elementor/widgets/register' hook is fired from inside that same
		// constructor, right after widget-base.php is required — so it's
		// the earliest point where this is guaranteed safe, and it's also
		// exactly where our own widget registration already happens.
		if ( did_action( 'elementor/widgets/register' ) ) {
			self::check_atomic_widgets_api();
		} else {
			add_action( 'elementor/widgets/register', [ __CLASS__, 'check_atomic_widgets_api' ], 1 );
		}
	}

	/**
	 * The deep, beta-namespace-touching checks. Only ever called once
	 * Elementor has fired 'elementor/loaded', so its own classes are safe
	 * to reference — with a try/catch as a second line of defense, since
	 * "safe to reference" turned out not to be guaranteed either (see the
	 * catch blocks in get_atomic_widgets_api_mismatches() for why).
	 */
	public static function check_atomic_widgets_api() {
		try {
			$api_mismatches = self::get_atomic_widgets_api_mismatches();
		} catch ( \Throwable $e ) {
			$api_mismatches = [ 'Unexpected error while checking Atomic Widgets API: ' . $e->getMessage() ];
		}

		if ( ! empty( $api_mismatches ) ) {
			self::$atomic_api_mismatches = $api_mismatches;
			add_action( 'admin_notices', [ __CLASS__, 'render_atomic_api_mismatch_notice' ] );
			return;
		}

		try {
			$available = self::is_atomic_editor_available();
		} catch ( \Throwable $e ) {
			$available = false;
		}

		if ( ! $available ) {
			add_action( 'admin_notices', [ __CLASS__, 'render_atomic_disabled_notice' ] );
		}
	}

	/**
	 * Every class from Elementor's beta `Elementor\Modules\AtomicWidgets\...`
	 * namespace that this plugin actually calls. Checked individually (not
	 * just the base class) because a beta module can rename or move any one
	 * of these in a point release without touching the others.
	 *
	 * @return string[]
	 */
	public static function required_atomic_widgets_classes(): array {
		return [
			'\Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base',
			'\Elementor\Modules\AtomicWidgets\Controls\Section',
			'\Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control',
			'\Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control',
			'\Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control',
			'\Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control',
			'\Elementor\Modules\AtomicWidgets\Controls\Types\Date_Range_Control',
			// NOTE: Classes_Control (per-addon CSS class fields, test pass on
			// Product Card) is deliberately NOT listed here. It's used
			// defensively via class_exists() at the call site instead, so
			// its absence degrades that one feature rather than blocking
			// the whole plugin the way a missing required class would.
			'\Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Date_Range_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Background_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Color_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Dimensions_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type',
            '\Elementor\Modules\AtomicWidgets\PropTypes\Background_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Color_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Dimensions_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\Styles\Style_Definition',
			'\Elementor\Modules\AtomicWidgets\Styles\Style_Variant',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type',
			'\Elementor\Modules\AtomicWidgets\Styles\Style_Definition',
			'\Elementor\Modules\AtomicWidgets\Styles\Style_Variant',
		];
	}

	/**
	 * Checks that every class above exists AND still exposes the specific
	 * methods this plugin calls on it (a rename is one risk; a changed
	 * method signature on an otherwise-unchanged class is another).
	 * Returns a list of problems — empty array means "safe to register".
	 *
	 * Every check below is individually wrapped in try/catch. This matters
	 * because class_exists() does NOT fail gracefully here: if one of
	 * Elementor's own beta files has a broken `extends` reference (e.g. its
	 * atomic-widget-base.php pointing at a Widget_Base class that doesn't
	 * exist in a given build), PHP throws a catchable \Error the moment
	 * autoloading is triggered — it does not just return false. Without a
	 * try/catch around each call, that \Error propagates as an uncaught
	 * fatal and takes down the whole page.
	 *
	 * @return string[]
	 */
	public static function get_atomic_widgets_api_mismatches(): array {
		$problems = [];

		foreach ( self::required_atomic_widgets_classes() as $class ) {
			try {
				if ( ! class_exists( $class ) ) {
					$problems[] = "Missing class: {$class}";
				}
			} catch ( \Throwable $e ) {
				$problems[] = "Class failed to load: {$class} ({$e->getMessage()})";
			}
		}

		if ( ! empty( $problems ) ) {
			// No point checking methods on classes that didn't load cleanly.
			return $problems;
		}

		$method_checks = [
			[ '\Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control', 'bind_to' ],
			[ '\Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control', 'set_options' ],
			[ '\Elementor\Modules\AtomicWidgets\Controls\Section', 'make' ],
			[ '\Elementor\Modules\AtomicWidgets\Controls\Section', 'set_items' ],
			[ '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type', 'make' ],
			[ '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type', 'enum' ],
		];

		foreach ( $method_checks as [ $class, $method ] ) {
			try {
				if ( ! method_exists( $class, $method ) ) {
					$problems[] = "Missing method: {$class}::{$method}()";
				}
			} catch ( \Throwable $e ) {
				$problems[] = "Method check failed: {$class}::{$method}() ({$e->getMessage()})";
			}
		}

		return $problems;
	}

	/**
	 * Whether Elementor's v4 Atomic Widgets engine is actually present and
	 * turned on (it ships as a hidden, opt-in "experiment" in 4.0), *and*
	 * its internal API still looks the way this plugin expects it to.
	 */
	public static function is_atomic_editor_available() {
		if ( ! empty( self::get_atomic_widgets_api_mismatches() ) ) {
			return false;
		}

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->experiments ) ) {
			// The feature flag Elementor itself registers for Atomic Widgets.
			if ( method_exists( \Elementor\Plugin::$instance->experiments, 'is_feature_active' )
				&& ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_atomic_elements' ) ) {
				return false;
			}
		}

		return true;
	}

	public static function render_notices() {
		if ( empty( self::$errors ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>Atomora:</strong></p><ul style="list-style:disc;margin-left:20px;">';
		foreach ( self::$errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></div>';
	}

	public static function render_atomic_disabled_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Atomora:</strong> ' .
			esc_html__( 'Elementor\'s Atomic Editor (V4) is installed but not yet enabled on this site. Turn it on from Elementor → Editor → Settings → "Atomic Editor" (or the Features/Experiments panel), then reload this page. Atomora widgets will stay hidden until it is enabled.', 'atomora' ) .
			'</p></div>';
	}

	/**
	 * Shown when Elementor's Atomic Widgets classes exist, but no longer
	 * match what this plugin was built against — i.e. Elementor's module
	 * changed shape in an update. Atomora disables its widgets rather
	 * than risk a fatal error, and logs specifics for debugging.
	 */
	public static function render_atomic_api_mismatch_notice() {
		if ( did_action( 'wp_loaded' ) && function_exists( 'error_log' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			foreach ( self::$atomic_api_mismatches as $problem ) {
				error_log( 'Atomora — Elementor Atomic Widgets API mismatch: ' . $problem ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>Atomora:</strong> ' .
			esc_html__( 'Elementor updated its Atomic Widgets engine and part of its internal structure no longer matches what this plugin expects. Atomora has safely disabled its widgets rather than risk breaking your site — nothing on your existing pages was touched.', 'atomora' ) .
			'</p><p>' . esc_html__( 'Details (for support / debugging):', 'atomora' ) . '</p><ul style="list-style:disc;margin-left:20px;">';
		foreach ( self::$atomic_api_mismatches as $problem ) {
			echo '<li><code>' . esc_html( $problem ) . '</code></li>';
		}
		echo '</ul></div>';
	}
}