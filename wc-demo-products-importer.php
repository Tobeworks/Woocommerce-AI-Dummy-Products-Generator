<?php

/**
 * Plugin Name: WooCommerce Demo Products Importer
 * Description: Imports AI-generated sample products into WooCommerce using OpenAI
 * Version: 2.1.2
 * Author: Tobias Lorsbach
 * Text Domain: woo-ai-dummy-products
 */

defined('ABSPATH') || exit;

/**
 * Main plugin class
 */
class WC_Demo_Products_Importer
{
    /**
     * Settings tab instance
     * @var WC_Demo_Products_Settings_Tab
     */
    private $settings_tab;

    /**
     * Import tab instance
     * @var WC_Demo_Products_Import_Tab
     */
    private $import_tab;

    /**
     * Option name in WordPress options table
     * @var string
     */
    private $option_name = 'wc_demo_products_settings';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Load required files
        $this->load_dependencies();

        // Initialize tabs
        $this->settings_tab = new WC_Demo_Products_Settings_Tab($this->option_name);
        $this->import_tab = new WC_Demo_Products_Import_Tab($this->option_name);

        // Add hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'check_woocommerce'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    /**
     * Load required files
     */
    private function load_dependencies()
    {
        $files = array(
            'includes/class-settings-tab.php',
            'includes/class-import-tab.php',
            'includes/class-openai-handler.php'
        );

        foreach ($files as $file) {
            $path = plugin_dir_path(__FILE__) . $file;
            if (file_exists($path)) {
                require_once $path;
            } else {
                add_action('admin_notices', function () use ($file) {
                    printf(
                        '<div class="error"><p>%s</p></div>',
                        sprintf(
                            esc_html__('WooCommerce Demo Products Importer: Required file %s not found.', 'woo-ai-dummy-products'),
                            esc_html($file)
                        )
                    );
                });
            }
        }
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'woo-ai-dummy-products',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                printf(
                    '<div class="error"><p>%s</p></div>',
                    esc_html__('WooCommerce Demo Products Importer requires WooCommerce to be installed and active.', 'woo-ai-dummy-products')
                );
            });
        }
    }

    /**
     * Add admin menu item
     */
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

        if ($page) {
            add_action('load-' . $page, array($this, 'admin_scripts'));
        }
    }

    public function admin_scripts()
    {
        // Admin CSS
        wp_enqueue_style(
            'wc-demo-products-admin',
            plugins_url('assets/css/admin.css', __FILE__),
            array(),
            '2.1.2'
        );

        // Admin JavaScript
        wp_enqueue_script(
            'wc-demo-products-admin',
            plugins_url('assets/js/admin.js', __FILE__),
            array('jquery'),
            '2.1.2',
            true
        );

        // Settings page specific assets
        if (isset($_GET['tab']) && $_GET['tab'] === 'settings') {
            wp_enqueue_style(
                'wc-demo-products-settings',
                plugins_url('assets/css/settings.css', __FILE__),
                array('wc-demo-products-admin'),
                '2.1.2'
            );

            wp_enqueue_script(
                'wc-demo-products-settings',
                plugins_url('assets/js/settings.js', __FILE__),
                array('jquery'),
                '2.1.2',
                true
            );
        }
    }


    /**
     * Render admin page
     */
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
            if ($active_tab === 'import') {
                $this->maybe_show_api_key_notice();
                $this->import_tab->render();
            } else {
                $this->settings_tab->render();
            }
            ?>
        </div>
<?php
    }

    /**
     * Show API key notice if needed
     */
    private function maybe_show_api_key_notice()
    {
        $options = get_option($this->option_name);
        if (empty($options['openai_api_key'])) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    __('Please add your OpenAI API key in the %1$sSettings tab%2$s before importing products.', 'woo-ai-dummy-products'),
                    '<a href="?page=wc-demo-products&tab=settings">',
                    '</a>'
                )
            );
        }
    }
}

// Initialize the plugin
new WC_Demo_Products_Importer();
