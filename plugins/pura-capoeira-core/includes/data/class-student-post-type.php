<?php
/**
 * `pura_student`: one post per student (keyed by lowercase email). Profile data lives here only.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Student_Post_Type {

	public const POST_TYPE = 'pura_student';

	/** @var array<string, array{type:string,label:string}> */
	public const FIELDS = array(
		'_pura_email'             => array(
			'type'  => 'string',
			'label' => 'Correo',
		),
		'_pura_first_name'        => array(
			'type'  => 'string',
			'label' => 'Nombre(s)',
		),
		'_pura_last_name'         => array(
			'type'  => 'string',
			'label' => 'Apellidos',
		),
		'_pura_parent_name'       => array(
			'type'  => 'string',
			'label' => 'Padre/madre/tutor',
		),
		'_pura_phone'             => array(
			'type'  => 'string',
			'label' => 'Teléfono',
		),
		'_pura_parent_phone'      => array(
			'type'  => 'string',
			'label' => 'Teléfono del tutor',
		),
		'_pura_emergency_phone'   => array(
			'type'  => 'string',
			'label' => 'Teléfono de emergencia',
		),
		'_pura_address'           => array(
			'type'  => 'string',
			'label' => 'Dirección',
		),
		'_pura_dob'               => array(
			'type'  => 'string',
			'label' => 'Fecha de nacimiento',
		),
		'_pura_group'             => array(
			'type'  => 'string',
			'label' => 'Grupo',
		),
		'_pura_last_paid_at'      => array(
			'type'  => 'string',
			'label' => 'Último pago',
		),
		'_pura_inscription_count' => array(
			'type'  => 'integer',
			'label' => 'Inscripciones',
		),
	);

	private const NONCE = 'pura_student_meta';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Alumnos', 'pura' ),
					'singular_name' => __( 'Alumno', 'pura' ),
					'all_items'     => __( 'Alumnos', 'pura' ),
					'edit_item'     => __( 'Alumno', 'pura' ),
					'add_new_item'  => __( 'Añadir alumno', 'pura' ),
					'search_items'  => __( 'Buscar por nombre o correo', 'pura' ),
					'not_found'     => __( 'No hay alumnos.', 'pura' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'pura',
				'show_in_rest'        => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'supports'            => array( 'title' ),
				'capability_type'     => self::POST_TYPE,
				'map_meta_cap'        => true,
			)
		);

		foreach ( self::FIELDS as $key => $field ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => $field['type'],
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static fn () => current_user_can( 'manage_options' ),
				)
			);
		}

		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'search_by_email' ) );
	}

	public function add_meta_box(): void {
		add_meta_box( 'pura_student_profile', __( 'Ficha del alumno', 'pura' ), array( $this, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		echo '<table class="form-table"><tbody>';
		foreach ( self::FIELDS as $key => $field ) {
			$value    = (string) get_post_meta( $post->ID, $key, true );
			$readonly = in_array( $key, array( '_pura_last_paid_at', '_pura_inscription_count' ), true );
			$name     = 'pura_student[' . $key . ']';
			echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
			if ( '_pura_group' === $key ) {
				echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">';
				echo '<option value="adult" ' . selected( $value, 'adult', false ) . '>Adultos</option>';
				echo '<option value="kids" ' . selected( $value, 'kids', false ) . '>Niños</option>';
				echo '</select>';
			} elseif ( '_pura_address' === $key ) {
				echo '<textarea id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" class="large-text" rows="2">' . esc_textarea( $value ) . '</textarea>';
			} else {
				$type = '_pura_email' === $key ? 'email' : ( '_pura_dob' === $key ? 'date' : 'text' );
				echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" class="regular-text" value="' . esc_attr( $value ) . '" ' . ( $readonly ? 'readonly' : '' ) . ' />';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ], $_POST['pura_student'] ) || ! is_array( $_POST['pura_student'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$input = wp_unslash( $_POST['pura_student'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.

		foreach ( self::FIELDS as $key => $field ) {
			if ( in_array( $key, array( '_pura_last_paid_at', '_pura_inscription_count' ), true ) ) {
				continue;
			}
			$raw = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			if ( '_pura_email' === $key ) {
				$value = strtolower( sanitize_email( $raw ) );
			} elseif ( '_pura_address' === $key ) {
				$value = sanitize_textarea_field( $raw );
			} else {
				$value = sanitize_text_field( $raw );
			}
			update_post_meta( $post_id, $key, $value );
		}

		Student_Repository::flush_cache( (string) get_post_meta( $post_id, '_pura_email', true ) );
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'           => $columns['cb'] ?? '',
			'title'        => __( 'Alumno', 'pura' ),
			'email'        => __( 'Correo', 'pura' ),
			'phone'        => __( 'Teléfono', 'pura' ),
			'group'        => __( 'Grupo', 'pura' ),
			'inscriptions' => __( 'Inscripciones', 'pura' ),
			'last_paid'    => __( 'Último pago', 'pura' ),
		);
	}

	public function column_content( string $column, int $post_id ): void {
		$map = array(
			'email'        => '_pura_email',
			'phone'        => '_pura_phone',
			'inscriptions' => '_pura_inscription_count',
			'last_paid'    => '_pura_last_paid_at',
		);
		if ( 'group' === $column ) {
			echo esc_html( 'kids' === get_post_meta( $post_id, '_pura_group', true ) ? 'Niños' : 'Adultos' );
			return;
		}
		if ( isset( $map[ $column ] ) ) {
			echo esc_html( (string) get_post_meta( $post_id, $map[ $column ], true ) );
		}
	}

	public function search_by_email( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$search = trim( (string) $query->get( 's' ) );
		if ( '' !== $search && str_contains( $search, '@' ) ) {
			$query->set( 's', '' );
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => '_pura_email',
						'value'   => strtolower( $search ),
						'compare' => 'LIKE',
					),
				)
			); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin search, small volume.
		}
	}
}
