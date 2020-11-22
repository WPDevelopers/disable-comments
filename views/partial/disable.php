<form action="<?php echo admin_url('admin-post.php'); ?>" method="POST">
    <input type="hidden" name="action" value="disable_comments_save_settings">
    <?php wp_nonce_field('disable_comments_save_settings', 'dc_settings_nonce'); ?>
    <div class="disable__comment__option mb50">
        <h3 class="title">Settings</h3>
        <p class="subtitle">Configure the settings below to disable comments globally or on specific types of post</p>
        <div class="disable_option dc-text__block mb30 mt30">
            <input type="radio" id="remove_everywhere" name="mode" value="<?php echo esc_attr('remove_everywhere') ?>" <?php checked($this->options['remove_everywhere']); ?> />
            <label for="remove_everywhere">Everywhere: <span>Disable comments globally on your entire website</span></label>
            <p class="disable__option__description"><span class="danger">Warnings:</span> This will disable comments from every page and post on your website. Use this setting if you do not want to show comments anywhere</p>
        </div>
        <div class="disable_option dc-text__block">
            <input type="radio" id="selected_types" name="mode" value="<?php echo esc_attr('selected_types'); ?>" <?php checked(!$this->options['remove_everywhere']); ?> />
            <label for="selected_types">On Specific Post Types:</label>
            <div class="remove__checklist">
                <?php
                $types = $this->get_all_post_types();
                foreach ($types as $key => $value) {
                    echo '<div class="remove__checklist__item">
                                    <input type="checkbox" id="remove__checklist__item-' . $key . '" name="disabled_types[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $this->options['disabled_post_types']), true, false) . '>
                                    <label for="remove__checklist__item-' . $key . '">' . $value->labels->name . '</label>
                                </div>';
                }
                ?>
                <?php if ($this->networkactive) :
                    $extradisabletypes = implode(', ', (array) $this->options['extra_post_types']);
                ?>
                    <p class="indent" id="extratypes"><?php _e('Only the built-in post types appear above. If you want to disable comments on other custom post types on the entire network, you can supply a comma-separated list of post types below (use the slug that identifies the post type).', 'disable-comments'); ?>
                        <br /><label><?php _e('Custom post types:', 'disable-comments'); ?> <input type="text" name="extra_post_types" size="30" value="<?php echo esc_attr($extradisabletypes); ?>" /></label></p>
                <?php endif; ?>
                <p class="indent"><?php _e('Disabling comments will also disable trackbacks and pingbacks. All comment-related fields will also be hidden from the edit/quick-edit screens of the affected posts. These settings cannot be overridden for individual posts.', 'disable-comments'); ?></p>
            </div>
            <p class="disable__option__description">This will disable comments from the selected post type(s) only. Comments will be visible on all other post types</p>
        </div>
    </div>
    <div class="disable__comment__option mb50">
        <h3 class="title">Disable Comments With API</h3>
        <p class="subtitle">You can disable comments made on your website using WordPress specifications</p>
        <div class="disable_option dc-text__block mt30">
            <div class="disable__switchs">
                <div class="dissable__switch__item">
                    <input type="checkbox" id="switch-xml" checked>
                    <label for="switch-xml">
                        <span class="switch">
                            <span class="switch__text on">On</span>
                            <span class="switch__text off">Off</span>
                        </span>
                        Disable Comments via XML-RPC
                    </label>
                </div>
                <div class="dissable__switch__item">
                    <input type="checkbox" id="switch-api">
                    <label for="switch-api">
                        <span class="switch">
                            <span class="switch__text on">On</span>
                            <span class="switch__text off">Off</span>
                        </span>Disable Comments via REST API
                    </label>
                </div>
            </div>
            <p class="disable__option__description">Turning on these settings will disable any comments made on your website via XML-RPC or REST API specifications</p>
        </div>
    </div>
    <!-- save -->
    <button class="button button__success">Save Changes</button>
</form>