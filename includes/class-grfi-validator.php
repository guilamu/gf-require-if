<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hooks into gform_pre_validation to dynamically set isRequired
 * based on the field's requireIf configuration.
 */
class GRFI_Validator {

    /**
     * Supported target field types for v1.
     */
    private static $supported_target_types = array(
        'text', 'textarea', 'email', 'phone', 'number',
        'select', 'radio', 'checkbox', 'date', 'time', 'website',
    );

    /**
     * Filter callback for gform_pre_validation.
     *
     * @param array $form GF form object.
     * @return array Modified form object.
     */
    public static function evaluate_required_states( $form ) {
        if ( empty( $form['fields'] ) ) {
            return $form;
        }

        foreach ( $form['fields'] as &$field ) {
            $require_if = rgar( $field, 'requireIf' );

            if ( empty( $require_if['enabled'] ) || empty( $require_if['rules'] ) ) {
                continue;
            }

            if ( ! self::is_supported_target_field( $field ) ) {
                continue;
            }

            // Respect GF's own conditional-logic visibility.
            if ( GFFormsModel::is_field_hidden( $form, $field, array() ) ) {
                continue;
            }

            $result = self::evaluate_rules( $require_if, $form );

            // Only promote to required; never demote a natively required field.
            if ( $result ) {
                $field->isRequired = true;
            }
        }

        return $form;
    }

    /**
     * Evaluate the full set of rules with AND/OR logic.
     */
    private static function evaluate_rules( $config, $form ) {
        $logic_type = rgar( $config, 'logicType', 'all' );
        $rules      = rgar( $config, 'rules', array() );
        $results    = array();

        foreach ( $rules as $rule ) {
            $source = rgar( $rule, 'source' );
            if ( $source === 'gf_field' ) {
                $results[] = GRFI_Evaluator_GF::evaluate( $rule, $form );
            } else {
                $results[] = GRFI_Evaluator_WP::evaluate( $rule );
            }
        }

        if ( empty( $results ) ) {
            return false;
        }

        if ( $logic_type === 'any' ) {
            return in_array( true, $results, true );
        }

        // 'all' — every rule must be true.
        return ! in_array( false, $results, true );
    }

    private static function is_supported_target_field( $field ) {
        return in_array( $field->type, self::$supported_target_types, true );
    }
}
