<?php
/**
 * `gallery_video` post type + `gallery_category` taxonomy. Replaces data/gallery.json.
 *
 * Videos link out (iCloud, YouTube…), so the type has no single view.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Gallery_Post_Type {

	public const POST_TYPE = 'gallery_video';
	public const TAXONOMY  = 'gallery_category';
	public const META_URL  = 'pura_video_url';

	private const NONCE = 'pura_gallery_video_meta';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Videos', 'pura' ),
					'singular_name'      => __( 'Video', 'pura' ),
					'add_new'            => __( 'Añadir video', 'pura' ),
					'add_new_item'       => __( 'Añadir video', 'pura' ),
					'edit_item'          => __( 'Editar video', 'pura' ),
					'new_item'           => __( 'Nuevo video', 'pura' ),
					'all_items'          => __( 'Videos', 'pura' ),
					'search_items'       => __( 'Buscar videos', 'pura' ),
					'not_found'          => __( 'No hay videos.', 'pura' ),
					'featured_image'     => __( 'Miniatura', 'pura' ),
					'set_featured_image' => __( 'Elegir miniatura', 'pura' ),
				),
				'description'         => __( 'Videos de clases, rodas, música y eventos para la galería.', 'pura' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'pura',
				'show_in_rest'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-video-alt3',
				'supports'            => array( 'title', 'excerpt', 'thumbnail' ),
				'capability_type'     => 'post',
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Categorías de video', 'pura' ),
					'singular_name' => __( 'Categoría', 'pura' ),
					'all_items'     => __( 'Todas las categorías', 'pura' ),
					'edit_item'     => __( 'Editar categoría', 'pura' ),
					'add_new_item'  => __( 'Añadir categoría', 'pura' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'rewrite'           => false,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_URL,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => static fn () => current_user_can( 'edit_posts' ),
			)
		);

		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'pura_video_url',
			__( 'Enlace del video', 'pura' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( \WP_Post $post ): void {
		$url = (string) get_post_meta( $post->ID, self::META_URL, true );
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		?>
		<p>
			<label for="pura_video_url" class="screen-reader-text"><?php esc_html_e( 'URL del video', 'pura' ); ?></label>
			<input type="url" id="pura_video_url" name="<?php echo esc_attr( self::META_URL ); ?>" class="large-text code" value="<?php echo esc_attr( $url ); ?>" placeholder="https://" />
		</p>
		<p class="description"><?php esc_html_e( 'Enlace al video o al álbum compartido. La miniatura se toma de la imagen destacada.', 'pura' ); ?></p>
		<?php
	}

	public function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$url = isset( $_POST[ self::META_URL ] ) ? esc_url_raw( wp_unslash( $_POST[ self::META_URL ] ) ) : '';
		if ( '' === $url ) {
			delete_post_meta( $post_id, self::META_URL );
		} else {
			update_post_meta( $post_id, self::META_URL, $url );
		}
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['thumb'] = __( 'Miniatura', 'pura' );
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['video_url'] = __( 'Enlace', 'pura' );
			}
		}

		return $new;
	}

	public function column_content( string $column, int $post_id ): void {
		if ( 'thumb' === $column ) {
			echo get_the_post_thumbnail( $post_id, array( 80, 45 ) );
		}
		if ( 'video_url' === $column ) {
			$url = (string) get_post_meta( $post_id, self::META_URL, true );
			if ( '' !== $url ) {
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_parse_url( $url, PHP_URL_HOST ) ?: $url ) . '</a>';
			}
		}
	}
}
