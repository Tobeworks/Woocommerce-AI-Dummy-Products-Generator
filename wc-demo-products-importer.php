<?php

/**
 * Plugin Name: WooCommerce Demo Products Importer
 * Description: Imports AI-generated sample products into WooCommerce using OpenAI
 * Version: 2.1.1
 * Author: Tobias Lorsbach
 * Text Domain: woo-ai-dummy-products
 */

defined('ABSPATH') || exit;

// Load required files
require_once plugin_dir_path(__FILE__) . 'includes/class-settings-tab.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-import-tab.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-openai-handler.php';

class WC_Demo_Products_Importer
{
    private $settings_tab;
    private $import_tab;
    private $option_name = 'wc_demo_products_settings';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'check_woocommerce'));

        $this->settings_tab = new WC_Demo_Products_Settings_Tab($this->option_name);
        $this->import_tab = new WC_Demo_Products_Import_Tab($this->option_name);
    }

    public function check_woocommerce()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                echo '<div class="error"><p>' .
                    esc_html__('WooCommerce Demo Products Importer requires WooCommerce to be installed and active.', 'woo-ai-dummy-products') .
                    '</p></div>';
            });
        }
    }

    public function add_admin_menu()
    {
        $page = add_submenu_page(
            'woocommerce',
            __('Import Dummy Products', 'woo-ai-dummy-products'),
            __('Import Dummy Products', 'woo-ai-dummy-products'),
            'manage_woocommerce',
            'wc-demo-products',
            array($this, 'render_admin_page')
        );

        add_action('load-' . $page, array($this, 'admin_scripts'));
    }

    public function admin_scripts()
    {
        wp_enqueue_style(
            'wc-demo-products-admin',
            plugins_url('assets/css/admin.css', __FILE__)
        );
        wp_enqueue_script(
            'wc-demo-products-admin',
            plugins_url('assets/js/admin.js', __FILE__),
            array('jquery'),
            '1.0.0',
            true
        );
    }

    public function render_admin_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'import';
?>
        <div class="wrap">
            <h1><?php echo esc_html__('Import Dummy Products', 'woo-ai-dummy-products'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=wc-demo-products&tab=import"
                    class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Import Products', 'woo-ai-dummy-products'); ?>
                </a>
                <a href="?page=wc-demo-products&tab=settings"
                    class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'woo-ai-dummy-products'); ?>
                </a>
            </nav>

            <?php
            // Check for API key if on import tab
            if ($active_tab === 'import') {
                $options = get_option($this->option_name);
                if (empty($options['openai_api_key'])) {
                    echo '<div class="notice notice-warning"><p>' .
                        sprintf(
                            __('Please add your OpenAI API key in the %1$sSettings tab%2$s before importing products.', 'woo-ai-dummy-products'),
                            '<a href="?page=wc-demo-products&tab=settings">',
                            '</a>'
                        ) .
                        '</p></div>';
                }
                $this->import_tab->render();
            } else {
                $this->settings_tab->render();
            }
            ?>
        </div>
<?php
    }
}

// Initialize the plugin
new WC_Demo_Products_Importer();
