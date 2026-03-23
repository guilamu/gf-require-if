<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Evaluates a single GF-field-based rule against submitted POST data.
 */
class GRFI_Evaluator_GF {

    /**
     * Supported source field types for v1.
     */
    private static $supported_types = array(
        'text', 'textarea', 'email', 'phone', 'number',
        'select', 'radio', 'checkbox', 'date', 'time', 'website',
    );

    /**
     * Evaluate a single gf_field rule.
     *
     * @param array $rule Rule definition from requireIf config.
     * @param array $form GF form object.
     * @return bool
     */
    public static function evaluate( $rule, $form ) {
        $field_id = rgar( $rule, 'fieldId' );
        $operator = rgar( $rule, 'operator', 'is' );
        $expected = rgar( $rule, 'value' );

        $source_field = GFFormsModel::get_field( $form, $field_id );

        if ( ! $source_field || ! self::is_supported_source_field( $source_field ) ) {
            return false;
        }

        $actual = self::get_submitted_value( $source_field );

        if ( $actual === null ) {
            return false;
        }

        // Checkbox special handling: check if expected value is in the selected values.
        if ( is_array( $actual ) ) {
            return self::match_array( $actual, $expected, $operator );
        }

        return self::fallback_match( $actual, $expected, $operator );
    }

    private static function is_supported_source_field( $field ) {
        return in_array( $field->type, self::$supported_types, true );
    }

    /**
     * Extract the submitted value from $_POST for the given field.
     *
     * @param GF_Field $field
     * @return string|array|null
     */
    private static function get_submitted_value( $field ) {
        if ( $field->type === 'checkbox' ) {
            $values = array();
            if ( is_array( $field->inputs ) ) {
                foreach ( $field->inputs as $input ) {
                    $input_id = rgar( $input, 'id' );
                    $posted   = rgpost( 'input_' . str_replace( '.', '_', $input_id ) );
                    if ( $posted !== null && $posted !== '' ) {
                        $values[] = $posted;
                    }
                }
            }
            return $values;
        }

        $input_name = 'input_' . str_replace( '.', '_', (string) $field->id );
        return rgpost( $input_name );
    }

    /**
     * Match when the actual value is an array (e.g. checkbox).
     */
    private static function match_array( $actual_values, $expected, $operator ) {
        switch ( $operator ) {
            case 'is':
                return in_array( $expected, $actual_values );
            case 'isnot':
                return ! in_array( $expected, $actual_values );
            case 'contains':
                foreach ( $actual_values as $v ) {
                    if ( stripos( $v, $expected ) !== false ) {
                        return true;
                    }
                }
                return false;
            default:
                return false;
        }
    }

    /**
     * Fallback comparison when GFFormsModel::is_value_match() is not available.
     */
    private static function fallback_match( $actual, $expected, $operator ) {
        switch ( $operator ) {
            case 'is':
                return (string) $actual === (string) $expected;
            case 'isnot':
                return (string) $actual !== (string) $expected;
            case '>':
                return (float) $actual > (float) $expected;
            case '<':
                return (float) $actual < (float) $expected;
            case 'contains':
                return stripos( $actual, $expected ) !== false;
            case 'starts_with':
                return stripos( $actual, $expected ) === 0;
            case 'ends_with':
                $len = strlen( $expected );
                if ( $len === 0 ) {
                    return true;
                }
                return substr( strtolower( $actual ), -$len ) === strtolower( $expected );
            default:
                return false;
        }
    }
}
