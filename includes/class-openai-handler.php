<?php
class WC_Demo_Products_OpenAI_Handler
{
    private $options;
    private $generation_categories;

    public function __construct($options)
    {
        $this->options = $options;
        $this->generation_categories =  $this->get_product_categories();
    }

    private function get_product_categories()
    {
        return include(plugin_dir_path(dirname(__FILE__)) . 'includes/config/categories.php');
    }

    public function generate_and_import_products($count, $category_id, $generation_category, $language = 'en')
    {
        try {
            // Validate API key
            if (empty($this->options['openai_api_key'])) {
                throw new Exception('OpenAI API key is missing. Please enter your API key in the settings.');
            }

            // Get category name
            $category = get_term_by('id', $category_id, 'product_cat');
            if (!$category) {
                throw new Exception('Category not found with ID: ' . $category_id);
            }

            // Validate generation category
            if (!isset($this->generation_categories[$generation_category])) {
                throw new Exception('Invalid generation category selected.');
            }

            // Generate product data
            $products_data = $this->call_openai_api(
                $count,
                $this->generation_categories[$generation_category],
                $language
            );

            if (!$products_data) {
                throw new Exception('Failed to generate product data.');
            }

            $imported_count = 0;
            $image_success_count = 0;

            // Import each product
            foreach ($products_data as $product_data) {
                // Create product
                $product = new WC_Product_Simple();

                // Set basic product data
                $product->set_name($product_data['name']);
                $product->set_regular_price($product_data['price']);
                $product->set_sale_price($product_data['sale_price'] ?? '');
                $product->set_description($product_data['long_description']);
                $product->set_short_description($product_data['short_description']);
                $product->set_sku($product_data['sku'] ?? '');

                // Set inventory data if provided
                if (isset($product_data['stock_quantity'])) {
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($product_data['stock_quantity']);
                    $product->set_stock_status($product_data['stock_status'] ?? 'instock');
                }

                // Set dimensions if provided
                if (isset($product_data['dimensions'])) {
                    $product->set_length($product_data['dimensions']['length'] ?? '');
                    $product->set_width($product_data['dimensions']['width'] ?? '');
                    $product->set_height($product_data['dimensions']['height'] ?? '');
                }

                if (isset($product_data['weight'])) {
                    $product->set_weight($product_data['weight']);
                }

                // Set the category
                $product->set_category_ids(array($category_id));

                // Set tags if provided
                if (!empty($product_data['tags'])) {
                    wp_set_object_terms($product->get_id(), $product_data['tags'], 'product_tag');
                }

                // Save product
                $product_id = $product->save();

                // Generate and attach AI image
                $image_url = $this->generate_product_image(
                    $product_data['name'],
                    $product_data['short_description'],
                    $category->name
                );

                if ($image_url && $this->download_and_attach_image($image_url, $product_id, $product_data['name'])) {
                    $image_success_count++;
                }

                $imported_count++;
            }

            return array(
                'success' => true,
                'imported' => $imported_count,
                'images' => $image_success_count,
                'message' => ''
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'imported' => 0,
                'images' => 0,
                'message' => $e->getMessage()
            );
        }
    }

    private function call_openai_api($count, $category_name, $language = 'en')
    {
        try {
            // Validate inputs
            if (!is_numeric($count) || $count < 1 || $count > 25) {
                throw new Exception('Invalid product count. Please select between 1 and 25 products.');
            }

            // Prepare messages for API
            $system_prompt = sprintf(
                "You are a product data generator for an ecommerce store. Generate content in %s language. You must respond with valid JSON only.",
                strtoupper($language)
            );

            $user_prompt = sprintf(
                'Generate %d realistic products for the category "%s". All text should be in %s language. Include realistic prices, descriptions, and features. Return JSON in this format:
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
                $category_name,
                strtoupper($language)
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
                ))
            ));

            if (is_wp_error($response)) {
                throw new Exception('WordPress HTTP Error: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $body = json_decode($response_body, true);

            if ($response_code !== 200) {
                $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
                throw new Exception(sprintf('OpenAI API Error (HTTP %s): %s', $response_code, $error_message));
            }

            if (empty($body['choices']) || !isset($body['choices'][0]['message']['content'])) {
                throw new Exception('Invalid response structure from OpenAI API.');
            }

            $content = $body['choices'][0]['message']['content'];
            $products_data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(sprintf('JSON parsing error: %s', json_last_error_msg()));
            }

            if (!isset($products_data['products']) || !is_array($products_data['products'])) {
                throw new Exception('Invalid product data format received from API.');
            }

            return $products_data['products'];
        } catch (Exception $e) {
            error_log(sprintf(
                '[WC Demo Products Importer] Error: %s, Category: %s, Count: %d, Language: %s',
                $e->getMessage(),
                $category_name,
                $count,
                $language
            ));
            return false;
        }
    }

    private function generate_product_image($product_name, $product_description, $category)
    {
        try {
            // Create prompt for DALL-E
            $prompt = sprintf(
                'A professional product photo of %s. %s. Style: Professional e-commerce white background photography, high resolution, product-focused.',
                $product_name,
                $product_description
            );

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
}
