<?php
/**
 * `pura_inscription`: one post per registration or payment attempt.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

use Pura\Core\Pricing;

defined( 'ABSPATH' ) || exit;

final class Inscription_Post_Type {

	public const POST_TYPE = 'pura_inscription';

	public const STATUSES = array(
		'free'                    => 'Beca (sin pago)',
		'pending_payment'         => 'Pago pendiente (en línea)',
		'pending_payment_offline' => 'Pago pendiente (en persona)',
		'paid'                    => 'Pagado',
		'expired'                 => 'Expirado',
	);

	/** @var array<string, string> meta key => type */
	public const META = array(
		'_pura_status'                => 'string',
		'_pura_plan'                  => 'string',
		'_pura_label'                 => 'string',
		'_pura_amount_cents'          => 'integer',
		'_pura_currency'              => 'string',
		'_pura_group'                 => 'string',
		'_pura_promocode'             => 'string',
		'_pura_promo_type'            => 'string',
		'_pura_trial_date'            => 'string',
		'_pura_member'                => 'boolean',
		'_pura_student_id'            => 'integer',
		'_pura_email'                 => 'string',
		'_pura_stripe_session_id'     => 'string',
		'_pura_stripe_payment_intent' => 'string',
		'_pura_paid_at'               => 'string',
		'_pura_legacy_hash'           => 'string',
	);

	private const NONCE = 'pura_inscription_status';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Inscripciones', 'pura' ),
					'singular_name' => __( 'Inscripción', 'pura' ),
					'all_items'     => __( 'Inscripciones', 'pura' ),
					'edit_item'     => __( 'Inscripción', 'pura' ),
					'search_items'  => __( 'Buscar por nombre o correo', 'pura' ),
					'not_found'     => __( 'No hay inscripciones.', 'pura' ),
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
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			)
		);

		foreach ( self::META as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static fn () => current_user_can( 'manage_options' ),
				)
			);
		}

		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_status' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'filters' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filters_and_search' ) );
	}

	public static function status_label( string $status ): string {
		return self::STATUSES[ $status ] ?? $status;
	}

	public function add_meta_boxes(): void {
		add_meta_box( 'pura_inscription_status', __( 'Estado', 'pura' ), array( $this, 'render_status_box' ), self::POST_TYPE, 'side', 'high' );
		add_meta_box( 'pura_inscription_details', __( 'Detalles', 'pura' ), array( $this, 'render_details_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public function render_status_box( \WP_Post $post ): void {
		$status = (string) get_post_meta( $post->ID, '_pura_status', true );
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		?>
		<p>
			<select name="pura_status" id="pura_status" class="widefat">
				<?php foreach ( self::STATUSES as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Marca "Pagado" cuando un alumno con pago en persona liquide su cuota.', 'pura' ); ?></p>
		<?php
	}

	public function render_details_box( \WP_Post $post ): void {
		$rows = array(
			__( 'Paquete', 'pura' )          => get_post_meta( $post->ID, '_pura_label', true ),
			__( 'Monto', 'pura' )            => Pricing::format_pesos( (int) get_post_meta( $post->ID, '_pura_amount_cents', true ) ) . ' ' . get_post_meta( $post->ID, '_pura_currency', true ),
			__( 'Grupo', 'pura' )            => 'kids' === get_post_meta( $post->ID, '_pura_group', true ) ? 'Niños' : 'Adultos',
			__( 'Correo', 'pura' )           => get_post_meta( $post->ID, '_pura_email', true ),
			__( 'Código promo', 'pura' )     => get_post_meta( $post->ID, '_pura_promocode', true ),
			__( 'Clase de prueba', 'pura' )  => get_post_meta( $post->ID, '_pura_trial_date', true ),
			__( 'Alumno existente', 'pura' ) => get_post_meta( $post->ID, '_pura_member', true ) ? 'Sí' : 'No',
			__( 'Stripe session', 'pura' )   => get_post_meta( $post->ID, '_pura_stripe_session_id', true ),
			__( 'Pagado el', 'pura' )        => get_post_meta( $post->ID, '_pura_paid_at', true ),
		);

		$student_id = (int) get_post_meta( $post->ID, '_pura_student_id', true );
		if ( $student_id && get_post( $student_id ) ) {
			$rows[ __( 'Ficha del alumno', 'pura' ) ] = '<a href="' . esc_url( (string) get_edit_post_link( $student_id ) ) . '">' . esc_html( get_the_title( $student_id ) ) . '</a>';
		}

		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$allowed = array( 'a' => array( 'href' => array() ) );
			echo '<tr><th style="width:12em">' . esc_html( $label ) . '</th><td>' . wp_kses( (string) $value, $allowed ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function save_status( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ], $_POST['pura_status'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = sanitize_key( wp_unslash( $_POST['pura_status'] ) );
		if ( ! isset( self::STATUSES[ $status ] ) ) {
			return;
		}

		$previous = (string) get_post_meta( $post_id, '_pura_status', true );
		update_post_meta( $post_id, '_pura_status', $status );

		if ( 'paid' === $status && 'paid' !== $previous && '' === (string) get_post_meta( $post_id, '_pura_paid_at', true ) ) {
			update_post_meta( $post_id, '_pura_paid_at', current_time( 'mysql' ) );
		}
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'     => $columns['cb'] ?? '',
			'title'  => __( 'Alumno', 'pura' ),
			'status' => __( 'Estado', 'pura' ),
			'plan'   => __( 'Paquete', 'pura' ),
			'amount' => __( 'Monto', 'pura' ),
			'group'  => __( 'Grupo', 'pura' ),
			'email'  => __( 'Correo', 'pura' ),
			'date'   => __( 'Fecha', 'pura' ),
		);
	}

	public function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'status':
				$status = (string) get_post_meta( $post_id, '_pura_status', true );
				echo '<span class="pura-status pura-status--' . esc_attr( $status ) . '">' . esc_html( self::status_label( $status ) ) . '</span>';
				break;
			case 'plan':
				echo esc_html( (string) get_post_meta( $post_id, '_pura_label', true ) );
				break;
			case 'amount':
				echo esc_html( Pricing::format_pesos( (int) get_post_meta( $post_id, '_pura_amount_cents', true ) ) );
				break;
			case 'group':
				echo esc_html( 'kids' === get_post_meta( $post_id, '_pura_group', true ) ? 'Niños' : 'Adultos' );
				break;
			case 'email':
				echo esc_html( (string) get_post_meta( $post_id, '_pura_email', true ) );
				break;
		}
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['status'] = 'status';
		$columns['amount'] = 'amount';
		return $columns;
	}

	public function filters( string $post_type ): void {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$status = isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '';
		$group  = isset( $_GET['pura_group'] ) ? sanitize_key( wp_unslash( $_GET['pura_group'] ) ) : '';
		// phpcs:enable

		echo '<select name="pura_status"><option value="">' . esc_html__( 'Todos los estados', 'pura' ) . '</option>';
		foreach ( self::STATUSES as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';

		echo '<select name="pura_group"><option value="">' . esc_html__( 'Todos los grupos', 'pura' ) . '</option>';
		echo '<option value="adult" ' . selected( $group, 'adult', false ) . '>' . esc_html__( 'Adultos', 'pura' ) . '</option>';
		echo '<option value="kids" ' . selected( $group, 'kids', false ) . '>' . esc_html__( 'Niños', 'pura' ) . '</option>';
		echo '</select>';
	}

	public function apply_filters_and_search( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$status = isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '';
		$group  = isset( $_GET['pura_group'] ) ? sanitize_key( wp_unslash( $_GET['pura_group'] ) ) : '';
		// phpcs:enable

		if ( '' !== $status && isset( self::STATUSES[ $status ] ) ) {
			$meta_query[] = array(
				'key'   => '_pura_status',
				'value' => $status,
			);
		}
		if ( in_array( $group, Pricing::GROUPS, true ) ) {
			$meta_query[] = array(
				'key'   => '_pura_group',
				'value' => $group,
			);
		}

		$search = trim( (string) $query->get( 's' ) );
		if ( '' !== $search && str_contains( $search, '@' ) ) {
			$meta_query[] = array(
				'key'     => '_pura_email',
				'value'   => strtolower( $search ),
				'compare' => 'LIKE',
			);
			$query->set( 's', '' );
		}

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin list, small volume.
		}

		$orderby = (string) $query->get( 'orderby' );
		if ( 'status' === $orderby ) {
			$query->set( 'meta_key', '_pura_status' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'amount' === $orderby ) {
			$query->set( 'meta_key', '_pura_amount_cents' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value_num' );
		}
	}
}
