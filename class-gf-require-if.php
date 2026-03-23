<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

GFForms::include_addon_framework();

class GF_Require_If extends GFAddOn {

    protected $_version                    = GRFI_VERSION;
    protected $_min_gravityforms_version   = '2.5';
    protected $_slug                       = 'gravity-forms-require-if';
    protected $_path                       = 'gravity-forms-require-if/gravity-forms-require-if.php';
    protected $_full_path                  = __FILE__;
    protected $_title                      = 'Gravity Forms Require If';
    protected $_short_title                = 'Require If';

    protected $_capabilities               = array( 'gravityforms_edit_forms', 'gravityforms_uninstall' );
    protected $_capabilities_settings_page = array( 'gravityforms_edit_forms' );
    protected $_capabilities_form_settings = array( 'gravityforms_edit_forms' );
    protected $_capabilities_uninstall     = array( 'gravityforms_uninstall' );

    private static $_instance = null;

    public static function get_instance() {
        if ( self::$_instance === null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function init() {
        parent::init();
        require_once GRFI_PATH . 'includes/class-grfi-evaluator-gf.php';
        require_once GRFI_PATH . 'includes/class-grfi-evaluator-wp.php';
        require_once GRFI_PATH . 'includes/class-grfi-validator.php';
    }

    public function init_admin() {
        parent::init_admin();
        require_once GRFI_PATH . 'includes/class-grfi-field-settings.php';
        GRFI_Field_Settings::init();
    }

    public function init_frontend() {
        parent::init_frontend();
        add_filter( 'gform_pre_render', array( $this, 'inject_wp_conditions_payload' ), 10, 1 );
        add_filter( 'gform_pre_validation', array( 'GRFI_Validator', 'evaluate_required_states' ), 10, 1 );
        add_action( 'wp_footer', array( $this, 'print_frontend_data' ), 8 );
    }

    public function init_ajax() {
        parent::init_ajax();
        add_filter( 'gform_pre_validation', array( 'GRFI_Validator', 'evaluate_required_states' ), 10, 1 );
    }

    // -------------------------------------------------------------------------
    // Script & Style Enqueuing
    // -------------------------------------------------------------------------

    public function scripts() {
        $supported_types = array(
            'text', 'textarea', 'select', 'checkbox',
            'radio', 'email', 'phone', 'website',
            'date', 'time', 'number',
        );

        return array_merge( parent::scripts(), array(
            array(
                'handle'  => 'grfi-admin',
                'src'     => $this->get_base_url() . '/assets/js/grfi-admin.js',
                'version' => $this->_version,
                'deps'    => array( 'jquery', 'gform_gravityforms' ),
                'enqueue' => array(
                    array( 'admin_page' => array( 'form_editor' ) ),
                ),
            ),
            array(
                'handle'  => 'grfi-frontend',
                'src'     => $this->get_base_url() . '/assets/js/grfi-frontend.js',
                'version' => $this->_version,
                'deps'    => array( 'jquery', 'gform_gravityforms' ),
                'enqueue' => array(
                    array( 'field_types' => $supported_types ),
                ),
            ),
        ) );
    }

    public function styles() {
        $supported_types = array(
            'text', 'textarea', 'select', 'checkbox',
            'radio', 'email', 'phone', 'website',
            'date', 'time', 'number',
        );

        return array_merge( parent::styles(), array(
            array(
                'handle'  => 'grfi-admin-css',
                'src'     => $this->get_base_url() . '/assets/css/grfi-admin.css',
                'version' => $this->_version,
                'enqueue' => array(
                    array( 'admin_page' => array( 'form_editor' ) ),
                ),
            ),
            array(
                'handle'  => 'grfi-frontend-css',
                'src'     => $this->get_base_url() . '/assets/css/grfi-frontend.css',
                'version' => $this->_version,
                'enqueue' => array(
                    array( 'field_types' => $supported_types ),
                ),
            ),
        ) );
    }

    // -------------------------------------------------------------------------
    // Frontend WP-context payload injection
    // -------------------------------------------------------------------------

    /** Collected per-form configs to output in wp_footer. */
    private $frontend_forms   = array();
    private $frontend_context = null;

    public function inject_wp_conditions_payload( $form ) {
        $configs = $this->get_form_require_if_configs( $form );
        if ( empty( $configs ) ) {
            return $form;
        }

        $form_id = (string) rgar( $form, 'id' );
        $this->frontend_forms[ $form_id ] = $configs;

        // Store the required indicator HTML for this form.
        if ( ! isset( $this->frontend_indicators ) ) {
            $this->frontend_indicators = array();
        }
        if ( method_exists( 'GFFormsModel', 'get_required_indicator' ) ) {
            $this->frontend_indicators[ $form_id ] = GFFormsModel::get_required_indicator( $form_id );
        } else {
            $this->frontend_indicators[ $form_id ] = '<span class="gfield_required gfield_required_asterisk">*</span>';
        }

        // Build WP context (once — same for every form on the page).
        if ( $this->frontend_context === null ) {
            $wp_context = array(
                'is_logged_in' => is_user_logged_in() ? 'true' : 'false',
                'user_role'    => '',
                'user_id'      => (string) get_current_user_id(),
                'page_id'      => (string) get_the_ID(),
                'url_params'   => array_map( 'sanitize_text_field', wp_unslash( $_GET ) ),
                'user_meta'    => array(),
                'custom'       => array(),
            );

            $user = wp_get_current_user();
            if ( $user && ! empty( $user->roles ) ) {
                $wp_context['user_role'] = $user->roles[0];
            }

            $this->frontend_context = $wp_context;
        }

        // Collect user-meta keys needed by this form.
        foreach ( $configs as $field_config ) {
            $rules = isset( $field_config['rules'] ) ? $field_config['rules'] : array();
            foreach ( $rules as $rule ) {
                if ( rgar( $rule, 'source' ) === 'wp_user_meta' && ! empty( $rule['metaKey'] ) ) {
                    $key = sanitize_text_field( $rule['metaKey'] );
                    if ( ! isset( $this->frontend_context['user_meta'][ $key ] ) ) {
                        $this->frontend_context['user_meta'][ $key ] = (string) get_user_meta( get_current_user_id(), $key, true );
                    }
                }
            }
        }

        // Collect custom condition values needed by this form.
        foreach ( $configs as $field_config ) {
            $rules = isset( $field_config['rules'] ) ? $field_config['rules'] : array();
            foreach ( $rules as $rule ) {
                if ( rgar( $rule, 'source' ) === 'custom' && ! empty( $rule['conditionKey'] ) ) {
                    $cond_key = sanitize_text_field( $rule['conditionKey'] );
                    if ( ! isset( $this->frontend_context['custom'][ $cond_key ] ) ) {
                        $result = apply_filters( 'grfi_evaluate_custom_condition', null, $cond_key, $rule );
                        $this->frontend_context['custom'][ $cond_key ] = ( $result !== null ) ? (string) $result : '';
                    }
                }
            }
        }

        return $form;
    }

    /**
     * Print the grfiData <script> block in wp_footer so it is available
     * before GF fires gform_post_render.
     */
    public function print_frontend_data() {
        if ( empty( $this->frontend_forms ) ) {
            return;
        }

        $payload_forms      = wp_json_encode( $this->frontend_forms );
        $payload_context    = wp_json_encode( $this->frontend_context );
        $payload_indicators = wp_json_encode( isset( $this->frontend_indicators ) ? $this->frontend_indicators : new stdClass() );

        echo '<script>'
            . 'window.grfiData = window.grfiData || { forms: {}, wp_context: {}, indicators: {} };'
            . 'window.grfiData.forms = Object.assign( window.grfiData.forms, ' . $payload_forms . ' );'
            . 'window.grfiData.wp_context = ' . $payload_context . ';'
            . 'window.grfiData.indicators = Object.assign( window.grfiData.indicators || {}, ' . $payload_indicators . ' );'
            . '</script>' . "\n";
    }

    /**
     * Extract per-field requireIf configs from the form object.
     */
    private function get_form_require_if_configs( $form ) {
        $configs = array();
        if ( empty( $form['fields'] ) ) {
            return $configs;
        }
        foreach ( $form['fields'] as $field ) {
            $require_if = rgar( $field, 'requireIf' );
            if ( empty( $require_if['enabled'] ) || empty( $require_if['rules'] ) ) {
                continue;
            }
            $configs[ (string) $field->id ] = $require_if;
        }
        return $configs;
    }

    /**
     * Return WP roles keyed by slug => display name for the admin JS.
     */
    public static function get_wp_roles_for_js() {
        $roles  = array();
        $wp_roles = wp_roles();
        foreach ( $wp_roles->roles as $slug => $details ) {
            $roles[] = array(
                'value' => $slug,
                'label' => translate_user_role( $details['name'] ),
            );
        }
        return $roles;
    }
}
