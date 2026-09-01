<?php
/**
 * The "Settings" tab: the tools that configure or diagnose the plugin itself,
 * as opposed to the core "where are comments off?" choice on the first tab.
 *
 * Rendered INSIDE the same <form> as partials/_disable.php — see views/settings.php.
 * That is not incidental. apply_settings() runs with $preserve_existing = false
 * for a settings-screen save, so it clears every option the payload omits. Split
 * these panels into a form of their own and saving one tab would wipe the
 * other's settings; the conditional rules below would be the first casualty.
 *
 * Each panel is a top-level section (.disable__comment__option + .title +
 * .subtitle), matching "Settings" and "Disable Comments With API" on the first
 * tab. They used to be small <h4> sub-labels stacked inside that tab, which
 * read as minor items rather than features.
 *
 * @package Disable_Comments
 */
?>

<?php if (!is_network_admin()): ?>
    <!-- Conditional Rules -->
    <?php
    // Read the stored rules directly, not get_conditional_rules(),
    // which returns nothing while the feature is switched off. Rendering
    // blanks there would submit blanks on the next save and
    // sanitize_conditional_rules() would drop the rules — turning a
    // temporary "off" into silent deletion of a configuration the user
    // never touched.
    $dc_rules            = isset($this->options['conditional_rules']) ? array_values((array) $this->options['conditional_rules']) : array();
    $dc_rules_enabled    = !empty($this->options['enable_conditional_rules']);
    $dc_auto_close_days  = isset($this->options['auto_close_days']) ? (int) $this->options['auto_close_days'] : 0;

    // The stored format is a list of rules. This screen edits the first
    // taxonomy rule and the first template rule; WP-CLI and the
    // settings import can write as many as they like, and any extras
    // are preserved untouched below.
    $dc_tax_rule      = array();
    $dc_template_rule = array();
    $dc_extra_rules   = array();
    foreach ($dc_rules as $dc_rule) {
        if ('taxonomy' === $dc_rule['type'] && empty($dc_tax_rule)) {
            $dc_tax_rule = $dc_rule;
        } elseif ('template' === $dc_rule['type'] && empty($dc_template_rule)) {
            $dc_template_rule = $dc_rule;
        } else {
            $dc_extra_rules[] = $dc_rule;
        }
    }

    $dc_taxonomies = get_taxonomies(array('public' => true, 'show_ui' => true), 'objects');
    ?>
    <div class="disable__comment__option mb50" role="group" aria-labelledby="conditional-rules-heading">
        <h3 id="conditional-rules-heading" class="title">
            <?php esc_html_e('Conditional Rules', 'disable-comments'); ?>
        </h3>
        <p class="subtitle">
            <?php esc_html_e('Close or reopen comments on individual posts by category, page template or age, on top of the settings on the Disable Comments tab.', 'disable-comments'); ?>
        </p>

        <div id="conditional_rules_wrapper" class="disable_option dc-text__block mb30 mt30">

        <div class="dissable__switch__item">
            <input type="hidden" name="enable_conditional_rules" value="0">
            <input type="checkbox"
                name="enable_conditional_rules"
                id="enable_conditional_rules"
                value="1"
                aria-controls="conditional_rules_fields"
                <?php checked($dc_rules_enabled); ?>>

            <label for="enable_conditional_rules">
                <span class="switch" role="presentation" tabindex="0">
                    <span class="switch__text on" aria-hidden="true"><?php esc_html_e('On', 'disable-comments'); ?></span>
                    <span class="switch__text off" aria-hidden="true"><?php esc_html_e('Off', 'disable-comments'); ?></span>
                </span>
                <?php esc_html_e('Add Conditional Rules', 'disable-comments'); ?>
            </label>
        </div>

        <div id="conditional_rules_fields" <?php echo $dc_rules_enabled ? '' : 'hidden'; ?>>

            <?php // Any rules beyond the one-of-each this screen edits ride along untouched. ?>
            <?php foreach ($dc_extra_rules as $dc_i => $dc_extra): ?>
                <input type="hidden" name="conditional_rules[extra<?php echo (int) $dc_i; ?>][type]" value="<?php echo esc_attr($dc_extra['type']); ?>">
                <input type="hidden" name="conditional_rules[extra<?php echo (int) $dc_i; ?>][action]" value="<?php echo esc_attr($dc_extra['action']); ?>">
                <?php if ('taxonomy' === $dc_extra['type']): ?>
                    <input type="hidden" name="conditional_rules[extra<?php echo (int) $dc_i; ?>][taxonomy]" value="<?php echo esc_attr($dc_extra['taxonomy']); ?>">
                    <?php foreach ((array) $dc_extra['terms'] as $dc_term_id): ?>
                        <input type="hidden" name="conditional_rules[extra<?php echo (int) $dc_i; ?>][terms][]" value="<?php echo (int) $dc_term_id; ?>">
                    <?php endforeach; ?>
                <?php else: ?>
                    <input type="hidden" name="conditional_rules[extra<?php echo (int) $dc_i; ?>][template]" value="<?php echo esc_attr($dc_extra['template']); ?>">
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Taxonomy rule -->
            <div class="mb10">
                <input type="hidden" name="conditional_rules[taxonomy][type]" value="taxonomy">

                <label for="conditional_rule_taxonomy_action">
                    <?php esc_html_e('For posts in these terms:', 'disable-comments'); ?>
                </label>

                <select id="conditional_rule_taxonomy_action" name="conditional_rules[taxonomy][action]">
                    <option value="disable" <?php selected(isset($dc_tax_rule['action']) ? $dc_tax_rule['action'] : 'disable', 'disable'); ?>>
                        <?php esc_html_e('Disable comments', 'disable-comments'); ?>
                    </option>
                    <option value="enable" <?php selected(isset($dc_tax_rule['action']) ? $dc_tax_rule['action'] : '', 'enable'); ?>>
                        <?php esc_html_e('Keep comments open (exception)', 'disable-comments'); ?>
                    </option>
                </select>

                <label for="conditional_rule_taxonomy" class="visually-hidden">
                    <?php esc_html_e('Taxonomy', 'disable-comments'); ?>
                </label>
                <select id="conditional_rule_taxonomy" name="conditional_rules[taxonomy][taxonomy]">
                    <option value=""><?php esc_html_e('— Select a taxonomy —', 'disable-comments'); ?></option>
                    <?php foreach ($dc_taxonomies as $dc_tax): ?>
                        <option value="<?php echo esc_attr($dc_tax->name); ?>"
                            <?php selected(isset($dc_tax_rule['taxonomy']) ? $dc_tax_rule['taxonomy'] : '', $dc_tax->name); ?>>
                            <?php echo esc_html($dc_tax->labels->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="conditional_rule_terms" class="visually-hidden">
                    <?php esc_html_e('Terms', 'disable-comments'); ?>
                </label>
                <?php // dc-select2 matches the roles picker: removable chips, and de-selecting is a click on the chip's x rather than an undiscoverable ctrl-click. ?>
                <select id="conditional_rule_terms"
                    class="dc-select2"
                    name="conditional_rules[taxonomy][terms][]"
                    multiple
                    aria-describedby="conditional-rules-description">
                    <?php
                    // Only the terms this rule already uses are rendered.
                    // Everything else is searched for on demand, so a taxonomy
                    // with more terms than one list can hold is reachable in
                    // full — this used to render the first 200 and stop, and
                    // terms after those 200 could not be picked at all.
                    //
                    // The saved ones still have to be here: an option that is
                    // not rendered is not submitted, so the rule would come back
                    // with no terms and be dropped on save, silently destroying
                    // a rule nobody touched.
                    if (!empty($dc_tax_rule['taxonomy']) && taxonomy_exists($dc_tax_rule['taxonomy'])) {
                        $dc_selected = array_values(array_unique(array_filter(array_map('intval', (array) $dc_tax_rule['terms']))));

                        if (!empty($dc_selected)) {
                            $dc_terms = get_terms(array(
                                'taxonomy'   => $dc_tax_rule['taxonomy'],
                                'hide_empty' => false,
                                'include'    => $dc_selected,
                                'orderby'    => 'name',
                            ));
                            $dc_terms = is_wp_error($dc_terms) ? array() : $dc_terms;

                            foreach ($dc_terms as $dc_term) {
                                printf(
                                    '<option value="%1$s" selected>%2$s</option>',
                                    (int) $dc_term->term_id,
                                    esc_html($dc_term->name)
                                );
                            }
                        }
                    }
                    ?>
                </select>
            </div>

            <!-- Template rule -->
            <div class="mb10">
                <input type="hidden" name="conditional_rules[template][type]" value="template">

                <label for="conditional_rule_template_action">
                    <?php esc_html_e('For content using this page template:', 'disable-comments'); ?>
                </label>

                <select id="conditional_rule_template_action" name="conditional_rules[template][action]">
                    <option value="disable" <?php selected(isset($dc_template_rule['action']) ? $dc_template_rule['action'] : 'disable', 'disable'); ?>>
                        <?php esc_html_e('Disable comments', 'disable-comments'); ?>
                    </option>
                    <option value="enable" <?php selected(isset($dc_template_rule['action']) ? $dc_template_rule['action'] : '', 'enable'); ?>>
                        <?php esc_html_e('Keep comments open (exception)', 'disable-comments'); ?>
                    </option>
                </select>

                <label for="conditional_rule_template" class="visually-hidden">
                    <?php esc_html_e('Page template', 'disable-comments'); ?>
                </label>
                <select id="conditional_rule_template" name="conditional_rules[template][template]">
                    <option value=""><?php esc_html_e('— No template rule —', 'disable-comments'); ?></option>
                    <?php
                    $dc_saved_template = isset($dc_template_rule['template']) ? $dc_template_rule['template'] : '';
                    $dc_templates      = wp_get_theme()->get_page_templates(null, 'page');
                    $dc_seen_saved     = false;
                    foreach ($dc_templates as $dc_file => $dc_name) {
                        if ($dc_file === $dc_saved_template) {
                            $dc_seen_saved = true;
                        }
                        printf(
                            '<option value="%1$s" %2$s>%3$s</option>',
                            esc_attr($dc_file),
                            selected($dc_saved_template, $dc_file, false),
                            esc_html($dc_name)
                        );
                    }
                    // A template saved against a theme that has since changed
                    // must stay selectable, or saving this screen would
                    // silently drop the rule.
                    if (!$dc_seen_saved && '' !== $dc_saved_template) {
                        printf(
                            '<option value="%1$s" selected>%2$s</option>',
                            esc_attr($dc_saved_template),
                            /* translators: %s: page template file name. */
                            esc_html(sprintf(__('%s (not in the active theme)', 'disable-comments'), $dc_saved_template))
                        );
                    }
                    ?>
                </select>
            </div>

            <!-- Auto close -->
            <div class="mb10">
                <label for="auto_close_days">
                    <?php esc_html_e('Close comments on content older than', 'disable-comments'); ?>
                </label>
                <input type="number"
                    id="auto_close_days"
                    name="auto_close_days"
                    min="0"
                    step="1"
                    value="<?php echo (int) $dc_auto_close_days; ?>"
                    aria-describedby="conditional-rules-description">
                <?php esc_html_e('days (0 = no age limit)', 'disable-comments'); ?>
            </div>
        </div>

        <p id="conditional-rules-description" class="disable__option__description mt10">
            <span class="danger" aria-hidden="true"><?php esc_html_e('Note:', 'disable-comments'); ?></span>
            <?php esc_html_e('Rules are applied per post, on top of the settings on the Disable Comments tab. An exception keeps comments open even where those settings would close them, and it also survives the age limit.', 'disable-comments'); ?>
        </p>
        </div>
    </div>

    <!-- Blocked attempts -->
    <?php if ($this->blocked_stats_enabled()):
        $dc_blocked = $this->get_blocked_stats(); ?>
        <div class="disable__comment__option mb50" role="group" aria-labelledby="blocked-attempts-heading">
            <h3 id="blocked-attempts-heading" class="title">
                <?php esc_html_e('Blocked Attempts', 'disable-comments'); ?>
            </h3>
            <p class="subtitle">
                <?php esc_html_e('How much this plugin has actually turned away, broken down by where the attempt came from.', 'disable-comments'); ?>
            </p>

            <div id="blocked_attempts_wrapper" class="disable_option dc-text__block mb30 mt30">

            <p class="disable__option__description" aria-live="polite" id="blocked_attempts_summary">
                <?php
                printf(
                    /* translators: 1: number of blocked attempts, 2: date counting started. */
                    esc_html__('%1$s attempts blocked since %2$s.', 'disable-comments'),
                    '<strong>' . esc_html(number_format_i18n($dc_blocked['total'])) . '</strong>',
                    esc_html(date_i18n(get_option('date_format'), $dc_blocked['since']))
                );
                ?>
            </p>

            <ul class="blocked-attempts-list">
                <?php foreach ($this->get_blocked_vectors() as $dc_vector => $dc_label): ?>
                    <li>
                        <span class="blocked-attempts-label"><?php echo esc_html($dc_label); ?></span>
                        <span class="blocked-attempts-count" data-vector="<?php echo esc_attr($dc_vector); ?>">
                            <?php echo esc_html(number_format_i18n($dc_blocked['counts'][$dc_vector])); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="disable__option__description mt10">
                <?php esc_html_e('Comments blocked over XML-RPC are not counted: that method is removed from the server rather than rejected, so there is no request to observe.', 'disable-comments'); ?>
            </p>

            <button type="button" class="button" id="reset_blocked_stats">
                <?php esc_html_e('Reset counters', 'disable-comments'); ?>
            </button>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

    <!-- Settings portability -->
    <div class="disable__comment__option mb50" role="group" aria-labelledby="settings-portability-heading">
        <h3 id="settings-portability-heading" class="title">
            <?php esc_html_e('Copy These Settings To Another Site', 'disable-comments'); ?>
        </h3>
        <p class="subtitle">
            <?php esc_html_e('Download this configuration as a file, then import it on your next site instead of setting everything up again.', 'disable-comments'); ?>
        </p>

        <div id="settings_portability_wrapper" class="disable_option dc-text__block mb30 mt30">

        <?php
        // Two labelled groups rather than one undifferentiated row. Export is a
        // single action; import is a three-step sequence whose later steps
        // unlock as you go, and a flat row of four controls gave no clue that an
        // order was intended.
        ?>
        <div class="dc-portability">
            <div class="dc-portability__group">
                <span class="dc-portability__group-label"><?php esc_html_e('Export', 'disable-comments'); ?></span>
                <div class="dc-portability__actions">
                    <button type="button" class="button dc-portability__action" id="export_dc_settings">
                        <?php esc_html_e('Download settings', 'disable-comments'); ?>
                    </button>
                </div>
            </div>

            <div class="dc-portability__group">
                <span class="dc-portability__group-label"><?php esc_html_e('Import', 'disable-comments'); ?></span>
                <div class="dc-portability__actions">
                    <label class="dc-portability__file" for="import_dc_settings_file">
                        <span class="dc-portability__file-button"><?php esc_html_e('Choose file', 'disable-comments'); ?></span>
                        <span class="dc-portability__file-name" id="import_dc_settings_filename"><?php esc_html_e('No file chosen', 'disable-comments'); ?></span>
                        <input type="file"
                            id="import_dc_settings_file"
                            class="dc-portability__file-input"
                            accept="application/json,.json"
                            aria-describedby="import_dc_settings_hint">
                    </label>

                    <button type="button" class="button dc-portability__action" id="import_dc_settings_preview" disabled>
                        <?php esc_html_e('Preview import', 'disable-comments'); ?>
                    </button>

                    <button type="button" class="button dc-portability__action" id="import_dc_settings_apply" disabled>
                        <?php esc_html_e('Apply import', 'disable-comments'); ?>
                    </button>
                </div>
            </div>

            <p class="dc-portability__hint" id="import_dc_settings_hint" aria-live="polite">
                <?php esc_html_e('Choose a settings file to preview what it would change.', 'disable-comments'); ?>
            </p>
        </div>

        <div id="import_dc_settings_result" class="mt10" aria-live="polite"></div>
        </div>
    </div>

<?php if (!is_network_admin()): ?>
    <!-- Theme conflict scanner -->
    <div class="disable__comment__option mb50" role="group" aria-labelledby="theme-scanner-heading">
        <h3 id="theme-scanner-heading" class="title">
            <?php esc_html_e('Still Seeing a Comment Form?', 'disable-comments'); ?>
        </h3>
        <p class="subtitle">
            <?php esc_html_e('If comments are off but the form is still showing on your site, this checks a real page and tells you what is putting it there.', 'disable-comments'); ?>
        </p>

        <div id="theme_scanner_wrapper" class="disable_option dc-text__block mb30 mt30">

        <button type="button" class="button" id="run_theme_scan">
            <?php esc_html_e('Check my theme', 'disable-comments'); ?>
        </button>

        <div id="theme_scan_result" class="mt10" aria-live="polite"></div>
        </div>
    </div>
<?php endif; ?>

    <!-- Save Button -->
    <button type="submit"
        class="button button__success button__fade"
        aria-label="<?php esc_attr_e('Save all disable comments settings', 'disable-comments'); ?>" tabindex="0">
        <span><?php esc_html_e('Save Changes', 'disable-comments'); ?></span>
    </button>
