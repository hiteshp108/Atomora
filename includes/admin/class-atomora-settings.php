<?php
namespace Atomora\Admin;

use Atomora\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A single, minimal settings screen. Deliberately kept to one option so it
 * stays lightweight and doesn't add its own heavy admin JS/CSS.
 */
class Settings {

	const PAGE_SLUG = 'atomora-settings';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_settings_page() {
		add_options_page(
			__( 'Atomora Settings', 'atomora' ),
			__( 'Atomora', 'atomora' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings() {
		register_setting(
			'atomora_settings_group',
			Loader::OPTION_DELETE_DATA,
			[
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					return 'yes' === $value ? 'yes' : 'no';
				},
				'default'           => 'no',
			]
		);

		register_setting(
			'atomora_settings_group',
			Loader::OPTION_HIDE_ELEMENTOR_ADS,
			[
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					return 'yes' === $value ? 'yes' : 'no';
				},
				'default'           => 'no',
			]
		);

		register_setting(
			'atomora_settings_group',
			Loader::OPTION_DESIGN_SYSTEM_TOOLS,
			[
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					return 'yes' === $value ? 'yes' : 'no';
				},
				'default'           => 'no',
			]
		);

		register_setting(
			'atomora_settings_group',
			Loader::OPTION_DESIGN_SYSTEM_ROW_NUMBERS,
			[
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					return 'yes' === $value ? 'yes' : 'no';
				},
				'default'           => 'no',
			]
		);

		register_setting(
			'atomora_settings_group',
			Loader::OPTION_DESIGN_SYSTEM_BULK_ACTIONS,
			[
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					return 'yes' === $value ? 'yes' : 'no';
				},
				'default'           => 'no',
			]
		);


		// Editor UX enhancements — all default to "on" since they're
		// purely editor-side conveniences with individual off-switches.
		foreach ( [
			Loader::OPTION_FULL_CLASS_NAMES,
			Loader::OPTION_CLASS_HIGHLIGHTER,
			Loader::OPTION_TAB_NAVIGATOR,
		] as $option ) {
			register_setting(
				'atomora_settings_group',
				$option,
				[
					'type'              => 'string',
					'sanitize_callback' => function ( $value ) {
						return 'yes' === $value ? 'yes' : 'no';
					},
					'default'           => 'yes',
				]
			);
		}
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$delete_data       = get_option( Loader::OPTION_DELETE_DATA, 'no' );
		$hide_ads          = get_option( Loader::OPTION_HIDE_ELEMENTOR_ADS, 'no' );
		$full_class_names  = get_option( Loader::OPTION_FULL_CLASS_NAMES, 'yes' );
		$class_highlighter = get_option( Loader::OPTION_CLASS_HIGHLIGHTER, 'yes' );
		$tab_navigator     = get_option( Loader::OPTION_TAB_NAVIGATOR, 'yes' );
		$design_sys_tools  = get_option( Loader::OPTION_DESIGN_SYSTEM_TOOLS, 'no' );
		$design_sys_nums   = get_option( Loader::OPTION_DESIGN_SYSTEM_ROW_NUMBERS, 'no' );
		$design_sys_bulk   = get_option( Loader::OPTION_DESIGN_SYSTEM_BULK_ACTIONS, 'no' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Atomora', 'atomora' ); ?></h1>
			<p><?php esc_html_e( 'Version', 'atomora' ); ?>: <?php echo esc_html( ATOMORA_VERSION ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . \Atomora\Admin\Crash_Reporter::PAGE_SLUG ) ); ?>">
					<?php esc_html_e( 'View crash reports →', 'atomora' ); ?>
				</a>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'atomora_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Elementor promotions', 'atomora' ); ?></th>
						<td>
							<label for="atomora_hide_elementor_ads">
								<input
									type="checkbox"
									id="atomora_hide_elementor_ads"
									name="<?php echo esc_attr( Loader::OPTION_HIDE_ELEMENTOR_ADS ); ?>"
									value="yes"
									<?php checked( $hide_ads, 'yes' ); ?>
								/>
								<?php esc_html_e( 'Hide Elementor\'s own promotional/upsell UI (AI nags, "Go Pro" banners, image-optimizer ads) in wp-admin and the editor.', 'atomora' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Off by default. Note: Elementor has no official API for this — it\'s an open, unresolved feature request on their end. This works by hiding specific known Elementor ad elements by name; a future Elementor update could rename something and require this to be updated.', 'atomora' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Design System quick-reorder', 'atomora' ); ?></th>
						<td>
							<label for="atomora_design_system_tools">
								<input
									type="checkbox"
									id="atomora_design_system_tools"
									name="<?php echo esc_attr( Loader::OPTION_DESIGN_SYSTEM_TOOLS ); ?>"
									value="yes"
									<?php checked( $design_sys_tools, 'yes' ); ?>
								/>
								<?php esc_html_e( 'Add "Move to top / up / down / bottom / to position" and "Duplicate" to the three-dot menu on Global Classes and Variables rows in Elementor\'s Design System panel.', 'atomora' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Off by default. Experimental: Elementor has no official menu API for this panel, but the moves and duplication themselves use real Elementor mechanisms. Classes are moved/duplicated by calling directly into Elementor\'s own internal (undocumented) editor store, the same actions its own UI uses. Variables are moved/duplicated via Elementor\'s own public variables service (the same functions its Save and Create actions call) — move falls back to simulating keyboard shortcuts (Space/Arrow keys) only if the direct call ever fails. Duplicating a class only works once that class has been opened at least once this session (Elementor only loads a class\'s styles on demand) — otherwise a clear message explains why. If a future Elementor update changes any of this, the feature disables itself and shows an on-screen warning rather than failing silently — normal dragging is never affected either way.', 'atomora' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Design System row numbers', 'atomora' ); ?></th>
						<td>
							<label for="atomora_design_system_row_numbers">
								<input
									type="checkbox"
									id="atomora_design_system_row_numbers"
									name="<?php echo esc_attr( Loader::OPTION_DESIGN_SYSTEM_ROW_NUMBERS ); ?>"
									value="yes"
									<?php checked( $design_sys_nums, 'yes' ); ?>
								/>
								<?php esc_html_e( 'Show a Sr. No. badge next to each row in the Classes and Variables tabs of Elementor\'s Design System panel.', 'atomora' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Off by default. Purely cosmetic — an overlay showing each row\'s current position, independent of the quick-reorder tools above and safe to enable on its own. Stays correct through dragging, searching/filtering, and reordering either way.', 'atomora' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Design System bulk move', 'atomora' ); ?></th>
						<td>
							<label for="atomora_design_system_bulk_actions">
								<input
									type="checkbox"
									id="atomora_design_system_bulk_actions"
									name="<?php echo esc_attr( Loader::OPTION_DESIGN_SYSTEM_BULK_ACTIONS ); ?>"
									value="yes"
									<?php checked( $design_sys_bulk, 'yes' ); ?>
								/>
								<?php esc_html_e( 'Add a checkbox to each Classes/Variables row with a floating toolbar to move or delete the selected items.', 'atomora' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Off by default. Move/delete for Classes uses direct calls into Elementor\'s own store (reliable) — class deletion is staged and still needs "Save changes" in the panel to persist. Variables move and delete both use Elementor\'s own variables service directly (the same function its Save button calls) and take effect immediately, not staged — a slower keyboard-simulated fallback is used only if that direct call ever fails.', 'atomora' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Editor enhancements', 'atomora' ); ?></th>
						<td>
							<label for="atomora_full_class_names">
								<input
									type="checkbox"
									id="atomora_full_class_names"
									name="<?php echo esc_attr( Loader::OPTION_FULL_CLASS_NAMES ); ?>"
									value="yes"
									<?php checked( $full_class_names, 'yes' ); ?>
								/>
								<?php esc_html_e( 'Display full class names (no truncation) in the Style tab.', 'atomora' ); ?>
							</label>
							<br/>
							<label for="atomora_class_highlighter">
								<input
									type="checkbox"
									id="atomora_class_highlighter"
									name="<?php echo esc_attr( Loader::OPTION_CLASS_HIGHLIGHTER ); ?>"
									value="yes"
									<?php checked( $class_highlighter, 'yes' ); ?>
								/>
								<?php esc_html_e( 'Class Highlighter — outline widgets on canvas sharing a class.', 'atomora' ); ?>
							</label>
							<br/>
							<label for="atomora_tab_navigator">
								<input
									type="checkbox"
									id="atomora_tab_navigator"
									name="<?php echo esc_attr( Loader::OPTION_TAB_NAVIGATOR ); ?>"
									value="yes"
									<?php checked( $tab_navigator, 'yes' ); ?>
								/>
								<?php esc_html_e( 'Tab Navigator — search bar + expand/collapse toggle for Style sections.', 'atomora' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'All on by default. Editor-only — none of this affects your live site output.', 'atomora' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'On uninstall', 'atomora' ); ?></th>
						<td>
							<label for="atomora_delete_data">
								<input
									type="checkbox"
									id="atomora_delete_data"
									name="<?php echo esc_attr( Loader::OPTION_DELETE_DATA ); ?>"
									value="yes"
									<?php checked( $delete_data, 'yes' ); ?>
								/>
								<?php esc_html_e( 'Permanently delete all Atomora settings when this plugin is deleted from the Plugins screen.', 'atomora' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Off by default. Widgets you have already placed on pages are never affected either way — this only controls the plugin\'s own stored options.', 'atomora' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}