<?php
/**
 * Plugin Name: WooCommerce Demo Products Importer
 * Description: Imports AI-generated sample products into WooCommerce using OpenAI
 * Version: 1.2
 * Author: Tobias Lorsbach
 * Text Domain: woo-demo-products
 */

// Prevent direct file access
defined('ABSPATH') || exit;

class WC_Demo_Products_Importer {
    private $options;
    private $option_name = 'wc_demo_products_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'check_woocommerce'));
        add_action('admin_init', array($this, 'register_settings'));
        $this->options = get_option($this->option_name);
    }

    public function register_settings() {
        register_setting($this->option_name, $this->option_name);
    }

    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . 
                     esc_html__('WooCommerce Demo Products Importer requires WooCommerce to be installed and active.', 'woo-demo-products') . 
                     '</p></div>';
            });
        }
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Import Demo Products', 'woo-demo-products'),
            __('Import Demo Products', 'woo-demo-products'),
            'manage_woocommerce',
            'wc-demo-products',
            array($this, 'render_admin_page')
        );
    }

    private function get_product_categories() {
        return array(
            'electronics' => 'Electronics',
            'clothing' => 'Clothing',
            'books' => 'Books',
            'home-garden' => 'Home & Garden',
            'sports' => 'Sports & Outdoors',
            'beauty' => 'Beauty & Personal Care',
            'toys' => 'Toys & Games',
            'food' => 'Food & Beverages',
            'jewelry' => 'Jewelry',
            'art' => 'Art & Crafts'
        );
    }

    public function render_admin_page() {
        // Save API Key if posted
        if (isset($_POST['openai_api_key'])) {
            $options = get_option($this->option_name, array());
            $options['openai_api_key'] = sanitize_text_field($_POST['openai_api_key']);
            update_option($this->option_name, $options);
        }

        $saved_key = isset($this->options['openai_api_key']) ? $this->options['openai_api_key'] : '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Import Demo Products', 'woo-demo-products'); ?></h1>
            
            <!-- API Key Settings -->
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="openai_api_key"><?php echo esc_html__('OpenAI API Key', 'woo-demo-products'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="openai_api_key" 
                                   name="openai_api_key" 
                                   value="<?php echo esc_attr($saved_key); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php echo esc_html__('Enter your OpenAI API key to enable AI-generated product data.', 'woo-demo-products'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save API Key', 'woo-demo-products')); ?>
            </form>

            <!-- Import Products Form -->
            <form method="post" action="">
                <?php wp_nonce_field('import_demo_products', 'demo_products_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="product_count"><?php echo esc_html__('Number of Products', 'woo-demo-products'); ?></label>
                        </th>
                        <td>
                            <select name="product_count" id="product_count">
                                <?php for ($i = 1; $i <= 25; $i++) : ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="product_category"><?php echo esc_html__('Product Category', 'woo-demo-products'); ?></label>
                        </th>
                        <td>
                            <select name="product_category" id="product_category">
                                <?php foreach ($this->get_product_categories() as $slug => $name) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="import_demo_products" class="button button-primary">
                        <?php echo esc_html__('Generate and Import Products', 'woo-demo-products'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php

        if (isset($_POST['import_demo_products']) && check_admin_referer('import_demo_products', 'demo_products_nonce')) {
            $this->generate_and_import_products(
                intval($_POST['product_count']),
                sanitize_text_field($_POST['product_category'])
            );
        }
    }

   private function call_openai_api($count, $category) {
        try {
            if (empty($this->options['openai_api_key'])) {
                throw new Exception('OpenAI API key is missing. Please enter your API key in the settings.');
            }

            // Validate inputs
            if (!is_numeric($count) || $count < 1 || $count > 25) {
                throw new Exception('Invalid product count. Please select between 1 and 25 products.');
            }

            if (!array_key_exists($category, $this->get_product_categories())) {
                throw new Exception('Invalid category selected.');
            }

            // Prepare messages for API
            $system_prompt = "You are a product data generator for an ecommerce store. You must respond with valid JSON only.";
            
            $user_prompt = sprintf(
                'Generate %d realistic products for the category "%s". Return JSON in this exact format:
                {
                    "products": [
                        {
                            "name": "Product Name",
                            "price": "29.99",
                            "short_description": "Brief product description",
                            "long_description": "Detailed product description",
                            "features": ["feature1", "feature2"],
                            "tags": ["tag1", "tag2"]
                        }
                    ]
                }',
                $count,
                $this->get_product_categories()[$category]
            );

            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->options['openai_api_key'],
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'model' => 'gpt-4-turbo-preview',
                    'messages' => array(
                        array('role' => 'system', 'content' => $system_prompt),
                        array('role' => 'user', 'content' => $user_prompt)
                    ),
                    'response_format' => array('type' => 'json_object'),
                    'temperature' => 0.7,
                    'max_tokens' => 2000
                )),
                'sslverify' => true,
                'httpversion' => '1.1'
            ));

            // Debug the request
            error_log('OpenAI Request Body: ' . wp_remote_retrieve_body($response));

            // Handle WordPress HTTP API errors
            if (is_wp_error($response)) {
                throw new Exception(
                    sprintf('WordPress HTTP Error: %s', $response->get_error_message())
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $body = json_decode($response_body, true);

            // Debug the response
            error_log('OpenAI Response: ' . $response_body);

            // Check for API errors
            if ($response_code !== 200) {
                $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
                throw new Exception(
                    sprintf('OpenAI API Error (HTTP %s): %s', $response_code, $error_message)
                );
            }

            // Check for model refusal
            if (isset($body['choices'][0]['message']['refusal'])) {
                throw new Exception(
                    sprintf('Model refused to generate: %s', 
                        $body['choices'][0]['message']['refusal']
                    )
                );
            }

            // Check finish reason
            if (isset($body['choices'][0]['finish_reason'])) {
                switch ($body['choices'][0]['finish_reason']) {
                    case 'length':
                        throw new Exception('Response exceeded maximum length. Try reducing the number of products.');
                    case 'content_filter':
                        throw new Exception('Content was filtered due to safety concerns.');
                }
            }

            // Validate response structure
            if (empty($body['choices']) || 
                !isset($body['choices'][0]['message']['content'])) {
                throw new Exception('Invalid response structure from OpenAI API.');
            }

            // Parse the JSON response
            $content = $body['choices'][0]['message']['content'];
            $products_data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(
                    sprintf('JSON parsing error: %s. Content received: %s', 
                        json_last_error_msg(),
                        substr($content, 0, 255)
                    )
                );
            }

            // Validate products data
            if (!isset($products_data['products']) || !is_array($products_data['products'])) {
                throw new Exception('Invalid product data format received from API.');
            }

            // Return only the products array
            return $products_data['products'];

        } catch (Exception $e) {
            // Log error for debugging
            error_log(sprintf(
                '[WC Demo Products Importer] Error: %s, Category: %s, Count: %d', 
                $e->getMessage(), 
                $category, 
                $count
            ));

            // Display error to user
            echo '<div class="error notice"><p>' . 
                 esc_html__('Error: ', 'woo-demo-products') . 
                 esc_html($e->getMessage()) . 
                 '</p></div>';
            return false;
        }
    }

    private function generate_and_import_products($count, $category) {
        // Call OpenAI API with error handling
        $products_data = $this->call_openai_api($count, $category);
        
        if (!$products_data) {
            return; // Error already displayed
        }

        $imported_count = 0;

        // Start product import
        try {
            foreach ($products_data as $product_data) {
                // Create product
                $product = new WC_Product_Simple();
                
                // Set product data
                $product->set_name($product_data['name']);
                $product->set_regular_price($product_data['price']);
                $product->set_description($product_data['long_description']);
                $product->set_short_description($product_data['short_description']);

                // Set category
                $term = get_term_by('slug', $category, 'product_cat');
                if (!$term) {
                    $term = wp_insert_term(
                        $this->get_product_categories()[$category],
                        'product_cat',
                        array('slug' => $category)
                    );
                    if (is_wp_error($term)) {
                        throw new Exception('Failed to create product category: ' . $term->get_error_message());
                    }
                    $product->set_category_ids(array($term['term_id']));
                } else {
                    $product->set_category_ids(array($term->term_id));
                }

                // Add features as product meta
                if (!empty($product_data['features'])) {
                    update_post_meta($product->get_id(), '_product_features', $product_data['features']);
                }

                // Set tags
                if (!empty($product_data['tags'])) {
                    $tag_result = wp_set_object_terms($product->get_id(), $product_data['tags'], 'product_tag');
                    if (is_wp_error($tag_result)) {
                        throw new Exception('Failed to set product tags: ' . $tag_result->get_error_message());
                    }
                }

                // Save product
                $product_id = $product->save();
                if (!$product_id) {
                    throw new Exception('Failed to save product: ' . $product_data['name']);
                }

                // Add placeholder image
                $this->set_product_images($product_id, array('https://via.placeholder.com/800x800'));

                $imported_count++;
            }

            echo '<div class="notice notice-success"><p>' . 
                 sprintf(
                     esc_html__('Successfully imported %d AI-generated products.', 'woo-demo-products'), 
                     $imported_count
                 ) . 
                 '</p></div>';

        } catch (Exception $e) {
            echo '<div class="error notice"><p>' . 
                 sprintf(
                     esc_html__('Error during product import: %s', 'woo-demo-products'),
                     esc_html($e->getMessage())
                 ) . 
                 '</p></div>';
        }
    }

    private function set_product_images($product_id, $image_urls) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        foreach ($image_urls as $url) {
            $tmp = download_url($url);
            if (!is_wp_error($tmp)) {
                $file_array = array(
                    'name' => basename($url),
                    'tmp_name' => $tmp
                );

                $image_id = media_handle_sideload($file_array, $product_id);
                if (!is_wp_error($image_id)) {
                    set_post_thumbnail($product_id, $image_id);
                }
            }
        }
    }
}

// Initialize the plugin
new WC_Demo_Products_Importer();