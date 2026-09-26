<?php
/**
 * "Pura Capoeira → Ajustes" settings page (Settings API).
 *
 * Money is entered in pesos and stored in cents. Secrets are never echoed back; a constant in
 * wp-config.php locks the field.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Admin;

use Pura\Core\Mailer;
use Pura\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class Settings_Page {

	public const GROUP = 'pura';

	/** Money fields: stored in cents, edited in pesos. */
	private const MONEY_FIELDS = array(
		'monthly_adult',
		'monthly_kids',
		'inscription_fee',
		'trial_price',
		'current_monthly_adult',
		'current_monthly_kids',
	);

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_pura_test_mail', array( $this, 'handle_test_mail' ) );
	}

	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => Settings::defaults(),
			)
		);
		register_setting(
			self::GROUP,
			Settings::SECRETS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_secrets' ),
				'default'           => array(),
			)
		);

		$sections = array(
			'pagos'          => array(
				'title'  => __( 'Pagos (Stripe)', 'pura' ),
				'fields' => array(
					'stripe_secret_key'     => array( 'secret', __( 'Llave secreta de Stripe', 'pura' ) ),
					'stripe_webhook_secret' => array( 'secret', __( 'Firma del webhook (whsec_…)', 'pura' ) ),
					'currency'              => array( 'text', __( 'Moneda', 'pura' ), __( 'Código ISO, p. ej. MXN.', 'pura' ) ),
					'store_page_id'         => array( 'page', __( 'Página de la tienda', 'pura' ) ),
					'inscriptions_page_id'  => array( 'page', __( 'Página de inscripciones', 'pura' ) ),
				),
			),
			'printful'       => array(
				'title'  => __( 'Tienda (Printful)', 'pura' ),
				'fields' => array(
					'printful_api_key'  => array( 'secret', __( 'Llave de API de Printful', 'pura' ) ),
					'price_multiplier'  => array( 'float', __( 'Multiplicador de precio', 'pura' ), __( '1 = los precios de Printful se usan tal cual. Úsalo si tus precios en Printful están en USD.', 'pura' ) ),
					'catalog_cache_ttl' => array( 'int', __( 'Caché del catálogo (segundos)', 'pura' ) ),
				),
			),
			'precios'        => array(
				'title'  => __( 'Precios de inscripción (MXN)', 'pura' ),
				'fields' => array(
					'monthly_adult'         => array( 'money', __( 'Mensualidad adultos', 'pura' ) ),
					'monthly_kids'          => array( 'money', __( 'Mensualidad niños', 'pura' ) ),
					'inscription_fee'       => array( 'money', __( 'Inscripción / uniforme', 'pura' ) ),
					'trial_price'           => array( 'money', __( 'Clase de prueba', 'pura' ) ),
					'quarter_discount_pct'  => array( 'float', __( 'Descuento trimestral (%)', 'pura' ) ),
					'year_discount_pct'     => array( 'float', __( 'Descuento anual (%)', 'pura' ) ),
					'current_monthly_adult' => array( 'money', __( 'Mensualidad alumnos actuales (adultos)', 'pura' ), __( 'Se aplica con el código de alumno actual.', 'pura' ) ),
					'current_monthly_kids'  => array( 'money', __( 'Mensualidad alumnos actuales (niños)', 'pura' ) ),
				),
			),
			'codigos'        => array(
				'title'  => __( 'Códigos promocionales', 'pura' ),
				'fields' => array(
					'promo_beca_code'    => array( 'text', __( 'Código de beca (100%)', 'pura' ) ),
					'promo_current_code' => array( 'text', __( 'Código de alumno actual', 'pura' ) ),
				),
			),
			'horarios'       => array(
				'title'  => __( 'Horarios', 'pura' ),
				'fields' => array(
					'schedule' => array( 'schedule', __( 'Clases', 'pura' ), __( 'Una clase por línea: grupo | día | inicio | fin. Grupo: adult o kids.', 'pura' ) ),
				),
			),
			'notificaciones' => array(
				'title'  => __( 'Notificaciones por correo', 'pura' ),
				'fields' => array(
					'notify_emails'     => array( 'text', __( 'Destinatarios', 'pura' ), __( 'Separados por comas.', 'pura' ) ),
					'notify_from_email' => array( 'email', __( 'Remitente', 'pura' ), __( 'Déjalo vacío para usar el remitente configurado en FluentSMTP.', 'pura' ) ),
					'notify_from_name'  => array( 'text', __( 'Nombre del remitente', 'pura' ) ),
					'cc_student'        => array( 'checkbox', __( 'Enviar copia al alumno', 'pura' ) ),
					'_test_mail'        => array( 'test_mail', __( 'Correo de prueba', 'pura' ) ),
				),
			),
			'contacto'       => array(
				'title'  => __( 'Contacto y redes', 'pura' ),
				'fields' => array(
					'whatsapp_number'          => array( 'text', __( 'WhatsApp (número internacional, solo dígitos)', 'pura' ) ),
					'whatsapp_display'         => array( 'text', __( 'WhatsApp (como se muestra)', 'pura' ) ),
					'calendly_trial_adult_url' => array( 'url', __( 'Calendly clase de prueba (adultos)', 'pura' ) ),
					'calendly_trial_kids_url'  => array( 'url', __( 'Calendly clase de prueba (niños)', 'pura' ) ),
					'instagram_url'            => array( 'url', __( 'Instagram (URL)', 'pura' ) ),
					'instagram_handle'         => array( 'text', __( 'Instagram (usuario)', 'pura' ) ),
					'facebook_url'             => array( 'url', __( 'Facebook (URL)', 'pura' ) ),
					'facebook_label'           => array( 'text', __( 'Facebook (nombre)', 'pura' ) ),
					'address'                  => array( 'textarea', __( 'Dirección', 'pura' ) ),
					'address_short'            => array( 'text', __( 'Dirección corta (pie de página)', 'pura' ) ),
					'maps_url'                 => array( 'url', __( 'Enlace de Google Maps', 'pura' ) ),
					'tagline'                  => array( 'textarea', __( 'Lema (pie de página)', 'pura' ) ),
				),
			),
		);

		foreach ( $sections as $id => $section ) {
			add_settings_section( 'pura_' . $id, $section['title'], '__return_null', self::GROUP );
			foreach ( $section['fields'] as $key => $def ) {
				add_settings_field(
					'pura_' . $key,
					$def[1],
					array( $this, 'render_field' ),
					self::GROUP,
					'pura_' . $id,
					array(
						'key'         => $key,
						'type'        => $def[0],
						'description' => $def[2] ?? '',
						'label_for'   => in_array( $def[0], array( 'test_mail' ), true ) ? '' : 'pura_' . $key,
					)
				);
			}
		}
	}

	/**
	 * @param array<string, string> $args Field args.
	 */
	public function render_field( array $args ): void {
		$key  = $args['key'];
		$type = $args['type'];
		$id   = 'pura_' . $key;
		$name = Settings::OPTION . '[' . $key . ']';

		switch ( $type ) {
			case 'secret':
				$source = Settings::secret_source( $key );
				$sname  = Settings::SECRETS . '[' . $key . ']';
				if ( 'constant' === $source ) {
					echo '<input type="password" id="' . esc_attr( $id ) . '" class="regular-text pura-locked" value="••••••••••••" disabled />';
					echo '<p class="description">' . esc_html__( 'Definido en wp-config.php', 'pura' ) . ' (<code>' . esc_html( Settings::secret_constant( $key ) ) . '</code>).</p>';
				} else {
					echo '<input type="password" id="' . esc_attr( $id ) . '" name="' . esc_attr( $sname ) . '" class="regular-text" value="" autocomplete="new-password" placeholder="' . esc_attr( 'option' === $source ? __( 'guardado — escribe para reemplazar', 'pura' ) : __( 'no configurado', 'pura' ) ) . '" />';
					if ( 'option' === $source ) {
						echo ' <label><input type="checkbox" name="pura_secrets_delete[' . esc_attr( $key ) . ']" value="1" /> ' . esc_html__( 'Borrar', 'pura' ) . '</label>';
					}
					echo '<p class="description">' . esc_html__( 'En producción se recomienda definirlo como constante en wp-config.php.', 'pura' ) . '</p>';
				}
				break;

			case 'money':
				$cents = (int) Settings::get( $key, 0 );
				echo '$ <input type="number" step="0.01" min="0" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( number_format( $cents / 100, 2, '.', '' ) ) . '" class="small-text" style="width:8em" /> MXN';
				break;

			case 'float':
				echo '<input type="number" step="0.01" min="0" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) Settings::get( $key, 0 ) ) . '" class="small-text" style="width:8em" />';
				break;

			case 'int':
				echo '<input type="number" step="1" min="0" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) (int) Settings::get( $key, 0 ) ) . '" class="small-text" style="width:8em" />';
				break;

			case 'checkbox':
				echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) Settings::get( $key, false ), true, false ) . ' /> ' . esc_html__( 'Activado', 'pura' ) . '</label>';
				break;

			case 'page':
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes its own markup.
				wp_dropdown_pages(
					array(
						'id'                => $id,
						'name'              => $name,
						'selected'          => (int) Settings::get( $key, 0 ),
						'show_option_none'  => __( '— Detectar por slug —', 'pura' ),
						'option_none_value' => '0',
					)
				);
				// phpcs:enable
				break;

			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" class="large-text">' . esc_textarea( (string) Settings::get( $key, '' ) ) . '</textarea>';
				break;

			case 'schedule':
				$rows  = Settings::get( 'schedule', array() );
				$lines = array();
				foreach ( is_array( $rows ) ? $rows : array() as $row ) {
					$lines[] = implode( ' | ', array( $row['group'] ?? '', $row['day'] ?? '', $row['start'] ?? '', $row['end'] ?? '' ) );
				}
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="6" class="large-text code">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
				break;

			case 'test_mail':
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=pura_test_mail' ), 'pura_test_mail' );
				echo '<a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Enviar correo de prueba a los destinatarios', 'pura' ) . '</a>';
				echo '<p class="description">' . esc_html__( 'Guarda los cambios antes de probar.', 'pura' ) . '</p>';
				break;

			case 'url':
			case 'email':
			case 'text':
			default:
				echo '<input type="' . esc_attr( 'text' === $type ? 'text' : $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) Settings::get( $key, '' ) ) . '" class="regular-text" />';
				break;
		}

		if ( ! empty( $args['description'] ) && 'secret' !== $type && 'test_mail' !== $type ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * @param mixed $input Submitted pura_settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$current  = Settings::all();
		$defaults = Settings::defaults();
		$out      = array();

		foreach ( $defaults as $key => $default ) {
			if ( 'schedule' === $key ) {
				$out[ $key ] = $this->parse_schedule( (string) ( $input[ $key ] ?? '' ) );
				continue;
			}
			if ( in_array( $key, self::MONEY_FIELDS, true ) ) {
				$out[ $key ] = isset( $input[ $key ] ) ? (int) round( (float) $input[ $key ] * 100 ) : (int) $current[ $key ];
				continue;
			}
			if ( 'cc_student' === $key ) {
				$out[ $key ] = ! empty( $input[ $key ] );
				continue;
			}
			if ( 'og_image_id' === $key ) {
				$out[ $key ] = (int) $current[ $key ];
				continue;
			}
			if ( ! array_key_exists( $key, $input ) ) {
				$out[ $key ] = $current[ $key ];
				continue;
			}

			$raw = $input[ $key ];
			if ( is_int( $default ) ) {
				$out[ $key ] = max( 0, (int) $raw );
			} elseif ( is_float( $default ) ) {
				$out[ $key ] = max( 0.0, (float) $raw );
			} elseif ( str_ends_with( $key, '_url' ) ) {
				$out[ $key ] = esc_url_raw( (string) $raw );
			} elseif ( 'notify_emails' === $key ) {
				$emails      = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $raw ) ) ) );
				$out[ $key ] = implode( ', ', array_filter( $emails, 'is_email' ) );
			} elseif ( 'notify_from_email' === $key ) {
				$out[ $key ] = sanitize_email( (string) $raw );
			} elseif ( in_array( $key, array( 'address', 'tagline' ), true ) ) {
				$out[ $key ] = sanitize_textarea_field( (string) $raw );
			} elseif ( 'whatsapp_number' === $key ) {
				$out[ $key ] = preg_replace( '/\D+/', '', (string) $raw );
			} elseif ( 'currency' === $key ) {
				$out[ $key ] = strtoupper( substr( sanitize_text_field( (string) $raw ), 0, 3 ) ) ?: 'MXN';
			} else {
				$out[ $key ] = sanitize_text_field( (string) $raw );
			}
		}

		if ( $out['price_multiplier'] <= 0 ) {
			$out['price_multiplier'] = 1.0;
		}

		return $out;
	}

	/**
	 * Empty inputs keep the stored secret; the "Borrar" checkbox removes it.
	 *
	 * @param mixed $input Submitted pura_secrets.
	 * @return array<string, string>
	 */
	public function sanitize_secrets( $input ): array {
		$stored = get_option( Settings::SECRETS, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$input  = is_array( $input ) ? $input : array();

		// options.php has already verified the settings nonce for this request.
		$delete = isset( $_POST['pura_secrets_delete'] ) && is_array( $_POST['pura_secrets_delete'] ) ? array_map( 'sanitize_key', array_keys( wp_unslash( $_POST['pura_secrets_delete'] ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		foreach ( Settings::SECRET_KEYS as $key ) {
			if ( in_array( $key, $delete, true ) ) {
				unset( $stored[ $key ] );
				continue;
			}
			$value = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
			if ( '' !== $value ) {
				$stored[ $key ] = sanitize_text_field( $value );
			}
		}

		return $stored;
	}

	/**
	 * @return array<int, array{group:string,day:string,start:string,end:string}>
	 */
	private function parse_schedule( string $text ): array {
		$rows = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) ?: array() as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) < 4 || '' === $parts[1] ) {
				continue;
			}
			$group  = strtolower( sanitize_key( $parts[0] ) );
			$rows[] = array(
				'group' => in_array( $group, array( 'adult', 'kids' ), true ) ? $group : 'adult',
				'day'   => sanitize_text_field( $parts[1] ),
				'start' => sanitize_text_field( $parts[2] ),
				'end'   => sanitize_text_field( $parts[3] ),
			);
		}

		return $rows;
	}

	public function handle_test_mail(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'pura' ) );
		}
		check_admin_referer( 'pura_test_mail' );

		$result  = Mailer::send_test();
		$message = $result['ok']
			/* translators: %d: number of recipients. */
			? sprintf( __( 'Correo de prueba enviado a %d destinatario(s).', 'pura' ), (int) $result['sent_count'] )
			: __( 'No se pudo enviar el correo de prueba: ', 'pura' ) . $result['message'];

		set_transient(
			'pura_settings_notice_' . get_current_user_id(),
			array(
				'ok'      => $result['ok'],
				'message' => $message,
			),
			60
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::SLUG ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = get_transient( 'pura_settings_notice_' . get_current_user_id() );
		if ( is_array( $notice ) ) {
			delete_transient( 'pura_settings_notice_' . get_current_user_id() );
			echo '<div class="notice ' . ( $notice['ok'] ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
		}
		?>
		<div class="wrap pura-settings">
			<h1><?php esc_html_e( 'Pura Capoeira — Ajustes', 'pura' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::GROUP );
				submit_button( __( 'Guardar cambios', 'pura' ) );
				?>
			</form>
		</div>
		<?php
	}
}
