<?php
/*
Plugin Name: Mistape
Description: Mistape allows users to effortlessly notify site staff about found spelling errors.
Version: 1.0.2
Author URI: https://deco.agency
Author: deco.agency
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: mistape
Domain Path: /languages

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.


THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

// set plugin instance
$mistape = new Deco_Mistape();

/**
 * Deco_Mistape class
 *
 * @class Deco_Mistape
 * @version	1.0.2
 */
class Deco_Mistape {

	/**
	 * @var $defaults
	 */
	private $defaults = array(
		'email_recipient'		 => array(
			'type' => 'admin',
			'id' => '1',
			'email' => '',
		),
		'post_types' 			=> array(),
		'caption_format'		=> 'text',
		'caption_image_url'		=> '',
		'first_run'				=> 'yes'
	);
	private $version			= '1.0.2';
	private $recipient_email	= null;
	private $email_recipient_types	= array();
	private $caption_formats	= array();
	private $post_types			= array();
	private $options 			= array();
	private $caption_text		= null;
	private $dialog_title		= null;
	private $dialog_message		= null;
	private $success_text		= null;
	private $close_text			= null;

	/**
	 * Constructor
	 */
	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		register_uninstall_hook( __FILE__, array( 'Deco_Mistape', 'uninstall_cleanup' ) );

		// settings
		$this->options = apply_filters( 'mistape_options', array_merge( $this->defaults, get_option( 'mistape_options', $this->defaults ) ) );

		// actions
		add_action( 'admin_init',				array( $this, 'register_settings' ) );
		add_action( 'admin_menu', 				array( $this, 'admin_menu_options' ) );
		add_action( 'plugins_loaded', 			array( $this, 'load_textdomain' ) );
		add_action( 'admin_notices', 			array( $this, 'plugin_activated_notice' ) );
		add_action( 'after_setup_theme',		array( $this, 'load_defaults' ) );
		add_action( 'admin_enqueue_scripts',	array( $this, 'admin_load_scripts_styles' ) );
		add_action( 'wp_enqueue_scripts',		array( $this, 'front_load_scripts_styles' ) );
		add_action( 'wp_ajax_mistape_report_error', array( $this, 'ajax_handler' ) );
		add_action( 'wp_ajax_nopriv_mistape_report_error', array( $this, 'ajax_handler' ) );
		add_action( 'wp_footer', 				array( $this, 'insert_dialog' ), 1000 );
		if ( $this->options['register_shortcode'] == 'yes' ) {
			add_shortcode( 'mistape', 	array( $this, 'render_shortcode' ) );
		}

		// filters
		add_filter( 'plugin_action_links', 		array( $this, 'plugins_page_settings_link' ), 10, 2 );
		add_filter( 'the_content', 				array( $this, 'append_caption_to_content' ) );
	}

	/**
	 * Load plugin defaults
	 */
	public function load_defaults() {
		$this->recipient_email = $this->get_recipient_email();
		$this->email_recipient_types = array(
			'admin'		=> __( 'Administrator', 'mistape' ),
			'editor' 	=> __( 'Editor', 'mistape' ),
			'other' 	=> __( 'Specify other', 'mistape' )
		);

		$this->post_types = $this->list_post_types();

		$this->caption_formats = array(
			'text'	=> __( 'Text', 'mistape' ),
			'image' => __( 'Image', 'mistape' )
		);

		$this->caption_text = apply_filters(
			'mistape_caption_text',
			__( 'If you have found a spelling error, please, notify us by selecting that text and pressing <em>Ctrl+Enter</em>.', 'mistape' )
		 );
		$this->dialog_title = __( 'Thanks!', 'mistape' );   
		$this->dialog_message = __( 'Our editors are notified.', 'mistape' );
		$this->close_text = __( 'Close', 'mistape' );

		update_option( 'mistape_options', $this->options );
	}

	/**
	 * Load textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'mistape', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Add submenu
	 */
	public function admin_menu_options() {
		add_options_page(
			'Mistape', 'Mistape', 'manage_options', 'mistape', array( $this, 'print_options_page' )
		);
	}

	/**
     * Handle shortcode
     *
	 * @param $atts
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		shortcode_atts(
			array(
				'format' => $this->options['caption_format'],
				'class'  => 'mistape_caption',
				'image'  => '',
				'text'  => $this->options['caption_text'],
			),
			$atts,
			'mistape'
		);

		if ( $atts['format'] == 'image' && !empty( $this->options['caption_image_url'] ) || $atts['format'] != 'text' && !empty( $atts['image'] ) ) {
			$imagesrc = $atts['image'] ? $atts['image'] : $this->options['caption_image_url'];
			$output = '<div class="' . $atts['class'] . '"><img src="' . $imagesrc . '" alt="' . $atts['text'] . '"></div>';
		} else {
			$output = '<div class="' . $atts['class'] . '"><p>' . $atts['text'] . '</p></div>';
		}

		return $output;
	}

	/**
	 * Options page output
	 */
	public function print_options_page() {
		$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'configuration';

		?>
		<div class="wrap">
			<h2>Mistape</h2>
			<h2 class="nav-tab-wrapper">
			<?php
				printf( '<a href="%s" class="nav-tab%s" data-bodyid="mistape-configuration" >%s</a>',   admin_url( 'options-general.php?page=mistape&tab=configuration' ), $active_tab == 'configuration' ? ' nav-tab-active' : '', __( 'Configuration', 'mistape' ) );
				printf( '<a href="%s" class="nav-tab%s" data-bodyid="mistape-help">%s</a>',             admin_url( 'options-general.php?page=mistape&tab=help' ), $active_tab == 'help' ? ' nav-tab-active' : '', __( 'Help', 'mistape' ) );
			?>
			</h2>
			<?php printf( '<div id="mistape-configuration" class="mistape-tab-contents" %s>', $active_tab == 'configuration' ? '' : 'style="display: none;"' ); ?>
				<form action="<?php echo admin_url( 'options.php' ); ?>" method="post">
				<?php
					settings_fields( 'mistape_options' );
					do_settings_sections( 'mistape_options' );
				?>
					<p class="submit">
						<?php submit_button( '', 'primary', 'save_mistape_options', false ); ?>
					</p>
				</form>
			</div>
			<?php
				printf( '<div id="mistape-help" class="mistape-tab-contents" %s>', $active_tab == 'help' ? '' : 'style="display: none;" ' );
                $this->print_help_page();
			?>
			</div>
			<div class="clear"></div>
		</div>
		<?php
	}

	/**
	 * Regiseter plugin settings
	 */
	public function register_settings() {
		register_setting( 'mistape_options', 'mistape_options', array( $this, 'validate_options' ) );

		add_settings_section( 'mistape_configuration', '', array( $this, 'section_configuration' ), 'mistape_options' );
		add_settings_field( 'mistape_email_recipient', 		__( 'Email recipient', 'mistape' ), 	array( $this, 'field_email_recipient' ), 		'mistape_options', 'mistape_configuration' );
		add_settings_field( 'mistape_post_types', 			__( 'Post types', 'mistape' ), 		array( $this, 'field_post_types' ), 			'mistape_options', 'mistape_configuration' );
		add_settings_field( 'mistape_register_shortcode', 	__( 'Shortcodes', 'mistape' ), 		array( $this, 'field_register_shortcode' ), 	'mistape_options', 'mistape_configuration' );
		add_settings_field( 'mistape_caption_format', 		__( 'Caption format', 'mistape' ), 	array( $this, 'field_caption_format' ), 		'mistape_options', 'mistape_configuration' );
	}

	/**
	 * Section callback
	 */
	public function section_configuration() {}

	/**
	 * Email recipient selection
	 */
	public function field_email_recipient() {
		echo '
		<fieldset>';


		foreach ( $this->email_recipient_types as $value => $label ) {
			echo '
			<label><input id="mistape_email_recipient_type-' . $value . '" type="radio" name="mistape_options[email_recipient][type]" value="' . esc_attr( $value ) . '" ' . checked( $value, $this->options['email_recipient']['type'], false ) . ' />' . esc_html( $label ) . '</label><br />';
		}

		echo '
			<div id="mistape_email_recipient_list-admin"' . ($this->options['email_recipient']['type'] == 'admin' ? '' : 'style="display: none;"' ) . '>';

		echo'
			<select name="mistape_options[email_recipient][id][admin]">';

		$admins = $this->get_user_list_by_role( 'administrator' );
		foreach ( $admins as $user ) {
			echo '
				<option value="' . $user->ID . '" ' . selected( $user->ID, $this->options['email_recipient']['id'], false ) . '>' . esc_html( $user->user_nicename . ' (' . $user->user_email . ')' ) . '</option>';
		}

		echo '
			</select>
			</div>';

		echo '
			<div id="mistape_email_recipient_list-editor"' . ($this->options['email_recipient']['type'] == 'editor' ? '' : 'style="display: none;"' ) . '>';

		echo'
			<select name="mistape_options[email_recipient][id][editor]">';

		$editors = $this->get_user_list_by_role( 'editor' );
		if ( !empty( $editors ) ) {
			foreach ( $editors as $user ) {
				echo '
				<option value="' . $user->ID . '" ' . selected( $user->ID, $this->options['email_recipient']['id'], false ) . '>' . esc_html( $user->user_nicename . ' (' . $user->user_email . ')' ) . '</option>';
			}
		}
		else {
			echo '
			<option value="empty" ' . selected( 'empty', $this->options['email_recipient']['id'], false ) . '>' . __( '-- no editors found --', 'cookie-notice' ) . '</option>';
		}

		echo '
			</select>
			</div>
			<div id="mistape_email_recipient_list-other" ' . ($this->options['email_recipient']['type'] == 'other' ? '' : 'style="display: none;"' ) . '>
				<input type="text" class="regular-text" name="mistape_options[email_recipient][email]" value="' . esc_attr( $this->options['email_recipient']['email'] ) . '" />
			</div>
		</fieldset>';
	}

	/**
	 * Post types to show caption in
	 */
	public function field_post_types() {
		echo '
		<fieldset style="max-width: 600px;">';

		foreach ( $this->post_types as $value => $label) {
			echo '
			<label style="padding-right: 8px; min-width: 60px;"><input id="mistape_post_type-' . $value . '" type="checkbox" name="mistape_options[post_types][' . $value . ']" value="1" '. checked( true, in_array( $value, $this->options['post_types'] ), false ) . ' />' . esc_html( $label ) . '</label>	';
		}

		echo '
			<p class="description">' . __( '"Press Ctrl+Enter&hellip;" captions will be displayed at the bottom of selected post types.', 'mistape' ) . '</p>
		</fieldset>';
	}

	/**
	 * Shortcode option
	 */
	public function field_register_shortcode() {
		echo '
		<fieldset>
			<label><input id="mistape_register_shortcode" type="checkbox" name="mistape_options[register_shortcode]" value="1" ' . checked( 'yes', $this->options['register_shortcode'], false ) . '/>' . __( 'Register <code>[mistape]</code> shortcode.', 'mistape' ) . '</label>
			<p class="description">' . __( 'Enable if manual caption insertion via shortcodes is needed.', 'mistape' ) . '</p>
			<p class="description">' . __( 'Usage examples are in Help section.', 'mistape' ) . '</p>
		</fieldset>';
	}

	/**
	 * Caption format option
	 */
	public function field_caption_format() {
		echo '
		<fieldset>';

		foreach ( $this->caption_formats as $value => $label ) {
			echo '
			<label><input id="mistape_caption_format-' . $value . '" type="radio" name="mistape_options[caption_format]" value="' . esc_attr( $value ) . '" ' . checked( $value, $this->options['caption_format'], false ) . ' />' . esc_html( $label ) . '</label><br />';
		}

		echo '
		<div id="mistape_caption_image"' . ( $this->options['register_shortcode'] == 'yes' || $this->options['caption_format'] === 'image' ? '' : 'style="display: none;"' ) . '>
			<p class="description">' . __( 'Enter the full image URL starting with http://', 'mistape' ) . '</p>
			<input type="text" class="regular-text" name="mistape_options[caption_image_url]" value="' . esc_attr( $this->options['caption_image_url'] ) . '" />
		</div>
		</fieldset>';
	}

	/**
	* Validate options
	*
	* @param $input
	* @return mixed
    */
	public function validate_options( $input ) {

		if ( ! current_user_can( 'manage_options' ) )
			return $input;

		if ( isset( $_POST['save_mistape_options'] ) ) {

			// mail recipient
			$input['email_recipient']['type'] = sanitize_text_field( isset( $input['email_recipient']['type'] ) && in_array( $input['email_recipient']['type'], array_keys( $this->email_recipient_types ) ) ? $input['email_recipient']['type'] : $this->defaults['email_recipient']['type'] );

			if ( $input['email_recipient']['type'] == 'admin' && isset( $input['email_recipient']['id']['admin'] ) && ( user_can( $input['email_recipient']['id']['admin'], 'administrator' ) ) ) {
				$input['email_recipient']['id'] = $input['email_recipient']['id']['admin'];
			}
			elseif ( $input['email_recipient']['type'] == 'editor' && isset( $input['email_recipient']['id']['editor'] ) && ( user_can( $input['email_recipient']['id']['editor'], 'editor' ) ) ) {
				$input['email_recipient']['id'] = $input['email_recipient']['id']['editor'];
			}
			elseif ( $input['email_recipient']['type'] == 'other' && isset( $input['email_recipient']['email'] ) && is_email( $input['email_recipient']['email'] ) ) {
				$input['email_recipient']['email'] = sanitize_email( $input['email_recipient']['email'] );
			}
			else {
				add_settings_error(
					'mistape_options',
					esc_attr( 'invalid_recipient' ),
					__( 'ERROR: You didn\'t select valid email recipient.' , 'mistape' ),
					'error'
				);
				$input['email_recipient'] = $this->defaults['email_recipient'];
			}

			// post types
			$input['post_types'] = isset( $input['post_types'] ) && is_array( $input['post_types'] ) && count( array_intersect( array_keys( $input['post_types'] ), array_keys( $this->post_types ) ) ) === count( $input['post_types'] ) ? array_keys( $input['post_types'] ) : array();

			// shortcode option
			$input['register_shortcode'] = (bool) isset( $input['register_shortcode'] ) ? 'yes' : 'no';

			// caption type
			$input['caption_format'] = sanitize_text_field( isset( $input['caption_format'] ) && in_array( $input['caption_format'], array_keys( $this->caption_formats ) ) ? $input['caption_format'] : $this->defaults['caption_format'] );
			if ( $input['caption_format'] === 'image' ) {
				if ( ! empty( $input['caption_image_url'] ) ) {
					$input['caption_image_url'] = esc_url( $input['caption_image_url'] );
				}
				else {
					add_settings_error(
						'mistape_options',
						esc_attr( 'no_image_url' ),
						__( 'ERROR: You didn\'t enter caption image URL.' , 'mistape'),
						'error'
					);
					$input['caption_format'] = $this->defaults['caption_format'];
					$input['caption_image_url'] = $this->defaults['caption_image_url'];
				}
			};

			$input['first_run'] = 'no';

		}

		return $input;
	}

	/**
	 * Get default settings
	 */
	public function get_defaults() {
		return $this->defaults;
	}

	/**
	 * Add links to settings page
	 *
	 * @param $links
	 * @param $file
	 *
	 * @return mixed
	 */
	public function plugins_page_settings_link( $links, $file ) {
		if ( ! current_user_can( 'manage_options' ) )
			return $links;

		$plugin = plugin_basename( __FILE__ );

		if ( $file == $plugin )
			array_unshift( $links, sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php?page=mistape' ), __( 'Settings', 'mistape' ) ) );

		return $links;
	}

	/**
	 * Activate the plugin
	 */
	public function activation() {
		add_option( 'mistape_options', $this->defaults, '', 'no' );
		add_option( 'mistape_version', $this->version, '', 'no' );
	}

	/**
	 * Delete settings on plugin uninstall
	 */
	public static function uninstall_cleanup() {
		delete_option('mistape_options');
		delete_option('mistape_version');
	}

	/**
	 * Load scripts and styles - admin
     *
	 * @param $page
	 */
	public function admin_load_scripts_styles( $page ) {
		if ( $page !== 'settings_page_mistape' )
			return;

		wp_enqueue_script(
			'mistape-admin', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery', 'wp-color-picker' ), $this->version
		);
	}

	/**
	 * Load scripts and styles - frontend
	 */
	public function front_load_scripts_styles() {
		wp_enqueue_script( 'mistape-front', plugins_url( 'js/front.js', __FILE__ ), array( 'jquery' ), $this->version, true );

		$nonce = wp_create_nonce( 'mistape_report' );
		wp_localize_script(
			'mistape-front', 'mistapeArgs', array(
				'ajaxurl'		=> admin_url( 'admin-ajax.php' ),
				'strings' 		=> array(
					'message'		=> $this->caption_text,
					'success'		=> $this->success_text,
					'close'			=> $this->close_text,
				),
				'nonce' => $nonce,
			)
		);

		wp_enqueue_style( 'mistape-front', plugins_url( 'css/front.css', __FILE__ ), array(), $this->version );

		// modal
		wp_enqueue_script( 'mistape-front-modal-modernizr', plugins_url( 'js/modal/modernizr.custom.js', __FILE__ ), array( 'jquery' ), $this->version, true );
		wp_enqueue_script( 'mistape-front-modal-classie', plugins_url( 'js/modal/classie.js', __FILE__ ), array( 'jquery' ), $this->version, true );
		wp_enqueue_script( 'mistape-front-modal-dialogfx', plugins_url( 'js/modal/dialogFx.js', __FILE__ ), array( 'jquery' ), $this->version, true );

		wp_enqueue_style( 'mistape-front-modal-dialog', plugins_url( 'css/modal/dialog.css', __FILE__ ), array(), $this->version );
		wp_enqueue_style( 'mistape-front-modal-sandra', plugins_url( 'css/modal/dialog-sandra.css', __FILE__ ), array(), $this->version );
		}

	/**
	 * Add admin notice after activation if not configured
	 */
	public function plugin_activated_notice() {
		$wp_screen = get_current_screen();
		if ( $this->options['first_run'] == 'yes' && current_user_can( 'manage_options' ) ) {
			$html = '<div class="updated">';
			$html .= '<p>';
			if ( $wp_screen && $wp_screen->id == 'settings_page_mistape' ) {
				$html .= __( '<strong>Mistape</strong> settings notice will be dismissed after saving changes.', 'mistape' );
			}
			else {
				$html .= sprintf( __( '<strong>Mistape</strong> must now be <a href="%s">configured</a> before use.', 'mistape' ), admin_url( 'options-general.php?page=mistape' ) );
			}
			$html .= '</p>';
			$html .= '</div>';
			echo $html;
		}
	}

	/**
	 * Get admins list for options page
     *
	 * @param $role
	 *
	 * @return array
	 */
	public function get_user_list_by_role( $role ) {
		$users_query = get_users( array(
			'role' => $role,
			'fields' => array(
				'ID',
				'user_nicename',
				'user_email',
			),
			'orderby' => 'display_name'
		) );
		return $users_query;
	}

	/**
	 * Get recipient email
	 */
	public function get_recipient_email() {
		if ( $this->options['email_recipient']['type'] == 'other' && $this->options['email_recipient']['email'] ) {
			$email = $this->options['email_recipient']['email'];
		}
		elseif ( $this->options['email_recipient']['type'] != 'other' && $this->options['email_recipient']['id'] ) {
			$email = get_the_author_meta( 'user_email', $this->options['email_recipient']['id'] );
		}
		else {
			$email = get_bloginfo( 'admin_email' );
		}

		return $email;
	}

	/**
	 * Return an array of registered post types with their labels
	 */
	public function list_post_types() {
		$post_types = get_post_types(
			array( 'public' => true ),
			'objects'
		);

		$post_types_list = array();
		foreach ( $post_types as $id => $post_type ) {
			$post_types_list[$id] = $post_type->label;
		}

		return $post_types_list;
	}

	/**
	 * Echo Help tab contents
	 */
	private static function print_help_page() {
		?>
		<div class="card">
			<h3><?php _e( 'Shortcodes' , 'mistape' ) ?></h3>
			<h4><?php _e( 'Optional shortcode parameters are:' , 'mistape' ) ?></h4>
			<ul>
				<li><code>format</code> — <?php _e( "can be 'text' or 'image'" , 'mistape' ) ?></li>
				<li><code>class</code> — <?php _e( 'override default css class' , 'mistape' ) ?></li>
				<li><code>text</code> — <?php _e( 'override caption text' , 'mistape' ) ?></li>
				<li><code>image</code> — <?php _e( 'override image URL' , 'mistape' ) ?></li>
			</ul>
			<p><?php _e( 'When no parameters specified, general configuration is used.' , 'mistape' ) ?><br />
				<?php _e( 'If image url is specified, format parameter can be omitted.' , 'mistape' ) ?></p>
			<h4><?php _e( 'Shortcode usage example:' , 'mistape' ) ?></h4>
			<ul>
				<li><p><code>[mistape format="text" class="mistape_caption_sidebar"]</code></p></li>
			</ul>
			<h4><?php _e( 'PHP code example:' , 'mistape' ) ?></h4>
			<ul>
				<li><p><code>&lt;?php do_shortcode( '[mistape format="image" class="mistape_caption_footer" image="/wp-admin/images/yes.png"]' ); ?&gt;</code></p></li>
			</ul>
		</div>

		<div class="card">
			<h3><?php _e( 'Hooks' , 'mistape' ) ?></h3>

			<h4><?php _e( 'Actions:' , 'mistape' ) ?></h4>

			<h4><code>mistape_process_report</code></h4>
			<p class="description"><?php _e( 'Description:' , 'mistape' ) ?> <?php _e( 'executes after Ctrl+Enter pressed and report validated, before sending email.' , 'mistape' ) ?></p>
			<p class="description"><?php _e( 'Parameters:' , 'mistape' ) ?> str $reported_text, str $context | str</p>

			<h4><?php _e( 'Filters:' , 'mistape' ) ?></h4>

			<h4><code>mistape_caption_text</code></h4>
			<p class="description"><?php _e( 'Description:' , 'mistape' ) ?> <?php _e( 'allows to modify caption text globally.' , 'mistape' ) ?></p>
			<p class="description"><?php _e( 'Parameters:' , 'mistape' ) ?> str $text </p>

			<h4><code>mistape_caption_output</code></h4>
			<p class="description"><?php _e( 'Description:' , 'mistape' ) ?> <?php _e( 'allows to modify the caption HTML before output.' , 'mistape' ) ?></p>
			<p class="description"><?php _e( 'Parameters:' , 'mistape' ) ?> str $html</p>

			<h4><code>mistape_dialog_args</code></h4>
			<p class="description"><?php _e( 'Description:' , 'mistape' ) ?> <?php _e( 'allows to modify modal dialog strings.' , 'mistape' ) ?></p>
			<p class="description"><?php _e( 'Parameters:' , 'mistape' ) ?> array $args </p>

			<h4><code>mistape_dialog_output</code></h4>
			<p class="description"><?php _e( 'Description:' , 'mistape' ) ?> <?php _e( 'allows to modify the modal dialog HTML before output.' , 'mistape' ) ?></p>
			<p class="description"><?php _e( 'Parameters:' , 'mistape' ) ?> str $html </p>

			<h4><code>mistape_mail_recipient</code></h4>
			<p class="description"><?php _e( 'Description:' , 'mistape' ) ?> <?php _e( 'allows to change email recipient.' , 'mistape' ) ?></p>
			<p class="description"><?php _e( 'Parameters:' , 'mistape' ) ?> str $recipient</p>

			<h4><code>mistape_mail_subject</code></h4>
			<p class="description"><?php _e( 'Description:' , 'mistape' ) ?> <?php _e( 'allows to change email subject.' , 'mistape' ) ?></p>
			<p class="description"><?php _e( 'Parameters:' , 'mistape' ) ?> str $subject</p>

			<h4><code>mistape_mail_message</code></h4>
			<p class="description"><?php _e( 'Description:' , 'mistape' ) ?> <?php _e( 'allows to modify email message to send.' , 'mistape' ) ?></p>
			<p class="description"><?php _e( 'Parameters:' , 'mistape' ) ?> str $message</p>

			<h4><code>mistape_options</code></h4>
			<p class="description"><?php _e( 'Description:' , 'mistape' ) ?> <?php _e( 'allows to modify global options array during initialization.' , 'mistape' ) ?></p>
			<p class="description"><?php _e( 'Parameters:' , 'mistape' ) ?> $options | arr</p>

		</div>
		<?php
	}

	/**
	 * Add Mistape caption to post content
     *
	 * @param $content
	 * @return string
	 */
	public function append_caption_to_content( $content ) {
		$output = '';
		if ( is_single() && in_array( get_post_type(), $this->options['post_types'] ) ) {

			$format = $this->options['caption_format'];

			if ( $format == 'text' ) {
				$output = '<div class="mistape_caption"><p>' . $this->caption_text . '</p></div>';
			} elseif ( $format == 'image' ) {
				$output = '<div class="mistape_caption"><img src="' . $this->options['caption_image_url'] . '" alt="' . $this->caption_text . '"></div>';
			}

			$output = apply_filters( 'mistape_caption_output', $output);

		}

		return $content . $output;
	}

	/**
	 * Mistape dialog output
	 */
	public function insert_dialog() {

		// get dialog args
		$strings = apply_filters( 'mistape_dialog_args', array(
			'title'		=> $this->dialog_title,
			'message'	=> $this->dialog_message,
			'close'		=> $this->close_text,
		) );

		// dialog output
		$output = '
		<div id="mistape_dialog" class="dialog">
			<div class="dialog__overlay"></div>
			<div class="dialog__content">
				<h2>' . $strings['title'] . '</h2>
				<h3>' . $strings['message'] . '</h2>
				<div><a class="action" data-dialog-close>' . $strings['close'] . '</a></div>
			</div>
		</div>';

		echo apply_filters( 'mistape_dialog_output', $output );
	}

	/**
	 * Handle AJAX reports
	 */
	public function ajax_handler() {

		$result = false;

		if (   isset( $_REQUEST['nonce'] )
			&& isset( $_REQUEST['reported_text'] )
			&& wp_verify_nonce( $_REQUEST['nonce'], "mistape_report")
		) {
			$reported_text = sanitize_text_field( $_REQUEST['reported_text'] );
			$context = sanitize_text_field( $_REQUEST['context'] );

			// check transients for repeated reports from IP
			$trans_name_short = 'mistape_short_ip_' . $_SERVER['REMOTE_ADDR'];
			$trans_name_long = 'mistape_long_ip_' . $_SERVER['REMOTE_ADDR'];
			$trans_5min = get_transient( $trans_name_short );
			$trans_30min = get_transient( $trans_name_long );
			$trans_5min = is_numeric( $trans_5min ) ? (int) $trans_5min : 0;
			$trans_30min = is_numeric( $trans_30min ) ? (int) $trans_30min : 0;

			if ( !empty( $reported_text ) && $trans_5min < 5 && $trans_30min < 30 ) {

				$trans_5min++;
				$trans_30min++;

				set_transient( $trans_name_short, $trans_5min, 300 );
				set_transient( $trans_name_long,  $trans_30min, 1800 );

				if ( strstr( $context, $reported_text ) !== false ) {
					$reported_text = str_replace( $reported_text, '<strong style="color: red;">' . $reported_text . '</strong>', $context );
				}

				do_action( 'mistape_process_report', $reported_text, $context );

				$url = wp_get_referer();
				$post_id = url_to_postid( $url );
				$user = wp_get_current_user();

				$to = $this->recipient_email;
				$subject = __( 'Spelling error reported' , 'mistape' );

				// referrer
				$message = '<p>' . __( 'Reported from page:' , 'mistape' ) . ' ';
				$message .= !empty( $url ) ? '<a href="' . $url . '">' . urldecode( $url ) . '</a>' : _x( 'unknown' , '[Email] Reported from page: unknown', 'mistape' );
				$message .= "</p>\n";

				// post edit link
				if( $post_id && $edit_post_link = $this->get_edit_post_link( $post_id, 'raw' ) ) {
					$message .= '<p>' . __( 'Post edit URL:', 'mistape' ) . ' <a href="' . $edit_post_link . '">' . $edit_post_link . "</a></p>\n";
				}

				// reported by
				if( $user->ID ) {
					$message .= '<p>' . __( 'Reported by:' , 'mistape' ) . ' ' . $user->display_name. ' (<a href="mailto:' . $user->data->user_email . '">' . $user->data->user_email . "</a>)</p>\n";
				}
				// reported text
				$message .= '<h3>' . __( 'Reported text' , 'mistape' ) . ":</h3>\n";
				$message .= '<code>' . $reported_text . "</code>\n";

				$headers = array('Content-Type: text/html; charset=UTF-8');

				$to = apply_filters( 'mistape_mail_recipient', $to);
				$subject = apply_filters( 'mistape_mail_subject', $subject);
				$message = apply_filters( 'mistape_mail_message', $message );

				$result = wp_mail( $to, $subject, $message, $headers );
			}

		}

		$response = json_encode( $result );

		echo $response;

		die();
	}
	/**
	 * duplicate of original WP function excluding user capabilities check
	 */
	function get_edit_post_link( $id = 0, $context = 'display' ) {
		if ( ! $post = get_post( $id ) )
			return;

		if ( 'revision' === $post->post_type )
			$action = '';
		elseif ( 'display' == $context )
			$action = '&amp;action=edit';
		else
			$action = '&action=edit';

		$post_type_object = get_post_type_object( $post->post_type );
		if ( !$post_type_object )
			return;

		// this part of original function is commented out
		/*if ( !current_user_can( 'edit_post', $post->ID ) )
			return;*/

		return apply_filters( 'get_edit_post_link', admin_url( sprintf( $post_type_object->_edit_link . $action, $post->ID ) ), $post->ID, $context );
	}
}
