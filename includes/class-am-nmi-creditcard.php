<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_NMI class.
 *
 * @extends WC_Payment_Gateway_CC
 */
class AM_NMI_WooCommerce_CreditCard_Gateway extends WC_Payment_Gateway_CC
{

    public $testmode;
    public $capture;
    public $liveusername;
    public $livepassword;
    public $testusername;
    public $testpassword;
    public $logging;
    public $line_items;
    public $allowed_card_types;
    private $url = 'https://secure.nmi.com/api/transact.php';

    /**
     * Constructor
     * @since 1.0.0
     * @return void
     * @access public
     */
    public function __construct()
    {
        if (defined('AM_NMI_GATEWAY_FOR_WOOCOMMERCE_VERSION')) {
            $this->version = AM_NMI_GATEWAY_FOR_WOOCOMMERCE_VERSION;
        } else {
            $this->version = '1.0.0';
        }

        $this->id = 'am-nmi-gateway-for-woocommerce';
        $this->method_title = __('AM Network Merchants Inc (NMI)', 'am-nmi-gateway-for-woocommerce');
        $this->method_description = __('Give clients the option to safely pay with their credit cards using the AM NMI Payment Gateway..', 'am-nmi-gateway-for-woocommerce');
        $this->has_fields = true;
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        $this->testmode = $this->get_option('testmode');
        $this->capture = 'sale';
        $this->liveusername = $this->get_option('liveusername');
        $this->livepassword = $this->get_option('livepassword');
        $this->testusername = $this->get_option('testusername');
        $this->testpassword = $this->get_option('testpassword');
        $this->logging = $this->get_option('logging') === 'yes';
        $this->line_items = $this->get_option('line_items') === 'yes';
        $this->allowed_card_types = $this->get_option('allowed_card_types', array());

        if ($this->testmode === 'yes') {
            $this->description .= ' ' . sprintf(__('<br /><br /><strong>TEST MODE ENABLED</strong><br /> In test mode, you can use the card number 4111111111111111 with any CVC and a valid expiration date or check the documentation', 'am-nmi-gateway-for-woocommerce'));
            $this->description = trim($this->description);
        }
        // Hooks
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Get CC Icons function.
     *
     * @access public
     * @return string
     */
    public function get_icon()
    {
        $icon = '';
        if (in_array('visa', $this->allowed_card_types)) {
            $icon .= '<img style="margin-left: 0.3em" src="' . plugin_dir_url(dirname(__FILE__)) . 'assets/images/visa.svg" alt="Visa" width="32" />';
        }
        if (in_array('mastercard', $this->allowed_card_types)) {
            $icon .= '<img style="margin-left: 0.3em" src="' . plugin_dir_url(dirname(__FILE__)) . 'assets/images/mastercard.svg" alt="Mastercard" width="32" />';
        }
        if (in_array('amex', $this->allowed_card_types)) {
            $icon .= '<img style="margin-left: 0.3em" src="' . plugin_dir_url(dirname(__FILE__)) . 'assets/images/amex.svg" alt="Amex" width="32" />';
        }
        if (in_array('discover', $this->allowed_card_types)) {
            $icon .= '<img style="margin-left: 0.3em" src="' . plugin_dir_url(dirname(__FILE__)) . 'assets/images/discover.svg" alt="Discover" width="32" />';
        }
        if (in_array('diners-club', $this->allowed_card_types)) {
            $icon .= '<img style="margin-left: 0.3em" src="' . plugin_dir_url(dirname(__FILE__)) . 'assets/images/diners.svg" alt="Diners Club" width="32" />';
        }
        if (in_array('jcb', $this->allowed_card_types)) {
            $icon .= '<img style="margin-left: 0.3em" src="' . plugin_dir_url(dirname(__FILE__)) . 'assets/images/jcb.svg" alt="JCB" width="32" />';
        }
        if (in_array('maestro', $this->allowed_card_types)) {
            $icon .= '<img style="margin-left: 0.3em" src="' . plugin_dir_url(dirname(__FILE__)) . 'assets/images/maestro.svg" alt="Maestro" width="32" />';
        }
        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    /**
     * Admin Notices For Fields 
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function admin_notices()
    {
        if ($this->enabled == 'no') {
            return;
        }

        if ($this->testmode === 'yes') {
            // Check required fields for Test Mode
            if (!$this->testusername) {
                echo '<div class="error notice is-dismissible"><p>' .
                    sprintf(
                        esc_html__('AM NMI error: Please enter your Test Username <a href="%s">here</a>', 'am-nmi-gateway-for-woocommerce'),
                        esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=am-nmi-gateway-for-woocommerce'))
                    ) . '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' .
                    esc_html__('Dismiss this notice.', 'am-nmi-gateway-for-woocommerce') .
                    '</span></button></div>';
            }
            if (!$this->testpassword) {
                echo '<div class="error notice is-dismissible"><p>' .
                    sprintf(
                        esc_html__('AM NMI error: Please enter your Test Password <a href="%s">here</a>', 'am-nmi-gateway-for-woocommerce'),
                        esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=am-nmi-gateway-for-woocommerce'))
                    ) . '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' .
                    esc_html__('Dismiss this notice.', 'am-nmi-gateway-for-woocommerce') .
                    '</span></button></div>';
            }
        } else {
            // Check required fields for Live Mode
            if (!$this->liveusername) {
                echo '<div class="error notice is-dismissible"><p>' .
                    sprintf(
                        esc_html__('AM NMI error: Please enter your Live Username <a href="%s">here</a>', 'am-nmi-gateway-for-woocommerce'),
                        esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=am-nmi-gateway-for-woocommerce'))
                    ) . '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' .
                    esc_html__('Dismiss this notice.', 'am-nmi-gateway-for-woocommerce') .
                    '</span></button></div>';
            }
            if (!$this->livepassword) {
                echo '<div class="error notice is-dismissible"><p>' .
                    sprintf(
                        esc_html__('AM NMI error: Please enter your Live Password <a href="%s">here</a>', 'am-nmi-gateway-for-woocommerce'),
                        esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=am-nmi-gateway-for-woocommerce'))
                    ) . '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' .
                    esc_html__('Dismiss this notice.', 'am-nmi-gateway-for-woocommerce') .
                    '</span></button></div>';
            }
        }

        // Show message if enabled and FORCE SSL is disabled and WordPressHTTPS plugin is not detected
        if (!wc_checkout_is_https()) {
            echo '<div class="notice notice-warning"><p>' .
                sprintf(
                    esc_html__('AM NMI is enabled, but an SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', 'am-nmi-gateway-for-woocommerce'),
                    esc_url('https://en.wikipedia.org/wiki/Transport_Layer_Security')
                ) . '</p></div>';
        }
    }

    public function admin_options()
    {
        // Enqueue the external JS file in the admin panel
        $this->enqueue_admin_custom_script();

        // Call the parent admin_options to render the options table
        parent::admin_options();
    }

    /**
     * Summary of enqueue_admin_custom_script
     * @return void
     */
    public function enqueue_admin_custom_script() {
        // Check if we're on the WooCommerce settings page
        $screen = get_current_screen();
        if (isset($screen->id) && $screen->id === 'woocommerce_page_wc-settings') {
            
            // Register the custom script for your admin area
            wp_register_script(
                'am-nmi-admin-custom-script',  // Handle for the script
                plugin_dir_url(__FILE__) . '../assets/js/admin-custom.js', // Path to your JS file
                array('jquery'), // Dependencies
                '1.0', // Version number
                true // Load script in footer
            );
    
            // Enqueue the registered script
            wp_enqueue_script('am-nmi-admin-custom-script');
        }
    }

    /**
     * Check if this gateway is enabled
     */
    public function is_available()
    {
        if ($this->enabled == "yes") {
            if (is_add_payment_method_page()) {
                return false;
            }
            // Required fields check
            if (!$this->testusername || !$this->testpassword && (!$this->liveusername || !$this->livepassword)) {
                return false;
            }
            return true;
        }
        return parent::is_available();
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'am-nmi-gateway-for-woocommerce'),
                'label' => __('Enable NMI', 'am-nmi-gateway-for-woocommerce'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'am-nmi-gateway-for-woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'am-nmi-gateway-for-woocommerce'),
                'default' => __('Credit card (NMI)', 'am-nmi-gateway-for-woocommerce')
            ),
            'description' => array(
                'title' => __('Description', 'am-nmi-gateway-for-woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'am-nmi-gateway-for-woocommerce'),
                'default' => __('Pay with your credit card via NMI.', 'am-nmi-gateway-for-woocommerce')
            ),
            'testmode' => array(
                'title' => __('Test mode', 'am-nmi-gateway-for-woocommerce'),
                'label' => __('Enable Test Mode', 'am-nmi-gateway-for-woocommerce'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode. This will display test information on the checkout page.', 'am-nmi-gateway-for-woocommerce'),
                'default' => 'no'
            ),
            'liveusername' => array(
                'title' => __('Production Username', 'am-nmi-gateway-for-woocommerce'),
                'type' => 'password',
                'description' => __('Enter your Production NMI account username', 'am-nmi-gateway-for-woocommerce'),
                'default' => ''
            ),
            'livepassword' => array(
                'title' => __('Production Password', 'am-nmi-gateway-for-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your Production NMI account password', 'am-nmi-gateway-for-woocommerce'),
                'default' => ''
            ),
            'testusername' => array(
                'title' => __('Sandbox Username', 'am-nmi-gateway-for-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your Sandbox NMI account username.', 'am-nmi-gateway-for-woocommerce'),
                'default' => ''
            ),
            'testpassword' => array(
                'title' => __('Sandbox Password', 'am-nmi-gateway-for-woocommerce'),
                'type' => 'password',
                'description' => __('Enter your Sandbox NMI account password.', 'am-nmi-gateway-for-woocommerce'),
                'default' => ''
            ),
            'logging' => array(
                'title' => __('Logging', 'am-nmi-gateway-for-woocommerce'),
                'label' => __('Log debug messages', 'am-nmi-gateway-for-woocommerce'),
                'type' => 'checkbox',
                'description' => sprintf(__('Save debug messages to the WooCommerce System Status log file <code>%s</code>.', 'am-nmi-gateway-for-woocommerce'), WC_Log_Handler_File::get_log_file_path('am-nmi-gateway-for-woocommerce')),
                'default' => 'no'
            ),
            'line_items' => array(
                'title' => __('Line Items', 'am-nmi-gateway-for-woocommerce'),
                'label' => __('Enable Line Items', 'am-nmi-gateway-for-woocommerce'),
                'type' => 'checkbox',
                'description' => __('Add line item data to description sent to the gateway (eg. Item x qty).', 'am-nmi-gateway-for-woocommerce'),
                'default' => 'no'
            ),
            'allowed_card_types' => array(
                'title' => __('Allowed Card types', 'am-nmi-gateway-for-woocommerce'),
                'class' => 'wc-enhanced-select',
                'type' => 'multiselect',
                'description' => __('Select the card types you want to allow payments from.', 'am-nmi-gateway-for-woocommerce'),
                'default' => array('visa', 'mastercard', 'discover', 'amex'),
                'options' => array(
                    'visa' => __('Visa', 'am-nmi-gateway-for-woocommerce'),
                    'mastercard' => __('MasterCard', 'am-nmi-gateway-for-woocommerce'),
                    'discover' => __('Discover', 'am-nmi-gateway-for-woocommerce'),
                    'amex' => __('American Express', 'am-nmi-gateway-for-woocommerce'),
                    'diners-club' => __('Diners Club', 'am-nmi-gateway-for-woocommerce'),
                    'jcb' => __('JCB', 'am-nmi-gateway-for-woocommerce'),
                    'maestro' => __('Maestro', 'am-nmi-gateway-for-woocommerce'),
                ),
            ),
        );
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {
        echo '<div class="nmi_new_card" id="nmi-payment-data">';

        if ($this->description) {
            echo wp_kses_post(apply_filters('am_nmi_description', wpautop($this->description)));
        }


        if (!empty($this->liveusername) && !empty($this->livepassword)) {
            // Both live username and password are not empty
            $this->collect_js_form();
        } else {
            // One or both are empty
            $this->form();
            wp_nonce_field('am_nmi_gateway_nonce_action', 'am_nmi_gateway_nonce_field');
        }

        echo '</div>';
    }

    public function collect_js_form()
    {
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class="wc-credit-card-form wc-payment-form"
            style="background:transparent;">
            <?php wp_nonce_field('am_nmi_gateway_nonce_action', 'am_nmi_gateway_nonce_field'); ?>
            <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>

            <!-- Used to display form errors -->
            <div class="nmi-source-errors" role="alert"></div>

            <div class="form-row form-row-wide">
                <label for="nmi-card-number-element"><?php esc_html_e('Card Number', 'am-nmi-gateway-for-woocommerce'); ?> <span
                        class="required">*</span></label>
                <div class="nmi-card-group">
                    <div id="nmi-card-number-element" class="am-nmi-gateway-for-woocommerce-elements-field">
                        <!-- a NMI Element will be inserted here. -->
                    </div>

                    <i class="nmi-credit-card-brand nmi-card-brand" alt="Credit Card"></i>
                </div>
            </div>

            <div class="form-row form-row-first">
                <label for="nmi-card-expiry-element"><?php esc_html_e('Expiry Date', 'am-nmi-gateway-for-woocommerce'); ?> <span
                        class="required">*</span></label>

                <div id="nmi-card-expiry-element" class="am-nmi-gateway-for-woocommerce-elements-field">
                    <!-- a NMI Element will be inserted here. -->
                </div>
            </div>

            <div class="form-row form-row-last">
                <label for="nmi-card-cvc-element"><?php esc_html_e('Card Code (CVC)', 'am-nmi-gateway-for-woocommerce'); ?>
                    <span class="required">*</span></label>
                <div id="nmi-card-cvc-element" class="am-nmi-gateway-for-woocommerce-elements-field">
                    <!-- a NMI Element will be inserted here. -->
                </div>
            </div>
            <div class="clear"></div>

            <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
            <div class="clear"></div>
        </fieldset>
        <?php
    }

    public function payment_scripts()
    {
        if (!$this->liveusername || !$this->livepassword || (!is_cart() && !is_checkout() && !is_add_payment_method_page())) {
            return;
        }
        wp_enqueue_script(
            'am-nmi-gateway-for-woocommerce',
            plugin_dir_url(__FILE__) . '../assets/js/am-nmi-gateway.js',  // Go up one level from 'includes'
            array(),
            $this->version,
            true
        );
    }

    /**
     * Summary of normalize_expiry_format
     * @param mixed $expiry
     * @return array|string|null
     */
    public function normalize_expiry_format($expiry)
    {
        // Remove all spaces from the string
        $expiry = preg_replace('/\s+/', '', $expiry);

        // Check if the format is MM/YY, MM-YY, or MMYY
        if (preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
            // If the format is MM/YY, return it as is (no changes needed)
            return str_replace('/', '', $expiry); // Return MMYY
        } elseif (preg_match('/^\d{2}-\d{2}$/', $expiry)) {
            // If the format is MM-YY, return MMYY
            return str_replace('-', '', $expiry); // Return MMYY
        } elseif (preg_match('/^\d{4}$/', $expiry)) {
            // If the format is MMYY, return as is (correct format)
            return $expiry;
        } elseif (preg_match('/^\d{1}\/\d{2}$/', $expiry)) {
            // If the month is one digit (e.g., M/YY), pad with zero (01 -> 09)
            return '0' . str_replace('/', '', $expiry); // Pad month and return MMYY
        } elseif (preg_match('/^\d{1}-\d{2}$/', $expiry)) {
            // If the month is one digit with a hyphen (e.g., M-YY), pad with zero
            return '0' . str_replace('-', '', $expiry); // Pad month and return MMYY
        }

        // If none of the above formats match, return an empty string or false
        return '';
    }

    /**
     * Process the payment
     */
    public function process_payment($order_id, $retry = true)
    {

        $order = wc_get_order($order_id);

        $ip_address = WC_Geolocation::get_ip_address();
        // Example usage in your payment args
        // $raw_ccexp = isset($_POST['am-nmi-gateway-for-woocommerce-card-expiry']) ? wc_clean(wp_unslash($_POST['am-nmi-gateway-for-woocommerce-card-expiry'])) : '';
        $raw_ccexp = isset($_POST['am-nmi-gateway-for-woocommerce-card-expiry']) ?
            sanitize_text_field(wp_unslash($_POST['am-nmi-gateway-for-woocommerce-card-expiry'])) : '';
        $normalized_ccexp = $this->normalize_expiry_format($raw_ccexp);

        $this->log("AM NMI Info: Beginning processing payment for order $order_id for the amount of {$order->get_total()}");

        $response = false;

        // Use NMI CURL API for payment
        try {
            // First, check if the nonce field is set and valid
            if (isset($_POST['am_nmi_gateway_nonce_field']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['am_nmi_gateway_nonce_field'])), 'am_nmi_gateway_nonce_action')) {
                // Check for CC details filled or not
                if (empty($_POST['am-nmi-gateway-for-woocommerce-card-number']) || empty($_POST['am-nmi-gateway-for-woocommerce-card-expiry']) || empty($_POST['am-nmi-gateway-for-woocommerce-card-cvc'])) {
                    throw new Exception(__('Credit card details cannot be left incomplete.', 'am-nmi-gateway-for-woocommerce'));
                }

                // Check for card type supported or not
                if (!in_array($this->get_card_type(wc_clean($_POST['am-nmi-gateway-for-woocommerce-card-number']), 'pattern', 'name'), $this->allowed_card_types)) {
                    $this->log(sprintf(__('Card type being used is not one of supported types in plugin settings: %s', 'am-nmi-gateway-for-woocommerce'), $this->get_card_type(sanitize_text_field(wp_unslash($_POST['am-nmi-gateway-for-woocommerce-card-number'])))));
                    throw new Exception(__('Card Type Not Accepted', 'am-nmi-gateway-for-woocommerce'));
                }

                // Validate the card expiry date
                if (isset($_POST['am-nmi-gateway-for-woocommerce-card-expiry'])) {
                    // Unslash and sanitize the input
                    $expiry = sanitize_text_field(wp_unslash($_POST['am-nmi-gateway-for-woocommerce-card-expiry'])); // Assuming MM/YY format

                    // Process the expiry data (if needed)
                } else {
                    // Handle the case where the field does not exist
                    $expiry = '';
                }

                // Split the expiry into month and year
                list($exp_month, $exp_year) = explode('/', $expiry);

                // Normalize year (e.g., 21 => 2021)
                $exp_year = strlen($exp_year) == 2 ? '20' . $exp_year : $exp_year;

                // Get the current month and year in UTC
                $current_year = gmdate('Y');
                $current_month = gmdate('m');
                $current_year = str_replace('20', '', $current_year);

                // Check if the card is expired
                if ($exp_year < $current_year || ($exp_year == $current_year && $exp_month < $current_month)) {
                    throw new Exception(__('Your credit card has expired. Please use a valid card.', 'am-nmi-gateway-for-woocommerce'));
                }

                $description = sprintf(__('%1$s - Order %2$s', 'am-nmi-gateway-for-woocommerce'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), $order->get_order_number());

                if ($this->line_items) {
                    $description .= ' (' . $this->get_line_items($order) . ')';
                }

                // Unslash and sanitize the credit card number input
                $card_number = isset($_POST['am-nmi-gateway-for-woocommerce-card-number']) ? sanitize_text_field(wp_unslash($_POST['am-nmi-gateway-for-woocommerce-card-number'])) : '';
                $card_cvv = isset($_POST['am-nmi-gateway-for-woocommerce-card-cvc']) ? sanitize_text_field(wp_unslash($_POST['am-nmi-gateway-for-woocommerce-card-cvc'])) : '';

                $query = '';

                if ($this->testmode === 'yes') {
                    'testmode';
                    $testusername = $this->testusername;
                    $testpassword = $this->testpassword;

                    $query .= 'username=' . $testusername . '&';
                    $query .= 'password=' . $testpassword . '&';
                    $query .= 'ccnumber=' . $card_number . '&';
                    $query .= 'ccexp=' . wc_clean($normalized_ccexp) . '&';
                    $query .= 'cvv=' . $card_cvv . '&';
                    $query .= 'ipaddress=' . $ip_address . '&';
                    $query .= 'orderid=' . $order->get_order_number() . '&';
                    $query .= 'orderdescription=' . $description . '&';
                    $query .= 'amount=' . $order->get_total() . '&';
                    $query .= 'transactionid=' . $order->get_transaction_id() . '&';
                    $query .= 'firstname=' . $order->get_billing_first_name() . '&';
                    $query .= 'lastname=' . $order->get_billing_last_name() . '&';
                    $query .= 'address1=' . $order->get_billing_address_1() . '&';
                    $query .= 'address2=' . $order->get_billing_address_2() . '&';
                    $query .= 'city=' . $order->get_billing_city() . '&';
                    $query .= 'state=' . $order->get_billing_state() . '&';
                    $query .= 'country=' . $order->get_billing_country() . '&';
                    $query .= 'zip=' . $order->get_billing_postcode() . '&';
                    $query .= 'email=' . $order->get_billing_email() . '&';
                    $query .= 'phone=' . $order->get_billing_phone() . '&';
                    $query .= 'company=' . $order->get_billing_company() . '&';
                    $query .= 'currency=' . $this->get_payment_currency($order_id) . '&';
                    $query .= 'type=' . $this->capture;

                } else {
                    $liveusername = $this->liveusername;
                    $livepassword = $this->livepassword;

                    $query .= 'username=' . $liveusername . '&';
                    $query .= 'password=' . $livepassword . '&';
                    $query .= 'ccnumber=' . $card_number . '&';
                    $query .= 'ccexp=' . wc_clean($normalized_ccexp) . '&';
                    $query .= 'cvv=' . $card_cvv . '&';
                    $query .= 'ipaddress=' . $ip_address . '&';
                    $query .= 'orderid=' . $order->get_order_number() . '&';
                    $query .= 'orderdescription=' . $description . '&';
                    $query .= 'amount=' . $order->get_total() . '&';
                    $query .= 'transactionid=' . $order->get_transaction_id() . '&';
                    $query .= 'firstname=' . $order->get_billing_first_name() . '&';
                    $query .= 'lastname=' . $order->get_billing_last_name() . '&';
                    $query .= 'address1=' . $order->get_billing_address_1() . '&';
                    $query .= 'address2=' . $order->get_billing_address_2() . '&';
                    $query .= 'city=' . $order->get_billing_city() . '&';
                    $query .= 'state=' . $order->get_billing_state() . '&';
                    $query .= 'country=' . $order->get_billing_country() . '&';
                    $query .= 'zip=' . $order->get_billing_postcode() . '&';
                    $query .= 'email=' . $order->get_billing_email() . '&';
                    $query .= 'phone=' . $order->get_billing_phone() . '&';
                    $query .= 'company=' . $order->get_billing_company() . '&';
                    $query .= 'currency=' . $this->get_payment_currency($order_id) . '&';
                    $query .= 'type=' . $this->capture;
                }

                $response = $this->am_nmi_request($query);

                if (is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }

                if ((!empty($response)) && isset($response['response']) && $response['response'] == 1 && isset($response['response_code']) && $response['response_code'] == 100) {

                    // Store charge ID
                    $order->update_meta_data('_nmi_charge_id', $response['transactionid']);
                    $order->update_meta_data('_nmi_authorization_code', $response['authcode']);


                    $order->set_transaction_id($response['transactionid']);
                    $order->update_meta_data('_nmi_charge_captured', 'yes');
                    $order->update_meta_data('NMI Payment ID', $response['transactionid']);

                    // Payment complete
                    $order->payment_complete($response['transactionid']);

                    // Mark order as completed
                    $order->update_status('completed');

                    // Add order note
                    $complete_message = sprintf(__('AM NMI charge complete (Charge ID: %s)', 'am-nmi-gateway-for-woocommerce'), $response['transactionid']);
                    $order->add_order_note($complete_message);
                    $this->log("Success: $complete_message");

                    $order->save();

                    // Remove cart
                    WC()->cart->empty_cart();

                    do_action('wc_gateway_am_nmi_process_payment', $response, $order);

                    // Return thank you page redirect
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );

                } else {
                    throw new Exception($response->get_error_message());
                }
            } else {
                // Nonce verification failed, handle it by displaying an error or stopping further processing
                wp_die(esc_html__('Nonce verification failed. Please try again.', 'am-nmi-gateway-for-woocommerce'));
            }

        } catch (Exception $e) {
            wc_add_notice(sprintf(__('AM NMI Gateway Error: %s', 'am-nmi-gateway-for-woocommerce'), $e->getMessage()), 'error');
            $this->log(sprintf(__('AM NMI  Gateway Error: %s', 'am-nmi-gateway-for-woocommerce'), $e->getMessage()));

            if (is_wp_error($response) && $response = $response->get_error_data()) {
                $order->add_order_note(sprintf(__('AM NMI failure reason: %s', 'am-nmi-gateway-for-woocommerce'), $response['response_code'] . ' - ' . $response['responsetext']));
            }

            $order->update_status('failed');

            return array(
                'result' => 'fail',
                'redirect' => ''
            );

        }
    }

    /**
     * Summary of am_nmi_request
     * @since 1.0.0
     * @param mixed $query
     * @return mixed
     */
    function am_nmi_request($query)
    {
        // Prepare the request arguments
        $args = array(
            'body' => $query,
            'timeout' => 15, // Timeout in seconds
            'sslverify' => false, // Set to true for production if using a secure SSL connection
        );

        // Make the request using wp_remote_post
        $response = wp_remote_post($this->url, $args);

        // Check if there was an error with the request
        if (is_wp_error($response)) {
            return 'ERROR'; // Return error if request failed
        }

        // Get the body of the response
        $data = wp_remote_retrieve_body($response);

        // Parse the response data (assuming it’s in the key=value&key=value format)
        $this->responses = array();
        $data = explode('&', $data);
        foreach ($data as $item) {
            $rdata = explode('=', $item);
            if (isset($rdata[0], $rdata[1])) {
                $this->responses[$rdata[0]] = $rdata[1];
            }
        }

        return $this->responses;
    }

    /**
     * Get inline Order Items
     * @since 1.0.0
     * @param mixed $order
     * @return string
     */
    function get_line_items($order)
    {
        $line_items = array();
        // order line items
        foreach ($order->get_items() as $item) {
            $line_items[] = $item->get_name() . ' x ' . $item->get_quantity();
        }
        return implode(', ', $line_items);
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Get Card Type from Card Number
     * @since 1.0.0
     * @param mixed $value
     * @param mixed $field
     * @param mixed $return
     * @return bool|string
     */
    function get_card_type($value, $field = 'pattern', $return = 'label')
    {
        $card_types = array(
            array(
                'label' => 'American Express',
                'name' => 'amex',
                'pattern' => '/^3[47]/',
                'valid_length' => '[15]'
            ),
            array(
                'label' => 'JCB',
                'name' => 'jcb',
                'pattern' => '/^35(2[89]|[3-8][0-9])/',
                'valid_length' => '[16]'
            ),
            array(
                'label' => 'Discover',
                'name' => 'discover',
                'pattern' => '/^(6011|622(12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5]|64[4-9])|65)/',
                'valid_length' => '[16]'
            ),
            array(
                'label' => 'MasterCard',
                'name' => 'mastercard',
                'pattern' => '/^5[1-5]/',
                'valid_length' => '[16]'
            ),
            array(
                'label' => 'Visa',
                'name' => 'visa',
                'pattern' => '/^4/',
                'valid_length' => '[16]'
            ),
            array(
                'label' => 'Maestro',
                'name' => 'maestro',
                'pattern' => '/^(5018|5020|5038|6304|6759|676[1-3])/',
                'valid_length' => '[12, 13, 14, 15, 16, 17, 18, 19]'
            ),
            array(
                'label' => 'Diners Club',
                'name' => 'diners-club',
                'pattern' => '/^3[0689]/',
                'valid_length' => '[14]'
            ),
        );

        foreach ($card_types as $type) {
            $compare = $type[$field];
            if (($field == 'pattern' && preg_match($compare, $value, $match)) || $compare == $value) {
                return $type[$return];
            }
        }

        return false;

    }

    /**
     * Get payment currency, either from current order or WC settings
     *
     * @since 1.0.0
     * @return string three-letter currency code
     */
    function get_payment_currency($order_id = false)
    {
        $currency = get_woocommerce_currency();
        $order_id = !$order_id ? $this->get_checkout_pay_page_order_id() : $order_id;

        // Gets currency for the current order, that is about to be paid for
        if ($order_id) {
            $order = wc_get_order($order_id);
            $currency = $order->get_currency();
        }
        return $currency;
    }

    /**
     * Returns the order_id if on the checkout pay page
     *
     * @since 1.0.0
     * @return int order identifier
     */
    public function get_checkout_pay_page_order_id()
    {
        global $wp;
        return isset($wp->query_vars['order-pay']) ? absint($wp->query_vars['order-pay']) : 0;
    }

    /**
     * Send the request to AM NMI's API
     *
     * @since 1.0.0
     *
     * @param string $message
     */
    public function log($message)
    {
        if ($this->logging) {
            AM_NMI_WooCommerce_Logger::log($message);
        }
    }
}
