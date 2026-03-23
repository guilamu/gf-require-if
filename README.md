# Gravity Forms Require If

Make the required state of supported Gravity Forms fields conditional — based on form field values, user roles, URL parameters, and more.

## Conditional Required Rules

- Set fields as required only when specific form field values match
- Combine multiple rules with **ALL** (AND) or **ANY** (OR) logic
- Evaluate against form fields, user roles, user meta, page/post ID, URL parameters, or custom developer conditions
- Supports operators: is, is not, greater than, less than, contains, starts with, ends with

## Real-Time Frontend Evaluation

- Required indicators appear/disappear instantly as users fill the form
- Respects the form's required indicator setting (text, asterisk, or custom)
- Works with AJAX-powered forms and multi-page forms
- Server-side validation ensures rules are enforced even with JavaScript disabled

## Native Form Editor Integration

- Accordion UI matches Gravity Forms' native Conditional Logic panel
- Flyout rule builder with familiar dropdown interface
- Works alongside GF's built-in conditional logic and third-party add-ons

## Key Features

- **Non-Destructive:** Only promotes fields to required; never demotes natively required fields
- **Multilingual:** Works with content in any language
- **Translation-Ready:** All admin strings are internationalized
- **Secure:** Server-side validation prevents bypassing via browser tools
- **GitHub Updates:** Automatic updates from GitHub releases

## Requirements

- Gravity Forms 2.5 or higher
- WordPress 6.0 or higher
- PHP 8.0 or higher

## Installation

1. Upload the `gravity-forms-require-if` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Open any form in the Gravity Forms editor
4. Select a supported field, then expand the **Conditional Required** accordion in the field settings sidebar
5. Toggle the feature on and configure your rules

## FAQ

### Which field types are supported?

Text, Textarea, Email, Phone, Number, Select, Radio, Checkbox, Date, Time, and Website.

### Can I combine multiple conditions?

Yes. Add multiple rules and choose **ALL** (every rule must match) or **ANY** (at least one must match).

### What happens if JavaScript is disabled?

The server-side validator (`gform_pre_validation`) enforces the same rules, so conditional required fields are validated regardless of JavaScript.

### Does it conflict with GF's native conditional logic?

No. If a field is hidden by GF's conditional logic, its "Require If" rules are skipped entirely.

### Can I add custom condition sources?

Yes, use the `grfi_wp_condition_sources` filter to register additional sources, and `grfi_evaluate_custom_condition` to handle evaluation:

```php
add_filter( 'grfi_wp_condition_sources', function( $sources ) {
    $sources['my_source'] = 'My Custom Source';
    return $sources;
} );

add_filter( 'grfi_evaluate_custom_condition', function( $result, $rule, $form, $field ) {
    if ( 'my_source' === $rule['source'] ) {
        return my_custom_check( $rule );
    }
    return $result;
}, 10, 4 );
```

### Does it work with page builders?

Yes. The frontend script initializes via multiple fallback methods (GF action hook, jQuery event, and DOM-ready) to ensure compatibility with Elementor, Bricks, Gutenberg, and other builders.

## Project Structure

```
.
├── gravity-forms-require-if.php          # Main plugin file & bootstrap
├── class-gf-require-if.php              # GFAddOn subclass (lifecycle, scripts, data injection)
├── uninstall.php                         # Cleanup on uninstall
├── README.md
├── assets
│   ├── css
│   │   ├── grfi-admin.css               # Form editor sidebar styles
│   │   └── grfi-frontend.css            # Frontend required indicator styles
│   └── js
│       ├── grfi-admin.js                # Form editor accordion & flyout rule builder
│       └── grfi-frontend.js             # Runtime conditional required evaluation
├── includes
│   ├── class-grfi-evaluator-gf.php      # GF field value evaluator
│   ├── class-grfi-evaluator-wp.php      # WordPress condition evaluator (roles, meta, etc.)
│   ├── class-grfi-field-settings.php    # Field settings sidebar UI (PHP)
│   ├── class-grfi-validator.php         # Server-side validation (gform_pre_validation)
│   └── class-github-updater.php         # GitHub auto-updates
└── languages
    ├── gf-require-if-fr_FR.po           # French translation (source)
    └── gf-require-if.pot                # Translation template
```

## Changelog

### 1.0.0
- Initial release
- Conditional required rules with AND/OR logic
- Form field, user role, user meta, page ID, URL parameter, and custom condition sources
- Real-time frontend evaluation with form-specific required indicators
- Server-side validation via `gform_pre_validation`
- Native accordion & flyout UI in the form editor
- GitHub auto-updater

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Made with love for the WordPress community
</p>
