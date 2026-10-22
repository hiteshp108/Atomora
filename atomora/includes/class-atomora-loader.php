<?php
namespace Atomora;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin once we already know the environment is safe
 * (see Compatibility::check()).
 */
class Loader {

	const OPTION_DELETE_DATA = 'atomora_delete_data_on_uninstall';
	const OPTION_HIDE_ELEMENTOR_ADS = 'atomora_hide_elementor_ads';
	const OPTION_FULL_CLASS_NAMES = 'atomora_full_class_names';
	const OPTION_CLASS_HIGHLIGHTER = 'atomora_class_highlighter';
	const OPTION_TAB_NAVIGATOR = 'atomora_tab_navigator';
	const OPTION_DESIGN_SYSTEM_TOOLS = 'atomora_design_system_tools';
	const OPTION_DESIGN_SYSTEM_ROW_NUMBERS = 'atomora_design_system_row_numbers';
	const OPTION_DESIGN_SYSTEM_BULK_ACTIONS = 'atomora_design_system_bulk_actions';

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// One-time cleanup: the Groups/folders feature (removed) stored
		// its data in this option. delete_option() is a harmless no-op
		// once it's gone, so this line is safe to delete entirely in a
		// future release once confirmed cleaned up everywhere it's used.
		delete_option( 'atomora_design_system_groups_data' );


		// Only register our atomic widgets if the Atomic Editor is actually
		// enabled on this install. We hook the same filter Elementor core
		// uses for its own atomic widgets, so ordering/behaviour matches.
		add_filter( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'elementor/elements/elements_registered', [ $this, 'register_elements' ] );
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_widget_category' ] );
		add_action( 'admin_notices', [ $this, 'pro_form_notice' ] );
		add_action( 'wp_ajax_atomora_quick_view', [ $this, 'ajax_quick_view' ] );
		add_action( 'wp_ajax_nopriv_atomora_quick_view', [ $this, 'ajax_quick_view' ] );
		add_action( 'wp_ajax_atomora_load_reviews', [ $this, 'ajax_load_reviews' ] );
		add_action( 'wp_ajax_nopriv_atomora_load_reviews', [ $this, 'ajax_load_reviews' ] );
		add_action( 'elementor/widgets/register', [ $this, 'reorder_widget_categories' ], 99 );

		// Lightweight settings screen: Settings → Atomora.
		require_once ATOMORA_PATH . 'includes/admin/class-atomora-settings.php';
		new Admin\Settings();

		// Crash reporting — always on (not opt-in), unconditionally hooked
		// so it can catch true fatals via 'shutdown' from the earliest
		// possible point in the request.
		require_once ATOMORA_PATH . 'includes/admin/class-crash-reporter.php';
		Admin\Crash_Reporter::instance();

		// Editor UX enhancements (class highlighter, full class names) —
		// gated per-feature via the settings screen.
		require_once ATOMORA_PATH . 'includes/admin/class-editor-enhancements.php';
		new Admin\Editor_Enhancements();

		// Opt-in: hides Elementor's own promotional/upsell UI. See class
		// file for the honest caveat — this is CSS-hiding of known
		// current class names, not an official Elementor API (none
		// exists as of this writing).
		if ( 'yes' === get_option( self::OPTION_HIDE_ELEMENTOR_ADS, 'no' ) ) {
			require_once ATOMORA_PATH . 'includes/admin/class-hide-elementor-ads.php';
			new Admin\Hide_Elementor_Ads();
		}

		// Registers a custom condition in Elementor Pro's native Display
		// Conditions dropdown — confirmed working mechanism (verified
		// against Elementor Pro's actual source): hook name is
		// 'elementor/display_conditions/register' (not 'elementor_pro/...'
		// — that was an earlier wrong guess), and registration goes
		// through the public register_condition_instance() method.
		add_action( 'elementor/display_conditions/register', [ $this, 'register_display_conditions' ] );

		register_activation_hook( ATOMORA_FILE, [ __CLASS__, 'on_activation' ] );
		register_deactivation_hook( ATOMORA_FILE, [ __CLASS__, 'on_deactivation' ] );
	}

	/**
	 * @param \ElementorPro\Modules\DisplayConditions\Classes\Conditions_Manager $conditions_manager
	 */
	public function register_display_conditions( $conditions_manager ) {
		if ( ! class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) ) {
			return;
		}
		if ( ! class_exists( '\ElementorPro\Core\Isolation\Wordpress_Adapter' ) ) {
			return;
		}
		if ( ! method_exists( $conditions_manager, 'register_condition_instance' ) ) {
			return;
		}

		// file => class name. Each is required + instantiated + registered
		// independently, so one bad/missing file never blocks the rest.
		$conditions = [
			'class-woocommerce-stock-condition.php' => 'WooCommerce_Stock_Condition',
			'class-product-category-condition.php'  => 'Product_Category_Condition',
			'class-product-tag-condition.php'       => 'Product_Tag_Condition',
			'class-product-price-condition.php'     => 'Product_Price_Condition',
			'class-featured-product-condition.php'  => 'Featured_Product_Condition',
		];

		foreach ( $conditions as $file => $class_name ) {
			try {
				require_once ATOMORA_PATH . 'includes/conditions/' . $file;

				$fqcn = '\\Atomora\\Conditions\\' . $class_name;
				if ( ! class_exists( $fqcn ) ) {
					continue;
				}

				$instance = new $fqcn( [ new \ElementorPro\Core\Isolation\Wordpress_Adapter() ] );
				$conditions_manager->register_condition_instance( $instance );
			} catch ( \Throwable $e ) {
				Admin\Crash_Reporter::instance()->report( 'Display condition: ' . $class_name, $e );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Atomora — failed to register display condition ' . $class_name . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}
	}

	/**
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	/**
	 * Moves the Atomora panel sections directly below "Atomic Form"
	 * (above Custom Widgets and the rest). Elementor promotes Custom Widgets
	 * after our categories are registered, so this runs once widgets register.
	 */
	public function reorder_widget_categories() {
		try {
			$manager    = \Elementor\Plugin::$instance->elements_manager;
			$categories = $manager->get_categories();
			$ours       = [ 'atomora', 'atomora-woocommerce', 'atomora-review' ];
			$anchor     = null;

			foreach ( [ 'atomic-form', 'v4-elements' ] as $candidate ) {
				if ( isset( $categories[ $candidate ] ) ) {
					$anchor = $candidate;
					break;
				}
			}
			if ( null === $anchor ) {
				return;
			}

			$moved = [];
			foreach ( $ours as $key ) {
				if ( isset( $categories[ $key ] ) ) {
					$moved[ $key ] = $categories[ $key ];
					unset( $categories[ $key ] );
				}
			}

			$result = [];
			foreach ( $categories as $key => $value ) {
				$result[ $key ] = $value;
				if ( $key === $anchor ) {
					$result += $moved;
				}
			}

			$property = new \ReflectionProperty( $manager, 'categories' );
			if ( PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$property->setValue( $manager, $result );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — category reorder failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	public function register_widget_category( $elements_manager ) {
		$elements_manager->add_category(
			'atomora',
			[
				'title' => __( 'Atomora', 'atomora' ),
				'icon'  => 'eicon-atomic',
			]
		);

		// Dedicated section for WooCommerce action widgets (Add to Cart,
		// Buy Now, Quantity, Variations) — kept separate from the general
		// 'atomora' category (Product Card, Gallery Slider) so they
		// group together in the panel. Elementor's category system is a
		// flat list (verified — no true nested subsections exist), so
		// "Variation" grouping within this is done via a shared
		// "Variation: " title prefix rather than a real sub-menu.
		$elements_manager->add_category(
			'atomora-woocommerce',
			[
				'title' => __( 'Atomora WooCom', 'atomora' ),
				'icon'  => 'eicon-woocommerce',
			]
		);

		$elements_manager->add_category(
			'atomora-review',
			[
				'title' => __( 'Atomora Review', 'atomora' ),
				'icon'  => 'eicon-product-rating',
			]
		);
	}

	/**
	 * Admin warning: the Reviews Form defaults use Elementor Pro's native form elements.
	 */
	public function pro_form_notice() {
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) || ! current_user_can( 'manage_options' ) || ! Compatibility::is_atomic_editor_available() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ( 'plugins' !== $screen->id && false === strpos( (string) $screen->id, 'elementor' ) ) ) {
			return;
		}
		echo '<div class="notice notice-warning is-dismissible"><p><strong>Atomora:</strong> '
			. esc_html__( 'The Reviews Form uses Elementor Pro\'s native form elements (label, textarea, submit button) by default. Elementor Pro is not active, so the form falls back to Atomora\'s own fields and a standard Button.', 'atomora' )
			. '</p></div>';
	}
	/**
	 * Loads the Reviews elements (container, list, form, item).
	 */
	private function require_review_elements(): void {
		require_once ATOMORA_PATH . 'includes/elements/class-review-item.php';
		require_once ATOMORA_PATH . 'includes/elements/class-reviews-list.php';
		require_once ATOMORA_PATH . 'includes/elements/class-reviews-form.php';
		require_once ATOMORA_PATH . 'includes/elements/class-reviews-container.php';
	}

	/**
	 * admin-ajax: Product Card quick view. Widgets register lazily, so the
	 * widget's own hooks would not exist yet in an admin-ajax request.
	 */
	public function ajax_quick_view() {
		try {
			if ( Compatibility::is_atomic_editor_available() && class_exists( '\WooCommerce' ) ) {
				$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'e-atomora-product-card' );
				if ( $widget && method_exists( $widget, 'ajax_quick_view' ) ) {
					$widget->ajax_quick_view();
				}
			}
		} catch ( \Throwable $e ) {
			Admin\Crash_Reporter::instance()->report( 'Quick view ajax', $e );
		}
		wp_send_json_error( null, 500 );
	}
	/**
	 * admin-ajax: "load more" / numbered pagination of the Reviews List.
	 */
	public function ajax_load_reviews() {
		try {
			if ( Compatibility::is_atomic_editor_available() && class_exists( '\WooCommerce' ) ) {
				\Elementor\Plugin::$instance->elements_manager->get_element_types();
				$this->require_review_elements();
				Elements\Reviews_List::ajax_load_reviews();
			}
		} catch ( \Throwable $e ) {
			Admin\Crash_Reporter::instance()->report( 'Reviews list ajax', $e );
		}
		wp_send_json_error( null, 500 );
	}

	/**
	 * Registers Atomora's own nestable atomic elements (containers).
	 * Widgets (leaf parts) go through register_widgets() instead.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public function register_elements( $elements_manager ) {
		try {
			if ( ! Compatibility::is_atomic_editor_available() ) {
				return;
			}

			$this->require_review_elements();

			foreach ( [ 'Reviews_Container', 'Reviews_List', 'Reviews_Form', 'Review_Item' ] as $class_name ) {
				$fqcn = '\\Atomora\\Elements\\' . $class_name;
				if ( class_exists( $fqcn ) ) {
					$elements_manager->register_element_type( new $fqcn() );
				}
			}
		} catch ( \Throwable $e ) {
			Admin\Crash_Reporter::instance()->report( 'Reviews container registration', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Reviews container failed to register: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public function register_widgets( $widgets_manager ) {
		try {
			$available = Compatibility::is_atomic_editor_available();
		} catch ( \Throwable $e ) {
			$available = false;
		}

		if ( ! $available ) {
			return;
		}

		// Everything below — including the require_once itself — is wrapped
		// in one try/catch. Loading a widget file declares a class that
		// extends Elementor's Atomic_Widget_Base; if that base class (or
		// anything it in turn extends) is broken in this Elementor build,
		// PHP throws a catchable \Error the moment the file is parsed, not
		// just when we later instantiate it — so the require_once has to
		// be inside the try, not just the `new`.
		try {
			require_once ATOMORA_PATH . 'includes/widgets/class-gallery-slider.php';

			if ( class_exists( '\Atomora\Widgets\Gallery_Slider' ) ) {
				$widgets_manager->register( new Widgets\Gallery_Slider() );
			}
		} catch ( \Throwable $e ) {
			Admin\Crash_Reporter::instance()->report( 'Gallery Slider registration', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Gallery Slider failed to register: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			add_action( 'admin_notices', function () use ( $e ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p><strong>Atomora:</strong> ' .
					esc_html__( 'The Gallery Slider widget could not be registered because Elementor\'s beta Atomic Widgets API returned something unexpected. Nothing else on your site was affected.', 'atomora' ) .
					'</p><p><code>' . esc_html( $e->getMessage() ) . '</code></p></div>';
			} );
		}

		// Separate try/catch from Gallery Slider above — a problem loading
		// one widget should never take the other down with it.
		try {
			require_once ATOMORA_PATH . 'includes/widgets/class-product-card.php';

			if ( class_exists( '\Atomora\Widgets\Product_Card' ) ) {
				$widgets_manager->register( new Widgets\Product_Card() );
			}
		} catch ( \Throwable $e ) {
			Admin\Crash_Reporter::instance()->report( 'Product Card registration', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Product Card failed to register: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			add_action( 'admin_notices', function () use ( $e ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p><strong>Atomora:</strong> ' .
					esc_html__( 'The Product Card widget could not be registered because Elementor\'s beta Atomic Widgets API returned something unexpected. Nothing else on your site was affected.', 'atomora' ) .
					'</p><p><code>' . esc_html( $e->getMessage() ) . '</code></p></div>';
			} );
		}

		// Atomora WooCom widgets — 8 fully independent widgets (no
		// shared container), each guarded separately so one failing
		// never blocks the others.
		$atc_widgets = [
			'class-atc-quantity-input.php'       => 'Atc_Quantity_Input',
			'class-atc-quantity-plus.php'         => 'Atc_Quantity_Plus',
			'class-atc-quantity-minus.php'        => 'Atc_Quantity_Minus',
			'class-atc-variations.php'            => 'Atc_Variations',
			'class-atc-variation-buttons.php'     => 'Atc_Variation_Buttons',
			'class-atc-add-to-cart-button.php'    => 'Atc_Add_To_Cart_Button',
			'class-atc-buy-now-button.php'        => 'Atc_Buy_Now_Button',
			'class-atc-product-price.php'         => 'Atc_Product_Price',
			'class-reviews-summary.php'          => 'Reviews_Summary',
			'class-review-item-part.php'         => 'Review_Item_Part',
			'class-review-form-field.php'        => 'Review_Form_Field',
			'class-review-average.php'          => 'Review_Average',
			'class-review-stars.php'            => 'Review_Stars',
			'class-review-count.php'            => 'Review_Count',
			'class-review-breakdown.php'        => 'Review_Breakdown',
		];

		foreach ( $atc_widgets as $file => $class_name ) {
			try {
				require_once ATOMORA_PATH . 'includes/widgets/' . $file;

				$fqcn = '\\Atomora\\Widgets\\' . $class_name;
				if ( class_exists( $fqcn ) ) {
					$widgets_manager->register( new $fqcn() );
				}
			} catch ( \Throwable $e ) {
				Admin\Crash_Reporter::instance()->report( $class_name . ' registration', $e );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Atomora — ' . $class_name . ' failed to register: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				add_action( 'admin_notices', function () use ( $e, $class_name ) {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p><strong>Atomora:</strong> ' .
						esc_html( sprintf(
							/* translators: %s: widget class name */
							__( 'The %s widget could not be registered because Elementor\'s beta Atomic Widgets API returned something unexpected. Nothing else on your site was affected.', 'atomora' ),
							$class_name
						) ) .
						'</p><p><code>' . esc_html( $e->getMessage() ) . '</code></p></div>';
				} );
			}
		}
    }

	public static function on_activation() {
		// Off by default, per plugin requirements: never delete user data
		// unless the site owner explicitly opts in from Settings.
		if ( false === get_option( self::OPTION_DELETE_DATA, false ) ) {
			add_option( self::OPTION_DELETE_DATA, 'no' );
		}
	}

	public static function on_deactivation() {
		// Deactivation never deletes data by itself. Deletion (if the
		// admin opted in) only happens on actual uninstall (see
		// uninstall.php), which is the correct, WordPress-standard place
		// for destructive cleanup.
	}
}