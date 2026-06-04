<?php
/**
 * Plugin Name: Kargo Sayacı
 * Description: WooCommerce tekil ürün sayfalarında kargo geri sayım alanını günlük saat planları, özelleştirilebilir metinler ve renk seçenekleriyle yönetmenizi sağlar.
 * Version: 1.6
 * Author: Pop Marley
 * Text Domain: kargo-sayaci
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Kargo_Sayaci_Plugin {
	const OPTION_NAME = 'kargo_sayaci_settings';
	const VERSION     = '1.6';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		$defaults = self::get_default_settings();
		$saved    = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$merged         = wp_parse_args( $saved, $defaults );
		$merged['days'] = array();

		foreach ( $defaults['days'] as $day_index => $day_defaults ) {
			$saved_day                    = isset( $saved['days'][ $day_index ] ) && is_array( $saved['days'][ $day_index ] ) ? $saved['days'][ $day_index ] : array();
			$merged['days'][ $day_index ] = wp_parse_args( $saved_day, $day_defaults );
		}

		update_option( self::OPTION_NAME, $merged );
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_plugin_action_links' ) );
	}

	private static function get_default_settings() {
		return array(
			'enabled'           => 1,
			'normal_text'       => 'içinde sipariş verirsen',
			'highlight_text'    => 'en geç {{today}} kargoda!',
			'background_color'  => '#c6623f',
			'box_text_color'    => '#ffffff',
			'text_color'        => '#111111',
			'accent_text_color' => '#111111',
			'today_text_color'  => '#c6623f',
			'card_bg_color'     => '#fff7f1',
			'body_bg_color'     => '#fffaf6',
			'border_color'      => '#c6623f',
			'delivery_label_color' => '#5f514b',
			'delivery_value_color' => '#111111',
			'delivery_text'     => 'Yarın kapında',
			'disabled_products' => array(),
			'hide_backorder'    => 1,
			'hide_out_of_stock' => 1,
			'closures'          => array(),
			'days'              => self::get_default_days(),
		);
	}

	private static function get_default_days() {
		return array(
			0 => array(
				'enabled'     => 0,
				'start'       => '00:00',
				'end'         => '00:00',
				'show_today'  => 0,
				'today_label' => 'bugün',
			),
			1 => array(
				'enabled'     => 1,
				'start'       => '00:00',
				'end'         => '17:15',
				'show_today'  => 1,
				'today_label' => 'bugün',
			),
			2 => array(
				'enabled'     => 1,
				'start'       => '00:00',
				'end'         => '17:15',
				'show_today'  => 1,
				'today_label' => 'bugün',
			),
			3 => array(
				'enabled'     => 1,
				'start'       => '00:00',
				'end'         => '17:15',
				'show_today'  => 1,
				'today_label' => 'bugün',
			),
			4 => array(
				'enabled'     => 1,
				'start'       => '00:00',
				'end'         => '17:15',
				'show_today'  => 1,
				'today_label' => 'bugün',
			),
			5 => array(
				'enabled'     => 1,
				'start'       => '00:00',
				'end'         => '17:15',
				'show_today'  => 1,
				'today_label' => 'bugün',
			),
			6 => array(
				'enabled'     => 1,
				'start'       => '00:00',
				'end'         => '15:45',
				'show_today'  => 1,
				'today_label' => 'bugün',
			),
		);
	}

	private function get_settings() {
		$defaults = self::get_default_settings();
		$saved    = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $saved ) ) {
			return $defaults;
		}

		$settings         = wp_parse_args( $saved, $defaults );
		$settings['days'] = array();

		foreach ( $defaults['days'] as $day_index => $day_defaults ) {
			$saved_day                      = isset( $saved['days'][ $day_index ] ) && is_array( $saved['days'][ $day_index ] ) ? $saved['days'][ $day_index ] : array();
			$settings['days'][ $day_index ] = wp_parse_args( $saved_day, $day_defaults );
		}

		$settings['closures'] = $this->normalize_closure_periods( $saved['closures'] ?? array() );
		$settings['disabled_products'] = $this->sanitize_product_id_list( $saved['disabled_products'] ?? array() );

		return $settings;
	}

	public function register_admin_menu() {
		add_menu_page(
			'Kargo Sayacı',
			'Kargo Sayacı',
			'manage_options',
			'kargo-sayaci',
			array( $this, 'render_settings_page' ),
			plugins_url( 'assets/icon-16.png', __FILE__ ),
			56
		);
	}

	public function register_settings() {
		register_setting(
			'kargo_sayaci_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_default_settings(),
			)
		);
	}

	public function sanitize_settings( $input ) {
		$defaults = self::get_default_settings();
		$output   = $defaults;
		$input    = is_array( $input ) ? $input : array();

		$output['enabled']           = empty( $input['enabled'] ) ? 0 : 1;
		$output['normal_text']       = isset( $input['normal_text'] ) ? sanitize_text_field( $input['normal_text'] ) : '';
		$output['highlight_text']    = isset( $input['highlight_text'] ) ? sanitize_text_field( $input['highlight_text'] ) : '';
		$output['background_color']  = sanitize_hex_color( $input['background_color'] ?? '' ) ?: $defaults['background_color'];
		$output['box_text_color']    = sanitize_hex_color( $input['box_text_color'] ?? '' ) ?: $defaults['box_text_color'];
		$output['text_color']        = sanitize_hex_color( $input['text_color'] ?? '' ) ?: $defaults['text_color'];
		$output['accent_text_color'] = sanitize_hex_color( $input['accent_text_color'] ?? '' ) ?: $defaults['accent_text_color'];
		$output['today_text_color']  = sanitize_hex_color( $input['today_text_color'] ?? '' ) ?: $defaults['today_text_color'];
		$output['card_bg_color']     = sanitize_hex_color( $input['card_bg_color'] ?? '' ) ?: $defaults['card_bg_color'];
		$output['body_bg_color']     = sanitize_hex_color( $input['body_bg_color'] ?? '' ) ?: $defaults['body_bg_color'];
		$output['border_color']      = sanitize_hex_color( $input['border_color'] ?? '' ) ?: $defaults['border_color'];
		$output['delivery_label_color'] = sanitize_hex_color( $input['delivery_label_color'] ?? '' ) ?: $defaults['delivery_label_color'];
		$output['delivery_value_color'] = sanitize_hex_color( $input['delivery_value_color'] ?? '' ) ?: $defaults['delivery_value_color'];
		$output['delivery_text']     = isset( $input['delivery_text'] ) ? sanitize_text_field( $input['delivery_text'] ) : $defaults['delivery_text'];
		$output['disabled_products'] = $this->sanitize_product_id_list( $input['disabled_products'] ?? array() );
		$output['hide_backorder']    = empty( $input['hide_backorder'] ) ? 0 : 1;
		$output['hide_out_of_stock'] = empty( $input['hide_out_of_stock'] ) ? 0 : 1;
		$output['closures']          = $this->sanitize_closure_periods( $input['closures'] ?? array() );

		foreach ( $defaults['days'] as $day_index => $day_defaults ) {
			$day_input = isset( $input['days'][ $day_index ] ) && is_array( $input['days'][ $day_index ] ) ? $input['days'][ $day_index ] : array();

			$output['days'][ $day_index ] = array(
				'enabled'     => empty( $day_input['enabled'] ) ? 0 : 1,
				'start'       => $this->sanitize_time( $day_input['start'] ?? '', $day_defaults['start'] ),
				'end'         => $this->sanitize_time( $day_input['end'] ?? '', $day_defaults['end'] ),
				'show_today'  => empty( $day_input['show_today'] ) ? 0 : 1,
				'today_label' => isset( $day_input['today_label'] ) ? sanitize_text_field( $day_input['today_label'] ) : $day_defaults['today_label'],
			);
		}

		add_settings_error( 'kargo_sayaci_messages', 'kargo_sayaci_saved', 'Kargo Sayacı ayarları güncellendi.', 'updated' );

		return $output;
	}

	private function sanitize_product_id_list( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ',', $value );
		}

		$ids = preg_split( '/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}

	private function sanitize_closure_periods( $periods ) {
		$output = array();
		$periods = is_array( $periods ) ? $periods : array();

		foreach ( $periods as $period ) {
			if ( ! is_array( $period ) ) {
				continue;
			}

			$start_date = $this->sanitize_date( $period['start_date'] ?? '' );
			$end_date   = $this->sanitize_date( $period['end_date'] ?? '' );

			if ( '' === $start_date || '' === $end_date ) {
				continue;
			}

			$output[] = array(
				'enabled'    => empty( $period['enabled'] ) ? 0 : 1,
				'label'      => isset( $period['label'] ) ? sanitize_text_field( $period['label'] ) : '',
				'start_date' => $start_date,
				'start_time' => $this->sanitize_time( $period['start_time'] ?? '', '00:00' ),
				'end_date'   => $end_date,
				'end_time'   => $this->sanitize_time( $period['end_time'] ?? '', '23:59' ),
			);
		}

		return $output;
	}

	private function normalize_closure_periods( $periods ) {
		return $this->sanitize_closure_periods( $periods );
	}

	private function sanitize_date( $value ) {
		$value = sanitize_text_field( $value );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $value ) );

		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	private function sanitize_time( $value, $fallback ) {
		$value = sanitize_text_field( $value );

		if ( ! preg_match( '/^\d{2}:\d{2}$/', $value ) ) {
			return $fallback;
		}

		list( $hours, $minutes ) = array_map( 'intval', explode( ':', $value ) );

		if ( $hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59 ) {
			return $fallback;
		}

		return sprintf( '%02d:%02d', $hours, $minutes );
	}

	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=kargo-sayaci' ) ) . '">Ayarlar</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_kargo-sayaci' !== $hook ) {
			return;
		}

		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
		}

		if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		wp_enqueue_style(
			'kargo-sayaci-admin',
			plugins_url( 'assets/css/admin.css', __FILE__ ),
			array(),
			self::VERSION
		);

		wp_enqueue_script(
			'kargo-sayaci-admin',
			plugins_url( 'assets/js/admin.js', __FILE__ ),
			array(),
			self::VERSION,
			true
		);

		wp_localize_script(
			'kargo-sayaci-admin',
			'KargoSayaciAdminData',
			array(
				'optionName' => self::OPTION_NAME,
			)
		);
	}

	public function enqueue_frontend_assets() {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$settings = $this->get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$product = $this->get_current_product();

		if ( $this->is_product_disabled_by_settings( $product, $settings ) ) {
			return;
		}

		wp_enqueue_style(
			'kargo-sayaci-frontend',
			plugins_url( 'assets/css/frontend.css', __FILE__ ),
			array(),
			self::VERSION
		);

		wp_enqueue_script(
			'kargo-sayaci-frontend',
			plugins_url( 'assets/js/frontend.js', __FILE__ ),
			array(),
			self::VERSION,
			true
		);

		wp_localize_script( 'kargo-sayaci-frontend', 'KargoSayaciData', $this->get_frontend_settings( $settings, $product ) );
	}

	private function get_frontend_settings( $settings, $product = null ) {
		$days = array();
		$closures = array();

		foreach ( $settings['days'] as $day_index => $day_settings ) {
			$days[ $day_index ] = array(
				'enabled'    => ! empty( $day_settings['enabled'] ),
				'start'      => $day_settings['start'],
				'end'        => $day_settings['end'],
				'showToday'  => ! empty( $day_settings['show_today'] ),
				'todayLabel' => $day_settings['today_label'],
			);
		}

		foreach ( $settings['closures'] as $closure ) {
			if ( empty( $closure['enabled'] ) ) {
				continue;
			}

			$closures[] = array(
				'label'     => $closure['label'],
				'startDate' => $closure['start_date'],
				'startTime' => $closure['start_time'],
				'endDate'   => $closure['end_date'],
				'endTime'   => $closure['end_time'],
			);
		}

		$timezone = wp_timezone_string();

		if ( preg_match( '/^[+-]\d{2}:\d{2}$/', $timezone ) ) {
			$timezone = '';
		}

		return array(
			'enabled'        => ! empty( $settings['enabled'] ),
			'textBefore'     => $settings['normal_text'],
			'highlightText'  => $settings['highlight_text'],
			'deliveryText'   => $settings['delivery_text'],
			'deliveryImage'  => plugins_url( 'assets/yurtici-kargo.png', __FILE__ ),
			'hideBackorder'  => ! empty( $settings['hide_backorder'] ),
			'hideOutOfStock' => ! empty( $settings['hide_out_of_stock'] ),
			'productStock'   => $this->get_product_stock_data( $product ),
			'timezone'       => $timezone,
			'gmtOffset'      => (float) get_option( 'gmt_offset', 0 ),
			'style'          => array(
				'backgroundColor' => $settings['background_color'],
				'boxTextColor'    => $settings['box_text_color'],
				'textColor'       => $settings['text_color'],
				'accentTextColor' => $settings['accent_text_color'],
				'todayTextColor'  => $settings['today_text_color'],
				'cardBgColor'     => $settings['card_bg_color'],
				'bodyBgColor'     => $settings['body_bg_color'],
				'borderColor'     => $settings['border_color'],
				'deliveryLabelColor' => $settings['delivery_label_color'],
				'deliveryValueColor' => $settings['delivery_value_color'],
			),
			'days'           => $days,
			'closures'       => $closures,
		);
	}

	private function get_current_product() {
		global $product;

		if ( $product instanceof WC_Product ) {
			return $product;
		}

		$product_id = get_queried_object_id();

		return $product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
	}

	private function get_product_stock_data( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return array(
				'isInStock'         => true,
				'backordersAllowed' => false,
			);
		}

		return array(
			'isInStock'         => $product->is_in_stock(),
			'backordersAllowed' => $product->backorders_allowed(),
		);
	}

	private function is_product_disabled_by_settings( $product, $settings ) {
		if ( ! $product instanceof WC_Product || empty( $settings['disabled_products'] ) ) {
			return false;
		}

		return in_array( (int) $product->get_id(), $settings['disabled_products'], true );
	}

	private function get_product_select_label( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$label = $product->get_name();
		$sku   = $product->get_sku();

		if ( '' !== $sku ) {
			$label .= ' - SKU: ' . $sku;
		}

		return sprintf( '#%1$d - %2$s', $product->get_id(), $label );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings          = $this->get_settings();
		$today_day_index   = (int) current_time( 'w' );
		$today_settings    = isset( $settings['days'][ $today_day_index ] ) ? $settings['days'][ $today_day_index ] : $settings['days'][1];
		$preview_highlight = $this->build_preview_highlight_text( $settings['highlight_text'], $today_settings );
		$day_labels        = $this->get_day_labels();
		$preview_style     = sprintf(
			'--kargo-bg:%1$s;--kargo-logo-bg:%2$s;--kargo-text:%3$s;--kargo-accent:%4$s;--kargo-today:%5$s;--kargo-card-bg:%6$s;--kargo-body-bg:%7$s;--kargo-border:%8$s;--kargo-delivery-label:%9$s;--kargo-delivery-value:%10$s;',
			esc_attr( $settings['background_color'] ),
			esc_attr( $settings['box_text_color'] ),
			esc_attr( $settings['text_color'] ),
			esc_attr( $settings['accent_text_color'] ),
			esc_attr( $settings['today_text_color'] ),
			esc_attr( $settings['card_bg_color'] ),
			esc_attr( $settings['body_bg_color'] ),
			esc_attr( $settings['border_color'] ),
			esc_attr( $settings['delivery_label_color'] ),
			esc_attr( $settings['delivery_value_color'] )
		);
		?>
		<div class="wrap kargo-sayaci-admin">
			<div class="kargo-sayaci-admin__header">
				<img class="kargo-sayaci-admin__logo" src="<?php echo esc_url( plugins_url( 'assets/icon-64.png', __FILE__ ) ); ?>" alt="Kargo Sayacı">
				<div>
					<h1>Kargo Sayacı <span>v<?php echo esc_html( self::VERSION ); ?></span></h1>
					<p>WooCommerce ürün sayfalarındaki kargo geri sayım alanını panelden yönetin, renklendirin ve gün bazında planlayın.</p>
				</div>
			</div>

			<?php settings_errors( 'kargo_sayaci_messages' ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'kargo_sayaci_group' ); ?>

				<div class="kargo-sayaci-admin__grid">
					<section class="kargo-sayaci-admin__card">
						<h2>Genel Ayarlar</h2>
						<div class="kargo-sayaci-admin__field">
							<label class="kargo-sayaci-admin__checkbox">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
								Sayacı aktif et
							</label>
						</div>

						<div class="kargo-sayaci-admin__field">
							<label for="kargo-normal-text">Normal metin</label>
							<input id="kargo-normal-text" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[normal_text]" value="<?php echo esc_attr( $settings['normal_text'] ); ?>">
							<p class="description">Sayaç kutularının yanında görünen düz metin alanı.</p>
						</div>

						<div class="kargo-sayaci-admin__field">
							<label for="kargo-highlight-text">Vurgulu metin</label>
							<input id="kargo-highlight-text" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[highlight_text]" value="<?php echo esc_attr( $settings['highlight_text'] ); ?>">
							<p class="description"><code>{{today}}</code> yazarsanız ilgili gün için "bugün" kelimesi eklenir ve "Bugün" vurgu rengiyle ayrı renklendirilir.</p>
						</div>

						<div class="kargo-sayaci-admin__field">
							<label for="kargo-delivery-text">Tahmini teslim metni</label>
							<input id="kargo-delivery-text" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[delivery_text]" value="<?php echo esc_attr( $settings['delivery_text'] ); ?>">
							<p class="description">Sayaç alanının altında Yurtiçi Kargo görseliyle birlikte gösterilir.</p>
						</div>

						<div class="kargo-sayaci-admin__field">
							<label for="kargo-disabled-products">Sayaç kapalı ürünler</label>
							<select
								id="kargo-disabled-products"
								class="wc-product-search kargo-sayaci-admin__product-select"
								multiple="multiple"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disabled_products][]"
								data-placeholder="Ürün adıyla ara ve seç"
								data-action="woocommerce_json_search_products"
								data-minimum_input_length="0"
								data-allow_clear="true">
								<?php foreach ( $settings['disabled_products'] as $disabled_product_id ) : ?>
									<?php $disabled_product = function_exists( 'wc_get_product' ) ? wc_get_product( $disabled_product_id ) : null; ?>
									<?php if ( $disabled_product instanceof WC_Product ) : ?>
										<option value="<?php echo esc_attr( $disabled_product_id ); ?>" selected="selected"><?php echo esc_html( $this->get_product_select_label( $disabled_product ) ); ?></option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
							<p class="description">Ürün adını arayıp seçin. Seçilen ürünlerde ürün stokta olsa bile sayaç gösterilmez.</p>
						</div>

						<div class="kargo-sayaci-admin__colors">
							<div class="kargo-sayaci-admin__field">
								<label for="kargo-bg-color">Üst bant rengi</label>
								<input id="kargo-bg-color" type="color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[background_color]" value="<?php echo esc_attr( $settings['background_color'] ); ?>">
							</div>

							<div class="kargo-sayaci-admin__field">
								<label for="kargo-card-bg-color">Kart zemin rengi</label>
								<input id="kargo-card-bg-color" type="color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[card_bg_color]" value="<?php echo esc_attr( $settings['card_bg_color'] ); ?>">
							</div>

							<div class="kargo-sayaci-admin__field">
								<label for="kargo-body-bg-color">Alt alan zemin rengi</label>
								<input id="kargo-body-bg-color" type="color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[body_bg_color]" value="<?php echo esc_attr( $settings['body_bg_color'] ); ?>">
							</div>

							<div class="kargo-sayaci-admin__field">
								<label for="kargo-border-color">Kenarlık rengi</label>
								<input id="kargo-border-color" type="color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[border_color]" value="<?php echo esc_attr( $settings['border_color'] ); ?>">
							</div>

							<div class="kargo-sayaci-admin__field">
								<label for="kargo-box-text-color">Logo kutusu rengi</label>
								<input id="kargo-box-text-color" type="color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[box_text_color]" value="<?php echo esc_attr( $settings['box_text_color'] ); ?>">
							</div>

							<div class="kargo-sayaci-admin__field">
								<label for="kargo-text-color">Normal metin rengi</label>
								<input id="kargo-text-color" type="color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>">
							</div>

							<div class="kargo-sayaci-admin__field">
								<label for="kargo-accent-color">Vurgulu metin rengi</label>
								<input id="kargo-accent-color" type="color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[accent_text_color]" value="<?php echo esc_attr( $settings['accent_text_color'] ); ?>">
							</div>

							<div class="kargo-sayaci-admin__field">
								<label for="kargo-today-color">"Bugün" vurgu rengi</label>
								<input id="kargo-today-color" type="color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[today_text_color]" value="<?php echo esc_attr( $settings['today_text_color'] ); ?>">
							</div>

							<div class="kargo-sayaci-admin__field">
								<label for="kargo-delivery-label-color">Teslim başlık rengi</label>
								<input id="kargo-delivery-label-color" type="color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[delivery_label_color]" value="<?php echo esc_attr( $settings['delivery_label_color'] ); ?>">
							</div>

							<div class="kargo-sayaci-admin__field">
								<label for="kargo-delivery-value-color">Teslim metni rengi</label>
								<input id="kargo-delivery-value-color" type="color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[delivery_value_color]" value="<?php echo esc_attr( $settings['delivery_value_color'] ); ?>">
							</div>
						</div>

						<div class="kargo-sayaci-admin__field">
							<label class="kargo-sayaci-admin__checkbox">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_backorder]" value="1" <?php checked( ! empty( $settings['hide_backorder'] ) ); ?>>
								Backorder ürünlerde sayacı gizle
							</label>
						</div>

						<div class="kargo-sayaci-admin__field">
							<label class="kargo-sayaci-admin__checkbox">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_out_of_stock]" value="1" <?php checked( ! empty( $settings['hide_out_of_stock'] ) ); ?>>
								Stokta olmayan ürünlerde sayacı gizle
							</label>
						</div>
					</section>

					<section class="kargo-sayaci-admin__card">
						<h2>Canlı Önizleme</h2>
						<div class="kargo-sayaci-preview__toolbar">
							<div class="kargo-sayaci-admin__field">
								<label for="kargo-preview-day-select">Önizleme günü</label>
								<select id="kargo-preview-day-select">
									<?php foreach ( $day_labels as $day_index => $day_label ) : ?>
										<option value="<?php echo esc_attr( $day_index ); ?>" <?php selected( $today_day_index, $day_index ); ?>><?php echo esc_html( $day_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<p class="description">Renkleri, metinleri ve gün ayarlarını değiştirince önizleme alanı anında güncellenir.</p>
						</div>
						<div id="kargo-preview-panel" class="kargo-sayaci-preview" style="<?php echo esc_attr( $preview_style ); ?>">
							<div class="kargo-sayaci-preview__summary">
								<p id="kargo-preview-status" class="kargo-sayaci-preview__status">Önizleme hazır.</p>
								<p id="kargo-preview-meta" class="kargo-sayaci-preview__meta"></p>
							</div>
							<div class="kargo-sayaci-preview__content">
								<div class="kargo-sayaci-preview__lead">
									<span class="kargo-sayaci-preview__icon" aria-hidden="true"></span>
									<div id="kargo-preview-text" class="kargo-sayaci-preview__text">
										<strong>2 saat 14 dakika 36 saniye</strong>
										<span id="kargo-preview-normal"><?php echo esc_html( '' !== $settings['normal_text'] ? ' ' . $settings['normal_text'] . ( '' !== $preview_highlight ? ' ' : '' ) : '' ); ?></span>
										<strong id="kargo-preview-highlight"><?php echo esc_html( $preview_highlight ); ?></strong>
									</div>
								</div>
								<div class="kargo-sayaci-preview__body">
									<div class="kargo-sayaci-preview__brand">
										<img src="<?php echo esc_url( plugins_url( 'assets/yurtici-kargo.png', __FILE__ ) ); ?>" alt="Yurtiçi Kargo">
									</div>
									<div class="kargo-sayaci-preview__delivery">
										<span>
											<span class="kargo-sayaci-preview__delivery-title">Tahmini Teslim:</span>
											<strong id="kargo-preview-delivery"><?php echo esc_html( $settings['delivery_text'] ); ?></strong>
										</span>
									</div>
								</div>
							</div>
						</div>
						<p class="description">Kaydetmeden önce görünümü, seçili günün açık/kapalı durumunu ve mesaj akışını buradan kontrol edebilirsin.</p>
					</section>
				</div>

				<section class="kargo-sayaci-admin__card kargo-sayaci-admin__card--wide">
					<h2>Özel Gün Kapatmaları</h2>
					<p class="description">Bayram, resmi tatil veya özel operasyon günleri için tarih ve saat aralığı girin. Bu aralıklarda sayaç otomatik gizlenir.</p>

					<div class="kargo-sayaci-admin__table-wrap">
						<table class="widefat striped kargo-sayaci-admin__table" id="kargo-closures-table">
							<thead>
								<tr>
									<th>Aktif</th>
									<th>Not</th>
									<th>Başlangıç</th>
									<th>Bitiş</th>
									<th>İşlem</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $settings['closures'] as $closure_index => $closure ) : ?>
									<tr>
										<td>
											<label class="kargo-sayaci-admin__checkbox">
												<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[closures][<?php echo esc_attr( $closure_index ); ?>][enabled]" value="1" <?php checked( ! empty( $closure['enabled'] ) ); ?>>
												Açık
											</label>
										</td>
										<td>
											<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[closures][<?php echo esc_attr( $closure_index ); ?>][label]" value="<?php echo esc_attr( $closure['label'] ); ?>" placeholder="Ramazan Bayramı">
										</td>
										<td class="kargo-sayaci-admin__date-time">
											<input type="date" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[closures][<?php echo esc_attr( $closure_index ); ?>][start_date]" value="<?php echo esc_attr( $closure['start_date'] ); ?>">
											<input type="time" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[closures][<?php echo esc_attr( $closure_index ); ?>][start_time]" value="<?php echo esc_attr( $closure['start_time'] ); ?>">
										</td>
										<td class="kargo-sayaci-admin__date-time">
											<input type="date" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[closures][<?php echo esc_attr( $closure_index ); ?>][end_date]" value="<?php echo esc_attr( $closure['end_date'] ); ?>">
											<input type="time" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[closures][<?php echo esc_attr( $closure_index ); ?>][end_time]" value="<?php echo esc_attr( $closure['end_time'] ); ?>">
										</td>
										<td>
											<button type="button" class="button kargo-closure-remove">Sil</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p>
						<button type="button" class="button button-secondary" id="kargo-add-closure">Kapanış aralığı ekle</button>
					</p>
				</section>

				<section class="kargo-sayaci-admin__card kargo-sayaci-admin__card--wide">
					<h2>Günlük Planlama</h2>
					<p class="description">Her gün için görünme durumu, başlangıç ve bitiş saati ile "bugün" kelimesinin kullanımı ayrı ayrı ayarlanabilir.</p>

					<div class="kargo-sayaci-admin__table-wrap">
						<table class="widefat striped kargo-sayaci-admin__table">
							<thead>
								<tr>
									<th>Gün</th>
									<th>Aktif</th>
									<th>Başlangıç</th>
									<th>Bitiş</th>
									<th>"Bugün" kullan</th>
									<th>"Bugün" yazısı</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $day_labels as $day_index => $day_label ) : ?>
									<?php $day = $settings['days'][ $day_index ]; ?>
									<tr>
										<td><strong><?php echo esc_html( $day_label ); ?></strong></td>
										<td>
											<label class="kargo-sayaci-admin__checkbox">
												<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[days][<?php echo esc_attr( $day_index ); ?>][enabled]" value="1" <?php checked( ! empty( $day['enabled'] ) ); ?>>
												Göster
											</label>
										</td>
										<td>
											<input type="time" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[days][<?php echo esc_attr( $day_index ); ?>][start]" value="<?php echo esc_attr( $day['start'] ); ?>">
										</td>
										<td>
											<input type="time" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[days][<?php echo esc_attr( $day_index ); ?>][end]" value="<?php echo esc_attr( $day['end'] ); ?>">
										</td>
										<td>
											<label class="kargo-sayaci-admin__checkbox">
												<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[days][<?php echo esc_attr( $day_index ); ?>][show_today]" value="1" <?php checked( ! empty( $day['show_today'] ) ); ?>>
												Evet
											</label>
										</td>
										<td>
											<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[days][<?php echo esc_attr( $day_index ); ?>][today_label]" value="<?php echo esc_attr( $day['today_label'] ); ?>">
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>

				<?php submit_button( 'Ayarları Kaydet' ); ?>
			</form>
		</div>
		<?php
	}

	private function build_preview_highlight_text( $template, $today_settings ) {
		$today_label = ! empty( $today_settings['show_today'] ) ? $today_settings['today_label'] : '';
		$text        = str_ireplace( '{{today}}', $today_label, $template );
		$text        = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	private function get_day_labels() {
		return array(
			0 => 'Pazar',
			1 => 'Pazartesi',
			2 => 'Salı',
			3 => 'Çarşamba',
			4 => 'Perşembe',
			5 => 'Cuma',
			6 => 'Cumartesi',
		);
	}
}

register_activation_hook( __FILE__, array( 'Kargo_Sayaci_Plugin', 'activate' ) );
Kargo_Sayaci_Plugin::instance();
