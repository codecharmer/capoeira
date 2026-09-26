<?php
/**
 * pura/gallery — server-rendered video cards with category pills.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @package Pura
 */

declare( strict_types=1 );

$show_filters  = ! isset( $attributes['showFilters'] ) || ! empty( $attributes['showFilters'] );
$limit         = max( 1, min( 200, (int) ( $attributes['limit'] ?? 48 ) ) );
$fallback_url  = esc_url_raw( (string) ( $attributes['fallbackUrl'] ?? '' ) );
$fallback_text = (string) ( $attributes['fallbackText'] ?? '' );

$videos = post_type_exists( 'gallery_video' ) ? get_posts(
	array(
		'post_type'              => 'gallery_video',
		'post_status'            => 'publish',
		'posts_per_page'         => $limit,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => true,
	)
) : array();

$terms = ( $show_filters && taxonomy_exists( 'gallery_category' ) ) ? get_terms(
	array(
		'taxonomy'   => 'gallery_category',
		'hide_empty' => true,
		'orderby'    => 'name',
	)
) : array();
$terms = is_array( $terms ) ? $terms : array();

$wrapper = get_block_wrapper_attributes( array( 'class' => 'gallery' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $videos && $terms ) : ?>
		<div class="gallery-filters reveal" role="group" aria-label="<?php esc_attr_e( 'Filtros de galería', 'pura' ); ?>">
			<button type="button" class="filter-pill is-active" data-js="gallery-filter" data-category="" aria-pressed="true"><?php esc_html_e( 'Todos', 'pura' ); ?></button>
			<?php foreach ( $terms as $term ) : ?>
				<button type="button" class="filter-pill" data-js="gallery-filter" data-category="<?php echo esc_attr( $term->slug ); ?>" aria-pressed="false"><?php echo esc_html( $term->name ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $videos ) : ?>
		<div class="gallery-grid" data-js="gallery-grid">
			<?php
			foreach ( $videos as $index => $video ) :
				$video_terms = get_the_terms( $video, 'gallery_category' );
				$video_terms = is_array( $video_terms ) ? $video_terms : array();
				$slugs       = implode( ' ', wp_list_pluck( $video_terms, 'slug' ) );
				$cat_name    = $video_terms ? $video_terms[0]->name : '';
				$url         = (string) get_post_meta( $video->ID, 'pura_video_url', true );
				$thumb       = get_the_post_thumbnail_url( $video, 'large' );
				$date        = get_the_date( 'j \d\e F \d\e Y', $video );
				$title       = get_the_title( $video );
				$desc        = $video->post_excerpt;
				?>
				<article class="video-card reveal" data-category="<?php echo esc_attr( $slugs ); ?>">
					<?php if ( '' !== $url ) : ?>
						<a class="video-card__thumb-link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( sprintf( __( 'Ver video: %s', 'pura' ), $title ) ); ?>">
					<?php endif; ?>
					<div class="video-card__thumb" <?php if ( $thumb ) : ?>style="background-image:url('<?php echo esc_url( $thumb ); ?>')"<?php endif; ?>>
						<span class="video-card__play" aria-hidden="true">▶</span>
					</div>
					<?php if ( '' !== $url ) : ?>
						</a>
					<?php endif; ?>
					<div class="video-card__body">
						<?php if ( '' !== $cat_name ) : ?><span class="video-card__cat"><?php echo esc_html( $cat_name ); ?></span><?php endif; ?>
						<h3 class="video-card__title"><?php echo esc_html( $title ); ?></h3>
						<span class="video-card__date"><?php echo esc_html( $date ); ?></span>
						<?php if ( '' !== $desc ) : ?><p class="video-card__desc"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
						<?php if ( '' !== $url ) : ?>
							<a class="video-card__link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver video', 'pura' ); ?> <span aria-hidden="true">→</span></a>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php elseif ( '' !== $fallback_url ) : ?>
		<div class="gallery-fallback">
			<span class="overline"><?php esc_html_e( 'Galería', 'pura' ); ?></span>
			<h3 style="margin:1rem 0;"><?php esc_html_e( 'Nuestra galería está disponible en iCloud', 'pura' ); ?></h3>
			<p><?php esc_html_e( 'Haz clic abajo para ver videos recientes de clases, rodas, entrenamientos y eventos.', 'pura' ); ?></p>
			<a class="btn btn--blue" href="<?php echo esc_url( $fallback_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $fallback_text ); ?></a>
		</div>
	<?php endif; ?>

	<?php if ( $videos && '' !== $fallback_url ) : ?>
		<div class="icloud-banner reveal">
			<span class="overline" style="color:rgba(255,215,0,0.9);">iCloud</span>
			<h3><?php echo esc_html( $fallback_text ); ?></h3>
			<p><?php esc_html_e( 'Accede a todos los videos del álbum compartido con clases, rodas, música, eventos y entrenamientos.', 'pura' ); ?></p>
			<a class="btn btn--primary" href="<?php echo esc_url( $fallback_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Abrir álbum de iCloud', 'pura' ); ?></a>
		</div>
	<?php endif; ?>
</div>
