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
     * Lowercase a value the same way the frontend engine does (String().toLowerCase()),
     * so client and server always agree on a match.
     */
    private static function lower( $value ) {
        $value = (string) $value;
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
    }

    /**
     * Match when the actual value is an array (e.g. checkbox).
     * Mirrors matchArray() in grfi-frontend.js — case-insensitive.
     */
    private static function match_array( $actual_values, $expected, $operator ) {
        $lower = array_map( array( __CLASS__, 'lower' ), $actual_values );
        $e     = self::lower( $expected );

        switch ( $operator ) {
            case 'is':
                return in_array( $e, $lower, true );
            case 'isnot':
                return ! in_array( $e, $lower, true );
            case 'contains':
                foreach ( $lower as $v ) {
                    if ( strpos( $v, $e ) !== false ) {
                        return true;
                    }
                }
                return false;
            default:
                return false;
        }
    }

    /**
     * Scalar comparison. Mirrors matchValue() in grfi-frontend.js — string
     * operators are case-insensitive on both sides.
     */
    private static function fallback_match( $actual, $expected, $operator ) {
        $a = self::lower( $actual );
        $e = self::lower( $expected );

        switch ( $operator ) {
            case 'is':
                return $a === $e;
            case 'isnot':
                return $a !== $e;
            case '>':
                return (float) $actual > (float) $expected;
            case '<':
                return (float) $actual < (float) $expected;
            case 'contains':
                return strpos( $a, $e ) !== false;
            case 'starts_with':
                return strpos( $a, $e ) === 0;
            case 'ends_with':
                $len = strlen( $e );
                if ( $len === 0 ) {
                    return true;
                }
                return substr( $a, -$len ) === $e;
            default:
                return false;
        }
    }
}
