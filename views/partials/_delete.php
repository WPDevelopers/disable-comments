<form id="deleteCommentSettings" action="#">
    <?php
    if ($this->get_all_comments_number() > 0) :
    ?>
        <div class="disable__comment__option mb50">
            <p class="subtitle"><span class="danger"><?php _e('Note:', 'disable-comments'); ?></span> <?php _e('These settings will permanently delete comments for your entire website, or for specific posts and comment types', 'disable-comments'); ?></p>
            <div class="disable_option dc-text__block mb30 mt30">
                <input type="radio" id="delete_everywhere" name="delete_mode" value="<?php echo esc_attr('delete_everywhere'); ?>" <?php checked($this->options['remove_everywhere']); ?> />
                <label for="delete_everywhere"><?php _e('Everywhere: Permanently delete all comments on your WordPress website', 'disable-comments'); ?></label>
                <p class="disable__option__description"><span class="danger"><?php _e('Warnings:', 'disable-comments'); ?></span> <?php _e('This will permanently delete comments everywhere on your website', 'disable-comments'); ?></p>
            </div>
            <div class="disable_option dc-text__block mb30">
                <input type="radio" id="selected_delete_types" name="delete_mode" value="<?php echo esc_attr('selected_delete_types'); ?>" <?php checked(!$this->options['remove_everywhere']); ?> />
                <label for="selected_delete_types"><?php _e('On Certain Post Types:', 'disable-comments'); ?></label>
                <div class="delete__checklist">
                    <?php
                    $types = $this->get_all_post_types();
                    foreach ($types as $key => $value) {
                        echo '<div class="delete__checklist__item">
                                            <input type="checkbox" id="delete__checklist__item-' . $key . '" name="delete_types[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $this->options['disabled_post_types']), true, false) . '>
                                            <label for="delete__checklist__item-' . $key . '">' . $value->labels->name . '</label>
                                        </div>';
                    }
                    ?>
                    <?php if ($this->networkactive) :
                        $extradeletetypes = implode(', ', (array) $this->options['extra_post_types']);
                    ?>
                        <p class="indent" id="extradeletetypes">
                            <?php _e('Only the built-in post types appear above. If you want to disable comments on other custom post types on the entire network, you can supply a comma-separated list of post types below (use the slug that identifies the post type).', 'disable-comments'); ?>
                            <br /><label><?php _e('Custom post types:', 'disable-comments'); ?> <input type="text" name="delete_extra_post_types" size="30" value="<?php echo esc_attr($extradeletetypes); ?>" /></label></p>
                    <?php endif; ?>
                </div>
                <p class="disable__option__description"><span class="danger"><?php _e('Warnings:', 'disable-comments') ?></span> <?php _e('This will remove existing comment entries for the selected post type(s) in the database and cannot be reverted without a database backups', 'disable-comments'); ?></p>
            </div>
            <div class="disable_option dc-text__block">
                <input type="radio" id="selected_delete_comment_types" name="delete_mode" value="<?php echo esc_attr('selected_delete_comment_types'); ?>" />
                <label for="selected_delete_comment_types"><?php _e('Delete Certain Comment Types:', 'disable-comments'); ?></label>
                <ul class="delete__feedback" id="listofdeletecommenttypes">
                    <?php
                    $commenttypes = $this->get_all_comment_types();
                    foreach ($commenttypes as $key => $value) {
                        echo "<li><label for='comment-type-$key'><input type='checkbox' name='delete_comment_types[]' value=" . esc_attr($key) . " id='comment-type-$key'> {$value}</label></li>";
                    }
                    ?>
                </ul>
                <p class="disable__option__description"><span class="danger"><?php _e('Warnings:', 'disable-comments'); ?></span> <?php _e('This will remove existing comment entries for the selected comment type(s) in the database' . 'disable-comments'); ?></p>
            </div>
        </div>
        <!-- save -->
        <button class="button button__success"><?php _e('Delete Comments', 'disable-comments'); ?></button>
    <?php
    else :
    ?>
        <p><strong><?php _e('No comments available for deletion.', 'disable-comments'); ?></strong></p>
    <?php
    endif;
    ?>
</form>