<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-reviews-shared.php';

/**
 * Atomora — Rating Summary
 */
class Reviews_Summary extends Atomic_Widget_Base {
	use Reviews_Shared;

	public static $widget_description = 'Average rating and star breakdown for a WooCommerce product.';

	public static function get_element_type(): string {
		return 'e-atomora-reviews-summary';
	}

	public function get_title() {
		return esc_html__( 'Rating Summary', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-product-rating';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'rating', 'product', 'summary' ];
	}

	protected static function define_props_schema(): array {
		return self::common_props() + [
			'summary_title' => String_Prop_Type::make()->default( 'Customer reviews' ),
		];
	}

	protected function define_atomic_controls(): array {
		return [
			$this->source_section(),
			Section::make()
				->set_id( 'content' )
				->set_label( esc_html__( 'Content', 'atomora' ) )
				->set_items( [
					Text_Control::bind_to( 'summary_title' )->set_label( esc_html__( 'Title', 'atomora' ) ),
				] ),
			$this->settings_section(),
		];
	}

	private function render_summary( \WC_Product $product, string $title ): void {
		$average = (float) $product->get_average_rating();
		$count   = (int) $product->get_review_count();
		?>
		<div class="atomora-reviews__summary">
			<?php if ( '' !== $title ) : ?>
				<h3 class="atomora-reviews__title"><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>
			<div class="atomora-reviews__overview">
				<span class="atomora-reviews__average"><?php echo esc_html( number_format_i18n( $average, 1 ) ); ?></span>
				<div>
					<?php echo self::stars_html( $average ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="atomora-reviews__count">
						<?php
						echo esc_html( sprintf(
							/* translators: %s: number of reviews */
							_n( 'Based on %s review', 'Based on %s reviews', $count, 'atomora' ),
							number_format_i18n( $count )
						) );
						?>
					</span>
				</div>
			</div>
			<ul class="atomora-reviews__breakdown">
				<?php for ( $star = 5; $star >= 1; $star-- ) : ?>
					<?php
					$star_count = (int) $product->get_rating_count( $star );
					$percent    = $count > 0 ? round( ( $star_count / $count ) * 100 ) : 0;
					?>
					<li class="atomora-reviews__row">
						<span class="atomora-reviews__row-label"><?php echo esc_html( sprintf( /* translators: %d: star number */ __( '%d star', 'atomora' ), $star ) ); ?></span>
						<span class="atomora-reviews__bar"><span class="atomora-reviews__bar-fill" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></span></span>
						<span class="atomora-reviews__row-count"><?php echo esc_html( (string) $star_count ); ?></span>
					</li>
				<?php endfor; ?>
			</ul>
		</div>
		<?php
	}

	private function render_widget(): void {
		$settings = $this->get_atomic_settings();
		$product  = $this->get_product_or_notice( $settings );
		if ( ! $product ) {
			return;
		}

		$root_tag = $this->prepare_root( $settings, $product );
		?>
		<<?php echo esc_html( $root_tag ); ?> <?php $this->print_render_attribute_string( '_wrapper' ); ?>>
			<?php $this->render_summary( $product, (string) ( $settings['summary_title'] ?? '' ) ); ?>
		</<?php echo esc_html( $root_tag ); ?>>
		<?php
	}
}
