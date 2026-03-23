/**
 * Gravity Forms Require If — Frontend Runtime Engine
 *
 * Evaluates conditional-required rules on the client side and toggles the
 * required indicator (asterisk + aria-required) in real time.
 *
 * Data is available in `window.grfiData` (injected per-form via wp_add_inline_script):
 *   grfiData.forms[ formId ][ fieldId ] = { enabled, logicType, rules }
 *   grfiData.wp_context = { is_logged_in, user_role, user_id, page_id, url_params, user_meta, custom }
 */
( function( $ ) {
    'use strict';

    var DEBUG = true;

    function log() {
        if ( DEBUG && window.console && console.log ) {
            var args = Array.prototype.slice.call( arguments );
            args.unshift( '[GRFI]' );
            console.log.apply( console, args );
        }
    }

    log( 'Script loaded. grfiData =', window.grfiData );
    log( 'gform available =', typeof gform !== 'undefined' );

    function onFormRender( formId ) {
        log( 'onFormRender called, formId =', formId );
        evaluateAll( formId );
        bindInputListeners( formId );
    }

    /* -----------------------------------------------------------------------
     * GF action hooks
     * -------------------------------------------------------------------- */
    if ( typeof gform !== 'undefined' && gform.addAction ) {
        log( 'Registering gform.addAction hooks' );
        gform.addAction( 'gform_post_render', onFormRender );

        gform.addAction( 'gform_post_conditional_logic', function( formId ) {
            log( 'gform_post_conditional_logic fired, formId =', formId );
            evaluateAll( formId );
        } );
    } else {
        log( 'WARNING: gform object not available — addAction hooks NOT registered' );
    }

    // jQuery event fallback (GF fires both the gform action and jQuery trigger)
    $( document ).on( 'gform_post_render', function( event, formId ) {
        log( 'jQuery gform_post_render fired, formId =', formId );
        onFormRender( formId );
    } );

    // DOM-ready fallback: evaluate any forms already on the page in case
    // gform_post_render fired before this script loaded.
    $( function() {
        log( 'DOM ready fallback. grfiData =', window.grfiData );
        if ( window.grfiData && window.grfiData.forms ) {
            $.each( window.grfiData.forms, function( formId ) {
                log( 'DOM ready: triggering for formId =', formId );
                onFormRender( parseInt( formId, 10 ) );
            } );
        } else {
            log( 'DOM ready: No grfiData.forms found' );
        }
    } );

    /* -----------------------------------------------------------------------
     * Bind change / input listeners for the form (once per form)
     * -------------------------------------------------------------------- */
    var boundForms = {};

    function bindInputListeners( formId ) {
        if ( boundForms[ formId ] ) {
            return;
        }
        boundForms[ formId ] = true;

        $( '#gform_wrapper_' + formId ).on(
            'change input',
            'input, select, textarea',
            function() {
                evaluateAll( formId );
            }
        );
    }

    /* -----------------------------------------------------------------------
     * Evaluate all fields for a given form
     * -------------------------------------------------------------------- */
    function evaluateAll( formId ) {
        var data = window.grfiData;
        log( 'evaluateAll formId =', formId, 'data =', data );
        if ( ! data || ! data.forms || ! data.forms[ formId ] ) {
            log( 'evaluateAll: no data for formId', formId, '— available keys:', data && data.forms ? Object.keys( data.forms ) : 'none' );
            return;
        }

        var fields = data.forms[ formId ];
        log( 'evaluateAll: fields to check =', Object.keys( fields ) );
        $.each( fields, function( fieldId, requireIf ) {
            if ( ! requireIf || ! requireIf.enabled ) {
                log( 'Field', fieldId, ': skipped (not enabled)', requireIf );
                return; // continue
            }

            var $field = $( '#field_' + formId + '_' + fieldId );
            if ( ! $field.length ) {
                log( 'Field', fieldId, ': DOM element #field_' + formId + '_' + fieldId + ' NOT found' );
                return;
            }

            // Skip hidden fields (GF conditional logic hides them)
            if ( isFieldHidden( $field ) ) {
                log( 'Field', fieldId, ': hidden — skipping' );
                return;
            }

            var result = evaluateRules( requireIf, formId );
            log( 'Field', fieldId, ': evaluateRules result =', result, 'config =', requireIf );
            toggleRequired( formId, fieldId, result, $field );
        } );
    }

    /* -----------------------------------------------------------------------
     * Check whether GF has hidden the field container
     * -------------------------------------------------------------------- */
    function isFieldHidden( $field ) {
        // GF hides fields by setting display:none on the container
        if ( $field.css( 'display' ) === 'none' ) {
            return true;
        }
        // GF 2.5+ may also use a specific class
        if ( $field.hasClass( 'gfield_hidden_product' ) ) {
            return true;
        }
        return false;
    }

    /* -----------------------------------------------------------------------
     * Evaluate the rule set (AND / OR)
     * -------------------------------------------------------------------- */
    function evaluateRules( requireIf, formId ) {
        var rules     = requireIf.rules || [];
        var logicType = requireIf.logicType || 'all'; // 'all' = AND, 'any' = OR

        if ( ! rules.length ) {
            return false;
        }

        for ( var i = 0; i < rules.length; i++ ) {
            var pass = evaluateSingleRule( rules[ i ], formId );

            if ( logicType === 'any' && pass ) {
                return true;
            }
            if ( logicType === 'all' && ! pass ) {
                return false;
            }
        }

        // Reached the end: for AND all passed; for OR none passed.
        return logicType === 'all';
    }

    /* -----------------------------------------------------------------------
     * Evaluate a single rule
     * -------------------------------------------------------------------- */
    function evaluateSingleRule( rule, formId ) {
        var source = rule.source || '';
        log( 'evaluateSingleRule: source =', source, 'rule =', JSON.stringify( rule ) );

        if ( source === 'gf_field' ) {
            var gfResult = evaluateGFRule( rule, formId );
            log( 'evaluateSingleRule: GF result =', gfResult );
            return gfResult;
        }

        // All other sources are WP / custom — values come from grfiData.wp_context
        var wpResult = evaluateWPRule( rule );
        log( 'evaluateSingleRule: WP result =', wpResult );
        return wpResult;
    }

    /* -----------------------------------------------------------------------
     * GF field rule: read the live DOM value and compare
     * -------------------------------------------------------------------- */
    function evaluateGFRule( rule, formId ) {
        var fieldId  = rule.fieldId;
        var operator = rule.operator || 'is';
        var expected = rule.value || '';

        if ( ! fieldId ) {
            log( 'evaluateGFRule: no fieldId in rule' );
            return false;
        }

        var actual = getFieldValue( formId, fieldId );
        log( 'evaluateGFRule: fieldId =', fieldId, 'actual =', JSON.stringify( actual ), 'operator =', operator, 'expected =', JSON.stringify( expected ) );
        var result = matchValue( actual, expected, operator );
        log( 'evaluateGFRule: matchValue =', result );
        return result;
    }

    /* -----------------------------------------------------------------------
     * Read the current DOM value for a given field
     * -------------------------------------------------------------------- */
    function getFieldValue( formId, fieldId ) {
        var container = '#field_' + formId + '_' + fieldId;

        // Radio buttons — check before the generic input lookup because
        // GF 2.5+ wraps radios in a <div id="input_{formId}_{fieldId}">
        // which would match the generic selector but return undefined from .val().
        var $radio = $( container + ' input[type="radio"]:checked' );
        if ( $radio.length ) {
            return $radio.val() || '';
        }
        // If radios exist but none is checked, return empty.
        if ( $( container + ' input[type="radio"]' ).length ) {
            return '';
        }

        // Checkboxes — return array of checked values
        var $checks = $( container + ' input[type="checkbox"]:checked' );
        if ( $checks.length ) {
            var vals = [];
            $checks.each( function() {
                vals.push( $( this ).val() );
            } );
            return vals;
        }
        if ( $( container + ' input[type="checkbox"]' ).length ) {
            return [];
        }

        // Select field
        var $select = $( container + ' select' );
        if ( $select.length ) {
            return $select.val() || '';
        }

        // Direct input (text, textarea, email, phone, number, website, date, time)
        var $input = $( '#input_' + formId + '_' + fieldId );
        if ( $input.is( 'input, textarea' ) ) {
            return $input.val() || '';
        }

        return '';
    }

    /* -----------------------------------------------------------------------
     * Match: compare actual vs expected using operator
     * -------------------------------------------------------------------- */
    function matchValue( actual, expected, operator ) {
        // Array handling (checkboxes)
        if ( Array.isArray( actual ) ) {
            return matchArray( actual, expected, operator );
        }

        var a = String( actual ).toLowerCase();
        var e = String( expected ).toLowerCase();

        switch ( operator ) {
            case 'is':          return a === e;
            case 'isnot':       return a !== e;
            case '>':           return parseFloat( actual ) > parseFloat( expected );
            case '<':           return parseFloat( actual ) < parseFloat( expected );
            case 'contains':    return a.indexOf( e ) !== -1;
            case 'starts_with': return a.indexOf( e ) === 0;
            case 'ends_with':   return a.length >= e.length && a.substring( a.length - e.length ) === e;
            default:            return false;
        }
    }

    function matchArray( values, expected, operator ) {
        var lower = [];
        for ( var i = 0; i < values.length; i++ ) {
            lower.push( String( values[ i ] ).toLowerCase() );
        }
        var e = String( expected ).toLowerCase();

        switch ( operator ) {
            case 'is':       return lower.indexOf( e ) !== -1;
            case 'isnot':    return lower.indexOf( e ) === -1;
            case 'contains':
                for ( var j = 0; j < lower.length; j++ ) {
                    if ( lower[ j ].indexOf( e ) !== -1 ) { return true; }
                }
                return false;
            default: return false;
        }
    }

    /* -----------------------------------------------------------------------
     * WP / Custom context rule
     * -------------------------------------------------------------------- */
    function evaluateWPRule( rule ) {
        var source   = rule.source || '';
        var operator = rule.operator || 'is';
        var expected = rule.value || '';
        var ctx      = ( window.grfiData && window.grfiData.wp_context ) ? window.grfiData.wp_context : {};
        var actual   = '';

        switch ( source ) {
            case 'wp_user_logged_in':
                actual = ctx.is_logged_in || 'false';
                break;
            case 'wp_user_role':
                actual = ctx.user_role || '';
                break;
            case 'wp_user_id':
                actual = ctx.user_id || '';
                break;
            case 'wp_page':
                actual = ctx.page_id || '';
                break;
            case 'wp_user_meta':
                var metaKey = rule.metaKey || '';
                actual = ( ctx.user_meta && ctx.user_meta[ metaKey ] ) || '';
                break;
            case 'url_param':
                var paramKey = rule.paramKey || '';
                actual = ( ctx.url_params && ctx.url_params[ paramKey ] ) || '';
                break;
            case 'custom':
                var condKey = rule.conditionKey || '';
                actual = ( ctx.custom && ctx.custom[ condKey ] ) || '';
                break;
            default:
                return false;
        }

        // WP rules only support is / isnot
        var a = String( actual );
        var e = String( expected );

        switch ( operator ) {
            case 'is':    return a === e;
            case 'isnot': return a !== e;
            default:      return false;
        }
    }

    /* -----------------------------------------------------------------------
     * Toggle required indicator on a field
     * -------------------------------------------------------------------- */
    function getIndicatorHtml( formId ) {
        var data = window.grfiData;
        if ( data && data.indicators && data.indicators[ formId ] ) {
            return data.indicators[ formId ];
        }
        // Fallback: asterisk
        return '<span class="gfield_required gfield_required_asterisk">*</span>';
    }

    function toggleRequired( formId, fieldId, isRequired, $field ) {
        var $label  = $field.find( '.gfield_label, .gfield_label_before_complex' ).first();
        var $inputs = $field.find( 'input, select, textarea' );

        if ( isRequired ) {
            // Add indicator only if one isn't already present
            if ( $label.find( '.gfield_required' ).length === 0 ) {
                var indicatorHtml = getIndicatorHtml( formId );
                // Wrap in our own span so we can remove it later
                $label.append(
                    '<span class="gfield_required grfi-injected">' + indicatorHtml + '</span>'
                );
            }
            $inputs.attr( 'aria-required', 'true' );
        } else {
            // Remove only our injected indicator — leave GF-native ones untouched
            $label.find( '.gfield_required.grfi-injected' ).remove();

            // Restore aria-required only if GF doesn't natively require the field
            if ( ! $field.hasClass( 'gfield_contains_required' ) ) {
                $inputs.removeAttr( 'aria-required' );
            }
        }
    }

} )( jQuery );
