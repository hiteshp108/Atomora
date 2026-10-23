<?php
/**
 * Fires only when the plugin is deleted from wp-admin ▸ Plugins (never on
 * simple deactivation). Respects the "delete data on uninstall" toggle,
 * which defaults to OFF so nothing is ever removed unless the site owner
 * opted in from Settings → Atomora.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$atomora_should_delete = get_option( 'atomora_delete_data_on_uninstall', 'no' );

if ( 'yes' !== $atomora_should_delete ) {
	return;
}

delete_option( 'atomora_delete_data_on_uninstall' );

// Crash reporting options — only removed here, on opted-in uninstall.
// The in-plugin "Clear all reports" button (Settings → Atomora →
// Crash Reports) is the manual/on-demand way to wipe these at any time
// without uninstalling the plugin.
delete_option( 'atomora_crash_reports' );
delete_option( 'atomora_crash_email_throttle' );
delete_option( 'atomora_crash_email_to' );

// Feature toggles and editor-tool settings.
foreach ( [
	'atomora_hide_elementor_ads',
	'atomora_design_system_tools',
	'atomora_design_system_row_numbers',
	'atomora_design_system_bulk_actions',
	'atomora_design_system_groups_data',
	'atomora_navigator_move',
	'atomora_grid_overlay',
	'atomora_full_class_names',
	'atomora_class_highlighter',
	'atomora_tab_navigator',
] as $atomora_option ) {
	delete_option( $atomora_option );
}

// Atomora stores no transients or custom tables. Widget instances live inside
// each page's own Elementor data and are intentionally never touched here,
// since deleting a design-tool plugin should never delete page content.
