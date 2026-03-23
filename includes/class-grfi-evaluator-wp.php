<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Evaluates WordPress-context rules (user state, page, URL params, custom).
 */
class GRFI_Evaluator_WP {

    /**
     * Evaluate a single WP-context rule.
     *
     * @param array $rule Rule definition.
     * @return bool
     */
    public static function evaluate( $rule ) {
        $source   = rgar( $rule, 'source' );
        $operator = rgar( $rule, 'operator', 'is' );
        $expected = rgar( $rule, 'value' );
        $actual   = self::get_actual_value( $source, $rule );

        return self::compare( $actual, $expected, $operator );
    }

    /**
     * Resolve the actual runtime value for the given source type.
     */
    private static function get_actual_value( $source, $rule ) {
        switch ( $source ) {
            case 'wp_user_logged_in':
                return is_user_logged_in() ? 'true' : 'false';

            case 'wp_user_role':
                $user = wp_get_current_user();
                return ( ! empty( $user->roles ) ) ? $user->roles[0] : '';

            case 'wp_user_id':
                return (string) get_current_user_id();

            case 'wp_user_meta':
                $meta_key = sanitize_text_field( rgar( $rule, 'metaKey' ) );
                if ( empty( $meta_key ) ) {
                    return '';
                }
                return (string) get_user_meta( get_current_user_id(), $meta_key, true );

            case 'wp_page':
                return (string) get_the_ID();

            case 'url_param':
                $param_key = sanitize_text_field( rgar( $rule, 'paramKey' ) );
                if ( empty( $param_key ) ) {
                    return '';
                }
                return isset( $_GET[ $param_key ] )
                    ? sanitize_text_field( wp_unslash( $_GET[ $param_key ] ) )
                    : '';

            case 'custom':
                $condition_key = sanitize_text_field( rgar( $rule, 'conditionKey' ) );
                $result = apply_filters( 'grfi_evaluate_custom_condition', null, $condition_key, $rule );
                return ( $result !== null ) ? (string) $result : '';

            default:
                return '';
        }
    }

    /**
     * Simple two-operator comparison for WP rules.
     */
    private static function compare( $actual, $expected, $operator ) {
        switch ( $operator ) {
            case 'is':
                return (string) $actual === (string) $expected;
            case 'isnot':
                return (string) $actual !== (string) $expected;
            default:
                return false;
        }
    }
}
