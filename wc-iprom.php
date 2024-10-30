<?php
/*
* Plugin Name: iPROM integration for WooCommerce
* Description: Integrate iPROM cloud marketing codes into WooCommerce e-shop
* Version: 1.0.0
* Author: iPROM
* Author URI: https://iprom.eu/
*
* Text Domain: wc-iprom
* Domain Path: /languages/
*
* Requires at least: 5.0
* Tested up to: 5.8
*
* WC requires at least: 5.0
* WC tested up to: 5.8
*
* Copyright: © 2021 iPROM
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 */
class WC_iprom {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version = '1.0.0';

	/**
	 * Min required WC version.
	 *
	 * @var string
	 */
	private $wc_min_version = '5.0.0';


	/**
	 * The single instance of the class.
	 *
	 */
	protected static $_instance = null;
	private $_message;
	
	const REQUIRED_CAPABILITY = 'administrator';

	/**
	 * Maininstance. Ensures only one instance is loaded or can be loaded 
	 *
	 * @static
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Not allowed!', 'wc-iprom' ), '1.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Not allowed!', 'wc-iprom' ), '1.0.0' );
	}

	/**
	 * Make stuff.
	 */
	protected function __construct() {
		// Entry point.
		add_action( 'plugins_loaded', array( $this, 'initialize_plugin' ), 9 );
	}

	/**
	 * Plugin URL getter.
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin path getter.
	 *
	 * @return string
	 */
	public function get_plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Plugin base path name getter.
	 *
	 * @return string
	 */
	public function get_plugin_basename() {
		return plugin_basename( __FILE__ );
	}

	/**
	 * Plugin version getter.
	 *
	 * @param  boolean  $base
	 * @param  string   $version
	 * @return string
	 */
	public function get_plugin_version( $base = false, $version = '' ) {

		$version = $version ? $version : $this->version;

		if ( $base ) {
			$version_parts = explode( '-', $version );
			$version       = count( $version_parts ) > 1 ? $version_parts[ 0 ] : $version;
		}

		return $version;
	}

	/**
	 * Define constants if not present.
	 *
	 * @since  1.0.0
	 *
	 * @return boolean
	 */
	protected function maybe_define_constant( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Indicates whether the plugin is fully initialized.
	 *
	 * @since  1.0.0
	 *
	 * @return boolean
	 */
	public function is_plugin_initialized() {
		return true;
	}

	/**
	 * Fire in the hole!
	 */
	public function initialize_plugin() {

		$this->define_constants();

		// WC version sanity check.
		if ( ! function_exists( 'WC' ) || version_compare( WC()->version, $this->wc_min_version ) < 0 ) {
			/* translators: %s: WC min version */
			$notice = sprintf( __( 'WooCommerce iPROM integration requires at least WooCommerce <strong>%s</strong>.', 'wc-iprom' ), $this->wc_min_version );
			$this->showError($notice);
			return false;
		}

		$this->includes();


		add_action( 'admin_menu', function() {
			add_submenu_page(
					'woocommerce',
					__('WooCommerce iPROM integration settings', 'wc-iprom'),
					__('WooCommerce iPROM integration', 'wc-iprom'),
					self::REQUIRED_CAPABILITY,
					'wci_settings',
					__CLASS__ . '::wci_settings'
				);
		});


		add_filter(
				'plugin_action_links_' . plugin_basename( __FILE__ ),
				function ($links) {
					array_unshift( $links, '<a href="'.menu_page_url( 'wci_settings', false ).'">'.esc_html__( 'Settings', 'default' ).'</a>' );
					return $links;
				}
		);


		// Load translations hook.
		add_action( 'woocommerce_after_add_to_cart_button', function() {
			if( function_exists('is_product')) {
				if ( is_product() ) {
					global $product;
					$id = $product->get_id();
					$price = $product->get_price();
					$obj = [
						'content_ids' => [ $id ],
						'content_type' => 'product',
						'value' => (float)$price,
						'currency' => 'EUR'
					];
					echo '<div class="iprom_cartdata" style="display:none">'.wp_json_encode($obj).'</div>';
				}
			}
		} );
		
		add_action( 'wp_footer', function() {			
			?>
				<script type="text/javascript">
					(function($){ 

						$( "form.cart" ).on( 'submit', function(){
							var iprom_cartdata = $(this).find(".iprom_cartdata").text();
							if (iprom_cartdata) {
								var obj = $.parseJSON(iprom_cartdata);
								_ipromNS( 'goal', 'AddToCart', obj);
							}
						});

					})(jQuery);
				</script>
			<?php
		});
		
		add_action( 'wp_head', function() {
			$site_path = get_option('iprom_site_path');
			if (!$site_path) { return; }
			
			$site_path = json_decode($site_path, true);
			
			echo '
			<!-- iPROM -->
			<script>
				(function(a,g,b,c){a[c]=a[c]||function(){ "undefined"!==typeof a.ipromNS&&a.ipromNS.execute?a.ipromNS.execute(arguments):(a[c].q=a[c].q||[]).push(arguments)};
				var k=function(){var b=g.getElementsByTagName("script")[0];return function h(f){var e=f.shift();a[c]("setConfig",{server:e}); var d=document.createElement("script");
				0<f.length&&(d.onerror=function(){a[c]("setConfig",{blocked:!0});h(f)}); d.src="https://cdn."+e+"/ipromNS.js";d.async=!0;d.defer=!0;b.parentNode.insertBefore(d,b)}}(),e=b;
				"string"===typeof b&&(e=[b]);k(e) })(window, document,["ad.server.iprom.net"],"_ipromNS");
				_ipromNS("init", {
					"sitePath" : ' . wp_json_encode($site_path) . '
				});
			</script>
			<!-- /iPROM -->
			';

			if( function_exists('is_product')) {
				if (is_product()) {
					// view product detail
					global $product;
					$id = $product->get_id();
					$price = $product->get_price();
					
					echo "<script>
					    _ipromNS( 'goal', 'ViewContent',  {
							content_ids: [" . esc_js($id) . "],
							content_type: 'product',  
							value: " . esc_js((float)$price) .",
							currency: 'EUR' 
						}  );
						</script>
					";
				}
			}
			
			if( function_exists('is_checkout')) {
				if (is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
					$cart_items = [];
					foreach ( WC()->cart->get_cart() as $cart_item ) {
						$cart_items[] = $cart_item['product_id'];
					}
					echo "<script>
					    _ipromNS( 'goal', 'InitiateCheckout',  {
							content_ids: ".wp_json_encode($cart_items).",
							content_type: 'product',  
							value: " . esc_js((float)WC()->cart->get_total()) . ",
							currency: 'EUR' 
						}  );
						</script>
					";
				}				
			}
			
			if( function_exists('is_wc_endpoint_url')) {
				if (is_wc_endpoint_url('order-received')) {
					$order_id = wc_get_order_id_by_order_key( $_GET['key'] );
					$order = wc_get_order( $order_id );
					
					$order_items = [];
					foreach ( $order->get_items() as $item ) {
						$order_items[] = $item->get_product_id();
					}
					
					echo "<script>
					    _ipromNS( 'goal', 'Purchase',  {
							content_ids: ".wp_json_encode($order_items).",
							content_type: 'product',  
							value: " . esc_js((float)$order->get_total()).",
							currency: 'EUR' 
						}  );
						</script>
					";
				}
			}
			
		});
		
		// cron - twice daily
		if (!wp_next_scheduled('generate_iprom_feed')) {
			wp_schedule_event( time(), 'twicedaily', 'generate_iprom_feed' );
		}
		
		//add_action('woocommerce_after_register_post_type', function() {
		add_action( 'generate_iprom_feed', array($this, 'generateFeed')); 
		//});


		//add_filter('cron_schedules','my_cron_schedules');
		
	}
	
	private function showError($str) {
		$this->_message = $str;
		add_action( 'admin_notices', function() {
			echo '<div class="notice is-dismissible notice-error"><p>' . esc_html($this->_message) . '</p></div>';
		} );
	}

	private function showSuccess($str) {
		$this->_message = $str;
		add_action( 'admin_notices', function() {
			echo '<div class="notice is-dismissible notice-success"><p>' . esc_html($this->_message) . '</p></div>';
		} );
	}

	public function generateFeed() {
		set_time_limit(3600);
		
		$upload_dir = wp_upload_dir();
		$feed_dir = $upload_dir['basedir'] . '/iprom';
		$feed_url = $upload_dir['baseurl'] . '/iprom';
		
		if (!file_exists($feed_dir)) {
			if (!mkdir($feed_dir)) {
				return;
			}
		}
		
		$feed_file = $feed_dir . '/spiderad.xml';
		if (file_exists($feed_file)) {
			unlink($feed_file);
		}
		
		$all_ids = get_posts( array(
			'post_type' => 'product',
			'numberposts' => -1,
			'post_status' => 'publish',
			'fields' => 'ids',
		) );
		
		$xmlWriter = new XMLWriter();
		$xmlWriter->openMemory();
		$xmlWriter->startDocument('1.0', 'UTF-8');
		$xmlWriter->startElement('shop');
		
		foreach ( $all_ids as $idx => $id ) {
			$product = wc_get_product($id);
			
			$xmlWriter->startElement('entry');
			$xmlWriter->writeElement('id', $product->get_id());
			$xmlWriter->writeElement('title', $product->get_title());
			//$xmlWriter->writeElement('description', strip_tags($product->get_short_description()));
			$xmlWriter->writeElement('link', $product->get_permalink());
			$xmlWriter->writeElement('image_link', wp_get_attachment_url( $product->get_image_id() ));
			$xmlWriter->writeElement('availability', $product->get_stock_status());
			$xmlWriter->writeElement('price', $product->get_price());
			$xmlWriter->endElement();
			if ($idx % 100 == 0) {
				file_put_contents($feed_file, $xmlWriter->flush(true), FILE_APPEND);
			}
		}
		$xmlWriter->endElement();
		
		file_put_contents($feed_file, $xmlWriter->flush(true), FILE_APPEND);
		
	}

	/**
	 * Constants.
	 */
	public function define_constants() {
		$this->maybe_define_constant( 'WC_IPROM_NAME', $this->version );
		$this->maybe_define_constant( 'WC_IPROM_VERSION', $this->version );
		$this->maybe_define_constant( 'WC_IPROM_ABSPATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
	}

	/**
	 * Includes.
	 */
	public function includes() {

		// Functions.
		//require_once  WC_IPROM_ABSPATH . 'includes/wc-gc-functions.php' ;

		// Admin includes.
		if ( is_admin() ) {
			$this->admin_includes();
		}

	}

	/**
	 * Admin & AJAX functions and hooks.
	 */
	public function admin_includes() {

		// Admin notices handling.
		
	}

	/**
	 * Load textdomain.
	 */
	public function load_translation() {
		load_plugin_textdomain( 'wc-iprom', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	public static function wci_settings() {
		
		if ( !current_user_can( self::REQUIRED_CAPABILITY ) ) {
			wp_die( 'Access denied.' );
		}
		
		$upload_dir = wp_upload_dir();
		$feed_dir = $upload_dir['basedir'] . '/iprom';
		$feed_url = $upload_dir['baseurl'] . '/iprom';
		
		if (!file_exists($feed_dir)) {
			if (!mkdir($feed_dir)) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__("Can not create feed folder:", 'wc-iprom').' '.esc_html($feed_dir).'</p></div>';
			}
		}
		
		if (isset($_POST['site-path'])) {
			$site_path = sanitize_text_field( $_POST['site-path'] );
			$site_path = preg_replace('/\[/','',$site_path);
			$site_path = preg_replace('/\]/','',$site_path);
			$site_path = preg_replace('/\'/','',$site_path);
			$site_path = preg_replace('/"+/','',$site_path);
			$site_path = stripslashes($site_path);
			$site_path = explode(',',$site_path);
			$sp = [];
			foreach ($site_path as $item) {
				$item = trim($item);
				$item = preg_replace('/"+/','',$item);
				if (preg_match('/^[a-zA-Z0-9\_\-]+$/',$item)) {
					$sp[] = $item;
				}
			}
			if (!empty($sp)) {
				if (get_option('iprom-site-path') !== false) {
					add_option('iprom_site_path', wp_json_encode($sp));
				} else {
					update_option('iprom_site_path', wp_json_encode($sp));
				}
				echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__("Site path settings were saved.", 'wc-iprom').'</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>'.esc_html__("Site path is not valid. Please enter a valid site path.", 'wc-iprom').'</p></div>';
			}
		}
		
		
		$site_path = get_option('iprom_site_path');
		
		echo '<div class="wrap">
		<div id="icon-options-general" class="icon32"><br /></div>
		<h1>' . __("WooCommerce iPROM integration settings", 'wc-iprom') .'</h1>
		<form method="post">
			<p>
				<label>' . __('iPROM site path', 'wc-iprom'). '</label><br>
				<input type="text" name="site-path" value="'.esc_attr($site_path).'" class="regular-text" />
			</p>
			
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button-primary" value="' . __( 'Save Changes', 'wc-iprom' ) .'" />
			</p>
		</form>
		
		<h2>Feed URL for iPROM Spider Ad</h2>
		<form>
			<input type="text" value="' . esc_url($feed_url) . '/spiderad.xml' . '" style="width: 100%; max-width: 600px" id="iprom-feed-url" readonly />
			<br />
			<a href="#" onclick="return copyToClipboard();" id="copy-btn">
				<span class="copy">'.__('Copy to clipboard', 'wc-iprom').'</span>
				<span class="copied hidden">'.__('Copied!', 'wc-iprom').'</span>
			</a>
		</form>
		';
		?>
		
		<script>
		function copyToClipboard(text) {
			var textField = jQuery("#iprom-feed-url")[0];
			textField.focus();
			textField.select();

		  try {
			var successful = document.execCommand('copy');
			if (successful) {
				jQuery("#copy-btn .copy").addClass("hidden");
				jQuery("#copy-btn .copied").removeClass("hidden");
				setTimeout(function() {
					jQuery("#copy-btn .copied").addClass("hidden");
					jQuery("#copy-btn .copy").removeClass("hidden");				
				}, 2000);
			} else {
				alert('Unable to copy');
			}
		  } catch (err) {
			alert('Unable to copy');
		  }
		}
		</script>
		
		<?php
		
		echo '</div>';
	}

}

/**
 * Returns the main instance of WC_iprom to prevent the need to use globals.
 *
 * @return  WC_iprom
 */
function WC_iPROM() {
	return WC_iprom::instance();
}

WC_iPROM();
