<?php

// EasyCommerce Core Version 
define('EZYC_CORE_VERSION', '1.0.0');

// Minimum PHP version support
define('EZYC_VERSION_PHP', '8.1');

// Minimum Wordpress version
define('EZYC_VERSION_WP', '6.0');

class EasyCommerce_Core {
    const SLUG = 'easycommerce';
    private static $instance = null;

    private function __construct() {
        if (is_admin()) :
            $plugins_active = apply_filters('active_plugins', get_option('active_plugins'));

            foreach($plugins_active as $plugin) :
                if (Strpos($plugin, 'easycommerce-') !== false) :
                    add_action('admin_menu', [$this, 'add_admin_menu']);
                    break;
                endif;
            endforeach;
        endif;
    }

    public function add_admin_menu() {
        global $menu;
        $woo_pos = 99;

        foreach ($menu as $position => $item) :
            if ($item[2] == 'woocommerce') :
                $woo_pos = $position-1;
                break;
            endif;
        endforeach;

        add_menu_page(
            'EasyCommerce',
            'EasyCommerce',
            'manage_options',
            self::SLUG,
            array($this, 'display_plugin_page'),
            'dashicons-admin-generic',
            $woo_pos
        );
    }

    public function display_plugin_page() {
        ?>
        <div class="wrap">
            <h1>EasyCommerce</h1>
            <p>Collection of features that allows more fine tuning of WooCommerce stores without the price tags</p>
            <br />
            <p>To submit a feature request or to report any bugs please go to</p>
        </div>
        <?php
    }

    public static function get_instance() {
        if (self::$instance === null)
            self::$instance = new static();

        return self::$instance;
    }

    public function check_requirements($plugin_file) {
        $pass = true;

        if (version_compare(phpversion(), EZYC_VERSION_PHP, '<')) :
            $pass = false;
            add_action('admin_notices', [$this, 'notice_version_php']);
        endif;

        if (version_compare(get_bloginfo('version'), EZYC_VERSION_WP, '<')) :
            $pass = false;
            add_action('admin_notices', [$this, 'notice_version_wp']);
        endif;

        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) :
            $pass = false;
            add_action('admin_notices', [$this, 'notice_woocommerce']);
        endif;

        if (!$pass) {
            add_action('admin_init', function () use ($plugin_file) {
                deactivate_plugins(plugin_basename($plugin_file));
    
                if (isset($_GET['activate']))// phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    unset($_GET['activate']);// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            });
        }

        return $pass;
    }

    public function notice_insufficent_permissions() {
        echo '<div class="notice notice-error is-dismisible"><p>';
        esc_attr_e(__('You do not have sufficent permissions to access these settings', 'easycommerce'));
        echo '</p></div>';
    }

    public function notice_version_php() {
        echo '<div class="notice notice-error is-dismisible"><p>';
        printf(
            wp_kses(/* translators: %s The minimal PHP version supported by UWoof Store Plugins. */
                __('Your site is running an unsupported version of PHP that is no longer supported. Please contact your web hosting provider to update your PHP version to at least <strong>%s</strong>', 'easycommerce'),
                array(
                    'strong' => array()
                )
            ),
            esc_html(EZYC_VERSION_PHP)
        );
        echo '</p></div>';
    }

    public function notice_version_wp() {
        echo '<div class="notice notice-error is-dismisible"><p>';
        printf(
            wp_kses(/* translators: %s The minimal WP version supported by UW Store Plugins. */
                __('Your site is running an unsupported version of wordpress. Please update your site to at least version <strong>%s</strong>', 'easycommerce'),
                array(
                    'strong' => array()
                )
            ),
            esc_html(EZYC_VERSION_WP)
        );
        echo '</p></div>';
    }

    public function notice_woocommerce() {
        echo '<div class="notice notice-error is-dismisible"><p>';
        esc_attr_e(__('WooCommerce is not currently activated. Please make sure the WooCommerce plugin is installed and activated', 'easycommerce'));
        echo '</p></div>';
    }
}

function easycommerce_core() {
    return EasyCommerce_Core::get_instance();
}