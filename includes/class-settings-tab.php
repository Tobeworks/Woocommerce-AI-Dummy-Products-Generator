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
    }

    public function render()
    {
        // Save API Key if posted
        if (isset($_POST['openai_api_key'])) {
            $options = get_option($this->option_name, array());
            $options['openai_api_key'] = sanitize_text_field($_POST['openai_api_key']);
            update_option($this->option_name, $options);
            $this->options = $options;
        }

        $saved_key = isset($this->options['openai_api_key']) ? $this->options['openai_api_key'] : '';
?>
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
<?php
    }
}
