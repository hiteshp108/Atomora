<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
use Elementor\Modules\AtomicWidgets\Styles\Style_Definition;
use Elementor\Modules\AtomicWidgets\Styles\Style_Variant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-review-part.php';

/**
 * Atomora — Review Breakdown (5★ … 1★ bars).
 */
class Review_Breakdown extends Atomic_Widget_Base {
	use Review_Part;

	public static $widget_description = 'Star-by-star rating distribution bars for a WooCommerce product.';

	public static function get_element_type(): string {
		return 'e-atomora-review-breakdown';
	}

	public function get_title() {
		return esc_html__( 'Review Breakdown', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-progress-tracker';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'rating', 'breakdown', 'distribution', 'bars' ];
	}

	private static function part_slug(): string {
		return 'breakdown';
	}

	protected function define_base_styles(): array {
		return [
			'base' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'font-size', Size_Prop_Type::generate( [ 'size' => 14, 'unit' => 'px' ] ) )
				),
		];
	}

	protected static function define_props_schema(): array {
		return self::common_props() + [
			'show_counts' => Boolean_Prop_Type::make()->default( true ),
		];
	}

	protected function define_atomic_controls(): array {
		return [
			$this->source_section(),
			Section::make()
				->set_id( 'content' )
				->set_label( esc_html__( 'Content', 'atomora' ) )
				->set_items( [
					Switch_Control::bind_to( 'show_counts' )
						->set_label( esc_html__( 'Show counts', 'atomora' ) ),
				] ),
			$this->settings_section(),
		];
	}

	private function render_part( \WC_Product $product, array $settings ): void {
		$total       = (int) $product->get_review_count();
		$show_counts = ! isset( $settings['show_counts'] ) || $settings['show_counts'];
		?>
		<ul class="atomora-review__rows">
			<?php for ( $star = 5; $star >= 1; $star-- ) : ?>
				<?php
				$star_count = (int) $product->get_rating_count( $star );
				$percent    = $total > 0 ? round( ( $star_count / $total ) * 100 ) : 0;
				?>
				<li class="atomora-review__row">
					<span class="atomora-review__row-label"><?php echo esc_html( sprintf( /* translators: %d: star number */ __( '%d star', 'atomora' ), $star ) ); ?></span>
					<span class="atomora-review__bar"><span class="atomora-review__bar-fill" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></span></span>
					<?php if ( $show_counts ) : ?>
						<span class="atomora-review__row-count"><?php echo esc_html( (string) $star_count ); ?></span>
					<?php endif; ?>
				</li>
			<?php endfor; ?>
		</ul>
		<?php
	}
}
