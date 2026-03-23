/**
 * Gravity Forms Require If — Form Editor Admin Script
 *
 * Accordion row + flyout panel pattern, matching GF's native Conditional Logic
 * and gf-ifonly UI. Config object `grfiConfig` is defined by inline script
 * in gform_editor_js before this file loads.
 *
 * @package GF_Require_If
 */
( function( $ ) {
    'use strict';

    /**
     * Boot function — called by the inline script after grfiConfig is set.
     */
    window.grfiBoot = function() {
        if ( typeof grfiConfig === 'undefined' ) {
            return;
        }

    var S     = grfiConfig.strings || {};
    var ROLES = grfiConfig.wp_roles || [];

    /* -----------------------------------------------------------------------
     * Sources, operators, supported types
     * -------------------------------------------------------------------- */
    var ALL_SOURCES = [
        { value: 'gf_field',          label: S.srcGfField   || 'Form Field',         group: 'gf'  },
        { value: 'wp_user_logged_in', label: S.srcLoggedIn  || 'User Logged In',     group: 'wp'  },
        { value: 'wp_user_role',      label: S.srcUserRole  || 'User Role',          group: 'wp'  },
        { value: 'wp_user_id',        label: S.srcUserId    || 'User ID',            group: 'wp'  },
        { value: 'wp_user_meta',      label: S.srcUserMeta  || 'User Meta',          group: 'wp'  },
        { value: 'wp_page',           label: S.srcPage      || 'Page / Post ID',     group: 'wp'  },
        { value: 'url_param',         label: S.srcUrlParam  || 'URL Parameter',      group: 'wp'  },
        { value: 'custom',            label: S.srcCustom    || 'Custom (Developer)', group: 'dev' }
    ];

    var GF_OPERATORS = {
        'is':          S.opIs         || 'is',
        'isnot':       S.opIsNot      || 'is not',
        '>':           S.opGt         || 'greater than',
        '<':           S.opLt         || 'less than',
        'contains':    S.opContains   || 'contains',
        'starts_with': S.opStartsWith || 'starts with',
        'ends_with':   S.opEndsWith   || 'ends with'
    };

    var WP_OPERATORS = {
        'is':    S.opIs    || 'is',
        'isnot': S.opIsNot || 'is not'
    };

    // Operators that force a text input instead of choices dropdown.
    var TEXT_OPERATORS = [ 'contains', 'starts_with', 'ends_with', '>', '<' ];

    var SUPPORTED_SOURCE_TYPES = [
        'text', 'textarea', 'email', 'phone', 'number',
        'select', 'radio', 'checkbox', 'date', 'time', 'website'
    ];

    /* -----------------------------------------------------------------------
     * Template engine (matches GF / gf-ifonly renderView)
     * -------------------------------------------------------------------- */
    function renderView( html, container, config, echo ) {
        var parsed = html;
        for ( var key in config ) {
            if ( ! config.hasOwnProperty( key ) ) continue;
            var rgx = new RegExp( '\\{\\{\\s*' + key + '\\s*\\}\\}', 'g' );
            parsed = parsed.replace( rgx, config[ key ] );
        }
        if ( ! echo ) return parsed;
        if ( container ) container.innerHTML = parsed;
        return true;
    }

    function esc( str ) {
        var el = document.createElement( 'span' );
        el.textContent = str;
        return el.innerHTML;
    }

    /* -----------------------------------------------------------------------
     * HTML Templates (inline — matching GF/gf-ifonly markup)
     * -------------------------------------------------------------------- */
    var TPL_ACCORDION = [
        '<div class="conditional_logic_accordion {{ toggle_class }}">',
            '<div class="conditional_logic_accordion__label">{{ title }}</div>',
            '<div class="conditional_logic_accordion__status_indicator">',
                '<span class="gform-status-indicator gform-status-indicator--size-sm gform-status-indicator--theme-cosmos gform-status--no-hover {{ active_class }}">',
                    '<span class="gform-status-indicator-status gform-typography--weight-medium gform-typography--size-text-xs">{{ active_text }}</span>',
                '</span>',
            '</div>',
            '<div class="conditional_logic_accordion__toggle {{ toggle_class }}">',
                '<button class="conditional_logic_accordion__toggle_button grfi_accordion__toggle_button" type="button">',
                    '<span class="screen-reader-text">{{ toggleText }}</span>',
                    '<i class="conditional_logic_accordion__toggle_button_icon" aria-hidden="true"></i>',
                '</button>',
            '</div>',
            '<div class="conditional_logic_accordion__desc {{ desc_class }}">{{ desc }}</div>',
        '</div>'
    ].join( '\n' );

    var TPL_FLYOUT = [
        '<aside class="conditional_logic_flyout grfi_flyout">',
            '<button class="conditional_logic_flyout__close" data-js-grfi-close type="button">',
                '<span class="screen-reader-text">Close</span>',
                '<i class="conditional_logic_flyout__close_icon" data-js-grfi-close></i>',
            '</button>',
            '<header class="conditional_logic_flyout__head">',
                '<div class="conditional_logic_flyout__title">{{ flyoutTitle }}</div>',
                '<div class="conditional_logic_flyout__desc">{{ flyoutDesc }}</div>',
            '</header>',
            '<article class="conditional_logic_flyout__body panel-block-tabs__body--settings" data-js="gform-simplebar">',
                '<div class="conditional_logic_flyout__toggle">',
                    '<span class="conditional_logic_flyout__toggle_label">{{ enableLabel }}</span>',
                    '<div class="conditional_logic_flyout__toggle_input gform-field__toggle">',
                        '<span class="gform-settings-input__container">',
                            '<input type="checkbox" class="gform-field__toggle-input" data-js-grfi-toggle id="grfi_toggle_{{ fieldId }}" {{ checked }}>',
                            '<label class="gform-field__toggle-container" for="grfi_toggle_{{ fieldId }}">',
                                '<span class="gform-field__toggle-switch-text screen-reader-text">{{ enabledText }}</span>',
                                '<span class="gform-field__toggle-switch"></span>',
                            '</label>',
                        '</span>',
                    '</div>',
                '</div>',
                '<div class="conditional_logic_flyout__main grfi_flyout__main">{{ main }}</div>',
            '</article>',
        '</aside>'
    ].join( '\n' );

    var TPL_MAIN = [
        '<fieldset class="conditional-flyout__main-fields {{ enabledClass }}">',
            '<div class="conditional_logic_flyout__action">',
                '{{ makeRequired }} ',
                '<select data-js-grfi-state="logicType">',
                    '<option value="all" {{ allSelected }}>{{ matchAll }}</option>',
                    '<option value="any" {{ anySelected }}>{{ matchAny }}</option>',
                '</select> ',
                '{{ ofTheFollowing }}',
            '</div>',
            '<div class="grfi_flyout__rules"></div>',
        '</fieldset>'
    ].join( '\n' );

    var TPL_RULE = [
        '<div class="conditional_logic_flyout__rule grfi_rule" data-grfi-rule="{{ rule_idx }}">',
            '{{ sourceMarkup }}',
            '{{ extraMarkup }}',
            '{{ operatorMarkup }}',
            '{{ valueMarkup }}',
            '<div class="conditional_logic_flyout__rule-controls">',
                '<button type="button" class="add_field_choice gform-st-icon gform-st-icon--circle-plus" data-js-grfi-add-rule title="{{ addRuleText }}"></button>',
                '<button type="button" class="delete_field_choice gform-st-icon gform-st-icon--circle-minus {{ deleteClass }}" data-js-grfi-delete-rule title="{{ removeRuleText }}"></button>',
            '</div>',
        '</div>'
    ].join( '\n' );

    var TPL_OPTION = '<option value="{{ value }}" {{ selected }}>{{ label }}</option>';

    /* -----------------------------------------------------------------------
     * GRFIFlyout controller
     * -------------------------------------------------------------------- */
    function GRFIFlyout() {
        this.fieldId = null;
        this.field   = null;
        this.form    = null;
        this.visible = false;
        this.state   = null;

        this.els = {
            sidebar: document.getElementById( 'grfi-sidebar-field' ),
            flyout:  document.getElementById( 'grfi_flyout_container' )
        };

        this.relocateFlyout();

        this._handleSidebarClick = this.handleSidebarClick.bind( this );
        this._handleFlyoutClick  = this.handleFlyoutClick.bind( this );
        this._handleFlyoutChange = this.handleFlyoutChange.bind( this );
        this._handleFlyoutInput  = this.handleFlyoutInput.bind( this );
        this._handleBodyClick    = this.handleBodyClick.bind( this );
        this._handleKeydown      = this.handleKeydown.bind( this );

        this.addGlobalListeners();
    }

    /* -- Initialization --------------------------------------------------- */

    GRFIFlyout.prototype.relocateFlyout = function() {
        // Move sidebar setting just before the native CL wrapper to avoid the gap.
        var settingEl     = document.getElementById( 'grfi_field_setting' );
        var nativeWrapper = settingEl && settingEl.parentNode && settingEl.parentNode.closest( '.conditional_logic_wrapper' );
        if ( nativeWrapper && nativeWrapper.parentNode ) {
            nativeWrapper.parentNode.insertBefore( settingEl, nativeWrapper );
        }

        // Move flyout container next to GF's native flyout.
        if ( ! this.els.flyout ) return;
        var nativeContainer = document.getElementById( 'conditional_logic_flyout_container' );
        if ( nativeContainer && nativeContainer.parentNode ) {
            nativeContainer.parentNode.insertBefore( this.els.flyout, nativeContainer );
        }
    };

    GRFIFlyout.prototype.addGlobalListeners = function() {
        if ( this.els.sidebar ) {
            this.els.sidebar.addEventListener( 'click', this._handleSidebarClick );
        }
        if ( this.els.flyout ) {
            this.els.flyout.addEventListener( 'click', this._handleFlyoutClick );
            this.els.flyout.addEventListener( 'change', this._handleFlyoutChange );
            this.els.flyout.addEventListener( 'input', this._handleFlyoutInput );
        }
        document.body.addEventListener( 'click', this._handleBodyClick );
        document.addEventListener( 'keydown', this._handleKeydown );
    };

    GRFIFlyout.prototype.loadField = function( field, form ) {
        if ( this.visible && this.fieldId !== field.id ) {
            this.hideFlyout();
        }

        this.field   = field;
        this.fieldId = field.id;
        this.form    = form;
        this.state   = this.getStateForField( field );

        this.renderSidebar();
        this.renderFlyout();

        if ( this.visible ) {
            this.renderRules();
        }
    };

    /* -- State ------------------------------------------------------------ */

    GRFIFlyout.prototype.getDefaultRule = function() {
        return { source: 'gf_field', fieldId: '', operator: 'is', value: '' };
    };

    GRFIFlyout.prototype.getDefaultState = function() {
        return { enabled: false, logicType: 'all', rules: [ this.getDefaultRule() ] };
    };

    GRFIFlyout.prototype.getStateForField = function( field ) {
        var ri = field.requireIf;
        if ( ! ri || typeof ri !== 'object' || ! ri.rules ) {
            return this.getDefaultState();
        }
        if ( ! ( 'enabled' in ri ) ) ri.enabled = true;
        return ri;
    };

    GRFIFlyout.prototype.updateForm = function() {
        if ( ! this.field ) return;
        this.field.requireIf = this.state.enabled ? this.state : null;
        if ( typeof SetFieldProperty === 'function' ) {
            SetFieldProperty( 'requireIf', this.field.requireIf );
        }
    };

    /* -- Render: sidebar accordion ---------------------------------------- */

    GRFIFlyout.prototype.renderSidebar = function() {
        if ( ! this.els.sidebar ) return;

        var hasFields = typeof GetFirstRuleField === 'function' && GetFirstRuleField() > 0;

        var config = {
            title:        S.title || 'Conditional Required',
            toggleText:   ( S.configure || 'Configure' ) + ' ' + ( S.title || 'Conditional Required' ),
            active_class: this.state.enabled ? 'gform-status--active' : '',
            active_text:  this.state.enabled ? ( S.active || 'Active' ) : ( S.inactive || 'Inactive' ),
            desc_class:   hasFields ? '' : 'active',
            toggle_class: hasFields ? 'active' : '',
            desc:         S.helperText || ''
        };

        renderView( TPL_ACCORDION, this.els.sidebar, config, true );
    };

    /* -- Render: flyout --------------------------------------------------- */

    GRFIFlyout.prototype.renderFlyout = function() {
        if ( ! this.els.flyout ) return;

        var config = {
            flyoutTitle: S.flyoutTitle || 'Configure Conditional Required',
            flyoutDesc:  S.flyoutDesc  || '',
            enableLabel: ( S.enable || 'Enable' ) + ' ' + ( S.title || 'Conditional Required' ),
            fieldId:     this.fieldId,
            checked:     this.state.enabled ? 'checked' : '',
            enabledText: this.state.enabled ? ( S.enabled || 'Enabled' ) : ( S.disabled || 'Disabled' ),
            main:        this.renderMainControls()
        };

        renderView( TPL_FLYOUT, this.els.flyout, config, true );
    };

    GRFIFlyout.prototype.renderMainControls = function() {
        var config = {
            enabledClass:   this.state.enabled ? 'active' : '',
            allSelected:    this.state.logicType === 'all' ? 'selected="selected"' : '',
            anySelected:    this.state.logicType === 'any' ? 'selected="selected"' : '',
            matchAll:       S.matchAll || 'ALL',
            matchAny:       S.matchAny || 'ANY',
            makeRequired:   S.makeRequired || 'Make required if',
            ofTheFollowing: S.ofTheFollowing || 'of the following match:',
            addRuleText:    S.addRule || 'add another rule'
        };

        return renderView( TPL_MAIN, null, config, false );
    };

    /* -- Render: rules ---------------------------------------------------- */

    GRFIFlyout.prototype.renderRules = function() {
        var container = this.els.flyout.querySelector( '.grfi_flyout__rules' );
        if ( ! container ) return;

        var html = '';
        for ( var i = 0; i < this.state.rules.length; i++ ) {
            html += this.renderRule( this.state.rules[ i ], i );
        }
        container.innerHTML = html;

        this.syncValuesFromDOM();
    };

    GRFIFlyout.prototype.syncValuesFromDOM = function() {
        var self  = this;
        var rules = this.els.flyout.querySelectorAll( '[data-grfi-rule]' );
        rules.forEach( function( ruleEl ) {
            var ri  = parseInt( ruleEl.dataset.grfiRule, 10 );
            var val = ruleEl.querySelector( '[data-js-grfi-rule="value"]' );
            if ( val && self.state.rules[ ri ] ) {
                self.state.rules[ ri ].value = val.value;
            }
        } );
    };

    GRFIFlyout.prototype.renderRule = function( rule, ruleIdx ) {
        var source = rule.source || 'gf_field';
        var operators = this.getOperatorsForSource( source );

        var config = {
            rule_idx:       ruleIdx,
            sourceMarkup:   this.renderSourceSelect( rule ),
            extraMarkup:    this.renderExtraInput( rule ),
            operatorMarkup: this.renderOperatorOptions( operators, rule.operator ),
            valueMarkup:    this.renderRuleValue( rule ),
            addRuleText:    S.addRule || 'add another rule',
            deleteClass:    this.state.rules.length > 1 ? 'active' : '',
            removeRuleText: S.removeRule || 'remove this rule'
        };

        return renderView( TPL_RULE, null, config, false );
    };

    /* -- Render helpers --------------------------------------------------- */

    GRFIFlyout.prototype.getOperatorsForSource = function( source ) {
        if ( source === 'gf_field' ) return GF_OPERATORS;
        return WP_OPERATORS;
    };

    GRFIFlyout.prototype.renderSourceSelect = function( rule ) {
        var html = '<select data-js-grfi-rule="source" class="gfield_rule_select">';
        var lastGroup = '';
        for ( var i = 0; i < ALL_SOURCES.length; i++ ) {
            var s = ALL_SOURCES[ i ];
            if ( s.group !== lastGroup ) {
                if ( lastGroup ) html += '</optgroup>';
                var label = s.group === 'gf' ? 'Gravity Forms' : ( s.group === 'wp' ? 'WordPress' : 'Developer' );
                html += '<optgroup label="' + esc( label ) + '">';
                lastGroup = s.group;
            }
            html += renderView( TPL_OPTION, null, {
                value:    esc( s.value ),
                label:    esc( s.label ),
                selected: s.value === rule.source ? 'selected="selected"' : ''
            }, false );
        }
        html += '</optgroup></select>';
        return html;
    };

    /**
     * Extra input for sources that need a key (meta key, param name, condition key).
     * For gf_field source, renders the field selector.
     */
    GRFIFlyout.prototype.renderExtraInput = function( rule ) {
        var source = rule.source || 'gf_field';

        switch ( source ) {
            case 'gf_field':
                return this.renderFieldSelect( rule );
            case 'wp_user_meta':
                return '<input type="text" data-js-grfi-rule="metaKey" class="gfield_rule_select gfield_rule_input active" value="' +
                    esc( rule.metaKey || '' ) + '" placeholder="' + esc( S.metaKey || 'Meta key' ) + '">';
            case 'url_param':
                return '<input type="text" data-js-grfi-rule="paramKey" class="gfield_rule_select gfield_rule_input active" value="' +
                    esc( rule.paramKey || '' ) + '" placeholder="' + esc( S.paramName || 'Parameter name' ) + '">';
            case 'custom':
                return '<input type="text" data-js-grfi-rule="conditionKey" class="gfield_rule_select gfield_rule_input active" value="' +
                    esc( rule.conditionKey || '' ) + '" placeholder="' + esc( S.condKey || 'Condition key' ) + '">';
            default:
                return '';
        }
    };

    GRFIFlyout.prototype.renderFieldSelect = function( rule ) {
        var fields = window.form ? window.form.fields : [];
        var html   = '<select data-js-grfi-rule="fieldId" class="gfield_rule_select">';
        html += renderView( TPL_OPTION, null, { value: '', label: esc( S.selectField || '— Select a field —' ), selected: '' }, false );

        for ( var i = 0; i < fields.length; i++ ) {
            var f = fields[ i ];
            if ( String( f.id ) === String( this.fieldId ) ) continue;
            if ( SUPPORTED_SOURCE_TYPES.indexOf( f.type ) === -1 ) continue;

            var label = f.adminLabel || f.label || ( 'Field ' + f.id );
            html += renderView( TPL_OPTION, null, {
                value:    esc( String( f.id ) ),
                label:    esc( label + ' (ID: ' + f.id + ')' ),
                selected: String( f.id ) === String( rule.fieldId ) ? 'selected="selected"' : ''
            }, false );
        }
        html += '</select>';
        return html;
    };

    GRFIFlyout.prototype.renderOperatorOptions = function( operators, selectedOp ) {
        var html = '<select data-js-grfi-rule="operator" class="gfield_rule_select">';
        for ( var key in operators ) {
            if ( ! operators.hasOwnProperty( key ) ) continue;
            html += renderView( TPL_OPTION, null, {
                value:    esc( key ),
                label:    esc( operators[ key ] ),
                selected: key === selectedOp ? 'selected="selected"' : ''
            }, false );
        }
        html += '</select>';
        return html;
    };

    GRFIFlyout.prototype.renderRuleValue = function( rule ) {
        var source   = rule.source || 'gf_field';
        var operator = rule.operator || 'is';

        // Bool dropdown for logged-in
        if ( source === 'wp_user_logged_in' ) {
            return this.renderBoolSelect( rule.value );
        }

        // Role dropdown
        if ( source === 'wp_user_role' ) {
            return this.renderRoleSelect( rule.value );
        }

        // GF field with choices (and not a text operator)
        if ( source === 'gf_field' && rule.fieldId && TEXT_OPERATORS.indexOf( operator ) === -1 ) {
            var field = this.getFieldById( rule.fieldId );
            if ( field && field.choices && field.choices.length ) {
                return this.renderChoicesSelect( field, rule.value );
            }
        }

        // Default: text input
        return '<input type="text" data-js-grfi-rule="value" class="gfield_rule_select gfield_rule_input active" value="' +
            esc( rule.value || '' ) + '" placeholder="' + esc( S.enterValue || 'Enter a value' ) + '">';
    };

    GRFIFlyout.prototype.renderBoolSelect = function( selectedValue ) {
        var html = '<select data-js-grfi-rule="value" class="gfield_rule_select gfield_rule_value_dropdown_cl active">';
        html += renderView( TPL_OPTION, null, { value: 'true',  label: esc( S.valYes || 'Yes' ), selected: selectedValue === 'true'  ? 'selected="selected"' : '' }, false );
        html += renderView( TPL_OPTION, null, { value: 'false', label: esc( S.valNo  || 'No' ),  selected: selectedValue === 'false' ? 'selected="selected"' : '' }, false );
        html += '</select>';
        return html;
    };

    GRFIFlyout.prototype.renderRoleSelect = function( selectedValue ) {
        var html = '<select data-js-grfi-rule="value" class="gfield_rule_select gfield_rule_value_dropdown_cl active">';
        for ( var i = 0; i < ROLES.length; i++ ) {
            html += renderView( TPL_OPTION, null, {
                value:    esc( ROLES[ i ].value ),
                label:    esc( ROLES[ i ].label ),
                selected: ROLES[ i ].value === selectedValue ? 'selected="selected"' : ''
            }, false );
        }
        html += '</select>';
        return html;
    };

    GRFIFlyout.prototype.renderChoicesSelect = function( field, selectedValue ) {
        var html = '<select data-js-grfi-rule="value" class="gfield_rule_select gfield_rule_value_dropdown_cl active">';
        for ( var i = 0; i < field.choices.length; i++ ) {
            var c   = field.choices[ i ];
            var val = c.value !== undefined ? String( c.value ) : String( c.text );
            html += renderView( TPL_OPTION, null, {
                value:    esc( val ),
                label:    esc( c.text || val ),
                selected: val === String( selectedValue ) ? 'selected="selected"' : ''
            }, false );
        }
        html += '</select>';
        return html;
    };

    GRFIFlyout.prototype.getFieldById = function( fieldId ) {
        if ( typeof GetFieldById === 'function' ) return GetFieldById( fieldId );
        var id = parseInt( fieldId, 10 );
        for ( var i = 0; i < form.fields.length; i++ ) {
            if ( form.fields[ i ].id == id ) return form.fields[ i ];
        }
        return null;
    };

    /* -- Flyout show / hide (matching GF animation) ---------------------- */

    GRFIFlyout.prototype.showFlyout = function() {
        var flyout = this.els.flyout;
        flyout.classList.remove( 'anim-out-ready', 'anim-out-active' );
        flyout.classList.add( 'anim-in-ready' );

        window.setTimeout( function() {
            flyout.classList.add( 'anim-in-active' );
        }, 25 );

        this.visible = true;
        this.renderRules();
    };

    GRFIFlyout.prototype.hideFlyout = function() {
        var flyout = this.els.flyout;
        if ( ! flyout.classList.contains( 'anim-in-active' ) ) return;

        flyout.classList.remove( 'anim-in-ready', 'anim-in-active' );
        flyout.classList.add( 'anim-out-ready' );

        window.setTimeout( function() {
            flyout.classList.add( 'anim-out-active' );
        }, 25 );

        window.setTimeout( function() {
            flyout.classList.remove( 'anim-out-ready', 'anim-out-active' );
        }, 215 );

        this.visible = false;
    };

    GRFIFlyout.prototype.toggleFlyout = function() {
        this.renderFlyout();
        if ( this.visible ) {
            this.hideFlyout();
        } else {
            this.showFlyout();
        }
    };

    /* -- Event handlers --------------------------------------------------- */

    GRFIFlyout.prototype.handleSidebarClick = function( e ) {
        if (
            e.target.classList.contains( 'grfi_accordion__toggle_button' ) ||
            e.target.classList.contains( 'conditional_logic_accordion__toggle_button_icon' )
        ) {
            e.stopPropagation();
            this.toggleFlyout();
        }
    };

    GRFIFlyout.prototype.handleFlyoutClick = function( e ) {
        e.stopPropagation();
        var target = e.target;

        // Enable toggle
        if ( 'jsGrfiToggle' in target.dataset ) {
            this.state.enabled = target.checked;
            this.renderSidebar();
            this.renderFlyout();
            if ( this.visible ) this.renderRules();
            this.updateForm();
            return;
        }

        // Close button
        if ( 'jsGrfiClose' in target.dataset ) {
            this.toggleFlyout();
            return;
        }

        // Add rule
        if ( 'jsGrfiAddRule' in target.dataset ) {
            this.state.rules.push( this.getDefaultRule() );
            this.renderRules();
            this.updateForm();
            return;
        }

        // Delete rule
        if ( 'jsGrfiDeleteRule' in target.dataset ) {
            var ruleEl = target.closest( '[data-grfi-rule]' );
            if ( ruleEl ) {
                var ri = parseInt( ruleEl.dataset.grfiRule, 10 );
                this.state.rules.splice( ri, 1 );
                if ( this.state.rules.length === 0 ) {
                    this.state.rules.push( this.getDefaultRule() );
                }
                this.renderRules();
                this.updateForm();
            }
            return;
        }
    };

    GRFIFlyout.prototype.handleFlyoutChange = function( e ) {
        var target = e.target;

        // Logic type (all / any)
        if ( 'jsGrfiState' in target.dataset ) {
            this.state[ target.dataset.jsGrfiState ] = target.value;
            this.updateForm();
            return;
        }

        // Rule property
        if ( 'jsGrfiRule' in target.dataset ) {
            var ruleEl = target.closest( '[data-grfi-rule]' );
            if ( ! ruleEl ) return;

            var ri  = parseInt( ruleEl.dataset.grfiRule, 10 );
            var key = target.dataset.jsGrfiRule;
            var val = target.value;

            this.state.rules[ ri ][ key ] = val;

            // Re-render on source / fieldId / operator change
            if ( key === 'source' || key === 'fieldId' || key === 'operator' ) {
                // Reset dependent keys on source change
                if ( key === 'source' ) {
                    this.state.rules[ ri ] = { source: val, operator: 'is', value: '' };
                }
                this.renderRules();
            }

            this.updateForm();
            return;
        }
    };

    GRFIFlyout.prototype.handleFlyoutInput = function( e ) {
        var target = e.target;
        if ( 'jsGrfiRule' in target.dataset ) {
            var ruleEl = target.closest( '[data-grfi-rule]' );
            if ( ! ruleEl ) return;
            var ri  = parseInt( ruleEl.dataset.grfiRule, 10 );
            var key = target.dataset.jsGrfiRule;
            this.state.rules[ ri ][ key ] = target.value;
            this.updateForm();
        }
    };

    GRFIFlyout.prototype.handleBodyClick = function( e ) {
        if ( ! this.visible ) return;
        if ( this.els.flyout.contains( e.target ) || this.els.sidebar.contains( e.target ) ) return;
        this.hideFlyout();
    };

    GRFIFlyout.prototype.handleKeydown = function( e ) {
        if ( this.visible && e.which === 27 ) {
            e.preventDefault();
            this.hideFlyout();
        }
    };

    /* -- Boot ------------------------------------------------------------- */

    window.grfiInstance = new GRFIFlyout();

    // Replay pending field load.
    if ( window._grfiPending ) {
        window.grfiInstance.loadField( window._grfiPending.field, window._grfiPending.form );
        delete window._grfiPending;
    }

    }; // end grfiBoot

    // Auto-boot if inline config already ran.
    if ( typeof grfiConfig !== 'undefined' ) {
        window.grfiBoot();
    }

} )( jQuery );
