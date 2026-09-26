<?php
/**
 * `wp pura-theme import` — bring the static site's content into WordPress.
 *
 * Subcommands are idempotent (keyed by slug, file name, or URL) so the command can be re-run.
 *
 * Pages, menu and settings come from the theme itself. Logos come from the theme's assets/images
 * unless --source points at a copy of the legacy static site (public_html on the server), which is
 * also the only place gallery videos (data/gallery.json) can be imported from.
 *
 * @package Pura
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Imports media, menu, pages, gallery videos, and settings from the static site.
 */
final class Pura_Theme_Import_Command {

	/** @var array<string, array{title:string,excerpt:string}> slug => page meta */
	private const PAGES = array(
		'inicio'        => array(
			'title'   => 'Inicio',
			'excerpt' => 'Capoeira para adultos y niños en Cuernavaca, Morelos. Descubre tu fuerza y aprende a darle sentido: lucha, música y juego en una comunidad sostenida por responsabilidad e integridad.',
		),
		'trayectoria'   => array(
			'title'   => 'Trayectoria de Professor Malandro',
			'excerpt' => 'Trayectoria de Professor Malandro, Alfredo Juliano Prince: dos décadas de capoeira entre las Islas Vírgenes, México y Brasil, y la idea de formación que guía Pura Capoeira Cuernavaca.',
		),
		'clases'        => array(
			'title'   => 'Clases de Capoeira en Cuernavaca',
			'excerpt' => 'Clases de capoeira para adultos y niños en Tlaltenango, Cuernavaca. Fuerza, música y juego en una práctica que te reta a conocerte y a crecer con otros.',
		),
		'inscripciones' => array(
			'title'   => 'Inscripciones',
			'excerpt' => 'Inscríbete a Pura Capoeira Cuernavaca. Clase de prueba, mensualidad o paquetes trimestral y anual para adultos y niños. Pago seguro con Stripe.',
		),
		'galeria'       => array(
			'title'   => 'Galería de Videos',
			'excerpt' => 'Videos de clases, rodas, música y eventos de Pura Capoeira Cuernavaca. Así se ve el entrenamiento desde adentro.',
		),
		'tienda'        => array(
			'title'   => 'Tienda',
			'excerpt' => 'Tienda oficial de Pura Capoeira Cuernavaca: playeras, hoodies y accesorios con producción y envío por Printful.',
		),
		'contacto'      => array(
			'title'   => 'Contacto',
			'excerpt' => 'Contacta a Pura Capoeira Cuernavaca. Clases en San Jerónimo 503, Tlaltenango, Cuernavaca, Morelos. WhatsApp, Instagram y Facebook.',
		),
	);

	private string $source = '';

	/**
	 * Import everything, in dependency order.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<path>]
	 * : Directory containing the legacy static site (index.html, assets/, data/). Optional: without
	 * it, logos come from the theme and the gallery import is skipped.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function all( array $args, array $assoc_args ): void {
		$this->media( $args, $assoc_args );
		$this->pages( $args, $assoc_args );
		$this->settings( $args, $assoc_args );
		$this->menu( $args, $assoc_args );
		$this->gallery( $args, $assoc_args );
		WP_CLI::success( 'Import complete.' );
	}

	/**
	 * Sideload logos; set the site logo, site icon, and OG image.
	 *
	 * [--source=<path>]
	 * : Directory containing the legacy static site. Optional: the theme's assets/images are used
	 * for any file the source does not have.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function media( array $args, array $assoc_args ): void {
		$this->resolve_source( $assoc_args );
		$this->require_media_functions();

		$logo = $this->import_local_file( $this->find_image( 'Logo-Pura-Capoeira-Prof-malandro.png' ), 'Pura Capoeira — Professor Malandro' );
		$mark = $this->import_local_file( $this->find_image( 'pura-capoeira-logo.png' ), 'Pura Capoeira' );
		$og   = $this->import_local_file( $this->find_image( 'og-image.jpg' ), 'Pura Capoeira Cuernavaca' );

		if ( $logo ) {
			set_theme_mod( 'custom_logo', $logo );
			update_option( 'site_icon', $logo );
			WP_CLI::log( "media: custom_logo/site_icon = #{$logo}" );
		}
		if ( $mark ) {
			WP_CLI::log( "media: brand mark = #{$mark}" );
		}
		if ( $og && function_exists( 'pura_settings' ) ) {
			Pura\Core\Settings::update( array( 'og_image_id' => $og ) );
			WP_CLI::log( "media: og_image_id = #{$og}" );
		}
	}

	/**
	 * Point the plugin settings at the store and inscriptions pages.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function settings( array $args, array $assoc_args ): void {
		if ( ! function_exists( 'pura_settings' ) ) {
			WP_CLI::warning( 'settings: plugin not active; skipping.' );
			return;
		}

		$store        = get_page_by_path( 'tienda' );
		$inscriptions = get_page_by_path( 'inscripciones' );

		Pura\Core\Settings::update(
			array(
				'store_page_id'        => $store ? $store->ID : 0,
				'inscriptions_page_id' => $inscriptions ? $inscriptions->ID : 0,
			)
		);
		WP_CLI::log( 'settings: page ids set.' );
	}

	/**
	 * Create the primary navigation (a wp_navigation post) with the seven pages.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function menu( array $args, array $assoc_args ): void {
		$links = array();
		foreach ( self::PAGES as $slug => $meta ) {
			$page = get_page_by_path( $slug );
			if ( ! $page ) {
				continue;
			}
			$label   = 'inicio' === $slug ? 'Inicio' : ( 'trayectoria' === $slug ? 'Trayectoria' : ( 'clases' === $slug ? 'Clases' : ( 'inscripciones' === $slug ? 'Inscripciones' : ( 'galeria' === $slug ? 'Galería' : ( 'tienda' === $slug ? 'Tienda' : 'Contacto' ) ) ) ) );
			$links[] = sprintf(
				'<!-- wp:navigation-link {"label":"%s","type":"page","id":%d,"url":"%s","kind":"post-type"} /-->',
				esc_attr( $label ),
				$page->ID,
				esc_url( get_permalink( $page ) )
			);
		}

		$whatsapp = function_exists( 'pura_whatsapp_url' ) ? pura_whatsapp_url() : 'https://wa.me/18056385603';
		$links[]  = sprintf(
			'<!-- wp:navigation-link {"label":"Contactar por WhatsApp","url":"%s","kind":"custom","opensInNewTab":true,"className":"overlay-only"} /-->',
			esc_url( $whatsapp )
		);

		$content  = implode( "\n", $links );
		$existing = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => 'publish',
				'title'          => 'Navegación principal',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( $existing ) {
			wp_update_post( array( 'ID' => $existing[0], 'post_content' => $content ) );
			WP_CLI::log( 'menu: updated #' . $existing[0] );
			return;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Navegación principal',
				'post_content' => $content,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( 'menu: ' . $id->get_error_message() );
			return;
		}
		WP_CLI::log( 'menu: created #' . $id );
	}

	/**
	 * Create or update the seven pages from the theme's page patterns, set the front page.
	 *
	 * [--source=<path>]
	 * : Directory containing the legacy static site.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function pages( array $args, array $assoc_args ): void {
		$registry = WP_Block_Patterns_Registry::get_instance();

		foreach ( self::PAGES as $slug => $meta ) {
			$pattern = $registry->get_registered( 'pura-capoeira/page-' . $slug );
			if ( ! $pattern ) {
				WP_CLI::warning( "pages: pattern pura-capoeira/page-{$slug} not registered; skipping." );
				continue;
			}

			$blocks  = parse_blocks( (string) $pattern['content'] );
			$blocks  = function_exists( 'resolve_pattern_blocks' ) ? resolve_pattern_blocks( $blocks ) : $blocks;
			$content = serialize_blocks( $blocks );

			$existing = get_page_by_path( $slug );
			$postarr  = array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $meta['title'],
				'post_excerpt' => $meta['excerpt'],
				'post_content' => $content,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			);

			if ( $existing ) {
				$postarr['ID'] = $existing->ID;
				$id            = wp_update_post( wp_slash( $postarr ), true );
			} else {
				$id = wp_insert_post( wp_slash( $postarr ), true );
			}

			if ( is_wp_error( $id ) ) {
				WP_CLI::warning( "pages: {$slug}: " . $id->get_error_message() );
				continue;
			}
			WP_CLI::log( sprintf( 'pages: %s → #%d (%s)', $slug, $id, $existing ? 'updated' : 'created' ) );

			if ( 'inicio' === $slug ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $id );
			}
		}

		// Retire the sample page/post if they still exist.
		foreach ( array( 'sample-page', 'hello-world' ) as $sample ) {
			$post = get_page_by_path( $sample, OBJECT, array( 'page', 'post' ) );
			if ( $post ) {
				wp_trash_post( $post->ID );
			}
		}

		flush_rewrite_rules();
	}

	/**
	 * Import data/gallery.json into the gallery_video post type.
	 *
	 * [--source=<path>]
	 * : Directory containing the legacy static site (the one with data/gallery.json). Required for
	 * this subcommand; without it nothing is imported.
	 *
	 * @param string[]              $args       Positional args.
	 * @param array<string, string> $assoc_args Named args.
	 */
	public function gallery( array $args, array $assoc_args ): void {
		if ( ! $this->resolve_source( $assoc_args ) ) {
			WP_CLI::warning( 'gallery: no legacy source directory; pass --source=/path/to/public_html to import videos. Skipping.' );
			return;
		}
		$this->require_media_functions();

		if ( ! post_type_exists( 'gallery_video' ) ) {
			WP_CLI::warning( 'gallery: gallery_video post type missing (plugin inactive?); skipping.' );
			return;
		}

		$file = $this->source . '/data/gallery.json';
		if ( ! is_readable( $file ) ) {
			WP_CLI::warning( "gallery: {$file} not found; skipping." );
			return;
		}

		$items = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local file.
		if ( ! is_array( $items ) ) {
			WP_CLI::warning( 'gallery: invalid JSON; skipping.' );
			return;
		}

		$created = 0;
		$skipped = 0;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['title'] ) ) {
				continue;
			}
			$key      = md5( (string) $item['title'] . '|' . (string) ( $item['url'] ?? '' ) );
			$existing = get_posts(
				array(
					'post_type'      => 'gallery_video',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_pura_import_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off import.
					'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			if ( $existing ) {
				// Backfill a missing thumbnail on re-runs.
				if ( ! has_post_thumbnail( (int) $existing[0] ) && ! empty( $item['thumbnail'] ) ) {
					$thumb = $this->import_remote_image( esc_url_raw( (string) $item['thumbnail'] ), (int) $existing[0], (string) $item['title'] );
					if ( $thumb ) {
						set_post_thumbnail( (int) $existing[0], $thumb );
						WP_CLI::log( 'gallery: thumbnail added to #' . $existing[0] );
					}
				}
				++$skipped;
				continue;
			}

			$date = ! empty( $item['date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $item['date'] ) ?: time() ) : current_time( 'mysql' );
			$id   = wp_insert_post(
				array(
					'post_type'    => 'gallery_video',
					'post_status'  => 'publish',
					'post_title'   => sanitize_text_field( (string) $item['title'] ),
					'post_excerpt' => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
					'post_date'    => $date,
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				WP_CLI::warning( 'gallery: ' . $id->get_error_message() );
				continue;
			}

			update_post_meta( $id, '_pura_import_key', $key );
			if ( ! empty( $item['url'] ) ) {
				update_post_meta( $id, 'pura_video_url', esc_url_raw( (string) $item['url'] ) );
			}
			if ( ! empty( $item['category'] ) ) {
				wp_set_object_terms( $id, sanitize_text_field( (string) $item['category'] ), 'gallery_category' );
			}
			if ( ! empty( $item['thumbnail'] ) ) {
				$thumb = $this->import_remote_image( esc_url_raw( (string) $item['thumbnail'] ), $id, (string) $item['title'] );
				if ( $thumb ) {
					set_post_thumbnail( $id, $thumb );
				}
			}
			++$created;
			WP_CLI::log( 'gallery: ' . $item['title'] . ' → #' . $id );
		}

		WP_CLI::log( "gallery: created {$created}, skipped {$skipped}." );
	}

	// ---- Helpers -------------------------------------------------------------------------

	/**
	 * Import a file from disk into the Media Library once (keyed by its basename).
	 */
	private function import_local_file( string $path, string $title ): int {
		if ( ! is_readable( $path ) ) {
			WP_CLI::warning( "media: {$path} not found." );
			return 0;
		}

		$basename = basename( $path );
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_pura_source_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off import.
				'meta_value'     => $basename, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $existing ) {
			return (int) $existing[0];
		}

		$upload = wp_upload_bits( $basename, null, (string) file_get_contents( $path ) ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local file.
		if ( ! empty( $upload['error'] ) ) {
			WP_CLI::warning( 'media: ' . $upload['error'] );
			return 0;
		}

		$type = wp_check_filetype( $upload['file'] );
		$id   = wp_insert_attachment(
			array(
				'post_mime_type' => (string) $type['type'],
				'post_title'     => $title,
				'post_status'    => 'inherit',
				'guid'           => (string) $upload['url'],
			),
			$upload['file']
		);
		if ( ! $id ) {
			return 0;
		}

		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, '_pura_source_file', $basename );
		update_post_meta( $id, '_wp_attachment_image_alt', $title );

		return $id;
	}

	/**
	 * Download a remote image (extension-less URLs included) and attach it to a post.
	 */
	private function import_remote_image( string $url, int $post_id, string $title ): int {
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			WP_CLI::warning( "media: {$url}: " . $tmp->get_error_message() );
			return 0;
		}

		$type = wp_get_image_mime( $tmp );
		$ext  = 'image/png' === $type ? 'png' : ( 'image/webp' === $type ? 'webp' : 'jpg' );
		$name = sanitize_title( $title ?: 'imagen' ) . '-' . substr( md5( $url ), 0, 8 ) . '.' . $ext;

		$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), $post_id, $title );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			WP_CLI::warning( "media: {$url}: " . $id->get_error_message() );
			return 0;
		}

		update_post_meta( (int) $id, '_wp_attachment_image_alt', $title );

		return (int) $id;
	}

	private function require_media_functions(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	/**
	 * Locate a copy of the legacy static site, if any.
	 *
	 * An explicit --source that does not exist is an error; otherwise the wp-env mapping
	 * (wp-content/pura-source) is tried and a missing source is simply reported as absent.
	 *
	 * @param array<string, string> $assoc_args Named args.
	 * @return bool Whether a source directory is available in $this->source.
	 */
	private function resolve_source( array $assoc_args ): bool {
		if ( '' !== $this->source ) {
			return true;
		}

		$explicit = (string) ( $assoc_args['source'] ?? '' );
		if ( '' !== $explicit ) {
			if ( ! is_dir( $explicit ) ) {
				WP_CLI::error( "Source directory not found: {$explicit}" );
			}
			$this->source = rtrim( $explicit, '/' );
			return true;
		}

		$mapped = WP_CONTENT_DIR . '/pura-source';
		if ( is_dir( $mapped ) ) {
			$this->source = $mapped;
			return true;
		}

		return false;
	}

	/**
	 * Path of an image, preferring the legacy source and falling back to the theme's own copy.
	 */
	private function find_image( string $basename ): string {
		if ( '' !== $this->source && is_readable( $this->source . '/assets/images/' . $basename ) ) {
			return $this->source . '/assets/images/' . $basename;
		}

		return PURA_THEME_DIR . '/assets/images/' . $basename;
	}
}

WP_CLI::add_command( 'pura-theme import', Pura_Theme_Import_Command::class );
