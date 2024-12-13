<?php
class WC_Demo_Products_Import_Tab
{
    private $option_name;
    private $options;
    private $openai_handler;

    public function __construct($option_name)
    {
        $this->option_name = $option_name;
        $this->options = get_option($option_name);
        $this->openai_handler = new WC_Demo_Products_OpenAI_Handler($this->options);
    }

    private function get_product_categories()
    {
        return include(plugin_dir_path(dirname(__FILE__)) . 'includes/config/categories.php');
    }

    private function get_languages()
    {
        return array(
            'en' => 'English',
            'de' => 'Deutsch',
            'es' => 'Español',
            'fr' => 'Français',
            'it' => 'Italiano',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'pt' => 'Português',
            'ru' => 'Русский',
            'zh' => '中文'
        );
    }

    public function render()
    {
        if (empty($this->options['openai_api_key'])) {
            echo '<div class="notice notice-warning"><p>' .
                esc_html__('Please enter your OpenAI API key in the Settings tab before importing products.', 'woo-ai-dummy-products') .
                '</p></div>';
            return;
        }
?>
        <form method="post" action="">
            <?php wp_nonce_field('import_demo_products', 'demo_products_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="product_count"><?php echo esc_html__('Number of Products', 'woo-ai-dummy-products'); ?></label>
                    </th>
                    <td>
                        <select name="product_count" id="product_count">
                            <?php for ($i = 1; $i <= 25; $i++) : ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('Select how many products to generate', 'woo-ai-dummy-products'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="generation_category"><?php echo esc_html__('Generate Products Type', 'woo-ai-dummy-products'); ?></label>
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
                            <?php echo esc_html__('Select the type of products to generate', 'woo-ai-dummy-products'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="content_language"><?php echo esc_html__('Content Language', 'woo-ai-dummy-products'); ?></label>
                    </th>
                    <td>
                        <select name="content_language" id="content_language">
                            <?php
                            foreach ($this->get_languages() as $code => $name) {
                                printf(
                                    '<option value="%s">%s</option>',
                                    esc_attr($code),
                                    esc_html($name)
                                );
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('Select the language for product descriptions', 'woo-ai-dummy-products'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="product_category"><?php echo esc_html__('Save Products in Category', 'woo-ai-dummy-products'); ?></label>
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
                            <?php echo esc_html__('Select the WooCommerce category where products will be saved', 'woo-ai-dummy-products'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="wc-demo-products-actions">
                <button type="submit" name="import_demo_products" class="button button-primary">
                    <?php echo esc_html__('Generate and Import Products', 'woo-ai-dummy-products'); ?>
                </button>

                <span class="spinner"></span>

                <div class="progress-bar" style="display: none;">
                    <div class="progress"></div>
                </div>
            </div>
        </form>
<?php

        if (isset($_POST['import_demo_products']) && check_admin_referer('import_demo_products', 'demo_products_nonce')) {
            $this->handle_import_submission();
        }
    }

    private function handle_import_submission()
    {
        try {
            // Validate inputs
            $count = $this->validate_product_count();
            $category_id = $this->validate_category_id();
            $generation_category = $this->validate_generation_category();
            $language = $this->validate_language();

            // Start import process
            $result = $this->openai_handler->generate_and_import_products(
                $count,
                $category_id,
                $generation_category,
                $language
            );

            if ($result['success']) {
                $this->show_success_notice($result['imported'], $result['images']);
            } else {
                $this->show_error_notice($result['message']);
            }
        } catch (Exception $e) {
            $this->show_error_notice($e->getMessage());
        }
    }

    private function validate_product_count()
    {
        $count = isset($_POST['product_count']) ? intval($_POST['product_count']) : 0;
        if ($count < 1 || $count > 25) {
            throw new Exception(__('Invalid number of products selected.', 'woo-ai-dummy-products'));
        }
        return $count;
    }

    private function validate_category_id()
    {
        $category_id = isset($_POST['product_category']) ? intval($_POST['product_category']) : 0;
        if (!term_exists($category_id, 'product_cat')) {
            throw new Exception(__('Selected category does not exist.', 'woo-ai-dummy-products'));
        }
        return $category_id;
    }

    private function validate_generation_category()
    {
        $category = isset($_POST['generation_category']) ? sanitize_text_field($_POST['generation_category']) : '';
        if (!array_key_exists($category, $this->get_product_categories())) {
            throw new Exception(__('Invalid generation category selected.', 'woo-ai-dummy-products'));
        }
        return $category;
    }

    private function validate_language()
    {
        $language = isset($_POST['content_language']) ? sanitize_text_field($_POST['content_language']) : '';
        if (!array_key_exists($language, $this->get_languages())) {
            throw new Exception(__('Invalid language selected.', 'woo-ai-dummy-products'));
        }
        return $language;
    }

    private function show_success_notice($imported_count, $image_count)
    {
        echo '<div class="notice notice-success"><p>' .
            sprintf(
                esc_html__('Successfully imported %d products with %d AI-generated images.', 'woo-ai-dummy-products'),
                $imported_count,
                $image_count
            ) .
            '</p></div>';
    }

    private function show_error_notice($message)
    {
        echo '<div class="error notice"><p>' .
            sprintf(
                esc_html__('Error during product import: %s', 'woo-ai-dummy-products'),
                esc_html($message)
            ) .
            '</p></div>';
    }
}
