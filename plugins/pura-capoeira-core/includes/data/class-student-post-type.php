<?php
/**
 * `pura_student`: one post per student (keyed by lowercase email). Profile data lives here only.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

use Pura\Core\Auth\Providers;
use Pura\Core\Auth\Student_Accounts;

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
		add_action( 'admin_notices', array( $this, 'link_error_notice' ) );
	}

	public function link_error_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		if ( empty( $_GET['pura_link_error'] ) ) {
			return;
		}
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'No se vinculó la cuenta: no existe un usuario con ese correo/ID o no tiene el rol Alumno.', 'pura' ) . '</p></div>';
	}

	public function add_meta_box(): void {
		add_meta_box( 'pura_student_profile', __( 'Ficha del alumno', 'pura' ), array( $this, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		echo '<table class="form-table"><tbody>';
		$this->render_account_row( $post );
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

		$this->save_account_link( $post_id, $input );

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

	/**
	 * "Cuenta vinculada" row: which WordPress user (Google/Apple sign-in) owns this record.
	 */
	private function render_account_row( \WP_Post $post ): void {
		$user_id = Student_Accounts::user_id_for_student( $post->ID );
		$user    = $user_id ? get_user_by( 'id', $user_id ) : null;

		echo '<tr><th scope="row"><label for="pura_student_user">' . esc_html__( 'Cuenta vinculada', 'pura' ) . '</label></th><td>';
		if ( $user ) {
			printf(
				'<p><a href="%s">%s</a> &lt;%s&gt; — %s</p>',
				esc_url( get_edit_user_link( $user->ID ) ),
				esc_html( $user->display_name ),
				esc_html( $user->user_email ),
				esc_html( implode( ', ', array_map( static fn ( $p ) => ucfirst( $p ), self::linked_providers( $user->ID ) ) ) )
			);
			if ( get_user_meta( $user->ID, Student_Accounts::META_PRIVATE_EMAIL, true ) ) {
				echo '<p class="description">' . esc_html__( 'Esta cuenta usa un correo privado de Apple (relay): el correo del alumno y el de la cuenta no coinciden, y eso es normal.', 'pura' ) . '</p>';
			}
			echo '<label><input type="checkbox" name="pura_student[_unlink_user]" value="1" /> ' . esc_html__( 'Desvincular esta cuenta', 'pura' ) . '</label>';
		} else {
			echo '<input type="text" id="pura_student_user" name="pura_student[_link_user]" class="regular-text" placeholder="' . esc_attr__( 'Correo de la cuenta o ID de usuario', 'pura' ) . '" />';
			echo '<p class="description">' . esc_html__( 'Sin vincular. Al iniciar sesión con el mismo correo del alumno se vincula sola; si el alumno usó otro correo (p. ej. un relay de Apple), escríbelo aquí.', 'pura' ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * @param array<string, mixed> $input Submitted pura_student fields.
	 */
	private function save_account_link( int $post_id, array $input ): void {
		if ( ! empty( $input['_unlink_user'] ) ) {
			$user_id = Student_Accounts::user_id_for_student( $post_id );
			if ( $user_id ) {
				Student_Accounts::unlink_student( $user_id );
			}
			return;
		}

		$raw = trim( (string) ( $input['_link_user'] ?? '' ) );
		if ( '' === $raw ) {
			return;
		}

		$user = is_numeric( $raw ) ? get_user_by( 'id', (int) $raw ) : get_user_by( 'email', sanitize_email( $raw ) );
		if ( ! $user || ! Student_Accounts::is_student_user( $user ) ) {
			add_filter(
				'redirect_post_location',
				static fn ( string $location ): string => add_query_arg( 'pura_link_error', '1', $location )
			);
			return;
		}

		Student_Accounts::link_student( $user->ID, $post_id );
	}

	/**
	 * @return string[] Provider ids with an identity on this user.
	 */
	private static function linked_providers( int $user_id ): array {
		$out = array();
		foreach ( Providers::IDS as $provider ) {
			if ( '' !== (string) get_user_meta( $user_id, Student_Accounts::identity_meta_key( $provider ), true ) ) {
				$out[] = $provider;
			}
		}

		return $out;
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
