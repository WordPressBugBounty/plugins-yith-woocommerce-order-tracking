<?php // phpcs:ignore WordPress.NamingConventions
/**
 * YITH_WooCommerce_Order_Tracking class
 *
 * @package YITH\OrderTracking\Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'YITH_WooCommerce_Order_Tracking' ) ) {

	/**
	 * Implements features of YITH WooCommerce Order Tracking
	 *
	 * @class  YITH_WooCommerce_Order_Tracking
	 * @since  1.0.0
	 * @author YITH <plugins@yithemes.com>
	 */
	class YITH_WooCommerce_Order_Tracking {

		/**
		 * Pickedup_status_changed
		 *
		 * @var array order ids with pickedup statsus change
		 */
		protected $pickedup_status_changed = array();

		/**
		 * Panel
		 *
		 * @var $panel Panel Object
		 */
		protected $panel;

		/**
		 * Premium_landing
		 *
		 * @var string Premium version landing link
		 */
		protected $premium_landing = 'http://yithemes.com/themes/plugins/yith-woocommerce-order-tracking/';

		/**
		 * Official_documentation
		 *
		 * @var string Plugin official documentation
		 */
		protected $official_documentation = 'https://docs.yithemes.com/yith-woocommerce-order-tracking/';

		/**
		 * Panel_page
		 *
		 * @var string Yith WooCommerce Order Tracking panel page
		 */
		protected $panel_page = 'yith_woocommerce_order_tracking_panel';

		/**
		 * Default_carrier
		 *
		 * @var mixed|void  Default carrier name
		 */
		protected $default_carrier;

		/**
		 * Orders_pattern
		 *
		 * @var string  Customizable text to be shown on orders
		 */
		protected $orders_pattern;

		/**
		 * Order_text_position
		 *
		 * @var position of text related to order details page
		 */
		protected $order_text_position;

		/**
		 * Single instance of the class
		 *
		 * @var \YITH_WooCommerce_Order_Tracking
		 * @since 1.0.0
		 */
		protected static $instance;

		/**
		 * Returns single instance of the class
		 *
		 * @return YITH_WooCommerce_Order_Tracking
		 * @since  1.0.0
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 *
		 * Initialize plugin and registers actions and filters to be used
		 *
		 * @since  1.0
		 * @access public
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_panel' ), 5 );
			add_filter( 'plugin_action_links_' . plugin_basename( YITH_YWOT_DIR . '/' . basename( YITH_YWOT_FILE ) ), array( $this, 'action_links' ) );
			add_filter( 'yith_show_plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 3 );

			add_action( 'yith_order_tracking_premium', array( $this, 'premium_tab' ) );

			$this->initialize_settings();

			/**
			 * Enqueue scripts and styles.
			 */
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			/**
			 * Add metabox on order, to let vendor add order tracking code and carrier
			 */
			add_action( 'add_meta_boxes', array( $this, 'add_order_tracking_metabox' ), 10, 2 );

			/**
			 * Set default carrier name on new orders.
			 */
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'set_default_carrier' ) );

			/**
			 * Show icon on order list for picked up orders.
			 */
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'prepare_picked_up_icon' ), 10, 2 );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'prepare_picked_up_icon' ), 10, 2 );

			/**
			 * Save Order Meta Boxes
			 * */
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_order_tracking_metabox' ), 30, 2 );

			/**
			 * Register action to show tracking information on customer order page.
			 */
			$this->register_order_tracking_actions();

			/**
			 * Show shipped icon on my orders page.
			 */
			add_action( 'woocommerce_my_account_my_orders_actions', array( $this, 'show_picked_up_icon_on_orders' ), 99, 2 );

			/**
			 * Declare HPOS compatibility.
			 */
			add_action( 'before_woocommerce_init', array( $this, 'declare_wc_features_support' ) );
		}

		/**
		 * Retrieve the admin panel tabs.
		 *
		 * @return array
		 */
		protected function get_admin_panel_tabs(): array {
			$admin_tabs = array(
				'general' => array(
					'title'       => _x( 'Settings', 'Settings tab name', 'yith-woocommerce-order-tracking' ),
					'description' => _x( 'Configure the general settings of the plugin.', 'Tab description in plugin settings panel', 'yith-woocommerce-order-tracking' ),
					'icon'        => 'settings',
				),
			);

			if ( defined( 'YITH_YWOT_PREMIUM' ) ) {
				$open_ticket_url = 'https://yithemes.com/my-account/support/submit-a-ticket/';

				$admin_tabs['carriers'] = array(
					'title'       => _x( 'Carriers', 'Carriers tab name', 'yith-woocommerce-order-tracking' ),
					'description' => __( 'Select the carriers that will be used for orders shipping.', 'yith-woocommerce-order-tracking' ),
					'icon'        => '<svg data-slot="icon" aria-hidden="true" fill="none" stroke-width="1.5" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
				);

				$admin_tabs['import'] = array(
					'title'       => _x( 'Import tracking codes', 'Import tracking codes settings panel', 'yith-woocommerce-order-tracking' ),
					'description' => _x( 'Upload a CSV file to import tracking codes into your orders.', 'Tab description in plugin import panel', 'yith-woocommerce-order-tracking' ),
					'icon'        => '<svg data-slot="icon" aria-hidden="true" fill="none" stroke-width="1.5" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15M9 12l3 3m0 0 3-3m-3 3V2.25" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
				);

			}

			return apply_filters( 'yith_ywot_admin_panel_tabs', $admin_tabs );
		}

		/**
		 * Retrieve the store tools tab content.
		 *
		 * @return array
		 */
		protected function get_store_tools_tab(): array {
			return array(
				'items' => array(
					'wishlist'             => array(
						'name'           => 'Wishlist',
						'icon_url'       => YITH_YWOT_URL . 'assets/images/plugins/wishlist.svg',
						'url'            => '//yithemes.com/themes/plugins/yith-woocommerce-wishlist/',
						'description'    => _x(
							'Allow your customers to create lists of products they want and share them with family and friends.',
							'[YOUR STORE TOOLS TAB] Description for plugin YITH WooCommerce Wishlist',
							'yith-woocommerce-order-tracking'
						),
						'is_active'      => defined( 'YITH_WCWL_PREMIUM' ),
						'is_recommended' => true,
					),
					'gift-cards'           => array(
						'name'           => 'Gift Cards',
						'icon_url'       => YITH_YWOT_URL . 'assets/images/plugins/gift-cards.svg',
						'url'            => '//yithemes.com/themes/plugins/yith-woocommerce-gift-cards/',
						'description'    => _x(
							'Sell gift cards in your shop to increase your earnings and attract new customers.',
							'[YOUR STORE TOOLS TAB] Description for plugin YITH WooCommerce Gift Cards',
							'yith-woocommerce-order-tracking'
						),
						'is_active'      => defined( 'YITH_YWGC_PREMIUM' ),
						'is_recommended' => true,
					),
					'ajax-product-filter'  => array(
						'name'           => 'Ajax Product Filter',
						'icon_url'       => YITH_YWOT_URL . 'assets/images/plugins/ajax-product-filter.svg',
						'url'            => '//yithemes.com/themes/plugins/yith-woocommerce-ajax-product-filter/',
						'description'    => _x(
							'Help your customers to easily find the products they are looking for and improve the user experience of your shop.',
							'[YOUR STORE TOOLS TAB] Description for plugin YITH WooCommerce Ajax Product Filter',
							'yith-woocommerce-order-tracking'
						),
						'is_active'      => defined( 'YITH_WCAN_PREMIUM' ),
						'is_recommended' => false,
					),
					'product-addons'       => array(
						'name'           => 'Product Add-Ons & Extra Options',
						'icon_url'       => YITH_YWOT_URL . 'assets/images/plugins/product-add-ons.svg',
						'url'            => '//yithemes.com/themes/plugins/yith-woocommerce-product-add-ons/',
						'description'    => _x(
							'Add paid or free advanced options to your product pages using fields like radio buttons, checkboxes, drop-downs, custom text inputs, and more.',
							'[YOUR STORE TOOLS TAB] Description for plugin YITH WooCommerce Product Add-Ons',
							'yith-woocommerce-order-tracking'
						),
						'is_active'      => defined( 'YITH_WAPO_PREMIUM' ),
						'is_recommended' => false,
					),
					'dynamic-pricing'      => array(
						'name'           => 'Dynamic Pricing and Discounts',
						'icon_url'       => YITH_YWOT_URL . 'assets/images/plugins/dynamic-pricing-and-discounts.svg',
						'url'            => '//yithemes.com/themes/plugins/yith-woocommerce-dynamic-pricing-and-discounts/',
						'description'    => _x(
							'Increase conversions through dynamic discounts and price rules, and build powerful and targeted offers.',
							'[YOUR STORE TOOLS TAB] Description for plugin YITH WooCommerce Dynamic Pricing and Discounts',
							'yith-woocommerce-order-tracking'
						),
						'is_active'      => defined( 'YITH_YWDPD_PREMIUM' ),
						'is_recommended' => false,
					),
					'customize-my-account' => array(
						'name'           => 'Customize My Account Page',
						'icon_url'       => YITH_YWOT_URL . 'assets/images/plugins/customize-myaccount-page.svg',
						'url'            => '//yithemes.com/themes/plugins/yith-woocommerce-customize-my-account-page/',
						'description'    => _x(
							'Customize the My Account page of your customers by creating custom sections with promotions and ad-hoc content based on your needs.',
							'[YOUR STORE TOOLS TAB] Description for plugin YITH WooCommerce Customize My Account',
							'yith-woocommerce-order-tracking'
						),
						'is_active'      => defined( 'YITH_WCMAP_PREMIUM' ),
						'is_recommended' => false,
					),
					'points'               => array(
						'name'        => 'Points and Rewards',
						'icon_url'    => YITH_YWOT_URL . 'assets/images/plugins/points.svg',
						'url'         => '//yithemes.com/themes/plugins/yith-woocommerce-points-and-rewards/',
						'description' => __(
							'Loyalize your customers with an effective points-based loyalty program and instant rewards.',
							'yith-woocommerce-order-tracking'
						),
						'is_active'   => defined( 'YITH_YWPAR_PREMIUM' ),
					),
					'ajax-search'          => array(
						'name'           => 'Ajax Search',
						'icon_url'       => YITH_YWOT_URL . 'assets/images/plugins/ajax-search.svg',
						'url'            => '//yithemes.com/themes/plugins/yith-woocommerce-ajax-search/',
						'description'    => __(
							'Add an instant search form to your e-commerce shop and help your customers quickly find the products they want to buy.',
							'yith-woocommerce-order-tracking'
						),
						'is_active'      => defined( 'YITH_WCAS_PREMIUM' ),
						'is_recommended' => false,
					),
				),
			);
		}

		/**
		 * Retrieve the content for the welcome modals.
		 *
		 * @return array
		 */
		protected function get_welcome_modals(): array {
			return array(
				'show_in'  => 'panel',
				'on_close' => function () {
					update_option( 'yith-ywot-welcome-modal', 'no' );
				},
				'modals'   => array(
					'welcome' => array(
						'type'        => 'welcome',
						'description' => __( 'Allows your customers to easily track the shipping of orders placed in e-commerce. ', 'yith-woocommerce-order-tracking' ),
						'show'        => get_option( 'yith-ywot-welcome-modal', 'welcome' ) === 'welcome',
						'items'       => array(
							'documentation' => array(
								'url' => $this->official_documentation,
							),
							'how-to-video'  => array(
								'url' => array(
									'en' => 'https://www.youtube.com/watch?v=fVHiDMlkYlA',
									'it' => 'https://www.youtube.com/watch?v=iBSBFNVygRQ',
									'es' => 'https://www.youtube.com/watch?v=X-fOKp5aQRo',
								),
							),
							'feature'       => array(
								'title'       => __( '<mark>Enable the carriers</mark>     you want to use for the shipment of your orders', 'yith-woocommerce-order-tracking' ),
								'description' => __( 'and embark on this new adventure!', 'yith-woocommerce-order-tracking' ),
								'url'         => add_query_arg(
									array(
										'page' => 'yith_woocommerce_order_tracking_panel',
										'tab'  => 'carriers',
									),
									admin_url( 'admin.php' )
								),
							),
						),
					),
				),
			);
		}

		/**
		 * Register panel.
		 */
		public function register_panel() {
			if ( ! empty( $this->panel ) ) {
				return;
			}

			/**
			 * APPLY_FILTERS: ywot_panel_capabilities
			 *
			 * Filter the Order Tracking settings panel capabilities.
			 *
			 * @param string the capabilities
			 *
			 * @return string
			 */
			$args = array(
				'ui_version'       => 2,
				'create_menu_page' => true,
				'parent_slug'      => '',
				'page_title'       => 'YITH WooCommerce Order & Shipment Tracking',
				'menu_title'       => 'Order & Shipment Tracking',
				'capability'       => apply_filters( 'ywot_panel_capabilities', 'manage_options' ),
				'parent'           => '',
				'parent_page'      => 'yith_plugin_panel',
				'plugin_slug'      => YITH_YWOT_SLUG,
				'plugin-url'       => YITH_YWOT_URL,
				'page'             => $this->panel_page,
				'admin-tabs'       => $this->get_admin_panel_tabs(),
				'options-path'     => YITH_YWOT_DIR . '/plugin-options',
				'class'            => yith_set_wrapper_class(),
				'is_free'          => defined( 'YITH_YWOT_FREE_INIT' ),
				'is_premium'       => defined( 'YITH_YWOT_PREMIUM' ),
				'premium_tab'      => array(
					'landing_page_url'          => $this->get_premium_landing_uri(),
					'premium_features'          => array(
						__( 'Choose your carriers from a list of <b>480+ carriers</b> supported to <b>automatically get the tracking URL.</b>', 'yith-woocommerce-order-tracking' ),
						__( 'Enter also the <b>Estimated Delivery Date</b> and <b>share the tracking info via email</b> with customers when the order is completed.', 'yith-woocommerce-order-tracking' ),
						__( 'Save time by <b>importing multiple tracking info into your orders from a CSV file.</b>', 'yith-woocommerce-order-tracking' ),
						__( 'Automatically change the order status to "Completed" after the tracking data insertion.', 'yith-woocommerce-order-tracking' ),
						__( 'Use the built-in shortcode to <b>create a custom order tracking page</b> on your shop.', 'yith-woocommerce-order-tracking' ),
						'<b>' . __( 'Regular updates, translations, and premium support.', 'yith-woocommerce-order-tracking' ) . '</b>',
					),
					'main_image_url'            => YITH_YWOT_URL . 'assets/images/get-premium-order-tracking.jpg',
					'show_free_vs_premium_link' => true,
				),
			);

			if ( ! class_exists( 'YIT_Plugin_Panel_WooCommerce' ) ) {
				require_once 'plugin-fw/lib/yit-plugin-panel-wc.php';
			}

			$this->panel = new YIT_Plugin_Panel_WooCommerce( $args );
		}

		/**
		 * Action Links.
		 *
		 * @param array $links Plugin links.
		 *
		 * @return array
		 */
		public function action_links( $links ) {
			$links = yith_add_action_links( $links, $this->panel_page, false );

			return $links;
		}

		/**
		 * Adds action links to plugin admin page
		 *
		 * @param array    $new_row_meta_args Row meta args.
		 * @param string[] $plugin_meta   An array of the plugin's metadata, including the version, author, author URI, and plugin URI.
		 * @param string   $plugin_file   Path to the plugin file relative to the plugins directory.
		 *
		 * @return array
		 */
		public function plugin_row_meta( $new_row_meta_args, $plugin_meta, $plugin_file ) {
			if ( YITH_YWOT_FREE_INIT === $plugin_file ) {
				$new_row_meta_args['slug'] = YITH_YWOT_SLUG;
			}

			return $new_row_meta_args;
		}

		/**
		 * Return url to plugin panel page
		 *
		 * @param string $tab  Tab slug.
		 * @param array  $args Array of additional arguments.
		 * @return string Panel url
		 */
		public function get_panel_url( $tab = '', $args = array() ) {
			$args = array_merge(
				$args,
				array(
					'page' => $this->panel_page,
				)
			);

			if ( ! empty( $tab ) ) {
				$args['tab'] = $tab;
			}

			return add_query_arg( $args, admin_url( 'admin.php' ) );
		}

		/**
		 * Get the premium landing uri
		 *
		 * @since   1.0.0
		 * @return  string The premium landing link
		 */
		public function get_premium_landing_uri() {
			return $this->premium_landing;
		}

		/**
		 * Set values from plugin settings page.
		 */
		public function initialize_settings() {
			$this->default_carrier     = get_option( 'ywot_carrier_default_name' );
			$this->order_text_position = get_option( 'ywot_order_tracking_text_position', '1' );
		}

		/**
		 * Add scripts
		 *
		 * @since  1.0
		 */
		public function enqueue_scripts() {
			global $post, $pagenow;

			wp_register_style( 'tooltipster', YITH_YWOT_URL . 'assets/css/tooltipster.bundle.min.css', array(), '4.2.8' );
			wp_register_style( 'tooltipster-borderless', YITH_YWOT_URL . 'assets/css/tooltipster-sidetip-borderless.css', array(), '4.2.8-RC1' );
			wp_register_style( 'ywot_style', YITH_YWOT_URL . 'assets/css/ywot_style.css', array(), YITH_YWOT_VERSION );

			wp_register_script( 'tooltipster', YITH_YWOT_URL . 'assets/js/tooltipster.bundle.min.js', array( 'jquery' ), '4.2.8', true );

			/**
			 * APPLY_FILTERS: yith_wc_order_tracking_ywot_js_path
			 *
			 * Filter the plugin script path URL.
			 *
			 * @param string the plugin script path URL
			 *
			 * @return string
			 */
			wp_register_script( 'ywot_script', apply_filters( 'yith_wc_order_tracking_ywot_js_path', YITH_YWOT_URL . 'assets/js/ywot.js' ), array( 'jquery-form', 'jquery' ), YITH_YWOT_VERSION, true );

			$can_be_enqueue = false;
			$if_shop_order  = false;

			if ( function_exists( 'get_current_screen' ) ) {
				$current_screen_id = get_current_screen() ? get_current_screen()->id : '';

				$if_shop_order = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) === $current_screen_id : 'shop-order' === $current_screen_id;
			}

			if ( is_admin() && ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'yith_woocommerce_order_tracking_panel' === $_GET['page'] ) || ( 'edit.php' === $pagenow && isset( $_GET['post_type'] ) && 'shop_order' === $_GET['post_type'] ) || ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) || ( 'post-new.php' === $pagenow && isset( $_GET['post_type'] ) && 'shop_order' === $_GET['post_type'] ) || $if_shop_order ) { // phpcs:ignore WordPress.Security.NonceVerification
				$can_be_enqueue = true;
			} elseif ( $post && ( has_shortcode( $post->post_content, 'yith_check_tracking_info_form' ) || has_shortcode( $post->post_content, 'yith_show_available_plugin_carriers' ) ) || is_account_page() ) {
				$can_be_enqueue = true;
			}

			if ( $can_be_enqueue ) {
				wp_enqueue_style( 'tooltipster' );
				wp_enqueue_style( 'tooltipster-borderless' );
				wp_enqueue_style( 'ywot_style' );

				wp_enqueue_script( 'tooltipster' );

				wp_localize_script(
					'ywot_script',
					'ywot',
					array(
						'is_account_page' => is_account_page(),
					)
				);

				wp_enqueue_script( 'ywot_script' );
			}

			if ( $if_shop_order && class_exists( 'YIT_Assets' ) ) {
				// Make sure pluing-fw scripts and styles are registered.
				if ( ! wp_script_is( 'yith-plugin-fw-fields', 'registered' ) || ! wp_style_is( 'yith-plugin-fw-fields', 'registered' ) ) {
					YIT_Assets::instance()->register_styles_and_scripts();
				}

				if ( ! defined( 'YITH_YWRAQ_PREMIUM' ) ) {
					wp_enqueue_script( 'yith-plugin-fw-fields' );
					wp_enqueue_style( 'yith-plugin-fw-fields' );
				}
			}
		}

		/**
		 * Add a metabox on backend order page, to be filled with order tracking information
		 *
		 * @param string  $post_type the current post type.
		 * @param WP_Post $post post.
		 *
		 * @since  1.0.0
		 */
		public function add_order_tracking_metabox( $post_type, $post ) {
			/**
			 * APPLY_FILTERS: yith_woocommerce_order_tracking_for_vendor_enabled
			 *
			 * Filter the condition to enable the tracking features to the YITH vendors.
			 *
			 * @param bool yes to enable it, false to not. Default: yes
			 *
			 * @return bool
			 */
			if ( ! apply_filters( 'yith_woocommerce_order_tracking_for_vendor_enabled', true, $post ) ) {
				return;
			}

			if ( in_array( $post_type, array( wc_get_page_screen_id( 'shop-order' ), 'shop_order' ), true ) ) {
				add_meta_box(
					'yith-order-tracking-information',
					_x( 'Order tracking', 'Order tracking metabox title', 'yith-woocommerce-order-tracking' ),
					array(
						$this,
						'show_order_tracking_metabox',
					),
					$post_type,
					'side',
					'high'
				);
			}
		}

		/**
		 * Show metabox content for tracking information on backend order page
		 *
		 * @param WP_Post $post the order object that is currently shown.
		 *
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function show_order_tracking_metabox( $post ) {
			if ( ! apply_filters( 'yith_woocommerce_order_tracking_for_vendor_enabled', true, $post ) ) {
				return;
			}

			$order = wc_get_order( $post );

			if ( ! $order ) {
				return;
			}

			$order_tracking_code = $order->get_meta( 'ywot_tracking_code' );
			$order_carrier_name  = $order->get_meta( 'ywot_carrier_name' );
			$order_pick_up_date  = $order->get_meta( 'ywot_pick_up_date' );
			$order_carrier_url   = $order->get_meta( 'ywot_carrier_url' );
			$order_picked_up     = $order->get_meta( 'ywot_picked_up' );

			$picked_up_field = array(
				'id'    => 'ywot_picked_up',
				'name'  => 'ywot_picked_up',
				'title' => __( 'Order picked up by Carrier', 'yith-woocommerce-order-tracking' ),
				'type'  => 'onoff',
				'value' => $order_picked_up,
			);

			$date_picker_field = array(
				'id'                => 'ywot_pick_up_date',
				'name'              => 'ywot_pick_up_date',
				'title'             => __( 'Pickup date:', 'yith-woocommerce-order-tracking' ),
				'type'              => 'datepicker',
				'value'             => $order_pick_up_date,
				'data'              => array(
					'date-format' => 'yy-mm-dd',
				),
				'custom_attributes' => array(
					'placeholder' => __( 'Enter pickup date', 'yith-woocommerce-order-tracking' ),
				),
			);

			?>
			<div class="yith-ywot-track-information yith-plugin-ui">
				<div class="yith-ywot-order-picked-up-container">
					<label class="yith-ywot-order-picked-up-label" for="ywot_picked_up"><?php echo esc_html( $picked_up_field['title'] ); ?></label>
					<?php
						yith_plugin_fw_get_field( $picked_up_field, true, false );
					?>
				</div>
				<p class="yith-ywot-tracking-code">
					<label for="ywot_tracking_code"><?php esc_html_e( 'Tracking code:', 'yith-woocommerce-order-tracking' ); ?></label>
					<input type="text" name="ywot_tracking_code" id="ywot_tracking_code" placeholder="<?php esc_attr_e( 'Enter tracking code', 'yith-woocommerce-order-tracking' ); ?>" value="<?php echo esc_attr( $order_tracking_code ); ?>"/>
				</p>
				<p class="yith-ywot-tracking-carrier-name">
					<label for="ywot_carrier_name"><?php esc_html_e( 'Carrier name:', 'yith-woocommerce-order-tracking' ); ?></label>
					<input type="text" id="ywot_carrier_name" name="ywot_carrier_name" placeholder="<?php esc_attr_e( 'Enter carrier name', 'yith-woocommerce-order-tracking' ); ?>" value="<?php echo esc_attr( $order_carrier_name ); ?>"/>
				</p>
				<div class="yith-ywot-tracking-pickup-date">
					<label class="yith-ywot-order-pickup-date-label" for="ywot_pick_up_date"><?php echo esc_html( $date_picker_field['title'] ); ?></label>
					<?php
						yith_plugin_fw_get_field( $date_picker_field, true, false );
					?>
				</div>
				<p class="yith-ywot-tracking-carrier-url">
					<label for="ywot_carrier_url"><?php esc_html_e( 'Carrier website link:', 'yith-woocommerce-order-tracking' ); ?></label>
					<input type="text" id="ywot_carrier_url" name="ywot_carrier_url" placeholder="<?php esc_attr_e( 'Enter carrier website link', 'yith-woocommerce-order-tracking' ); ?>" value="<?php echo esc_attr( $order_carrier_url ); ?>"/>
				</p>
			</div>
			<?php
		}

		/**
		 * Set default carrier name when an order is created (if related option is set).
		 *
		 * @param int $post_id post id being created.
		 *
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function set_default_carrier( $post_id ) {
			if ( isset( $this->default_carrier ) && ( strlen( $this->default_carrier ) > 0 ) ) {
				$order = wc_get_order( $post_id );

				if ( $order ) {
					$order->update_meta_data( 'ywot_carrier_name', $this->default_carrier );
					$order->save();
				}
			}
		}

		/**
		 * Check if an order is flagged as picked up
		 *
		 * @param WC_Order $order Order object.
		 *
		 * @since  1.0
		 *
		 * @return bool
		 */
		public function is_order_picked_up( $order ) {
			return $order->get_meta( 'ywot_picked_up' );
		}

		/**
		 * Build a text which indicates order tracking information
		 *
		 * @param WC_Order $order   Order object.
		 * @param string   $pattern Text pattern to be used.
		 *
		 * @since  1.0
		 */
		public function get_picked_up_message( $order, $pattern = '' ) {
			if ( ! isset( $pattern ) || ( 0 === strlen( $pattern ) ) ) {
				$pattern = get_option( 'ywot_order_tracking_text', wp_kses_post( __( 'Your order has been picked up by <b>[carrier_name]</b> on <b>[pickup_date]</b>. Your tracking code is <b>[track_code]</b>. Live tracking on [carrier_link]', 'yith-woocommerce-order-tracking' ) ) );
			}

			$pattern = is_admin() ? __( 'Picked up by <b>[carrier_name]</b> on <b>[pickup_date]</b>. Tracking code: <b>[track_code]</b>. Live tracking on [carrier_link]', 'yith-woocommerce-order-tracking' ) : $pattern;

			// Retrieve additional information to be shown.
			$order_tracking_code = $order->get_meta( 'ywot_tracking_code' );
			$order_carrier_name  = $order->get_meta( 'ywot_carrier_name' );
			$order_pick_up_date  = $order->get_meta( 'ywot_pick_up_date' );
			$order_carrier_link  = $order->get_meta( 'ywot_carrier_url' );
			$carrier_link        = ! empty( $order_carrier_link ) ? '<a href="' . esc_url( $order_carrier_link ) . '" target="_blank">' . wp_kses_post( $order_carrier_name ) . '</a>' : '<span>' . wp_kses_post( $order_carrier_name ) . '</span>';

			$message = str_replace(
				array( '[carrier_name]', '[pickup_date]', '[track_code]', '[carrier_link]' ),
				array(
					$order_carrier_name,
					date_i18n( get_option( 'date_format' ), strtotime( $order_pick_up_date ) ),
					$order_tracking_code,
					$carrier_link,
				),
				$pattern
			);

			return $message;
		}

		/**
		 * Show a image stating the order has been picked up
		 *
		 * @param WC_Order $order     Order object.
		 * @param string   $css_class CSS classes.
		 *
		 * @since  1.0
		 */
		public function show_picked_up_icon( $order, $css_class = '' ) {
			if ( ! $this->is_order_picked_up( $order ) ) {
				return;
			}

			$message   = $this->get_picked_up_message( $order );
			$track_url = $order->get_meta( 'ywot_carrier_url' );

			$href = ! empty( $track_url ) ? 'href="' . $track_url . '" target="_blank"' : '';

			?>
				<a class="button track-button <?php echo esc_attr( $css_class ); ?>" <?php echo wp_kses_post( $href ); ?> data-title="<?php echo esc_attr( $message ); ?>">
					<span class="ywot-icon-delivery track-icon"></span>

					<?php
					if ( ! is_admin() ) {
						esc_html_e( 'Track', 'yith-woocommerce-order-tracking' );
					}
					?>
				</a>
			<?php
		}

		/**
		 * Show a picked up icon on backend orders table
		 *
		 * @param string $column The column of backend order table being elaborated.
		 * @param int    $post_id   The order ID or the order object.
		 *
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function prepare_picked_up_icon( $column, $post_id ) {
			// If column is not of type order_status, skip it.
			if ( 'order_status' !== $column ) {
				return;
			}

			$order = $post_id instanceof WC_Order ? $post_id : wc_get_order( $post_id );

			// if current order is not flagged as picked up, skip.
			if ( ! $this->is_order_picked_up( $order ) ) {
				return;
			}

			$this->show_picked_up_icon( $order );
		}

		/**
		 * Save additional data to the order its going to be saved. We add tracking code, carrier name and data of picking.
		 *
		 * @param int $post_id  the post id whom order tracking information should be saved.
		 *
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function save_order_tracking_metabox( $post_id ) {
			$order = wc_get_order( $post_id );

			if ( $order ) {
				//phpcs:disable WordPress.Security.NonceVerification
				$picked_up     = isset( $_POST['ywot_picked_up'] );
				$tracking_code = isset( $_POST['ywot_tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['ywot_tracking_code'] ) ) : '';
				$pick_up_date  = isset( $_POST['ywot_pick_up_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ywot_pick_up_date'] ) ) : '';
				$carrier_name  = isset( $_POST['ywot_carrier_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ywot_carrier_name'] ) ) : '';
				$carrier_url   = isset( $_POST['ywot_carrier_url'] ) ? sanitize_text_field( wp_unslash( $_POST['ywot_carrier_url'] ) ) : '';
				//phpcs:enable WordPress.Security.NonceVerification

				$order->update_meta_data( 'ywot_picked_up', $picked_up );
				$order->update_meta_data( 'ywot_tracking_code', $tracking_code );
				$order->update_meta_data( 'ywot_pick_up_date', $pick_up_date );
				$order->update_meta_data( 'ywot_carrier_name', $carrier_name );
				$order->update_meta_data( 'ywot_carrier_url', $carrier_url );
				$order->save();
			}
		}

		/**
		 * Show message about the order tracking details.
		 *
		 * @param WC_Order $order   the order whose tracking information have to be shown.
		 * @param string   $pattern custom text to be shown.
		 * @param string   $prefix  Prefix to be shown before custom text.
		 *
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function show_tracking_information( $order, $pattern, $prefix = '' ) {
			/**
			 * Show information about order shipping
			 */
			$order_picked_up = $order->get_meta( 'ywot_picked_up' );

			// if current order is not flagged as picked, don't show shipping information.
			if ( ! $order_picked_up ) {
				return;
			}

			$message = $this->get_picked_up_message( $order, $pattern );

			return $prefix . $message;
		}

		/**
		 * Show order tracking information on user order page when the order is set to "completed"
		 *
		 * @param WC_Order $order the order whose tracking information have to be shown.
		 *
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function add_order_shipping_details( $order ) {
			if ( ! $this->is_order_picked_up( $order ) ) {
				return;
			}

			$container_class = 'ywot_order_details';

			// add top or bottom class, depending on the value of related option.
			if ( 1 === intval( $this->order_text_position ) ) {
				$container_class .= ' top';
			} else {
				$container_class .= ' bottom';
			}

			echo '<div class="yith-ywot-tracking-info-container"><p class="yith-ywot-tracking-info-header">' . esc_html__( 'Tracking info', 'yith-woocommerce-order-tracking' ) . '</p><div class="' . esc_attr( $container_class ) . '">' . wp_kses_post( $this->show_tracking_information( $order, get_option( 'ywot_order_tracking_text', wp_kses_post( __( 'Your order has been picked up by <b>[carrier_name]</b> on <b>[pickup_date]</b>. Your tracking code is <b>[track_code]</b>. Live tracking on [carrier_link]', 'yith-woocommerce-order-tracking' ) ) ), '' ) ) . '</div></div>';
		}

		/**
		 * Add callback to show shipping details on order page, in the position choosen from plugin settings
		 *
		 * @since  1.0
		 * @access public
		 * @return void
		 */
		public function register_order_tracking_actions() {
			if ( ! isset( $this->order_text_position ) || ( 1 === intval( $this->order_text_position ) ) ) {
				add_action( 'woocommerce_order_details_after_order_table_items', array( $this, 'add_order_shipping_details' ) );
			} else {
				add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_order_shipping_details' ) );
			}
		}

		/**
		 * Show on my orders page, a link image stating the order has been picked
		 *
		 * @param array    $actions others actions registered to the same hook.
		 * @param WC_Order $order   the order being shown.
		 *
		 * @return mixed    action passed as arguments
		 */
		public function show_picked_up_icon_on_orders( $actions, $order ) {
			if ( $this->is_order_picked_up( $order ) ) {
				$this->show_picked_up_icon( $order, 'button' );
			}

			return $actions;
		}

		/**
		 *  Declare support for WooCommerce features.
		 */
		public function declare_wc_features_support() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', YITH_YWOT_FREE_INIT, true );
			}
		}
	}
}
