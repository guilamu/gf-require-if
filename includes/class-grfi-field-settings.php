<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Injects the "Conditional Required" accordion row + flyout into the GF
 * form-editor field settings — same pattern as gf-ifonly.
 *
 * - gform_field_advanced_settings (pos 550): sidebar accordion container
 * - gform_editor_js: flyout container + inline config/boot script
 */
class GRFI_Field_Settings {

    /**
     * Supported target field types (v1 boundary).
     */
    private static $supported_types = array(
        'text', 'textarea', 'email', 'phone', 'number',
        'select', 'radio', 'checkbox', 'date', 'time', 'website',
    );

    public static function init() {
        add_action( 'gform_field_advanced_settings', array( __CLASS__, 'render_field_setting' ), 10, 2 );
        add_action( 'gform_editor_js', array( __CLASS__, 'editor_js_init' ) );
    }

    /**
     * Render the sidebar accordion container at position 550 (after Conditional Logic).
     */
    public static function render_field_setting( $position, $form_id ) {
        if ( 550 !== (int) $position ) {
            return;
        }
        ?>
        <div class="grfi_require_if_setting field_setting conditional_logic_wrapper" id="grfi_field_setting">
            <div id="grfi-sidebar-field">
                <!-- Accordion rendered by JS -->
            </div>
        </div>
        <?php
    }

    /**
     * Output the flyout container, localized config, and boot wiring.
     */
    public static function editor_js_init() {
        $supported = wp_json_encode( self::$supported_types );

        $config = array(
            'strings' => array(
                'title'         => esc_html__( 'Conditional Required', 'gf-require-if' ),
                'configure'     => esc_html__( 'Configure', 'gf-require-if' ),
                'active'        => esc_html__( 'Active', 'gf-require-if' ),
                'inactive'      => esc_html__( 'Inactive', 'gf-require-if' ),
                'enable'        => esc_html__( 'Enable', 'gf-require-if' ),
                'enabled'       => esc_html__( 'Enabled', 'gf-require-if' ),
                'disabled'      => esc_html__( 'Disabled', 'gf-require-if' ),
                'flyoutTitle'   => esc_html__( 'Configure Conditional Required', 'gf-require-if' ),
                'flyoutDesc'    => esc_html__( 'Make this field required when conditions match.', 'gf-require-if' ),
                'matchAll'      => esc_html__( 'ALL', 'gf-require-if' ),
                'matchAny'      => esc_html__( 'ANY', 'gf-require-if' ),
                'makeRequired'  => esc_html__( 'Make required if', 'gf-require-if' ),
                'ofTheFollowing' => esc_html__( 'of the following match:', 'gf-require-if' ),
                'addRule'       => esc_html__( 'add another rule', 'gf-require-if' ),
                'removeRule'    => esc_html__( 'remove this rule', 'gf-require-if' ),
                'enterValue'    => esc_html__( 'Enter a value', 'gf-require-if' ),
                'helperText'    => esc_html__( 'To use conditional required, first create fields that support conditional logic.', 'gf-require-if' ),
                'metaKey'       => esc_html__( 'Meta key', 'gf-require-if' ),
                'paramName'     => esc_html__( 'Parameter name', 'gf-require-if' ),
                'condKey'       => esc_html__( 'Condition key', 'gf-require-if' ),
                'selectField'   => esc_html__( '— Select a field —', 'gf-require-if' ),
                // Sources
                'srcGfField'    => esc_html__( 'Form Field', 'gf-require-if' ),
                'srcLoggedIn'   => esc_html__( 'User Logged In', 'gf-require-if' ),
                'srcUserRole'   => esc_html__( 'User Role', 'gf-require-if' ),
                'srcUserId'     => esc_html__( 'User ID', 'gf-require-if' ),
                'srcUserMeta'   => esc_html__( 'User Meta', 'gf-require-if' ),
                'srcPage'       => esc_html__( 'Page / Post ID', 'gf-require-if' ),
                'srcUrlParam'   => esc_html__( 'URL Parameter', 'gf-require-if' ),
                'srcCustom'     => esc_html__( 'Custom (Developer)', 'gf-require-if' ),
                // Operators
                'opIs'          => esc_html__( 'is', 'gf-require-if' ),
                'opIsNot'       => esc_html__( 'is not', 'gf-require-if' ),
                'opGt'          => esc_html__( 'greater than', 'gf-require-if' ),
                'opLt'          => esc_html__( 'less than', 'gf-require-if' ),
                'opContains'    => esc_html__( 'contains', 'gf-require-if' ),
                'opStartsWith'  => esc_html__( 'starts with', 'gf-require-if' ),
                'opEndsWith'    => esc_html__( 'ends with', 'gf-require-if' ),
                // Bool values
                'valYes'        => esc_html__( 'Yes', 'gf-require-if' ),
                'valNo'         => esc_html__( 'No', 'gf-require-if' ),
            ),
            'wp_roles' => GF_Require_If::get_wp_roles_for_js(),
        );
        ?>
        <div class="conditional_logic_flyout_container" id="grfi_flyout_container">
            <!-- Require If flyout rendered by JS -->
        </div>
        <script type="text/javascript">
            var grfiConfig = <?php echo wp_json_encode( $config ); ?>;

            // Boot the external JS now that grfiConfig is defined.
            if ( typeof window.grfiBoot === 'function' ) {
                window.grfiBoot();
            }

            // Register grfi_require_if_setting in fieldSettings for supported types.
            jQuery( document ).ready( function() {
                var supported = <?php echo $supported; ?>;
                if ( typeof fieldSettings !== 'undefined' ) {
                    for ( var i = 0; i < supported.length; i++ ) {
                        var type = supported[ i ];
                        if ( typeof fieldSettings[ type ] !== 'undefined' ) {
                            fieldSettings[ type ] += ', .grfi_require_if_setting';
                        }
                    }
                }

                if ( typeof window.grfiInstance === 'undefined' && typeof window.grfiBoot === 'function' ) {
                    window.grfiBoot();
                }
            });

            // Load state when a field is selected.
            jQuery( document ).on( 'gform_load_field_settings', function( event, field, form ) {
                if ( typeof window.grfiInstance !== 'undefined' ) {
                    window.grfiInstance.loadField( field, form );
                } else {
                    window._grfiPending = { field: field, form: form };
                }
            });
        </script>
        <?php
    }
}
