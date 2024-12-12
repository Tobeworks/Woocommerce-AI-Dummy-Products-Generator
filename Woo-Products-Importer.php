<?php

/**
 * Plugin Name: WooCommerce Demo Products Importer
 * Description: Imports AI-generated sample products into WooCommerce using OpenAI
 * Version: 1.2
 * Author: Tobias Lorsbach
 * Text Domain: woo-demo-products
 */


defined('ABSPATH') || exit;

class WC_Demo_Products_Importer
{
    private $options;
    private $option_name = 'wc_demo_products_settings';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'check_woocommerce'));
        add_action('admin_init', array($this, 'register_settings'));
        $this->options = get_option($this->option_name);
    }

    public function register_settings()
    {
        register_setting($this->option_name, $this->option_name);
    }

    public function check_woocommerce()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                echo '<div class="error"><p>' .
                    esc_html__('WooCommerce Demo Products Importer requires WooCommerce to be installed and active.', 'woo-demo-products') .
                    '</p></div>';
            });
        }
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Import Demo Products', 'woo-demo-products'),
            __('Import Demo Products', 'woo-demo-products'),
            'manage_woocommerce',
            'wc-demo-products',
            array($this, 'render_admin_page')
        );
    }

    private function get_product_categories()
    {
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

    public function render_admin_page()
    {
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
                            <label for="generation_category"><?php echo esc_html__('Generate Products Type', 'woo-demo-products'); ?></label>
                        </th>
                        <td>
                            <select name="generation_category" id="generation_category">
                                <?php
                                foreach ($this->get_product_categories() as $slug => $name) {
                                    printf(
                                        '<option value="%s">%s</option>',
                                        esc_attr($slug),
                                        esc_html($name)
                                    );
                                }
                                ?>
                            </select>
                            <p class="description">
                                <?php echo esc_html__('Select the type of products to generate', 'woo-demo-products'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="product_category"><?php echo esc_html__('Save Products in Category', 'woo-demo-products'); ?></label>
                        </th>
                        <td>
                            <select name="product_category" id="product_category">
                                <?php
                                $product_cats = get_terms(array(
                                    'taxonomy' => 'product_cat',
                                    'hide_empty' => false,
                                ));

                                if (!empty($product_cats) && !is_wp_error($product_cats)) {
                                    foreach ($product_cats as $cat) {
                                        printf(
                                            '<option value="%d">%s</option>',
                                            $cat->term_id,
                                            esc_html($cat->name)
                                        );
                                    }
                                }
                                ?>
                            </select>
                            <p class="description">
                                <?php echo esc_html__('Select the WooCommerce category where products will be saved', 'woo-demo-products'); ?>
                            </p>
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
            $count = isset($_POST['product_count']) ? intval($_POST['product_count']) : 1;
            $category_id = isset($_POST['product_category']) ? intval($_POST['product_category']) : 0;
            $generation_category = isset($_POST['generation_category']) ? sanitize_text_field($_POST['generation_category']) : '';

            $this->generate_and_import_products($count, $category_id, $generation_category);
        }
    }

    private function call_openai_api($count, $category_name, $language = 'en')
    {
        try {
            if (empty($this->options['openai_api_key'])) {
                throw new Exception('OpenAI API key is missing. Please enter your API key in the settings.');
            }

            // Validate inputs
            if (!is_numeric($count) || $count < 1 || $count > 25) {
                throw new Exception('Invalid product count. Please select between 1 and 25 products.');
            }

            // Prepare messages for API
            $system_prompt = "You are a product data generator for an ecommerce store. You must respond with valid JSON only.";

            $user_prompt = sprintf(
                'Generate %d realistic products for the category "%s". Return JSON in this exact format:
            {
                "products": [
                    {
                        "name": "Product Name",
                        "sku": "UNIQUE-SKU-123",
                        "price": "29.99",
                        "sale_price": "24.99",
                        "stock_quantity": 100,
                        "stock_status": "instock",
                        "weight": "1.5",
                        "dimensions": {
                            "length": "10",
                            "width": "5",
                            "height": "2"
                        },
                        "short_description": "Brief product description",
                        "long_description": "Detailed product description",
                        "features": ["feature1", "feature2"],
                        "tags": ["tag1", "tag2"]
                    }
                ]
            }',
                $count,
                $category_name
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
                    sprintf(
                        'Model refused to generate: %s',
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
            if (
                empty($body['choices']) ||
                !isset($body['choices'][0]['message']['content'])
            ) {
                throw new Exception('Invalid response structure from OpenAI API.');
            }

            // Parse the JSON response
            $content = $body['choices'][0]['message']['content'];
            $products_data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(
                    sprintf(
                        'JSON parsing error: %s. Content received: %s',
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




    private function generate_product_image($product_name, $product_description, $category)
    {
        try {
            if (empty($this->options['openai_api_key'])) {
                throw new Exception('OpenAI API key is missing.');
            }

            // Create prompt for DALL-E
            $prompt = sprintf(
                'A professional product photo of %s. %s. Style: Professional e-commerce white background photography, high resolution, product-focused.',
                $product_name,
                $product_description
            );

            // Call DALL-E API
            $response = wp_remote_post('https://api.openai.com/v1/images/generations', array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->options['openai_api_key'],
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'quality' => 'hd',
                    'response_format' => 'url'
                ))
            ));

            if (is_wp_error($response)) {
                throw new Exception('Failed to generate image: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            if ($response_code !== 200) {
                $error_message = isset($response_body['error']['message']) ? $response_body['error']['message'] : 'Unknown error';
                throw new Exception('DALL-E API Error: ' . $error_message);
            }

            if (empty($response_body['data'][0]['url'])) {
                throw new Exception('No image URL in response');
            }

            return $response_body['data'][0]['url'];
        } catch (Exception $e) {
            error_log('Image generation error: ' . $e->getMessage());
            return false;
        }
    }

    private function download_and_attach_image($image_url, $product_id, $product_name)
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        try {
            // Download image
            $tmp = download_url($image_url);
            if (is_wp_error($tmp)) {
                throw new Exception('Failed to download image: ' . $tmp->get_error_message());
            }

            // Prepare file array
            $file_array = array(
                'name' => sanitize_file_name($product_name . '-' . uniqid() . '.jpg'),
                'tmp_name' => $tmp
            );

            // Add image to media library and attach to product
            $image_id = media_handle_sideload($file_array, $product_id);

            if (is_wp_error($image_id)) {
                @unlink($tmp); // Clean up
                throw new Exception('Failed to add image to library: ' . $image_id->get_error_message());
            }

            // Set as product thumbnail
            set_post_thumbnail($product_id, $image_id);

            return true;
        } catch (Exception $e) {
            error_log('Image attachment error: ' . $e->getMessage());
            return false;
        }
    }

    // Modify the generate_and_import_products method to use AI images
    private function generate_and_import_products($count, $category_id, $generation_category, $language = 'en')
    {
        try {
            // Get save category for verification
            $category = get_term_by('id', $category_id, 'product_cat');
            if (!$category) {
                throw new Exception('Category not found with ID: ' . $category_id);
            }

            // Get generation category name
            $generation_categories = $this->get_product_categories();
            if (!isset($generation_categories[$generation_category])) {
                throw new Exception('Invalid generation category selected.');
            }

            // Call OpenAI API with generation category name
            $products_data = $this->call_openai_api($count, $generation_categories[$generation_category], $language);

            if (!$products_data) {
                return;
            }


            $imported_count = 0;
            $image_success_count = 0;

            foreach ($products_data as $product_data) {
                // Create product
                $product = new WC_Product_Simple();

                // Set basic product data
                $product->set_name($product_data['name']);
                $product->set_regular_price($product_data['price']);
                $product->set_description($product_data['long_description']);
                $product->set_short_description($product_data['short_description']);

                // Set the category
                $product->set_category_ids(array($category_id));

                // Save product
                $product_id = $product->save();

                // Generate and attach AI image
                $image_url = $this->generate_product_image(
                    $product_data['name'],
                    $product_data['short_description'],
                    $category->name
                );

                if (
                    $image_url && $this->download_and_attach_image(
                        $image_url,
                        $product_id,
                        $product_data['name']
                    )
                ) {
                    $image_success_count++;
                }

                $imported_count++;
            }

            echo '<div class="notice notice-success"><p>' .
                sprintf(
                    esc_html__('Successfully imported %d products with %d AI-generated images.', 'woo-demo-products'),
                    $imported_count,
                    $image_success_count
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

    private function set_product_images($product_id, $image_urls)
    {
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
