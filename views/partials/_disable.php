<form id="disableCommentSaveSettings" action="#">
    <div class="disable__comment__option mb50">
        <h3 class="title"><?php _e('Settings', 'disable-comments'); ?></h3>
        <p class="subtitle"><?php _e('Configure the settings below to disable comments globally or on specific types of posts.', 'disable-comments'); ?></p>
        <div class="disable_option dc-text__block mb30 mt30">
            <input type="radio" id="remove_everywhere" name="mode" value="<?php echo esc_attr('remove_everywhere') ?>" <?php checked($this->options['remove_everywhere']); ?> />
            <label for="remove_everywhere"><?php _e('Everywhere:', 'disable-comments'); ?> <span><?php _e('Disable comments globally on your entire website', 'disable-comments'); ?></span></label>
            <p class="disable__option__description"><span class="danger"><?php _e('Warnings:', 'disable-comments'); ?></span> <?php _e('This will disable comments from every page and post on your website. Use this setting if you do not want to show comments anywhere.', 'disable-comments'); ?></p>
        </div>
        <div class="disable_option dc-text__block">
            <input type="radio" id="selected_types" name="mode" value="<?php echo esc_attr('selected_types'); ?>" <?php checked(!$this->options['remove_everywhere']); ?> />
            <label for="selected_types"><?php _e('On Specific Post Types:', 'disable-comments'); ?></label>
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
            </div>
            <p class="subtitle"><span class="danger"><?php _e('Note:', 'disable-comments'); ?></span> <?php _e('Disabling comments will also disable trackbacks and pingbacks. All comment-related fields will also be hidden from the edit/quick-edit screens of the affected posts. These settings cannot be overridden for individual posts. Comments will be visible on all other post types.', 'disable-comments'); ?></p>
        </div>
    </div>
    <div class="disable__comment__option mb50">
        <h3 class="title"><?php _e('Disable Comments With API', 'disable-comments'); ?></h3>
        <p class="subtitle"><?php _e('You can disable comments made on your website using WordPress specifications.', 'disable-comments'); ?></p>
        <div class="disable_option dc-text__block mt30">
            <div class="disable__switchs">
                <div class="dissable__switch__item">
                    <input type="checkbox" id="switch-xml" name="remove_xmlrpc_comments" value="1" <?php checked((isset($this->options['remove_xmlrpc_comments']) ? $this->options['remove_xmlrpc_comments'] : 0)); ?>>
                    <label for="switch-xml">
                        <span class="switch">
                            <span class="switch__text on"><?php _e('On', 'disable-comments'); ?></span>
                            <span class="switch__text off"><?php _e('Off', 'disable-comments'); ?></span>
                        </span>
                        <?php _e('Disable Comments via XML-RPC', 'disable-comments'); ?>
                    </label>
                </div>
                <div class="dissable__switch__item">
                    <input type="checkbox" id="switch-api" name="remove_rest_API_comments" value="1" <?php checked((isset($this->options['remove_rest_API_comments']) ? $this->options['remove_rest_API_comments'] : 0)); ?>>
                    <label for="switch-api">
                        <span class="switch">
                            <span class="switch__text on"><?php _e('On', 'disable-comments'); ?></span>
                            <span class="switch__text off"><?php _e('Off', 'disable-comments'); ?></span>
                        </span><?php _e('Disable Comments via REST API', 'disable-comments'); ?>
                    </label>
                </div>
            </div>
            <p class="disable__option__description"><?php _e('Turning on these settings will disable any comments made on your website via XML-RPC or REST API specifications.', 'disable-comments'); ?></p>
        </div>
    </div>
    <!-- save -->
    <button class="button button__success"><?php _e('Save Changes', 'disable-comments'); ?></button>
</form>