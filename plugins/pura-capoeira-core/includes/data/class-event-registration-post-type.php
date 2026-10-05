<?php
/**
 * `pura_event_reg`: one post per person registered to an event (a roda, a workshop weekend…).
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Data;

defined( 'ABSPATH' ) || exit;

final class Event_Registration_Post_Type {

	public const POST_TYPE = 'pura_event_reg';

	public const STATUSES = array(
		'registered' => 'Registrado',
		'confirmed'  => 'Confirmado',
		'cancelled'  => 'Cancelado',
	);

	/** @var array<string, string> meta key => type */
	public const META = array(
		'_pura_status'          => 'string',
		'_pura_event'           => 'string',
		'_pura_event_name'      => 'string',
		'_pura_first_name'      => 'string',
		'_pura_last_name'       => 'string',
		'_pura_email'           => 'string',
		'_pura_phone'           => 'string',
		'_pura_city'            => 'string',
		'_pura_academy'         => 'string',
		'_pura_teacher'         => 'string',
		'_pura_graduation'      => 'string',
		'_pura_days'            => 'string',
		'_pura_shirt_size'      => 'string',
		'_pura_emergency_name'  => 'string',
		'_pura_emergency_phone' => 'string',
		'_pura_notes'           => 'string',
	);

	private const NONCE = 'pura_event_reg_status';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Registros a eventos', 'pura' ),
					'singular_name' => __( 'Registro a evento', 'pura' ),
					'all_items'     => __( 'Eventos', 'pura' ),
					'edit_item'     => __( 'Registro a evento', 'pura' ),
					'search_items'  => __( 'Buscar por nombre o correo', 'pura' ),
					'not_found'     => __( 'No hay registros.', 'pura' ),
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
		add_meta_box( 'pura_event_reg_status', __( 'Estado', 'pura' ), array( $this, 'render_status_box' ), self::POST_TYPE, 'side', 'high' );
		add_meta_box( 'pura_event_reg_details', __( 'Detalles', 'pura' ), array( $this, 'render_details_box' ), self::POST_TYPE, 'normal', 'high' );
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
		<p class="description"><?php esc_html_e( 'Marca "Confirmado" cuando la persona confirme su asistencia o cubra la cuota del evento.', 'pura' ); ?></p>
		<?php
	}

	public function render_details_box( \WP_Post $post ): void {
		$data = Event_Registration_Repository::to_array( $post->ID );
		if ( ! $data ) {
			return;
		}

		$rows = array(
			__( 'Evento', 'pura' )                 => $data['event_name'] ?: $data['event'],
			__( 'Nombre', 'pura' )                 => $data['name'],
			__( 'Correo', 'pura' )                 => $data['email'],
			__( 'Teléfono / WhatsApp', 'pura' )    => $data['phone'],
			__( 'Ciudad', 'pura' )                 => $data['city'],
			__( 'Grupo / academia', 'pura' )       => $data['academy'],
			__( 'Mestre / Professor', 'pura' )     => $data['teacher'],
			__( 'Graduación', 'pura' )             => $data['graduation'],
			__( 'Días', 'pura' )                   => $data['days'],
			__( 'Talla de playera', 'pura' )       => $data['shirt_size'],
			__( 'Contacto de emergencia', 'pura' ) => $data['emergency_name'],
			__( 'Teléfono de emergencia', 'pura' ) => $data['emergency_phone'],
			__( 'Comentarios', 'pura' )            => $data['notes'],
			__( 'Registrado el', 'pura' )          => $data['created_at'],
		);

		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			echo '<tr><th style="width:14em">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) $value ) ) . '</td></tr>';
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
		if ( isset( self::STATUSES[ $status ] ) ) {
			update_post_meta( $post_id, '_pura_status', $status );
		}
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'         => $columns['cb'] ?? '',
			'title'      => __( 'Nombre', 'pura' ),
			'status'     => __( 'Estado', 'pura' ),
			'event'      => __( 'Evento', 'pura' ),
			'days'       => __( 'Días', 'pura' ),
			'academy'    => __( 'Grupo', 'pura' ),
			'graduation' => __( 'Graduación', 'pura' ),
			'city'       => __( 'Ciudad', 'pura' ),
			'email'      => __( 'Correo', 'pura' ),
			'date'       => __( 'Fecha', 'pura' ),
		);
	}

	public function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'status':
				$status = (string) get_post_meta( $post_id, '_pura_status', true );
				echo '<span class="pura-status pura-status--' . esc_attr( $status ) . '">' . esc_html( self::status_label( $status ) ) . '</span>';
				break;
			case 'event':
				$name = (string) get_post_meta( $post_id, '_pura_event_name', true );
				echo esc_html( '' !== $name ? $name : (string) get_post_meta( $post_id, '_pura_event', true ) );
				break;
			case 'days':
			case 'academy':
			case 'graduation':
			case 'city':
			case 'email':
				echo esc_html( (string) get_post_meta( $post_id, '_pura_' . $column, true ) );
				break;
		}
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['status'] = 'status';
		$columns['event']  = 'event';
		return $columns;
	}

	public function filters( string $post_type ): void {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$status = isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '';
		$event  = isset( $_GET['pura_event'] ) ? sanitize_key( wp_unslash( $_GET['pura_event'] ) ) : '';
		// phpcs:enable

		echo '<select name="pura_status"><option value="">' . esc_html__( 'Todos los estados', 'pura' ) . '</option>';
		foreach ( self::STATUSES as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';

		$events = Event_Registration_Repository::events();
		if ( count( $events ) > 1 ) {
			echo '<select name="pura_event"><option value="">' . esc_html__( 'Todos los eventos', 'pura' ) . '</option>';
			foreach ( $events as $slug => $name ) {
				echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $event, $slug, false ) . '>' . esc_html( $name ) . '</option>';
			}
			echo '</select>';
		}
	}

	public function apply_filters_and_search( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$status = isset( $_GET['pura_status'] ) ? sanitize_key( wp_unslash( $_GET['pura_status'] ) ) : '';
		$event  = isset( $_GET['pura_event'] ) ? sanitize_key( wp_unslash( $_GET['pura_event'] ) ) : '';
		// phpcs:enable

		if ( '' !== $status && isset( self::STATUSES[ $status ] ) ) {
			$meta_query[] = array(
				'key'   => '_pura_status',
				'value' => $status,
			);
		}
		if ( '' !== $event ) {
			$meta_query[] = array(
				'key'   => '_pura_event',
				'value' => $event,
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
		} elseif ( 'event' === $orderby ) {
			$query->set( 'meta_key', '_pura_event' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value' );
		}
	}
}
