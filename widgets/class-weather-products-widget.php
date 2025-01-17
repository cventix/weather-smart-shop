<?php

/**
 * Weather Products Widget Class
 * 
 * Displays weather-appropriate products based on current conditions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Weather_Products_Widget extends WP_Widget
{
    /**
     * Widget setup
     */
    public function __construct()
    {
        parent::__construct(
            'weather_products_widget',
            __('Weather Products', 'weather-smart-shop'),
            array(
                'description' => __('Display weather-appropriate products based on current conditions', 'weather-smart-shop'),
                'classname' => 'weather-products-widget',
            )
        );

        // Register AJAX handlers for real-time updates
        add_action('wp_ajax_update_weather_products', array($this, 'ajax_update_products'));
        add_action('wp_ajax_nopriv_update_weather_products', array($this, 'ajax_update_products'));
    }

    /**
     * Front-end display of widget
     */
    public function widget($args, $instance)
    {
        if (!is_active_widget(false, false, $this->id_base)) {
            return;
        }

        // Extract widget args
        extract($args);

        $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title']);
        $number_of_products = isset($instance['number_of_products']) ? absint($instance['number_of_products']) : 4;
        $show_price = !empty($instance['show_price']);
        $show_weather = !empty($instance['show_weather']);
        $category = !empty($instance['category']) ? $instance['category'] : '';
        $layout = !empty($instance['layout']) ? $instance['layout'] : 'grid';

        echo $before_widget;

        if ($title) {
            echo $before_title . $title . $after_title;
        }

        // Get current weather data
        $weather_data = $this->get_weather_data();

        if ($show_weather && $weather_data) {
            $this->display_weather_info($weather_data);
        }

        // Get appropriate products
        $products = $this->get_weather_appropriate_products($weather_data, array(
            'posts_per_page' => $number_of_products,
            'category' => $category
        ));

        if ($products && $products->have_posts()) {
            $this->display_products($products, array(
                'show_price' => $show_price,
                'layout' => $layout
            ));
        } else {
            echo '<p>' . __('No weather-appropriate products found.', 'weather-smart-shop') . '</p>';
        }

        echo $after_widget;

        // Enqueue required scripts and styles
        $this->enqueue_widget_assets();
    }

    /**
     * Back-end widget form
     */
    public function form($instance)
    {
        $title = isset($instance['title']) ? $instance['title'] : __('Weather Products', 'weather-smart-shop');
        $number_of_products = isset($instance['number_of_products']) ? absint($instance['number_of_products']) : 4;
        $show_price = isset($instance['show_price']) ? (bool) $instance['show_price'] : true;
        $show_weather = isset($instance['show_weather']) ? (bool) $instance['show_weather'] : true;
        $category = isset($instance['category']) ? $instance['category'] : '';
        $layout = isset($instance['layout']) ? $instance['layout'] : 'grid';
?>

        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'weather-smart-shop'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                name="<?php echo $this->get_field_name('title'); ?>" type="text"
                value="<?php echo esc_attr($title); ?>">
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('number_of_products'); ?>">
                <?php _e('Number of products to show:', 'weather-smart-shop'); ?>
            </label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('number_of_products'); ?>"
                name="<?php echo $this->get_field_name('number_of_products'); ?>" type="number"
                step="1" min="1" value="<?php echo $number_of_products; ?>" size="3">
        </p>

        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_price); ?>
                id="<?php echo $this->get_field_id('show_price'); ?>"
                name="<?php echo $this->get_field_name('show_price'); ?>">
            <label for="<?php echo $this->get_field_id('show_price'); ?>">
                <?php _e('Show product prices', 'weather-smart-shop'); ?>
            </label>
        </p>

        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_weather); ?>
                id="<?php echo $this->get_field_id('show_weather'); ?>"
                name="<?php echo $this->get_field_name('show_weather'); ?>">
            <label for="<?php echo $this->get_field_id('show_weather'); ?>">
                <?php _e('Show weather information', 'weather-smart-shop'); ?>
            </label>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('category'); ?>">
                <?php _e('Product Category:', 'weather-smart-shop'); ?>
            </label>
            <?php
            $args = array(
                'taxonomy' => 'product_cat',
                'name' => $this->get_field_name('category'),
                'id' => $this->get_field_id('category'),
                'selected' => $category,
                'show_option_none' => __('All Categories', 'weather-smart-shop'),
                'class' => 'widefat'
            );
            wp_dropdown_categories($args);
            ?>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('layout'); ?>">
                <?php _e('Layout:', 'weather-smart-shop'); ?>
            </label>
            <select class="widefat" id="<?php echo $this->get_field_id('layout'); ?>"
                name="<?php echo $this->get_field_name('layout'); ?>">
                <option value="grid" <?php selected($layout, 'grid'); ?>>
                    <?php _e('Grid', 'weather-smart-shop'); ?>
                </option>
                <option value="list" <?php selected($layout, 'list'); ?>>
                    <?php _e('List', 'weather-smart-shop'); ?>
                </option>
                <option value="carousel" <?php selected($layout, 'carousel'); ?>>
                    <?php _e('Carousel', 'weather-smart-shop'); ?>
                </option>
            </select>
        </p>
    <?php
    }

    /**
     * Sanitize widget form values as they are saved
     */
    public function update($new_instance, $old_instance)
    {
        $instance = array();

        $instance['title'] = (!empty($new_instance['title']))
            ? strip_tags($new_instance['title'])
            : '';

        $instance['number_of_products'] = (!empty($new_instance['number_of_products']))
            ? absint($new_instance['number_of_products'])
            : 4;

        $instance['show_price'] = isset($new_instance['show_price'])
            ? (bool) $new_instance['show_price']
            : false;

        $instance['show_weather'] = isset($new_instance['show_weather'])
            ? (bool) $new_instance['show_weather']
            : false;

        $instance['category'] = (!empty($new_instance['category']))
            ? sanitize_text_field($new_instance['category'])
            : '';

        $instance['layout'] = (!empty($new_instance['layout']))
            ? sanitize_text_field($new_instance['layout'])
            : 'grid';

        return $instance;
    }

    /**
     * Get weather-appropriate products
     */
    private function get_weather_appropriate_products($weather_data, $args = array())
    {
        if (!$weather_data) {
            return false;
        }

        $defaults = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 4,
            'meta_query' => array(),
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_visibility',
                    'field' => 'name',
                    'terms' => 'exclude-from-catalog',
                    'operator' => 'NOT IN',
                )
            )
        );

        // Add weather condition meta query
        $weather_condition = strtolower($weather_data['weather'][0]['main']);
        $current_temp = $weather_data['main']['temp'];

        $meta_query = array(
            'relation' => 'OR',
            array(
                'key' => '_weather_condition',
                'value' => $weather_condition,
                'compare' => '='
            ),
            array(
                'relation' => 'AND',
                array(
                    'key' => '_weather_temp_min',
                    'value' => $current_temp,
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                ),
                array(
                    'key' => '_weather_temp_max',
                    'value' => $current_temp,
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                )
            )
        );

        $defaults['meta_query'][] = $meta_query;

        // Add category filter if specified
        if (!empty($args['category'])) {
            $defaults['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $args['category']
            );
        }

        $args = wp_parse_args($args, $defaults);
        return new WP_Query($args);
    }

    /**
     * Display weather information
     */
    private function display_weather_info($weather_data)
    {
        if (!$weather_data) return;

        $temp = round($weather_data['main']['temp']);
        $condition = $weather_data['weather'][0]['main'];
        $icon_code = $weather_data['weather'][0]['icon'];
    ?>
        <div class="weather-info">
            <img class="weather-icon"
                src="http://openweathermap.org/img/w/<?php echo esc_attr($icon_code); ?>.png"
                alt="<?php echo esc_attr($condition); ?>">
            <span class="weather-temp"><?php echo $temp; ?>°C</span>
            <span class="weather-condition"><?php echo esc_html($condition); ?></span>
        </div>
    <?php
    }

    /**
     * Display products
     */
    private function display_products($products, $display_options)
    {
        $layout_class = 'weather-products-' . $display_options['layout'];
    ?>
        <div class="weather-products <?php echo esc_attr($layout_class); ?>"
            data-layout="<?php echo esc_attr($display_options['layout']); ?>">
            <?php
            while ($products->have_posts()) : $products->the_post();
                global $product;
            ?>
                <div class="weather-product">
                    <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                        <?php echo $product->get_image('woocommerce_thumbnail'); ?>
                        <h4><?php echo esc_html($product->get_name()); ?></h4>
                        <?php if ($display_options['show_price']) : ?>
                            <div class="weather-product-price">
                                <?php echo $product->get_price_html(); ?>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>
            <?php
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
        <?php
        if ($display_options['layout'] === 'carousel') {
            $this->initialize_carousel();
        }
    }

    /**
     * Initialize carousel if needed
     */
    private function initialize_carousel()
    {
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('.weather-products-carousel').slick({
                    dots: true,
                    infinite: true,
                    speed: 300,
                    slidesToShow: 4,
                    slidesToScroll: 1,
                    responsive: [{
                            breakpoint: 1024,
                            settings: {
                                slidesToShow: 3
                            }
                        },
                        {
                            breakpoint: 768,
                            settings: {
                                slidesToShow: 2
                            }
                        },
                        {
                            breakpoint: 480,
                            settings: {
                                slidesToShow: 1
                            }
                        }
                    ]
                });
            });
        </script>
<?php
    }

    /**
     * Enqueue required assets
     */
    private function enqueue_widget_assets()
    {
        wp_enqueue_style('weather-products-widget-style');
        wp_enqueue_script('weather-products-widget-script');

        // Enqueue Slick Carousel if needed
        if ($this->get_layout() === 'carousel') {
            wp_enqueue_style('slick');
            wp_enqueue_style('slick-theme');
            wp_enqueue_script('slick');
        }
    }

    /**
     * Get current layout
     */
    private function get_layout()
    {
        $settings = $this->get_settings();
        $instance = isset($settings[$this->number]) ? $settings[$this->number] : array();
        return isset($instance['layout']) ? $instance['layout'] : 'grid';
    }

    /**
     * Get weather data
     */
    private function get_weather_data()
    {
        $weather_smart = WeatherSmartShop::get_instance();
        return $weather_smart->get_weather_data();
    }

    /**
     * AJAX handler for updating products
     */
    public function ajax_update_products()
    {
        check_ajax_referer('weather_products_nonce', 'nonce');

        $weather_data = $this->get_weather_data();
        $instance = $this->get_settings()[$_POST['widget_id']];

        $products = $this->get_weather_appropriate_products($weather_data, array(
            'posts_per_page' => $instance['number_of_products'],
            'category' => $instance['category']
        ));

        ob_start();
        $this->display_products($products, array(
            'show_price' => $instance['show_price'],
            'layout' => $instance['layout']
        ));
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'weather' => $weather_data
        ));
    }

    /**
     * Get cached weather data
     */
    private function get_cached_weather_data()
    {
        $cache_key = 'weather_products_data_' . sanitize_key(get_option('weathersmart_default_location', ''));
        $cached = get_transient($cache_key);

        if (false === $cached) {
            $weather_data = $this->get_weather_data();
            if ($weather_data) {
                set_transient($cache_key, $weather_data, 30 * MINUTE_IN_SECONDS);
                return $weather_data;
            }
            return false;
        }

        return $cached;
    }

    /**
     * Check if product is weather appropriate
     */
    private function is_weather_appropriate($product_id, $weather_data)
    {
        if (!$weather_data) {
            return true; // If no weather data, show all products
        }

        $weather_condition = strtolower($weather_data['weather'][0]['main']);
        $current_temp = $weather_data['main']['temp'];

        // Get product weather rules
        $product_condition = get_post_meta($product_id, '_weather_condition', true);
        $temp_min = get_post_meta($product_id, '_weather_temp_min', true);
        $temp_max = get_post_meta($product_id, '_weather_temp_max', true);

        // Check weather condition match
        if (!empty($product_condition) && $product_condition !== $weather_condition) {
            return false;
        }

        // Check temperature range
        if (!empty($temp_min) && !empty($temp_max)) {
            if ($current_temp < floatval($temp_min) || $current_temp > floatval($temp_max)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate cache key for product list
     */
    private function get_product_cache_key($args)
    {
        $weather_data = $this->get_cached_weather_data();
        $key_parts = array(
            'weather_products',
            $args['posts_per_page'],
            $args['category'],
            $weather_data ? $weather_data['weather'][0]['main'] : 'no_weather',
            $weather_data ? round($weather_data['main']['temp']) : 'no_temp'
        );

        return 'weather_products_' . md5(implode('_', $key_parts));
    }

    /**
     * Format temperature for display
     */
    private function format_temperature($temp_c)
    {
        $temp_unit = get_option('weathersmart_temp_unit', 'C');

        if ($temp_unit === 'F') {
            $temp_f = ($temp_c * 9 / 5) + 32;
            return round($temp_f) . '°F';
        }

        return round($temp_c) . '°C';
    }

    /**
     * Get weather icon class
     */
    private function get_weather_icon_class($condition)
    {
        $icon_map = array(
            'clear' => 'sun',
            'clouds' => 'cloud',
            'rain' => 'rain',
            'snow' => 'snow',
            'thunderstorm' => 'bolt',
            'drizzle' => 'cloud-rain',
            'mist' => 'smog'
        );

        $condition = strtolower($condition);
        return isset($icon_map[$condition]) ? 'weather-icon-' . $icon_map[$condition] : 'weather-icon-default';
    }

    /**
     * Register widget assets
     */
    public static function register_widget_assets()
    {
        wp_register_style(
            'weather-products-widget-style',
            plugins_url('assets/css/weather-widget.css', dirname(__FILE__)),
            array(),
            '1.0.0'
        );

        wp_register_script(
            'weather-products-widget-script',
            plugins_url('assets/js/weather-widget.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.0',
            true
        );

        // Register Slick Carousel assets
        wp_register_style(
            'slick',
            'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css',
            array(),
            '1.8.1'
        );

        wp_register_style(
            'slick-theme',
            'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css',
            array(),
            '1.8.1'
        );

        wp_register_script(
            'slick',
            'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js',
            array('jquery'),
            '1.8.1',
            true
        );

        // Localize script with Ajax URL and nonce
        wp_localize_script('weather-products-widget-script', 'weatherProductsWidget', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('weather_products_nonce'),
            'refreshInterval' => apply_filters('weather_products_refresh_interval', 30 * MINUTE_IN_SECONDS),
            'i18n' => array(
                'loading' => __('Loading products...', 'weather-smart-shop'),
                'error' => __('Error loading products', 'weather-smart-shop'),
                'noProducts' => __('No weather-appropriate products found', 'weather-smart-shop')
            )
        ));
    }

    /**
     * Init widget
     */
    public static function init()
    {
        add_action('widgets_init', function () {
            register_widget('Weather_Products_Widget');
        });

        add_action('wp_enqueue_scripts', array('Weather_Products_Widget', 'register_widget_assets'));
    }
}

// Initialize the widget
Weather_Products_Widget::init();
