<?php

/**
 * Plugin Name: WeatherSmart Shop
 * Description: Weather-based product recommendations and pricing for WooCommerce
 * Version: 1.0.0
 * Author: Cventix Co.
 * Text Domain: cventix.com.au
 * Requires WooCommerce: 5.0
 */

if (!defined('ABSPATH')) exit;

class WeatherSmartShop
{
    private static $instance = null;
    private $api_key;
    private $cache_duration = 1800; // 30 minutes

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Initialize plugin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('widgets_init', array($this, 'register_widgets'));

        // Register shortcodes
        add_shortcode('weather_products', array($this, 'weather_products_shortcode'));
        add_shortcode('weather_price', array($this, 'weather_price_shortcode'));

        // Add custom product tab
        add_filter('woocommerce_product_data_tabs', array($this, 'add_weather_product_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_weather_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_weather_product_fields'));

        // Modify product prices based on weather
        add_filter('woocommerce_product_get_price', array($this, 'modify_price_by_weather'), 10, 2);

        // AJAX handlers
        add_action('wp_ajax_get_weather_data', array($this, 'ajax_get_weather_data'));
        add_action('wp_ajax_nopriv_get_weather_data', array($this, 'ajax_get_weather_data'));

        // Load scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_front_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        $this->api_key = get_option('weathersmart_api_key');
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserializing
    private function __wakeup() {}

    /**
     * Admin Menu Setup
     */
    public function add_admin_menu()
    {
        add_menu_page(
            'WeatherSmart Shop',
            'WeatherSmart',
            'manage_options',
            'weathersmart-settings',
            array($this, 'render_settings_page'),
            'dashicons-cloud'
        );
    }

    public function register_settings()
    {
        register_setting('weathersmart_options', 'weathersmart_api_key');
        register_setting('weathersmart_options', 'weathersmart_default_location');
        register_setting('weathersmart_options', 'weathersmart_price_adjust');
    }

    /**
     * Settings Page Render
     */
    public function render_settings_page()
    {
?>
        <div class="wrap">
            <h2>WeatherSmart Shop Settings</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('weathersmart_options');
                do_settings_sections('weathersmart_options');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">OpenWeatherMap API Key</th>
                        <td>
                            <input type="text" name="weathersmart_api_key"
                                value="<?php echo esc_attr(get_option('weathersmart_api_key')); ?>"
                                class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Location</th>
                        <td>
                            <input type="text" name="weathersmart_default_location"
                                value="<?php echo esc_attr(get_option('weathersmart_default_location')); ?>"
                                class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php
    }

    /**
     * Weather API Integration
     */
    private function get_weather_data($location = null)
    {
        if (!$location) {
            $location = get_option('weathersmart_default_location', 'London');
        }

        $cache_key = 'weather_data_' . sanitize_title($location);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        $api_url = sprintf(
            'https://api.openweathermap.org/data/2.5/weather?q=%s&appid=%s&units=metric',
            urlencode($location),
            $this->api_key
        );

        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        set_transient($cache_key, $data, $this->cache_duration);

        return $data;
    }

    /**
     * Product Weather Rules
     */
    public function add_weather_product_tab($tabs)
    {
        $tabs['weather'] = array(
            'label' => __('Weather Rules', 'weather-smart-shop'),
            'target' => 'weather_product_data',
            'class' => array('show_if_simple', 'show_if_variable'),
        );
        return $tabs;
    }

    public function add_weather_product_fields()
    {
        global $post;

        echo '<div id="weather_product_data" class="panel woocommerce_options_panel">';

        // Weather condition rules
        woocommerce_wp_select(array(
            'id' => '_weather_condition',
            'label' => __('Weather Condition', 'weather-smart-shop'),
            'options' => array(
                '' => __('Any', 'weather-smart-shop'),
                'rain' => __('Rain', 'weather-smart-shop'),
                'snow' => __('Snow', 'weather-smart-shop'),
                'clear' => __('Clear', 'weather-smart-shop'),
            )
        ));

        // Temperature range
        woocommerce_wp_text_input(array(
            'id' => '_weather_temp_min',
            'label' => __('Min Temperature (°C)', 'weather-smart-shop'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.1',
                'min' => '-50',
                'max' => '50'
            )
        ));

        woocommerce_wp_text_input(array(
            'id' => '_weather_temp_max',
            'label' => __('Max Temperature (°C)', 'weather-smart-shop'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.1',
                'min' => '-50',
                'max' => '50'
            )
        ));

        // Price adjustment
        woocommerce_wp_text_input(array(
            'id' => '_weather_price_adjust',
            'label' => __('Weather Price Adjustment (%)', 'weather-smart-shop'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.1',
                'min' => '-100',
                'max' => '100'
            )
        ));

        echo '</div>';
    }

    public function save_weather_product_fields($post_id)
    {
        $fields = array(
            '_weather_condition',
            '_weather_temp_min',
            '_weather_temp_max',
            '_weather_price_adjust'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Price Modification
     */
    public function modify_price_by_weather($price, $product)
    {
        $weather_data = $this->get_weather_data();
        if (!$weather_data) return $price;

        $weather_condition = get_post_meta($product->get_id(), '_weather_condition', true);
        $temp_min = get_post_meta($product->get_id(), '_weather_temp_min', true);
        $temp_max = get_post_meta($product->get_id(), '_weather_temp_max', true);
        $price_adjust = get_post_meta($product->get_id(), '_weather_price_adjust', true);

        if (empty($price_adjust)) return $price;

        $current_temp = $weather_data['main']['temp'];
        $current_condition = strtolower($weather_data['weather'][0]['main']);

        $apply_adjustment = true;

        // Check temperature range
        if (!empty($temp_min) && $current_temp < $temp_min) {
            $apply_adjustment = false;
        }
        if (!empty($temp_max) && $current_temp > $temp_max) {
            $apply_adjustment = false;
        }

        // Check weather condition
        if (!empty($weather_condition) && $current_condition != $weather_condition) {
            $apply_adjustment = false;
        }

        if ($apply_adjustment) {
            $adjustment = 1 + ($price_adjust / 100);
            return $price * $adjustment;
        }

        return $price;
    }

    /**
     * Shortcodes
     */
    public function weather_products_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'condition' => '',
            'limit' => 4
        ), $atts);

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $atts['limit'],
            'meta_query' => array(
                array(
                    'key' => '_weather_condition',
                    'value' => $atts['condition'],
                    'compare' => '='
                )
            )
        );

        $products = new WP_Query($args);

        ob_start();

        if ($products->have_posts()) {
            echo '<div class="weather-products">';
            while ($products->have_posts()) {
                $products->the_post();
                wc_get_template_part('content', 'product');
            }
            echo '</div>';
        }

        wp_reset_postdata();

        return ob_get_clean();
    }

    public function weather_price_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts);

        if (!$atts['id']) return '';

        $product = wc_get_product($atts['id']);
        if (!$product) return '';

        $regular_price = $product->get_regular_price();
        $weather_price = $this->modify_price_by_weather($regular_price, $product);

        ob_start();
    ?>
        <div class="weather-price">
            <p class="regular-price"><?php echo wc_price($regular_price); ?></p>
            <p class="weather-adjusted-price"><?php echo wc_price($weather_price); ?></p>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Widget Registration
     */
    public function register_widgets()
    {
        require_once plugin_dir_path(__FILE__) . 'widgets/class-weather-products-widget.php';
        register_widget('Weather_Products_Widget');
    }

    /**
     * AJAX Handlers
     */
    public function ajax_get_weather_data()
    {
        check_ajax_referer('weather_data_nonce', 'nonce');

        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : null;
        $weather_data = $this->get_weather_data($location);

        wp_send_json($weather_data);
    }

    /**
     * Scripts and Styles
     */
    public function enqueue_front_scripts()
    {
        wp_enqueue_style(
            'weathersmart-style',
            plugins_url('assets/css/front.css', __FILE__),
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'weathersmart-script',
            plugins_url('assets/js/front.js', __FILE__),
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('weathersmart-script', 'weathersmartAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('weather_data_nonce')
        ));
    }

    public function enqueue_admin_scripts($hook)
    {
        if ('post.php' != $hook && 'post-new.php' != $hook) {
            return;
        }

        wp_enqueue_style(
            'weathersmart-admin-style',
            plugins_url('assets/css/admin.css', __FILE__),
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'weathersmart-admin-script',
            plugins_url('assets/js/admin.js', __FILE__),
            array('jquery'),
            '1.0.0',
            true
        );
    }
}

// Initialize plugin
function weathersmart_init()
{
    WeatherSmartShop::get_instance();
}
add_action('plugins_loaded', 'weathersmart_init');
