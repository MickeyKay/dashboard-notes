<?php
/**
 * Dashboard Notes
 *
 * Plugin Name:       Dashboard Notes
 * Plugin URI:        http://wordpress.org/plugins/dashboard-notes
 * Description:       Create dashboard notes/instructions for your client.
 * Version:           1.0.3
 * Author:            MIGHTYminnow
 * Author URI:        http://mightyminnow.com
 * Text Domain:       dashboard-notes
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       dashboard-notes
 * Domain Path:       /languages
 */

/**
 * Set up new Dashboard Notes object.
 */
new DashboardNotes();

/**
 * Dashboard Notes plugin object.
 *
 * @since  1.0.0
 */
class DashboardNotes {

    var $plugin_name;
    var $plugin_id = 'dashboard-notes';
    var $options_name = 'dashboard_notes_options';
    var $dn_options = array();
    var $plugin_settings = array();
    var $words_on_page = 0;
    var $notes_logos;

    function __construct() {

        // Don't do anything unless we're in the admin.
        if ( ! is_admin() ) {
            return;
        }

        add_action( 'plugins_loaded', array( $this, 'load_text_domain') );

        // Load plugin settings
        add_action( 'after_setup_theme', array( $this, 'load_plugin_settings' ) );

        // Register sidebar
        add_action( 'widgets_init', array( $this, 'register_sidebar' ), 99 );

        // Set up the admin settings page.
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'add_settings' ) );

        // Save widget context settings, when in admin area
        add_filter( 'admin_init', array( $this, 'save_widget_context_settings' ) );

        // Add admin menu for config
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

        // Add widget controls.
        add_action( 'in_widget_form', array( $this, 'add_widget_controls' ), 10, 2 );

        // Display dashboard notes
        add_action( 'admin_notices', array( $this, 'display_notices' ) );
    }

    /**
     * Load text domain.
     *
     * @since  1.0.3
     */
    function load_text_domain() {
        $plugin_dir = basename( dirname(__FILE__) );
        load_plugin_textdomain( 'dashboard-notes', false, $plugin_dir );
    }

    /**
     * Get available user roles.
     *
     * This function is included manually, because WP's native
     * get_editable_roles() function isn't available early enough.
     *
     * @since   1.0.3
     *
     * @return  array  Editable user roles.
     */
    function get_user_roles() {

        global $wp_roles;

        $all_roles = $wp_roles->roles;

        /**
         * Filter the list of editable roles using WP's standard filter.
         *
         * @since  1.0.3
         *
         * @param  array  $editable_roles  Editable user roles.
         */
        $editable_roles = apply_filters( 'editable_roles', $all_roles );

        /**
         * Filter the list of editable roles using custom DN filter.
         *
         * @since  1.0.3
         *
         * @param  array  $editable_roles  Editable user roles.
         */
        $editable_roles = apply_filters( 'dn_editable_roles', $all_roles );

        return $editable_roles;

    }

    function load_plugin_settings() {

        $this->plugin_name = __( 'Dashboard Notes', 'dashboard-notes' );

        $this->dn_options = get_option( $this->options_name );
        $this->plugin_settings = $this->get_plugin_settings();

        if ( ! is_array( $this->dn_options ) || empty( $this->dn_options ) )
            $this->dn_options = array();
    }

    function register_sidebar() {
        if ( function_exists('register_sidebar') ) {
            register_sidebar( array(
                'name'          => $this->plugin_name . ' ' . __( '(admin only)', 'dashboard-notes' ),
                'id'            => $this->plugin_id,
                'description'   => __( 'Dashboard notes/instructions. For admin purposes only.', 'dashboard-notes' ),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widgettitle">',
                'after_title'   => '</h3>',
                )
            );
        }
    }

    function admin_scripts() {
        wp_enqueue_style( 'dashboard-notes-css', plugins_url( 'css/admin-styles.css', __FILE__ ) );
    }

    function add_widget_controls( $widget, $instance = array() ) {

    	// Set up color control.
    	$color_control = $this->make_simple_dropdown(
    		array(
    			$widget->id,
    			'style'
    		),
    		array(
    			'green' => __( 'Green' ),
    			'red' => __( 'Red' ),
    			'orange' => __( 'Orange' ),
    			'yellow' => __( 'Yellow' ),
    			'blue' => __( 'Blue' )
    		),
			__( 'Style', 'dashboard-notes' )
		);

    	// Set up logo include control.
		$include_logo_control = $this->make_simple_checkbox( array( $widget->id, 'include-logo' ), __( 'Include logo' ) );

		// Set up logo URL control.
		$logo_url_control = $this->make_simple_textfield( array( $widget->id, 'logo-url' ), __( 'Logo URL:', 'dashboard-notes' ) );

		// Set up include/exclude control.
		$incexc_control = $this->make_simple_dropdown(
			array(
				$widget->id,
				'incexc'
			),
			array(
				'show' => __( 'Show everywhere'),
				'hide' => __('Hide everywhere'),
				'selected' => __('Show on selected URLs'),
				'notselected' => __('Hide on selected URLs')
			),
			__( 'Where to show', 'dashboard-notes' )
		);

		// Set up URLs control.
		$url_control = $this->make_simple_textarea(
			array(
				$widget->id,
				'url',
				'urls'
			),
			__( 'Target URLs', 'dashboard-notes' ),
			'<p>' . sprintf(
				__( 'Enter one URL regex pattern per line, targeting the URL path after %s', 'dashboard-notes' ),
				'<code>' . untrailingslashit( get_admin_url() ) . '</code>'
			) . '</p>' .
			'<p>' . sprintf(
				__( 'For example, %s will show the widget when adding a new page.', 'dashboard-notes' ),
				'<code>post-new.php?post_type=page</code>'
			) . '</p>'
		);

    	ob_start();
    	?>

    		<div class="dashboard-notes-controls">
    			<h3><?php echo $this->plugin_name; ?></h3>
    			<p><?php echo $color_control; ?></p>
    			<p><?php echo $include_logo_control; ?></p>
    			<p><?php echo $logo_url_control; ?></p>
    			<p><?php echo $incexc_control; ?></p>
    			<p><?php echo $url_control; ?></p>
    		</div>

    	<?php
    	$controls = ob_get_clean();

    	echo $controls;

    }

    /**
     * Get settings options.
     *
     * @since   1.0.3
     *
     * @return  array  Plugin settings.
     */
    function get_plugin_settings() {

        return wp_parse_args(
            get_option( $this->plugin_id, array() ),
            $this->get_settings_defaults()
        );

    }

    /**
     * Return array of settings defaults.
     *
     * @since   1.0.3
     *
     * @return  array  $defaults  Plugin settings defaults.
     */
    function get_settings_defaults() {

        $defaults = array();

        foreach ( $this->get_user_roles() as $role_id => $role ) {
            $defaults[ $role_id ] = true;
        }

        return $defaults;

    }

    /**
     * Create the plugin settings page.
     */
    function add_settings_page() {

        add_options_page(
            $this->plugin_name,
            $this->plugin_name,
            'manage_options',
            $this->plugin_id,
            array( $this, 'create_admin_page' )
        );

    }

    /**
     * Output the plugin settings page contents.
     *
     * @since  0.10.0
     */
    public function create_admin_page() {
    ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo $this->plugin_name; ?></h2>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'option_group' );
                do_settings_sections( $this->plugin_id );
                submit_button();
            ?>
            </form>
        </div>
    <?php
    }

    /**
     * Populate the settings page with specific settings.
     *
     * @since  0.10.0
     */
    function add_settings() {

        register_setting(
            'option_group', // Option group
            $this->plugin_id, // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'settings_section_primary', // ID
            ' ', // Title
            array( $this, 'role_settings_callback' ), // Callback
            $this->plugin_id // Page
        );

        // Create checkbox for each role.
        foreach ( $this->get_user_roles() as $role_id => $role ) {

            add_settings_field(
                $role_id,
                $role['name'],
                array( $this, 'checkbox_callback' ),
                $this->plugin_id,
                'settings_section_primary',
                array(
                    'id'   => $role_id,
                    'name' => $role['name'],
                    'type' => 'checkbox',
                )
            );

        }

    }

    function role_settings_callback() {
        _e( 'Choose the roles for which dashboard notes should be visible.', 'dashboard-notes' );
    }

    /**
     * Output a checkbox setting.
     *
     * @since  0.10.0
     */
    public function checkbox_callback( $args ) {

        $option_name = esc_attr( $this->plugin_id ) . '[' . $args['id'] . ']';
        $option_value = isset( $this->plugin_settings[ $args['id'] ] ) ? $this->plugin_settings[ $args['id'] ] : '';

        printf(
            '<input type="checkbox" value="1" id="%s" name="%s" %s/>',
            $args['id'],
            $option_name,
            checked( 1, $option_value, false )
        );

    }

    /**
     * Sanitize saved setting.
     *
     * @since   1.0.3
     *
     * @param   array  $input      Settings array.
     *
     * @return  array  $new_input  Sanitized settings array.
     */
    function sanitize( $input ) {

        // Initialize the new array that will hold the sanitize values.
        $new_input = array();

        // Loop through the input and sanitize each of the values (true or false).
        foreach ( $this->get_user_roles() as $role_id => $role ) {

            $new_input[ $role_id ] = ( isset( $input[ $role_id ] ) ) ? true : false;

        }

        return $new_input;

    }

    function save_widget_context_settings() {
        if ( ! current_user_can( 'edit_theme_options' ) || empty( $_POST ) || ! isset( $_POST['sidebar'] ) || empty( $_POST['sidebar'] ) )
            return;

        // Delete
        if ( isset( $_POST['delete_widget'] ) && $_POST['delete_widget'] )
            unset( $this->dn_options[ $_POST['widget-id'] ] );

        // Add / Update
        if ( isset( $_POST['dn'] ) )
            $this->dn_options = array_merge( $this->dn_options, $_POST['dn'] );

        update_option( $this->options_name, $this->dn_options );
    }

    /**
     * Check whether or not the current user should se notices based on the plugin settings.
     *
     * @since   1.0.3
     *
     * @return  boolean  true|false
     */
    function current_user_can_see_notices() {

        // Get role(s) of current user.
        $current_roles = wp_get_current_user()->roles;

        foreach ( $current_roles as $role ) {

            if ( isset( $this->plugin_settings[ $role ] ) && $this->plugin_settings[ $role ] ) {
                return true;
            }

        }

        return false;

    }

    /*===========================================
     * Output notices
    ===========================================*/
    function display_notices( ) {

        // Don't do anything if the current user can't see notices.
        if ( ! $this->current_user_can_see_notices() ) {
            return;
        }

        global $wp_registered_sidebars, $wp_registered_widgets;

        $sidebar = $wp_registered_sidebars[ $this->plugin_id ];

        // Get array of widgets that are in the Dashboard Notes sidebar
        $sidebar_widgets = wp_get_sidebars_widgets();
        $dn_widgets = ! empty( $sidebar_widgets[ $this->plugin_id ] ) ? $sidebar_widgets[ $this->plugin_id ] : '';

        if ( ! $dn_widgets )
            return false;

        foreach( $dn_widgets as $id ) {

            if ( !isset( $wp_registered_widgets[$id]) ) continue;

            // Get widget options
            $widget_options = isset( $this->dn_options[ $id ] ) ? $this->dn_options[ $id ] : array();
            $style = isset( $widget_options['style'] ) ? $widget_options['style'] : 'updated';
            $logo = isset( $widget_options['include-logo'] ) ? 'include-logo' : '';
            $widget_id = 'widget-id-' . $id;
            $class = 'updated ' . $style . ' ' . $logo . ' ' . $widget_id;

            $params = array_merge(
                array( array_merge( $sidebar, array('widget_id' => $id, 'widget_name' => $wp_registered_widgets[$id]['name']) ) ),
                (array) $wp_registered_widgets[$id]['params']
                );

            // Substitute HTML id and class attributes into before_widget
            $classname_ = '';
            foreach ( (array) $wp_registered_widgets[$id]['classname'] as $cn ) {
                if ( is_string($cn) )
                    $classname_ .= '_' . $cn;
                elseif ( is_object($cn) )
                    $classname_ .= '_' . get_class($cn);
            }
            $classname_ = ltrim($classname_, '_');
            $params[0]['before_widget'] = sprintf($params[0]['before_widget'], $id, $classname_);

            $params = apply_filters( 'dynamic_sidebar_params', $params );

            $callback = $wp_registered_widgets[$id]['callback'];

            do_action( 'dynamic_sidebar', $wp_registered_widgets[$id] );

            // Output widget as notification
            if ( is_callable( $callback ) && $this->check_widget_visibility( $id ) ) {
                // Output notice
                ?>
                <div class="<?php echo $class; ?> dashboard-note">
                    <?php
                    if ( isset( $widget_options['include-logo'] ) ) {
                        if ( $widget_options['logo-url'] )
                            $logo_url = $widget_options['logo-url'];
                        else
                            $logo_url = plugin_dir_url( __FILE__ ) . 'images/mm-logo.png';
                        echo '<img class="dn-logo" src="' . $logo_url . '" />';
                    }
                    call_user_func_array( $callback, $params );
                    ?>
                </div>
                <?php
            }
        }
    }

    function get_current_url() {
        if ($_SERVER['REQUEST_URI'] == '')
            $uri = $_SERVER['REDIRECT_URL'];
        else
            $uri = $_SERVER['REQUEST_URI'];

        return (is_ssl())
        ? "https://".$_SERVER['SERVER_NAME'].$uri
        : "http://".$_SERVER['SERVER_NAME'].$uri;
    }

    // Thanks to Drupal: http://api.drupal.org/api/function/drupal_match_path/6
    function match_path( $path, $patterns ) {
        $patterns_safe = array();

        // Strip home url and check only the REQUEST_URI part
        $path = trim( str_replace( trailingslashit( get_admin_url() ), '', $path ), '/' );

        // If we're right on /wp-admin, force the path to '/'.
        $path = $path ? $path : '/';

        foreach ( explode( "\n", $patterns ) as $pattern ) {

        	$pattern = trim( $pattern );

        	if ( strlen( $pattern ) != 1 ) {
        		$pattern = untrailingslashit( $pattern );
        	}

            $patterns_safe[] = $pattern;

        }

        $regexps = '/^('. preg_replace( array( '/(\r\n|\n| )+/', '/\\\\\*/' ), array( '|', '.*' ), preg_quote( implode( "\n", array_filter( $patterns_safe, 'trim' ) ), '/' ) ) .')$/';

        return preg_match( $regexps, $path );

    }


    function check_widget_visibility( $widget_id ) {

        // Show widget because no context settings found
        if ( ! isset( $this->dn_options[ $widget_id ] ) )
            return true;

        $vis_settings = $this->dn_options[ $widget_id ];

        // Hide/show if forced
        if ( $vis_settings['incexc'] == 'hide' ) {
            return false;
        } elseif ( $vis_settings['incexc'] == 'show' ) {
            return true;
        }

        $do_show = true;
        $do_show_by_url = false;

        // Check by current URL
        if ( ! empty( $vis_settings['url']['urls'] ) ) {

            if ( $this->match_path( $this->get_current_url(), $vis_settings['url']['urls'] ) ) {
                $do_show_by_url = true;
            }

        }

        // Combine all context checks.
        if ( $do_show_by_url ) {
            $one_is_true = true;
        } else {
            $one_is_true = false;
        }

        // Combine all context checks.
        if ( ( $vis_settings['incexc'] == 'selected' ) && $one_is_true ) {
            $do_show = true;
        } elseif ( ( $vis_settings['incexc'] == 'notselected' ) && ! $one_is_true ) {
            $do_show = true;
        } elseif ( ! empty( $vis_settings['incexc'] ) ) {
            $do_show = false;
        } else {
            $do_show = true;
        }

        // Allow other plugins to override any of the above logic
        $do_show = apply_filters( 'widget_context_visibility', $do_show, $widget_id, $vis_settings );

        return $do_show;
    }


    /*===========================================
     * Interface Constructors
    ===========================================*/
    function make_simple_checkbox( $name, $label ) {
        return sprintf(
            '<label class="dn-%s"><input type="checkbox" value="1" name="dn%s" %s />&nbsp;%s</label>',
            $this->get_field_classname( $name ),
            $this->get_field_name( $name ),
            checked( (bool) $this->get_field_value( $name ), 1, false ),
            $label
            );
    }


    function make_simple_textarea( $name, $label, $tip = null ) {
        if ( $tip )
            $tip = sprintf( '<p class="dn-tip">%s</p>', $tip );

        return sprintf(
            '<div class="dn-%s"><label><strong>%s</strong><textarea class="widefat" name="dn%s" rows="5">%s</textarea></label><div class="description">%s</div></div>',
            $this->get_field_classname( $name ),
            $label,
            $this->get_field_name( $name ),
            esc_textarea( $this->get_field_value( $name ) ),
            $tip
            );
    }


    function make_simple_textfield( $name, $label_before = null, $label_after = null) {
        return sprintf(
            '<label class="dn-%s widefat"><strong>%s</strong> <input type="text" class="widefat" name="dn%s" value="%s" /> %s</label>',
            $this->get_field_classname( $name ),
            $label_before,
            $this->get_field_name( $name ),
            esc_attr( $this->get_field_value( $name ) ),
            $label_after
            );
    }


    function make_simple_dropdown( $name, $selection = array(), $label_before = null, $label_after = null ) {
        $value = esc_attr( $this->get_field_value( $name ) );
        $options = array();

        if ( empty( $selection ) )
            $options[] = sprintf( '<option value="">%s</option>', __('No options given') );

        foreach ( $selection as $sid => $svalue )
            $options[] = sprintf( '<option value="%s" %s>%s</option>', $sid, selected( $value, $sid, false ), $svalue );

        return sprintf(
            '<label class="dn-%s"><strong>%s</strong><br /><select name="dn%s">%s</select>%s</label>',
            $this->get_field_classname( $name ),
            $label_before,
            $this->get_field_name( $name ),
            implode( '', $options ),
            $label_after
            );
    }

    /**
     * Returns [part1][part2][partN] from array( 'part1', 'part2', 'part3' )
     * @param  array  $parts i.e. array( 'part1', 'part2', 'part3' )
     * @return string        i.e. [part1][part2][partN]
     */
    function get_field_name( $parts ) {
        return esc_attr( sprintf( '[%s]', implode( '][', $parts ) ) );
    }

    function get_field_classname( $parts ) {
        return sanitize_html_class( str_replace( '_', '-', end( $parts ) ) );
    }


    /**
     * Given option keys return its value
     * @param  array  $parts   i.e. array( 'part1', 'part2', 'part3' )
     * @param  array  $options i.e. array( 'part1' => array( 'part2' => array( 'part3' => 'VALUE' ) ) )
     * @return string          Returns option value
     */
    function get_field_value( $parts, $options = array() ) {
        if ( empty( $options ) )
            $options = $this->dn_options;

        if ( ! empty( $parts ) )
            $part = array_shift( $parts );

        if ( isset( $part ) && isset( $options[ $part ] ) && is_array( $options[ $part ] ) )
            $value = $this->get_field_value( $parts, $options[ $part ] );
        elseif ( isset( $options[ $part ] ) )
            $value = $options[ $part ];
        else
            $value = '';

        return trim( $value );
    }


}