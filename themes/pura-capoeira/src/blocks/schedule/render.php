<?php
/**
 * pura/schedule — rows for one group.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

if ( ! function_exists( 'pura_schedule' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="pura-notice">' . esc_html__( 'Activa el plugin Pura Capoeira Core para mostrar este bloque.', 'pura' ) . '</p>';
	}
	return;
}

$group = 'kids' === ( $attributes['group'] ?? 'adult' ) ? 'kids' : 'adult';
$rows  = pura_schedule( $group );

$wrapper = get_block_wrapper_attributes( array( 'class' => 'schedule schedule--' . $group ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! $rows ) : ?>
		<div class="info-card__row"><span><?php esc_html_e( 'Horario por confirmar', 'pura' ); ?></span><span></span></div>
	<?php else : ?>
		<?php foreach ( $rows as $row ) : ?>
			<div class="info-card__row">
				<span><?php echo esc_html( (string) $row['day'] ); ?></span>
				<span><?php echo esc_html( (string) $row['start'] ); ?> – <?php echo esc_html( (string) $row['end'] ); ?></span>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
