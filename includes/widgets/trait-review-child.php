<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Has_Template;
use Elementor\Modules\AtomicWidgets\Elements\TemplateRenderer\Template_Renderer;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Classes_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-review-context.php';

/**
 * Atomora — shared logic for the Twig-based child widgets of the Reviews
 * List / Reviews Form. Twig means they render client-side in the editor like
 * native atomic widgets (no editor wrapper, no extra spacing).
 *
 * Using classes define get_templates() and may override template_context().
 */
trait Review_Child {
	use Has_Template;

	public function get_categories(): array {
		return [ 'atomora-review' ];
	}

	public static function get_computed_html_tag( array $settings ): string {
		$tag = $settings['html_tag'] ?? 'div';
		return in_array( $tag, [ 'div', 'span', 'p', 'header', 'section', 'article', 'aside', 'footer' ], true ) ? $tag : 'div';
	}

	protected static function child_props(): array {
		return [
			'classes'    => Classes_Prop_Type::make()->default( [] ),
			'attributes' => Attributes_Prop_Type::make(),
			'html_tag'   => String_Prop_Type::make()->enum( [ 'div', 'span', 'p', 'header', 'section', 'article', 'aside', 'footer' ] )->default( 'div' ),
		];
	}

	protected function settings_section(): Section {
		return Section::make()
			->set_id( 'settings' )
			->set_label( esc_html__( 'Settings', 'atomora' ) )
			->set_items( array_filter( [
				Text_Control::bind_to( '_cssid' )
					->set_label( esc_html__( 'ID', 'atomora' ) )
					->set_meta( $this->get_css_id_control_meta() ),
				$this->maybe_classes_control( 'classes' ),
				$this->maybe_attributes_control( 'attributes' ),
			] ) );
	}

	private function maybe_classes_control( string $prop_name ) {
		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Classes_Control' ) ) {
			return null;
		}
		try {
			return Classes_Control::bind_to( $prop_name )->set_label( esc_html__( 'CSS Classes', 'atomora' ) );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private function maybe_attributes_control( string $prop_name ) {
		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Attributes_Control' ) ) {
			return null;
		}
		try {
			return \Elementor\Modules\AtomicWidgets\Controls\Types\Attributes_Control::bind_to( $prop_name )
				->set_label( esc_html__( 'Attributes', 'atomora' ) );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/** Extra Twig variables for the front end (real review data). */
	protected function template_context( array $settings ): array {
		return [];
	}

	public function get_style_depends() {
		wp_register_style( 'atomora-review-parts', ATOMORA_URL . 'assets/css/review-parts.css', [], ATOMORA_VERSION );
		return [ 'atomora-review-parts' ];
	}

	protected function render() {
		try {
			$renderer = Template_Renderer::instance();
			foreach ( array_merge( $this->get_shared_templates(), $this->get_templates() ) as $name => $path ) {
				if ( ! $renderer->is_registered( $name ) ) {
					$renderer->register( $name, $path );
				}
			}

			$settings = $this->get_atomic_settings();
			$context  = [
				'id'             => $this->get_id(),
				'interaction_id' => $this->get_interaction_id(),
				'type'           => $this->get_name(),
				'settings'       => $settings,
				'tag'            => static::get_computed_html_tag( $settings ),
				'base_styles'    => $this->get_base_styles_dictionary(),
			] + $this->template_context( $settings );

			echo $renderer->render( $this->get_main_template(), $context ); // phpcs:ignore WordPress.Security.EscapeOutput
		} catch ( \Throwable $e ) {
			\Atomora\Admin\Crash_Reporter::instance()->report( static::get_element_type() . ' render', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — ' . static::get_element_type() . ' render error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}
}