<?php
class WC_Demo_Products_Settings_Tab
{
    private $option_name;
    private $options;

    public function __construct($option_name)
    {
        $this->option_name = $option_name;
        $this->options = get_option($option_name);
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings()
    {
        register_setting($this->option_name, $this->option_name);
        register_setting('wc_demo_products_categories', 'wc_demo_products_categories');
    }

    public function render()
    {
        // Handle form submissions
        if (isset($_POST['submit'])) {
            $this->handle_form_submission();
        }

        $saved_key = isset($this->options['openai_api_key']) ? $this->options['openai_api_key'] : '';
?>
        <div class="wrap">
            <form method="post" action="">
                <?php wp_nonce_field('wc_demo_products_settings', 'wc_demo_products_nonce'); ?>

                <!-- OpenAI API Settings -->
                <h2><?php echo esc_html__('OpenAI API Configuration', 'woo-demo-products'); ?></h2>
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

                <!-- Product Categories Configuration -->
                <h2><?php echo esc_html__('Product Categories', 'woo-demo-products'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Manage Categories', 'woo-demo-products'); ?></th>
                        <td>
                            <div id="product-categories">
                                <?php $this->render_category_list(); ?>
                            </div>
                            <button type="button" class="button add-category">
                                <?php echo esc_html__('Add Category', 'woo-demo-products'); ?>
                            </button>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function handle_form_submission()
    {
        if (!check_admin_referer('wc_demo_products_settings', 'wc_demo_products_nonce')) {
            return;
        }

        // Save API Key
        if (isset($_POST['openai_api_key'])) {
            $options = get_option($this->option_name, array());
            $options['openai_api_key'] = sanitize_text_field($_POST['openai_api_key']);
            update_option($this->option_name, $options);
        }

        // Save Categories
        if (isset($_POST['categories'])) {
            $categories = array();
            foreach ($_POST['categories'] as $category) {
                $slug = sanitize_title($category['slug']);
                if (!empty($slug)) {
                    $categories[$slug] = sanitize_text_field($category['name']);
                }
            }
            update_option('wc_demo_products_categories', $categories);
        }
    }

    private function render_category_list()
    {
        $categories = get_option('wc_demo_products_categories', array(
            'electronics' => 'Electronics',
            'clothing' => 'Clothing',
            'books' => 'Books',
        ));

        foreach ($categories as $slug => $name) {
        ?>
            <div class="category-item">

                <input type="text"
                    name="categories[<?php echo esc_attr($slug); ?>][name]"
                    value="<?php echo esc_attr($name); ?>"
                    placeholder="Category Name">
                <input type="text"
                    name="categories[<?php echo esc_attr($slug); ?>][slug]"
                    value="<?php echo esc_attr($slug); ?>"
                    placeholder="category-slug" readonly>
                <button type="button" class="button remove-category">Remove</button>
            </div>
<?php
        }
    }
}
