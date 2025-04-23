<form id="disableCommentSaveSettings" action="#" aria-label="<?php esc_attr_e('Disable Comments Settings', 'disable-comments'); ?>">
    <div class="disable__comment__option mb50" role="group" aria-labelledby="settings-heading">
        <h3 id="settings-heading" class="title"><?php esc_html_e('Settings', 'disable-comments'); ?></h3>
        <p class="subtitle"><?php esc_html_e('Configure the settings below to disable comments globally or on specific types of posts.', 'disable-comments'); ?></p>

        <?php if (is_network_admin()): ?>
            <div class="disable_option dc-text__block mb30 mt30" role="group" aria-labelledby="sitewide-settings-heading">
                <h4 id="sitewide-settings-heading" class="visually-hidden"><?php esc_html_e('Site-wide Settings Control', 'disable-comments'); ?></h4>

                <div class="dissable__switch__item">
                    <input type="hidden" name="sitewide_settings" value="0">
                    <input type="checkbox"
                        name="sitewide_settings"
                        id="sitewide_settings"
                        value="1"
                        aria-describedby="sitewide-description"
                        <?php checked($this->options['sitewide_settings']); ?>>

                    <label for="sitewide_settings">
                        <span class="switch" role="presentation" tabindex="0">
                            <span class="switch__text on" aria-hidden="true"><?php esc_html_e('On', 'disable-comments'); ?></span>
                            <span class="switch__text off" aria-hidden="true"><?php esc_html_e('Off', 'disable-comments'); ?></span>
                        </span>
                        <?php esc_html_e('Enable Site Wise settings', 'disable-comments'); ?>
                    </label>

                    <p id="sitewide-description" class="disable__option__description">
                        <span class="danger" aria-hidden="true"><?php esc_html_e('Note:', 'disable-comments'); ?></span>
                        <?php esc_html_e('If you enable Site Wise settings, you need to configure your "Disable Comments" settings individually on every website in the network.', 'disable-comments'); ?>
                    </p>
                </div>
            </div>
            <div class="disable_option sites_list_wrapper dc-text__block mb30 mt30"
                data-type="disabled"
                role="region"
                aria-labelledby="sites-list-heading">

                <h3 id="sites-list-heading">
                    <?php esc_html_e('Disable comments in the following sites:', 'disable-comments'); ?>
                </h3>

                <?php
                $type = 'disabled';
                include DC_PLUGIN_VIEWS_PATH . 'partials/_sites.php';
                ?>

                <p id="sites-list-description"
                    class="disable__option__description">
                    <span class="danger" aria-hidden="true">
                        <?php esc_html_e('Note:', 'disable-comments'); ?>
                    </span>
                    <?php esc_html_e('All the underneath settings (except Avatar settings) will be applied for these selected sub sites.', 'disable-comments'); ?>
                </p>
            </div>

        <?php elseif ($this->options['sitewide_settings'] && !empty($this->options['is_network_options'])): ?>
            <div class="disable_option dc-text__block mb30 mt30"
                role="alert"
                aria-live="polite">
                <div>
                    <p class="disable__option__description">
                        <span class="danger" aria-hidden="true">
                            <?php esc_html_e('Note:', 'disable-comments'); ?>
                        </span>
                        <?php esc_html_e('This site will be controlled by the network admin until you click the "Save Changes" button.', 'disable-comments'); ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
        <div class="disable_option dc-text__block mb30 mt30" role="radiogroup" aria-labelledby="disable-everywhere-heading">
            <h4 id="disable-everywhere-heading" class="visually-hidden">
                <?php esc_html_e('Global Comment Settings', 'disable-comments'); ?>
            </h4>

            <div class="radio-option radio-style">
                <input type="radio"
                    id="remove_everywhere"
                    name="mode"
                    value="<?php echo esc_attr('remove_everywhere') ?>"
                    aria-describedby="everywhere-description"
                    <?php checked($this->options['remove_everywhere']); ?> />

                <label for="remove_everywhere">
                    <i class="icon" tabindex="0"></i>
                    <strong><?php esc_html_e('Everywhere:', 'disable-comments'); ?></strong>
                </label>

                <span class="description">
                    <?php esc_html_e('Disable comments globally on your entire website', 'disable-comments'); ?>
                </span>

                <p id="everywhere-description" class="disable__option__description">
                    <span class="danger" aria-hidden="true"><?php esc_html_e('Warning:', 'disable-comments'); ?></span>
                    <?php esc_html_e('This will disable comments from every page and post on your website. Use this setting if you do not want to show comments anywhere.', 'disable-comments'); ?>
                </p>
            </div>
        </div>
        <div class="disable_option dc-text__block" role="group" aria-labelledby="post-types-heading">
            <h4 id="post-types-heading" class="visually-hidden">
                <?php esc_html_e('Select Specific Post Types', 'disable-comments'); ?>
            </h4>

            <div class="radio-option radio-style">
                <input type="radio"
                    id="selected_types"
                    name="mode"
                    value="<?php echo esc_attr('selected_types'); ?>"
                    aria-expanded="<?php echo !$this->options['remove_everywhere'] ? 'true' : 'false'; ?>"
                    aria-controls="disable__post__types"
                    <?php checked(!$this->options['remove_everywhere']); ?> />

                <label for="selected_types">
                    <i class="icon" tabindex="0"></i>
                    <?php esc_html_e('On Specific Post Types:', 'disable-comments'); ?>
                </label>
            </div>

            <div id="disable__post__types"
                class="remove__checklist"
                role="group"
                aria-label="<?php esc_attr_e('Available post types', 'disable-comments'); ?>"
                <?php echo $this->options['remove_everywhere'] ? 'hidden aria-hidden="true"' : ''; ?>>

                <?php
                $types = $this->get_all_post_types();
                foreach ($types as $key => $value) : ?>
                    <div class="remove__checklist__item checkbox-style">
                        <input type="checkbox"
                            id="remove__checklist__item-<?php echo esc_attr($key); ?>"
                            name="disabled_types[]"
                            value="<?php echo esc_attr($key); ?>"
                            <?php checked(in_array($key, $this->get_disabled_post_types()), true, false); ?>>
                        <label for="remove__checklist__item-<?php echo esc_attr($key); ?>">
                            <i class="icon" tabindex="0"></i>
                            <?php echo esc_html($value->labels->name); ?>
                        </label>
                    </div>
                <?php endforeach; ?>

                <?php if ($this->networkactive && is_network_admin()) :
                    $this->options['extra_post_types'] = empty($this->options['extra_post_types']) ? [] : $this->options['extra_post_types'];
                    $extradisabletypes = implode(', ', (array) $this->options['extra_post_types']);
                ?>
                    <div class="custom-types-input" role="group" aria-labelledby="extratypes">
                        <p id="extratypes" class="indent subtitle">
                            <?php esc_html_e('Only the built-in post types appear above. If you want to disable comments on other custom post types on the entire network, you can supply a comma-separated list of post types below (use the slug that identifies the post type).', 'disable-comments'); ?>
                        </p>

                        <div class="form-field">
                            <label for="extra_post_types">
                                <strong><?php esc_html_e('Custom post types:', 'disable-comments'); ?></strong>
                            </label>
                            <input type="text"
                                id="extra_post_types"
                                class="form__control"
                                name="extra_post_types"
                                size="30"
                                value="<?php echo esc_attr($extradisabletypes); ?>"
                                aria-describedby="extratypes">
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <p id="post-types-note" class="subtitle">
                <span class="danger" aria-hidden="true"><?php esc_html_e('Note:', 'disable-comments'); ?></span>
                <?php esc_html_e('Disabling comments will also disable trackbacks and pingbacks. All comment-related fields will also be hidden from the edit/quick-edit screens of the affected posts. These settings cannot be overridden for individual posts. Comments will be visible on all other post types.', 'disable-comments'); ?>
            </p>
        </div>
        <?php if (!is_network_admin()): ?>
            <div id="exclude_by_role_wrapper"
                class="disable_option dc-text__block mb30 mt30"
                role="group"
                aria-labelledby="exclude-roles-heading">

                <h4 id="exclude-roles-heading" class="visually-hidden">
                    <?php esc_html_e('Role-based Exclusion Settings', 'disable-comments'); ?>
                </h4>

                <div class="dissable__switch__item">
                    <input type="hidden" name="enable_exclude_by_role" value="0">
                    <input type="checkbox"
                        name="enable_exclude_by_role"
                        id="enable_exclude_by_role"
                        value="1"
                        aria-controls="exclude_by_role_select_description_wrapper"
                        aria-expanded="false"
                        <?php checked(isset($this->options['enable_exclude_by_role']) ? $this->options['enable_exclude_by_role'] : false); ?>>

                    <label for="enable_exclude_by_role">
                        <span class="switch" role="presentation" tabindex="0">
                            <span class="switch__text on" aria-hidden="true"><?php esc_html_e('On', 'disable-comments'); ?></span>
                            <span class="switch__text off" aria-hidden="true"><?php esc_html_e('Off', 'disable-comments'); ?></span>
                        </span>
                        <?php esc_html_e('Exclude Disable Comments Settings Based On User Roles', 'disable-comments'); ?>
                    </label>
                </div>

                <div id="exclude_by_role_select_description_wrapper"
                    class="roles-selection-wrapper"
                    <?php echo !isset($this->options['enable_exclude_by_role']) || !$this->options['enable_exclude_by_role'] ? 'hidden' : ''; ?>>

                    <div id="exclude_by_role_select_wrapper" class="mb10">
                        <?php
                        $selected_roles = isset($this->options['exclude_by_role']) ? $this->options['exclude_by_role'] : [];
                        $roles = $this->get_roles($selected_roles);
                        ?>
                        <label for="exclude_by_role" class="visually-hidden">
                            <?php esc_html_e('Select user roles to exclude', 'disable-comments'); ?>
                        </label>
                        <select id="exclude_by_role"
                            class="dc-select2"
                            name="exclude_by_role[]"
                            data-options='<?php echo wp_json_encode($roles); ?>'
                            multiple
                            aria-describedby="roles-description">
                        </select>
                    </div>

                    <div class="roles-status" aria-live="polite">
                        <p class="disable__option__description description__roles excluded-roles" hidden></p>
                        <p class="disable__option__description description__roles included-roles" hidden></p>
                    </div>
                </div>

                <p id="roles-description" class="disable__option__description mt10">
                    <span class="danger" aria-hidden="true"><?php esc_html_e('Note:', 'disable-comments'); ?></span>
                    <?php esc_html_e('This will exclude all the above settings for the selected user roles.', 'disable-comments'); ?>
                </p>
            </div>

            <!-- Avatar Settings -->
            <div class="disable_option dc-text__block mt30"
                role="group"
                aria-labelledby="avatar-settings-heading">

                <h4 id="avatar-settings-heading" class="visually-hidden">
                    <?php esc_html_e('Avatar Display Settings', 'disable-comments'); ?>
                </h4>

                <div class="disable__switchs">
                    <div class="dissable__switch__item">
                        <input type="hidden" name="disable_avatar" value="0">
                        <input type="checkbox"
                            id="disable_avatar"
                            name="disable_avatar"
                            value="1"
                            aria-describedby="avatar-description"
                            <?php checked(!get_option('show_avatars', false)); ?>>

                        <label for="disable_avatar">
                            <span class="switch" role="presentation" tabindex="0">
                                <span class="switch__text on" aria-hidden="true"><?php esc_html_e('On', 'disable-comments'); ?></span>
                                <span class="switch__text off" aria-hidden="true"><?php esc_html_e('Off', 'disable-comments'); ?></span>
                            </span>
                            <?php esc_html_e('Disable Avatar', 'disable-comments'); ?>
                        </label>
                    </div>
                </div>

                <p id="avatar-description" class="disable__option__description">
                    <span class="danger" aria-hidden="true"><?php esc_html_e('Note:', 'disable-comments'); ?></span>
                    <?php esc_html_e('This will change Avatar state from your entire site.', 'disable-comments'); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="disable_option dc-text__block mt30"
                role="group"
                aria-labelledby="avatar-settings-heading">

                <h3 id="avatar-settings-heading">
                    <?php esc_html_e("Avatar settings:", 'disable-comments'); ?>
                </h3>

                <div class="disable__switch" role="radiogroup" aria-labelledby="avatar-settings-heading">
                    <div class="avatar__status radio-style">
                        <input type="radio"
                            id="dont_change"
                            name="disable_avatar"
                            value="-1"
                            aria-describedby="avatar-description"
                            <?php checked($avatar_status, -1); ?>>
                        <label for="dont_change">
                            <i class="icon" tabindex="0"></i>
                            <?php esc_html_e('Don\'t Change', 'disable-comments'); ?>
                        </label>
                    </div>

                    <div class="avatar__status radio-style">
                        <input type="radio"
                            id="enable_avatar"
                            name="disable_avatar"
                            value="0"
                            aria-describedby="avatar-description"
                            <?php checked($avatar_status, 0); ?>>
                        <label for="enable_avatar">
                            <i class="icon" tabindex="0"></i>
                            <?php esc_html_e('Enable Avatar', 'disable-comments'); ?>
                        </label>
                    </div>

                    <div class="avatar__status radio-style">
                        <input type="radio"
                            id="disable_avatar"
                            name="disable_avatar"
                            value="1"
                            aria-describedby="avatar-description"
                            <?php checked($avatar_status, 1); ?>>
                        <label for="disable_avatar">
                            <i class="icon" tabindex="0"></i>
                            <?php esc_html_e('Disable Avatar', 'disable-comments'); ?>
                        </label>
                    </div>
                </div>

                <p id="avatar-description" class="disable__option__description">
                    <span class="danger" aria-hidden="true"><?php esc_html_e('Note:', 'disable-comments'); ?></span>
                    <?php esc_html_e('This will change Avatar state from your entire network. If you want to change the Avatar setting specifically on your subsites by enabling site-wise settings, select "Don\'t change" from here.', 'disable-comments'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <div class="disable__comment__option mb50" role="group" aria-labelledby="api-settings-heading">
        <h3 id="api-settings-heading" class="title">
            <?php esc_html_e('Disable Comments With API', 'disable-comments'); ?>
        </h3>
        <p class="subtitle" id="api-description">
            <?php esc_html_e('You can disable comments made on your website using WordPress specifications.', 'disable-comments'); ?>
        </p>

        <div class="disable_option dc-text__block mt30">
            <div class="disable__switchs" role="group" aria-labelledby="api-settings-heading">
                <div class="dissable__switch__item">
                    <input type="checkbox"
                        id="switch-xml"
                        name="remove_xmlrpc_comments"
                        value="1"
                        aria-describedby="xmlrpc-description"
                        <?php checked((isset($this->options['remove_xmlrpc_comments']) ? $this->options['remove_xmlrpc_comments'] : 0)); ?>>
                    <label for="switch-xml">
                        <span class="switch" role="presentation" tabindex="0">
                            <span class="switch__text on" aria-hidden="true"><?php esc_html_e('On', 'disable-comments'); ?></span>
                            <span class="switch__text off" aria-hidden="true"><?php esc_html_e('Off', 'disable-comments'); ?></span>
                        </span>
                        <?php esc_html_e('Disable Comments via XML-RPC', 'disable-comments'); ?>
                    </label>
                </div>

                <div class="dissable__switch__item">
                    <input type="checkbox"
                        id="switch-api"
                        name="remove_rest_API_comments"
                        value="1"
                        aria-describedby="rest-api-description"
                        <?php checked((isset($this->options['remove_rest_API_comments']) ? $this->options['remove_rest_API_comments'] : 0)); ?>>
                    <label for="switch-api">
                        <span class="switch" role="presentation" tabindex="0">
                            <span class="switch__text on" aria-hidden="true"><?php esc_html_e('On', 'disable-comments'); ?></span>
                            <span class="switch__text off" aria-hidden="true"><?php esc_html_e('Off', 'disable-comments'); ?></span>
                        </span>
                        <?php esc_html_e('Disable Comments via REST API', 'disable-comments'); ?>
                    </label>
                </div>
            </div>

            <div class="api-descriptions">
                <p id="xmlrpc-description" class="visually-hidden">
                    <?php esc_html_e('Toggle XML-RPC comments. When enabled, this will prevent comments from being submitted through XML-RPC.', 'disable-comments'); ?>
                </p>
                <p id="rest-api-description" class="visually-hidden">
                    <?php esc_html_e('Toggle REST API comments. When enabled, this will prevent comments from being submitted through the REST API.', 'disable-comments'); ?>
                </p>
                <p class="disable__option__description" id="api-settings-description">
                    <?php esc_html_e('Turning on these settings will disable any comments made on your website via XML-RPC or REST API specifications.', 'disable-comments'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Save Button -->
    <button type="submit"
        class="button button__success button__fade"
        aria-label="<?php esc_attr_e('Save all disable comments settings', 'disable-comments'); ?>" tabindex="0">
        <span><?php esc_html_e('Save Changes', 'disable-comments'); ?></span>
    </button>

    <?php if (is_network_admin()): ?>
        <input type="hidden" name="is_network_admin" value="1">
    <?php endif; ?>
</form>