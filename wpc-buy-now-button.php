<?php
/**
Plugin Name: WPC Buy Now Button for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Buy Now Button is the ultimate time-saving plugin that helps customers skip the cart page and get redirected right straight to the checkout step.
Version: 2.3.1
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-buy-now-button
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Requires PHP: 8.0
Tested up to: 6.8
WC requires at least: 3.0
WC tested up to: 10.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCBN_VERSION' ) && define( 'WPCBN_VERSION', '2.3.1' );
! defined( 'WPCBN_LITE' ) && define( 'WPCBN_LITE', __FILE__ );
! defined( 'WPCBN_FILE' ) && define( 'WPCBN_FILE', __FILE__ );
! defined( 'WPCBN_URI' ) && define( 'WPCBN_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCBN_DIR' ) && define( 'WPCBN_DIR', plugin_dir_path( __FILE__ ) );

include 'includes/hpos.php';

if ( ! function_exists( 'wpcbn_init' ) ) {
	add_action( 'plugins_loaded', 'wpcbn_init', 11 );

	function wpcbn_init(): ?WPCleverWpcbn
    {
		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcbn_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcbn' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWpcbn {
				protected static mixed $param;
				protected static array $settings = [];
				protected static array $localization = [];
				protected static ?WPCleverWpcbn $instance = null;

				public static function instance(): ?WPCleverWpcbn
                {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				public function __construct() {
					self::$settings     = (array) get_option( 'wpcbn_settings', [] );
					self::$localization = (array) get_option( 'wpcbn_localization', [] );
					self::$param        = self::get_setting( 'parameter', 'buy-now' );

					add_action( 'init', [ $this, 'init' ] );
					add_filter( 'woocommerce_post_class', [ $this, 'product_class' ], 99, 2 );
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// add button for archive
					$position_archive = apply_filters( 'wpcbn_button_position_archive', self::get_setting( 'button_position_archive', 'after_add_to_cart' ) );

					switch ( $position_archive ) {
						case 'after_title':
							add_action( 'woocommerce_shop_loop_item_title', [ $this, 'button_archive' ], 11 );
							break;
						case 'after_rating':
							add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'button_archive' ], 6 );
							break;
						case 'after_price':
							add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'button_archive' ], 11 );
							break;
						case 'before_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', [ $this, 'button_archive' ], 9 );
							break;
						case 'after_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', [ $this, 'button_archive' ], 11 );
							break;
					}

					// add button for single
					$position_single = apply_filters( 'wpcbn_button_position_single', self::get_setting( 'button_position_single', 'after_add_to_cart' ) );

					switch ( $position_single ) {
						case 'before_add_to_cart':
							add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'button_single' ] );
							break;
						case 'after_add_to_cart':
							add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'button_single' ] );
							break;
					}

					// WPC AJAX Add to Cart
					add_filter( 'wooaa_ignore_form_data', [ $this, 'ignore_form_data' ] );

					// add to cart
					add_action( 'template_redirect', [ $this, 'handle_buy_now' ] );
					add_filter( 'woocommerce_add_to_cart_redirect', [ $this, 'add_to_cart_redirect' ], 9999 );

					// dropdown multiple
					add_filter( 'wp_dropdown_cats', [ $this, 'dropdown_cats_multiple' ], 10, 2 );
				}

				public static function get_settings() {
					return apply_filters( 'wpcbn_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						if ( self::$settings[ $name ] !== '' ) {
							$setting = self::$settings[ $name ];
						} else {
							$setting = $default;
						}
					} else {
						$setting = get_option( 'wpcbn_' . $name, $default );
					}

					return apply_filters( 'wpcbn_get_setting', $setting, $name, $default );
				}

				public static function localization( $key = '', $default = '' ) {
					$str = '';

					if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
						$str = self::$localization[ $key ];
					} elseif ( ! empty( $default ) ) {
						$str = $default;
					}

					return apply_filters( 'wpcbn_localization_' . $key, $str );
				}

				public function init(): void
                {
					// load text-domain
					load_plugin_textdomain( 'wpc-buy-now-button', false, basename( WPCBN_DIR ) . '/languages/' );

					// parameter
					self::$param = apply_filters( 'wpcbn_parameter', ( ! empty( self::$param ) ? sanitize_title( self::$param ) : 'buy-now' ) );

					// shortcode
					add_shortcode( 'wpcbn_btn_archive', [ $this, 'archive_shortcode' ] );
					add_shortcode( 'wpcbn_btn_single', [ $this, 'single_shortcode' ] );
				}

				public function enqueue_scripts(): void
                {
					wp_enqueue_style( 'wpcbn-frontend', WPCBN_URI . 'assets/css/frontend.css', [], WPCBN_VERSION );
					wp_enqueue_script( 'wpcbn-frontend', WPCBN_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCBN_VERSION, true );
					wp_localize_script( 'wpcbn-frontend', 'wpcbn_vars', apply_filters( 'wpcbn_vars', [
							'nonce'      => wp_create_nonce( 'wpcbn-security' ),
							'wc_ajax_url' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
						] )
					);
				}

				public function admin_enqueue_scripts(): void
                {
					wp_enqueue_style( 'wpcbn-backend', WPCBN_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCBN_VERSION );
					wp_enqueue_script( 'wpcbn-backend', WPCBN_URI . 'assets/js/backend.js', [
						'jquery',
						'selectWoo'
					], WPCBN_VERSION, true );
				}

				public function archive_shortcode( $attrs ) {
					$output = '';

					$attrs = shortcode_atts( [
						'id' => null
					], $attrs, 'wpcbn_btn_archive' );

					if ( ! $attrs['id'] ) {
						global $product;
					} else {
						$product = wc_get_product( $attrs['id'] );
					}

					if ( $product && $this->is_valid_product( $product, 'archive' ) ) {
						$attrs['id'] = $product_id = $product->get_id();
						$btn_text    = apply_filters( 'wpcbn_btn_archive_text', self::localization( 'button_text', esc_html__( 'Buy now', 'wpc-buy-now-button' ) ), $attrs );
						$btn_class   = apply_filters( 'wpcbn_btn_archive_class', 'wpcbn-btn wpcbn-btn-archive button product_type_simple add_to_cart_button', $attrs );
						$btn_href    = apply_filters( 'wpcbn_redirect', self::get_setting( 'redirect', 'checkout' ) ) === 'cart' ? wc_get_cart_url() : wc_get_checkout_url();
						$output      .= sprintf( '<a href="%s?' . self::$param . '=%s" data-quantity="1" class="%s" data-product_id="%s" rel="nofollow">%s</a>', esc_url( $btn_href ), esc_attr( $product_id ), esc_attr( $btn_class ), esc_attr( $product_id ), esc_html( $btn_text ) );
					}

					return apply_filters( 'wpcbn_btn_archive', $output, $attrs );
				}

				public function single_shortcode( $attrs ) {
					$output = '';

					$attrs = shortcode_atts( [
						'id' => null
					], $attrs, 'wpcbn_btn_single' );

					if ( ! $attrs['id'] ) {
						global $product;
					} else {
						$product = wc_get_product( $attrs['id'] );
					}

					if ( $product && $this->is_valid_product( $product, 'single' ) ) {
						$attrs['id'] = $product_id = $product->get_id();
						$btn_text    = apply_filters( 'wpcbn_btn_single_text', self::localization( 'button_text', esc_html__( 'Buy now', 'wpc-buy-now-button' ) ), $attrs );
						$btn_class   = apply_filters( 'wpcbn_btn_single_class', 'wpcbn-btn wpcbn-btn-single wpcbn-btn-' . $product->get_type() . ' single_add_to_cart_button button alt', $attrs );
						$output      .= sprintf( '<button type="submit" name="' . esc_attr( self::$param ) . '" value="%d" class="%s" data-product_id="%s">%s</button>', esc_attr( $product_id ), esc_attr( $btn_class ), esc_attr( $product_id ), esc_html( $btn_text ) );
					}

					return apply_filters( 'wpcbn_btn_single', $output, $attrs );
				}

				public function is_valid_product( $product, $context = 'archive' ) {
					// Early return if product is invalid
					if (!$product instanceof WC_Product) {
						return apply_filters( 'wpcbn_is_valid_product', false, $product, $context );
					}

					// Check basic product conditions
					$is_purchasable = $product->is_purchasable();
					$is_in_stock    = $product->is_in_stock();

					// If either condition is false, return early
					if ( ! $is_purchasable || ! $is_in_stock ) {
						return apply_filters( 'wpcbn_is_valid_product', false, $product, $context );
					}

					// Check categories
					$selected_cats = self::get_setting( 'cats', [] );

					if ( ! empty( $selected_cats ) && $selected_cats[0] !== '0' ) {
						if ( ! has_term( $selected_cats, 'product_cat', $product->get_id() ) ) {
							return apply_filters( 'wpcbn_is_valid_product', false, $product, $context );
						}
					}

					return apply_filters( 'wpcbn_is_valid_product', true, $product, $context );
				}

				public function product_class( $classes, $product ) {
					if ( ( self::get_setting( 'hide_atc', 'no' ) === 'yes' ) && $product && $this->is_valid_product( $product ) ) {
						$classes[] = 'wpcbn-hide-atc';
					}

					return $classes;
				}

				public function action_links( $links, $file ): array
                {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpc-buy-now-button&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-buy-now-button' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				public function row_meta( $links, $file ): array
                {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="https://wordpress.org/support/plugin/wpc-buy-now-button" target="_blank">' . esc_html__( 'Community support', 'wpc-buy-now-button' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				public function register_settings(): void
                {
					// settings
					register_setting( 'wpcbn_settings', 'wpcbn_settings' );

					// localization
					register_setting( 'wpcbn_localization', 'wpcbn_localization' );
				}

				public function admin_menu(): void
                {
					add_submenu_page( 'woocommerce', esc_html__( 'WPC Buy Now Button', 'wpc-buy-now-button' ), esc_html__( 'Buy Now Button', 'wpc-buy-now-button' ), 'manage_options', 'wpc-buy-now-button', [
						$this,
						'admin_menu_content'
					] );
				}

				public function admin_menu_content(): void
                {
					$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1><?php esc_html_e( 'WPC Buy Now Button', 'wpc-buy-now-button' ); ?></h1>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-buy-now-button' ); ?></p>
                            </div>
						<?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-buy-now-button&tab=settings' ) ); ?>"
                                   class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'wpc-buy-now-button' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-buy-now-button&tab=localization' ) ); ?>"
                                   class="<?php echo esc_attr( $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Localization', 'wpc-buy-now-button' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) { ?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th><?php esc_html_e( 'General', 'wpc-buy-now-button' ); ?></th>
                                            <td><?php esc_html_e( 'General settings.', 'wpc-buy-now-button' ); ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Button position on archive', 'wpc-buy-now-button' ); ?></th>
                                            <td>
												<?php $position_archive = apply_filters( 'wpcbn_button_position_archive', 'default' ); ?>
                                                <label>
                                                    <select name="wpcbn_settings[button_position_archive]" <?php echo esc_attr( $position_archive !== 'default' ? 'disabled' : '' ); ?>>
														<?php if ( $position_archive === 'default' ) {
															$position_archive = self::get_setting( 'button_position_archive', 'after_add_to_cart' );
														} ?>
                                                        <option value="after_title" <?php selected( $position_archive, 'after_title' ); ?>><?php esc_html_e( 'After title', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="after_rating" <?php selected( $position_archive, 'after_rating' ); ?>><?php esc_html_e( 'After rating', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="after_price" <?php selected( $position_archive, 'after_price' ); ?>><?php esc_html_e( 'After price', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="before_add_to_cart" <?php selected( $position_archive, 'before_add_to_cart' ); ?>><?php esc_html_e( 'Before add to cart button', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="after_add_to_cart" <?php selected( $position_archive, 'after_add_to_cart' ); ?>><?php esc_html_e( 'After add to cart button', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="0" <?php selected( $position_archive, '0' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-buy-now-button' ); ?></option>
                                                    </select> </label>
                                                <span class="description"><?php printf( /* translators: shortcode */ esc_html__( 'You also can use the shortcode %s', 'wpc-buy-now-button' ), '<code>[wpcbn_btn_archive]</code>' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Button position on single', 'wpc-buy-now-button' ); ?></th>
                                            <td>
												<?php $position_single = apply_filters( 'wpcbn_button_position_single', 'default' ); ?>
                                                <label>
                                                    <select name="wpcbn_settings[button_position_single]" <?php echo esc_attr( $position_single !== 'default' ? 'disabled' : '' ); ?>>
														<?php if ( $position_single === 'default' ) {
															$position_single = self::get_setting( 'button_position_single', 'after_add_to_cart' );
														} ?>
                                                        <option value="before_add_to_cart" <?php selected( $position_single, 'before_add_to_cart' ); ?>><?php esc_html_e( 'Before add to cart button', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="after_add_to_cart" <?php selected( $position_single, 'after_add_to_cart' ); ?>><?php esc_html_e( 'After add to cart button', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="0" <?php selected( $position_single, '0' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-buy-now-button' ); ?></option>
                                                    </select> </label>
                                                <span class="description"><?php printf( /* translators: shortcode */ esc_html__( 'You also can use the shortcode %s', 'wpc-buy-now-button' ), '<code>[wpcbn_btn_single]</code>' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Parameter', 'wpc-buy-now-button' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="text" name="wpcbn_settings[parameter]"
                                                           placeholder="buy-now"
                                                           value="<?php echo self::get_setting( 'parameter' ); ?>"/>
                                                </label>
                                                <span class="description"><?php printf( /* translators: parameter */ esc_html__( 'Parameter for the Buy Now button or link. Default %s', 'wpc-buy-now-button' ), '<code>buy-now</code>' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Categories', 'wpc-buy-now-button' ); ?></th>
                                            <td>
												<?php
												$selected_cats = self::get_setting( 'cats' );

												if ( empty( $selected_cats ) ) {
													$selected_cats = [ 0 ];
												}

												wc_product_dropdown_categories(
													[
														'name'             => 'wpcbn_settings[cats]',
														'id'               => 'wpcbn_settings_cats',
														'hide_empty'       => 0,
														'value_field'      => 'id',
														'multiple'         => true,
														'show_option_all'  => esc_html__( 'All categories', 'wpc-buy-now-button' ),
														'show_option_none' => '',
														'selected'         => implode( ',', $selected_cats )
													] );
												?>
                                                <span class="description"><?php esc_html_e( 'Only show the Buy Now button for products in selected categories.', 'wpc-buy-now-button' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Hide add-to-cart button', 'wpc-buy-now-button' ); ?></th>
                                            <td>
												<?php $hide_atc = self::get_setting( 'hide_atc', 'no' ); ?>
                                                <label> <select name="wpcbn_settings[hide_atc]">
                                                        <option value="yes" <?php selected( $hide_atc, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="no" <?php selected( $hide_atc, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-buy-now-button' ); ?></option>
                                                    </select> </label>
                                                <span class="description"><?php esc_html_e( 'Hide the default add-to-cart button on products that already has Buy Now button.', 'wpc-buy-now-button' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Reset cart', 'wpc-buy-now-button' ); ?></th>
                                            <td>
												<?php $reset_cart = self::get_setting( 'reset_cart', 'no' ); ?>
                                                <label> <select name="wpcbn_settings[reset_cart]">
                                                        <option value="yes" <?php selected( $reset_cart, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="no" <?php selected( $reset_cart, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-buy-now-button' ); ?></option>
                                                    </select> </label>
                                                <span class="description"><?php esc_html_e( 'Reset the cart before doing buy now.', 'wpc-buy-now-button' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Action', 'wpc-buy-now-button' ); ?></th>
                                            <td>
                                                <p class="description"><?php esc_html_e( 'Choose action after doing buy now.', 'wpc-buy-now-button' ); ?></p>
												<?php $redirect = self::get_setting( 'redirect', 'checkout' ); ?>
                                                <label>
                                                    <select name="wpcbn_settings[redirect]" class="wpcbn_redirect"
                                                            style="float: left">
                                                        <option value="checkout" <?php selected( $redirect, 'checkout' ); ?>><?php esc_html_e( 'Redirect to Checkout page', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="cart" <?php selected( $redirect, 'cart' ); ?>><?php esc_html_e( 'Redirect to Cart page', 'wpc-buy-now-button' ); ?></option>
                                                        <option value="custom" <?php selected( $redirect, 'custom' ); ?>><?php esc_html_e( 'Redirect to Custom page', 'wpc-buy-now-button' ); ?></option>
                                                    </select> </label> <label>
                                                    <input name="wpcbn_settings[redirect_custom]" type="url"
                                                           class="regular-text wpcbn_hide_if_redirect wpcbn_show_if_redirect_custom"
                                                           value="<?php echo esc_url( self::get_setting( 'redirect_custom' ) ); ?>"
                                                           placeholder="https://"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcbn_settings' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'localization' ) { ?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'General', 'wpc-buy-now-button' ); ?></th>
                                            <td>
												<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-buy-now-button' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Button text', 'wpc-buy-now-button' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="text" class="regular-text"
                                                           name="wpcbn_localization[button_text]"
                                                           value="<?php echo esc_attr( self::localization( 'button_text' ) ); ?>"
                                                           placeholder="<?php esc_attr_e( 'Buy now', 'wpc-buy-now-button' ); ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcbn_localization' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } ?>
                        </div><!-- /.wpclever_settings_page_content -->
                    </div>
					<?php
				}

				public function button_archive(): void
                {
					echo do_shortcode( '[wpcbn_btn_archive]' );
				}

				public function button_single(): void
                {
					echo do_shortcode( '[wpcbn_btn_single]' );
				}

				public function handle_buy_now(): void
                {
					// Early return if required parameter is missing - check first to avoid unnecessary processing
					if ( ! isset( $_REQUEST[ self::$param ] ) ) {
						return;
					}

					// Only run on pages where buy-now makes sense (checkout, cart, or with the parameter)
					if ( ! is_checkout() && ! is_cart() && ! isset( $_REQUEST[ self::$param ] ) ) {
						return;
					}

					// Sanitize and validate input parameters
					$product_id = absint( $_REQUEST[ self::$param ] ?? 0 );
					if ( ! $product_id ) {
						return;
					}

					// Extract and sanitize other parameters
					$quantity     = floatval( $_REQUEST['quantity'] ?? 1 );
					$variation_id = absint( $_REQUEST['variation_id'] ?? 0 );

					// More efficient variation attributes collection
					$variation = array_filter(
						$_REQUEST,
						static function ( $value, $key ) {
							return str_starts_with( $key, 'attribute_' ) ? $value : null;
						},
						ARRAY_FILTER_USE_BOTH
					);

					// Get cart instance once
					$cart = WC()->cart;

					// Reset cart if needed
					if ( self::get_setting( 'reset_cart', 'no' ) === 'yes' ) {
						$cart->empty_cart();
					}

					// Add product to cart
					$cart->add_to_cart(
						$product_id,
						$quantity,
						$variation_id ?: 0,
						$variation_id ? $variation : []
					);

					// Determine redirect URL
					$redirect = $this->get_redirect_url();

					wp_safe_redirect( $redirect );
				}

				/**
				 * Helper method to determine redirect URL
				 * @return string
				 */
				private function get_redirect_url(): string
                {
					$redirect_type = apply_filters(
						'wpcbn_redirect',
						self::get_setting( 'redirect', 'checkout' )
					);

                    $redirect = match ($redirect_type) {
                        'checkout' => wc_get_checkout_url(),
                        'cart' => wc_get_cart_url(),
                        default => self::get_setting('redirect_custom', '/'),
                    };

					$redirect = esc_url( apply_filters( 'wpcbn_redirect_url', $redirect ) );

					return empty( $redirect ) ? '/' : $redirect;
				}

				public function ignore_form_data( $form_data ) {
					$form_data[] = self::$param;

					return $form_data;
				}

				public function add_to_cart_redirect( $url ) {
					if ( empty( $_REQUEST[ self::$param ] ) ) {
						return $url;
					}

					return $this->get_redirect_url();
				}

				public function dropdown_cats_multiple( $output, $r ) {
					if ( isset( $r['multiple'] ) && $r['multiple'] ) {
						$output = preg_replace( '/^<select/i', '<select multiple', $output );
						$output = str_replace( "name='{$r['name']}'", "name='{$r['name']}[]'", $output );

						foreach ( array_map( 'trim', explode( ",", $r['selected'] ) ) as $value ) {
							$output = str_replace( "value=\"$value\"", "value=\"$value\" selected", $output );
						}
					}

					return $output;
				}
			}

			return WPCleverWpcbn::instance();
		}

		return null;
	}
}

if ( ! function_exists( 'wpcbn_notice_wc' ) ) {
	function wpcbn_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Buy Now Button</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
