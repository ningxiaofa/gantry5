<?php
defined('ABSPATH') or die;

add_action( 'admin_init', 'gantry5_admin_start_buffer', -10000 );
add_action( 'admin_init', 'gantry5_register_admin_settings' );
add_filter( 'plugin_action_links', 'gantry5_modify_plugin_action_links', 10, 2 );
add_filter( 'network_admin_plugin_action_links', 'gantry5_modify_plugin_action_links', 10, 2 );
add_action( 'admin_enqueue_scripts', 'gantry5_admin_scripts' );
add_action( 'wp_ajax_gantry5', 'gantry5_layout_manager' );

// Check if Timber is active before displaying sidebar button
if ( class_exists( 'Timber' ) ) {
    // Load Gantry 5 icon styling for the admin sidebar
    add_action( 'admin_enqueue_scripts',
        function () {
            if( is_admin() ) {
                wp_enqueue_style( 'wordpress-admin-icon', Gantry\Framework\Document::url( 'gantry-assets://css/wordpress-admin-icon.css' ) );
            }
        }
    );

    // Adjust menu to contain Gantry stuff.
    add_action(
        'admin_menu',
        function () {
            $gantry = Gantry\Framework\Gantry::instance();
            $theme = $gantry['theme']->details()['details.name'];
            remove_submenu_page( 'themes.php', 'theme-editor.php' );
            add_menu_page( $theme . ' Theme', $theme . ' Theme', 'manage_options', 'layout-manager', 'gantry5_layout_manager' );
            add_submenu_page( null, 'Gantry 5 Settings', 'Gantry 5 Settings', 'manage_options', 'g5-settings', 'gantry5_plugin_settings' );
        },
        100
    );
}

function gantry5_admin_start_buffer() {
    ob_start();
}

function gantry5_admin_scripts() {
    if( isset( $_GET['page'] ) && $_GET['page'] == 'layout-manager' ) {
        gantry5_layout_manager();
    }
}

function gantry5_layout_manager() {
    static $output = null;

    if ( !current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    add_filter( 'admin_body_class', function () {
        return 'gantry5 gantry5-wordpress';
    } );

    if ( $output ) {
        echo $output;
        return;
    }

    // Detect Gantry Framework or fail gracefully.
    if (!class_exists('Gantry5\Loader')) {
        wp_die( __( 'Gantry 5 Framework not found.' ) );
    }

    // Initialize administrator or fail gracefully.
    try {
        Gantry5\Loader::setup();

        $gantry = Gantry\Framework\Gantry::instance();
        $gantry['router'] = function ($c) {
            return new \Gantry\Admin\Router($c);
        };

        // Dispatch to the controller.
        $output = $gantry['router']->dispatch();

    } catch (Exception $e) {
        throw $e;
//        wp_die( $e->getMessage() );
    }
}

function gantry5_modify_plugin_action_links( $links, $file ) {
    // Return normal links if not Gantry 5
    if ( plugin_basename( GANTRY5_PATH . '/gantry5.php' ) != $file ) {
        return $links;
    }

    // Add a few links to the existing links array
    return array_merge( $links, array(
        'settings' => '<a href="' . esc_url( add_query_arg( [ 'page' => 'g5-settings' ] ) ) . '">' . esc_html__( 'Settings', 'gantry5' ) . '</a>'
    ) );

}

function gantry5_register_admin_settings() {
    register_setting( 'gantry5_plugin_options', 'gantry5_plugin' );
}

function gantry5_plugin_settings() {
    $option = get_option( 'gantry5_plugin' );

    if( isset( $_GET[ 'settings-updated' ] ) && $_GET[ 'settings-updated' ] == 'true' ) {
        echo '<div id="message" class="updated fade"><p>Gantry 5 plugin settings saved.</p></div>';
    }

    ?>

    <div id="g5-options-main">
        <form method="post" action="options.php">
            <?php settings_fields( 'gantry5_plugin_options' ); ?>

            <table class="widefat fixed">
                <tfoot>
                <tr>
                    <th colspan="2">
                        <input type="submit" class="button button-primary rb-submit" value="<?php _e('Save Changes', 'gantry5'); ?>" />
                    </th>
                </tr>
                </tfoot>
                <tbody>
                <tr>
                    <td>
                        <h3 class="available-options"><?php _e( 'Available Options', 'gantry5' ); ?></h3>
                        <div class="param-row alternate first">
                            <div class="label"><?php _e( 'Production Mode', 'gantry5' ); ?></div>
                            <div class="option">
                                <input id="production1" type="radio" <?php checked( $option[ 'production' ], '1' ); ?> value="1" name="gantry5_plugin[production]"/>
                                <label for="production1"><?php _e('Enable', 'gantry5'); ?></label>&nbsp;&nbsp;
                                <input id="production2" class="second" type="radio" <?php checked( $option['production'], '0' ); ?> value="0" name="gantry5_plugin[production]"/>
                                <label for="production2"><?php _e('Disable', 'gantry5'); ?></label>
                            </div>
                        </div>
                        <div class="param-row last">
                            <div class="label"><?php _e( 'Debug Mode', 'gantry5' ); ?></div>
                            <div class="option">
                                <input id="debug1" type="radio" <?php checked( $option[ 'debug' ], '1' ); ?> value="1" name="gantry5_plugin[debug]"/>
                                <label for="debug1"><?php _e('Enable', 'gantry5'); ?></label>&nbsp;&nbsp;
                                <input id="debug2" class="second" type="radio" <?php checked( $option['debug'], '0' ); ?> value="0" name="gantry5_plugin[debug]"/>
                                <label for="debug2"><?php _e('Disable', 'gantry5'); ?></label>
                            </div>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </form>
    </div>

<?php
}
