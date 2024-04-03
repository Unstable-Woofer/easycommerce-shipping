<?php
/*
* Plugin Name:       EasyCommerce - Shipping Methods
* Plugin URI:        https://github.com/Unstable-Woofer/easycommerce-shipping
* Description:       Table rate shipping method for WooCommerce support rates based on class, total and weight
* Version:           1.0.0
* Requires at least: 6.0
* Requires PHP:      8.1
* Author:            Unstable Woofer
* Author URI:        https://github.com/Unstable-Woofer
* License:           GPL v2 or later
* License URI:       https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:       easycommerce-shipping
* Domain Path:       /languages
*/

namespace EasyCommerce\Shipping;

defined('ABSPATH') || exit;

if (!class_exists('EasyCommerce_Core'))
    require_once(plugin_dir_path(__FILE__) . '/libaries/easycommerce.php');

easycommerce_core()->check_requirements(__FILE__) || exit;

use WC_Shipping_Method;

// EasyCommerce shipping version
define('EZYC_SHIPPING_VERSION', '1.0.0');

function easycommerce_shipping_init() {
    class EasyCommerce_Shipping_Method extends WC_Shipping_Method {
        private $easycommerce_shipping_key;
        private $easycommerce_shipping_conditions;
        private $easycommerce_shipping_classes;
        private $plugin_path_dir;
        private $plugin_path_url;

        public function __construct($instance_id = 0) {
            $this->id                   = 'easycommerce_shipping_method_table_rate';
            $this->method_title         = __('EasyCommerce - Table Rate', 'easycommerce-shipping');
            $this->method_description   = __('Table rate for selecting the shipping cost base off weight, total and shipping classes', 'easycommerce-shipping');
            $this->instance_id          = $instance_id;
            $this->title                = $this->get_option('title', 'EasyCommerce - Table Rate');
            $this->enabled              = $this->get_option('enabled', 'yes');
            $this->supports             = array(
                'shipping-zones',
                'instance-settings',
            );

            $this->plugin_path_dir = plugin_dir_path(__FILE__);
            $this->plugin_path_url = plugin_dir_url(__FILE__);

            $this->init_settings();
            $this->init_instance_settings();

            $this->easycommerce_shipping_key = $this->id . '_' . $this->instance_id;
            $this->create_table_rate_conditions();
            $this->get_woocommerce_shipping_classes();

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Automatically called by Woocommerce to display the edit pages
         * 
         * @since 1.0.0
         */
        public function admin_options() {
            if (!current_user_can('manage_woocommerce')) :
                easycommerce_core()->notice_insufficent_permissions();
                return;
            endif;

            $action = isset($_GET['action']) && check_admin_referer('easycommerce-shipping-nonce', 'easycommerce-shipping-nonce') ? sanitize_text_field($_GET['action']) : false;
            $instance_id                        = sanitize_text_field($_GET['instance_id']);
            $zone                               = \WC_Shipping_Zones::get_zone_by('instance_id', $instance_id);
            $get_shipping_method_by_instance_id = \WC_Shipping_Zones::get_shipping_method($instance_id);
            $link                       = '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping') . '">' . __('Shipping Zones', 'woocommerce') . '</a> > ';
            $link                      .= '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . absint($zone->get_id())) . '">' . esc_html($zone->get_zone_name()) . '</a> > ';
            $link                      .= '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&instance_id=' . (int) ($instance_id)) . '">' . esc_html(($get_shipping_method_by_instance_id->get_title())) . '</a>';
            ?>
            <script>
                jQuery(document).ready(function() {
                    jQuery("#mainform h2").first().replaceWith('<h2>' + '<?php echo wp_kses($link, 'post'); ?>' + '</h2>');
                });
            </script>
            <table class="form-table">
            <?php
            if ($action == 'new' || $action == 'edit') :
                $method = array(
                    'method_id'         => '',
                    'method_title'        => '',
                    'method_enabled'      => 'no',
                    'woocommerce_method_instance_id' => $this->instance_id,
                    'method_id_for_shipping' => '',
                    'method_handle_fee' => ''
                );

                if ($action == 'edit') :
                    $get_shipping_methods = get_option($this->easycommerce_shipping_key, array());
                    $method_id = sanitize_text_field($_GET['method_id']);
                    $method = $get_shipping_methods[$method_id];
                    $method['method_id_for_shipping'] = $this->id . '_' . $this->instance_id . '_' . sanitize_title($method['method_title']);
                endif;

                wp_nonce_field('easycommerce-shipping-nonce', 'easycommerce-shipping-nonce');
                
                $this->form_fields = array(
                    'method_enabled' => array(
                        'title'   => __('Enable/Disable', 'easycommerce-shipping'),
                        'label'   => __('Enable this shipping method', 'easycommerce-shipping'),
                        'type'    => 'checkbox',
                        'default' => $method['method_enabled'],
                    ),
                    'method_title'        => array(
                        'title'       => __('Method Title', 'easycommerce-shipping'),
                        'description' => __('Sets the name the customer see at checkout', 'easycommerce-shipping'),
                        'type'        => 'text',
                        'default'     => $method['method_title'],
                        'desc_tip'    => false,
                    ),
                    'method_handle_fee' => array(
                        'title' => __('Handling Fee', 'easycommerce-shipping'),
                        'description' => __('Adds a fee for handling.', 'easycommerce-shipping'),
                        'type'        => 'text',
                        'default' => $method['method_handle_fee']
                    ),
                    'method_tax_status' => array(
                        'title'   => __('Tax Status', 'easycommerce-shipping'),
                        'type'    => 'select',
                        'default' => isset($method['method_tax_status']) ? $method['method_tax_status'] : 'notax',
                        'options' => array(
                            'taxable' => __('Taxable', 'easycommerce-shipping'),
                            'notax'   => __('Not Taxable', 'easycommerce-shipping'),
                        )
                    ),
                    'rates_table'   => array(
                        'title'       => __('Shipping Methods', 'easycommerce-shipping'),
                        'type'        => 'rates_table',
                        'default'     => isset($method['method_table_rates']) ? $method['method_table_rates'] : array(),
                        'description' => '',
                    ),
                    'shipping_method_action' => array(
                        'type' => 'hidden',
                        'default' => $action
                    ),
                    'shipping_method_id' => array(
                        'type' => 'hidden',
                        'default' => $method['method_id']
                    ),
                    'method_id_for_shipping' => array(
                        'type' => 'hidden',
                        'default' => $method['method_id_for_shipping']
                    )
                );
                $this->generate_settings_html();
            elseif ($action == 'delete') :
                $selected_method_ids = explode(',', sanitize_text_field($_GET['shipping_methods_id']));
                $get_shipping_methods = get_option($this->easycommerce_shipping_key, array());

                foreach ($selected_method_ids as $method_id) :
                    if (isset($get_shipping_methods[$method_id]))
                        unset($get_shipping_methods[$method_id]);
                endforeach;

                update_option($this->easycommerce_shipping_key, $get_shipping_methods);

                $redirect_url = admin_url('admin.php?page=wc-settings&tab=shipping&instance_id=' . $this->instance_id);
                wp_safe_redirect($redirect_url);
                exit;
            else:
                $this->form_fields = array(
                    'shipping_list' => array(
                        'title'       => __('Shipping Methods', 'easycommerce-shipping'),
                        'type'        => 'shipping_list',
                        'description' => '',
                    ),
                );
                $this->generate_settings_html();
            endif;
            echo '</table>';
        }

        /**
         * Automatically called by Woocommerce on to display
         * the shipping rates to the customer
         * 
         * @since 1.0.0
         */
        public function calculate_shipping($package = array()) {
            $get_shipping_methods = get_option($this->easycommerce_shipping_key, array());
            $cart_weight =  WC()->cart->cart_contents_weight;
            $cart_total = WC()->cart->get_cart_contents_total();

            foreach ($get_shipping_methods as $method) :
                $handle_fee = !empty($method['method_handle_fee']) ? $method['method_handle_fee'] : 0;
                $matched = false;
                $tax = false;

                if ($method['method_enabled'] == 'no')
                    continue;

                if ($method['method_tax_status'] == 'taxable') :
                    if ('incl' == get_option('woocommerce_tax_display_cart'))
                        $cart_total = $cart_total + WC()->cart->get_cart_contents_tax();

                    $tax = '';
                endif;

                foreach ($method['method_table_rates'] as $method_rate) :
                    if ($matched)
                        break;
                    $cost = null;

                    if (!empty($method_rate['shipping_class'])) {
                        $all_match = true;

                        foreach ($package['contents'] as $item) :
                            $product = new WC_Product($item['product_id']);

                            if ($product->get_shipping_class_id() != $method_rate['shipping_class']) {
                                $all_match=false;
                                break;
                            }
                        endforeach;

                        if (!$all_match)
                            continue;
                    }

                    if ($method_rate['condition'] == 'weight')
                        $cost = $this->find_rate($cart_weight, $method_rate);
                    else if ($method_rate['condition'] == 'total')
                        $cost = $this->find_rate($cart_total, $method_rate);
                    
                    if ($cost == null)
                        continue;

                    $matched = true;
                    $rate = array(
                        'id' => $this->id . '_' . $method['method_id'],
                        'title' => $method['method_title'],
                        'label' => $method['method_title'],
                        'cost' => $cost + $handle_fee,
                        'taxes' => $tax,
                        'calc_tax' => 'per_order'
                    );

                    $this->add_rate($rate);
                endforeach;
            endforeach;
        }

        /**
         * Create and array for the conditions for the table rate
         * 
         * @since 1.0.0
         */
        public function create_table_rate_conditions() {
            $this->easycommerce_shipping_conditions = array();
            $this->easycommerce_shipping_conditions['weight'] = sprintf(__('Weight (%s)', 'easycommerce-shipping'), get_option('woocommerce_weight_unit'));
            $this->easycommerce_shipping_conditions['total']  = sprintf(__('Total Price (%s)', 'easycommerce-shipping'), get_woocommerce_currency_symbol());
        }

        /**
         * Called in calculate shipping to find a matching rate
         * 
         * @since 1.0.0
         * 
         * @param array $rate
         * @param int   $value
         * 
         * @return int
         */
        public function find_rate($value, $rate) {
            if (empty($rate['max'])) {
                if ($value >= $rate['min'])
                    return $rate['shipping_fee'];
            } else if ($value >= $rate['min'] && $value <= $rate['max']) {
                return $rate['shipping_fee'];
            }

            return null;
        }

        /**
         * Automatically called by WooCommerce to generate the 
         * method list table
         * 
         * @since 1.0.0
         */
        public function generate_rates_table_html() {
            ob_start();
            $table_title = esc_attr('Rates', 'easycommerce-shipping');
            $get_shipping_methods = get_option($this->easycommerce_shipping_key, array());
            ?>
            <script>
                jQuery(document).ready(function() {
                    array_conditions = <?php echo wp_json_encode($this->easycommerce_shipping_conditions); ?>;
                    array_shipping_classes = <?php echo wp_json_encode($this->easycommerce_shipping_classes); ?>;
                    plugin_id = <?php echo wp_json_encode($this->id); ?>;

                    <?php if ($_GET['action'] == 'edit') : ?>
                    array_current_methods = <?php echo wp_json_encode($get_shipping_methods[$_GET['method_id']]); ?>;
                    <?php endif; ?>
                });
            </script>
            <?php
            include($this->plugin_path_dir . 'inc/admin/views/table-rates.php');

            return ob_get_clean();
        }

        /**
         * Automatically called by WooCommerce to generate the 
         * method list table
         * 
         * @since 1.0.0
         */
        public function generate_shipping_list_html() {
            ob_start();
            ?>
            <table class="form-table">
                <tr valign="top">
                    <td>
                        <table id="easycommerce-shipping-methods-table" class="widefat wc_shipping wp-list-table" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="sort" style="width: 1%">&nbsp;</th>
                                    <th style="width: 30%"><?php esc_attr_e('Method Name', 'easycommerce-shipping'); ?></th>
                                    <th style="width: 1%"><?php esc_attr_e('Enabled', 'easycommerce-shipping'); ?></th>
                                    <th><input type="checkbox" class="tips checkbox-select-all" data-tip="<?php esc_attr_e('Select all', 'easycommerce-shipping'); ?> " class="checkall-checkbox-class" id="checkall-checkbox-id" /></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $get_shipping_methods = get_option($this->easycommerce_shipping_key, array());

                                foreach ($get_shipping_methods as $shipping_method) :
                                    $method_url_edit = esc_attr(remove_query_arg(
                                        'shipping_methods_id',
                                        add_query_arg(
                                            array(
                                                'method_id' => $shipping_method['method_id'],
                                                'action' => 'edit',
                                                'easycommerce-shipping-nonce' => wp_create_nonce('easycommerce-shipping-nonce')
                                            )
                                        )
                                    ));
                                    ?>
                                    <tr id="easycommerce_shipping_table_rate_id_<?php echo esc_attr($shipping_method['method_id']); ?>">
                                        <td class="sort">
                                            <input type="hidden" name="method_order[<?php echo esc_attr($shipping_method['method_id']); ?>]" value="<?php echo esc_attr($shipping_method['method_id']); ?>" />
                                        </td>
                                        <td class="easycommerce-shipping-method-title">
                                            <a href="<?php echo $method_url_edit; ?>"><strong><?php echo esc_html($shipping_method['method_title']); ?></strong></a>
                                        </td>
                                        <td class="method-status" style="width: 524px;display: -moz-stack;">
                                            <?php if ($shipping_method['method_enabled'] == 'yes') : ?>
                                            <span class="status-enabled tips" data-tip="<?php esc_attr_e('yes', 'easycommerce-shipping'); ?>"><?php esc_attr_e('yes', 'easycommerce-shipping'); ?></span>
                                            <?php else : ?>
                                            <span class="na">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="method-select" style="width: 2% !important;text-align: center;" nowrap>
                                            <input type="checkbox" class="tips checkbox-select" value="<?php echo esc_attr($shipping_method['method_id']); ?>" data-tip="<?php echo esc_attr($shipping_method['method_title']); ?>" />
                                        </td>
                                    </tr>
                                    <?php
                                endforeach;
                                ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>&nbsp;</th>
                                    <th colspan="8">
                                        <span class="description"><?php esc_attr_e('Drag and drop the above methods to control their display order. Confirm by clicking Save changes button below.', 'easycommerce-shipping'); ?></span>
                                    </th>
                                </tr>
                                <tr>
                                    <th>&nbsp;</th>
                                    <th id="easycommerce-shipping-methods-table-actions" colspan="8">
                                        <button class="button add" data-url="<?php
                                            echo esc_url(remove_query_arg(
                                                'shipping_methods_id',
                                                add_query_arg(
                                                    array(
                                                        'action' => 'new',
                                                        'easycommerce-shipping-nonce' => wp_create_nonce('easycommerce-shipping-nonce')
                                                    )
                                                )
                                            ));
                                        ?>" disabled><?php esc_attr_e('Add new method', 'easycommerce-shipping'); ?></button>
                                        <button class="button delete" data-url="<?php
                                        echo esc_url(add_query_arg(
                                            array(
                                                'action'               => 'delete',
                                                'easycommerce-shipping-nonce' => wp_create_nonce('easycommerce-shipping-nonce'),
                                                'shipping_methods_id'  => '',
                                            )));?>" disabled><?php esc_attr_e('Remove selected', 'easycommerce-shipping'); ?></button>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </td>
                </tr>
            </table>
            <?php
            return ob_get_clean();
        }

        public function get_woocommerce_shipping_classes() {
            $this->easycommerce_shipping_classes = get_terms(array(
                'taxonomy' => 'product_shipping_class',
                'hide_empty' => false
            ));
        }

        /**
         * Called by WooComemrce to process adding and editing the selected methods
         * 
         * @since 1.0.0
         */
        public function process_admin_options() {
            if (!current_user_can('manage_woocommerce')) {
                easycommerce_core()->notice_insufficent_permissions();
                return;
            }

            $woo_field_name = 'woocommerce_' . $this->id . '_';
            $action = isset($_POST[$woo_field_name . 'shipping_method_action']) && wp_verify_nonce($_POST['easycommerce-shipping-nonce'], 'easycommerce-shipping-nonce') ? sanitize_text_field($_POST[$woo_field_name . 'shipping_method_action']) : false;
            $method_id = null;
            

            if ($action == 'new' || $action == 'edit') {
                $method = array();
                $table_rates = array();

                if (isset($_POST['rate'])) {
                    foreach($_POST['rate'] as $submitted_rate) :
                        $sanitized_rate = array(
                            'condition' => sanitize_text_field($submitted_rate['condition']),
                            'min' => isset($submitted_rate['min']) && !empty($submitted_rate['min']) ? sanitize_text_field($submitted_rate['min']) : "0",
                            'max' => sanitize_text_field($submitted_rate['max']),
                            'shipping_class' => sanitize_text_field($submitted_rate['shipping_class']),
                            'shipping_fee' => isset($submitted_rate['shipping_fee']) && !empty($submitted_rate['shipping_fee']) ? sanitize_text_field($submitted_rate['shipping_fee']) : "0",
                            //
                        );
        
                        $table_rates[] = $sanitized_rate;
                    endforeach;
                }
            
                $method['method_enabled'] = isset($_POST[$woo_field_name . 'method_enabled']) ? sanitize_text_field($_POST[$woo_field_name . 'method_enabled']) : "0";
                $method['method_enabled'] = intval($method['method_enabled']) ? "yes" : "no";
                $method['method_title'] =  sanitize_text_field($_POST[$woo_field_name . 'method_title']);
                $method['method_table_rates'] = $table_rates;
                $method['method_handle_fee'] = isset($_POST[$woo_field_name . 'method_handle_fee']) ? sanitize_text_field($_POST[$woo_field_name . 'method_handle_fee']) : '';
                $method['method_tax_status'] = sanitize_text_field($_POST[$woo_field_name . 'method_tax_status']);

                if ($action == 'new') {
                    $method['method_id'] = get_option($this->id . '_next_id', 0);
                    $method['method_id_for_shipping'] = $this->id . '_' . $this->instance_id . '_' .  $method['method_id'];   
                } else {
                    $method['method_id'] =sanitize_text_field($_POST[$woo_field_name . 'shipping_method_id']);
                    $method['method_id_for_shipping'] = sanitize_text_field($_POST[$woo_field_name . 'method_id_for_shipping']);
                }

                $get_shipping_methods = get_option($this->easycommerce_shipping_key, array());
                $get_shipping_methods[$method['method_id']] = $method;

                update_option($this->id . '_next_id', intval($method['method_id'])+1);
                update_option($this->easycommerce_shipping_key, $get_shipping_methods);

                $method_id = $method['method_id'];
            }

            $redirect = add_query_arg(
                array(
                    'action' => 'edit',
                    'method_id' => $method_id,
                    'easycommerce-shipping-nonce' => wp_create_nonce('easycommerce-shipping-nonce')
                )
            );

            wp_safe_redirect($redirect);
        }
    }
}

function easycommerce_shipping_methods($methods) {
    $methods['easycommerce_shipping_method_table_rate'] = 'EasyCommerce\Shipping\EasyCommerce_Shipping_Method';

    return $methods;
}

function enqueue_admin_scripts() {
    wp_enqueue_script('easycommerce-shipping-js-admin', plugin_dir_url( __FILE__ ) . 'inc/admin/assets/easycommerce-shipping-admin.js', array('jquery'), '1.0', true);
}

/**
         * Add the sub menu for the shipping plugin
         * 
         * @since 1.0.0
         */
        function add_admin_menu() {
            add_submenu_page(
                easycommerce_core()::SLUG,
                'Shipping',
                'Shipping',
                'manage_options',
                'abc',
                'EasyCommerce\Shipping\callback_admin_menu'
            );
        }

        function callback_admin_menu() {
            wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=shipping'));
        }

add_action('woocommerce_shipping_init', 'EasyCommerce\Shipping\easycommerce_shipping_init');
add_filter('woocommerce_shipping_methods', 'EasyCommerce\Shipping\easycommerce_shipping_methods');

//
if (is_admin()) :
    add_action('admin_enqueue_scripts', 'EasyCommerce\Shipping\enqueue_admin_scripts');
    add_action('admin_menu', 'EasyCommerce\Shipping\add_admin_menu');
endif;
?>