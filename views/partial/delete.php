<form action="<?php echo admin_url('admin-post.php'); ?>" method="POST">
    <input type="hidden" name="action" value="delete_comments_settings">
    <?php wp_nonce_field('delete_comments_settings', 'dc_delete_settings_nonce'); ?>
    <?php
    if ($this->get_all_comments_number() > 0) :
    ?>
        <div class="disable__comment__option mb50">
            <p class="subtitle"><span class="danger">Note:</span> These settings will permanently delete comments for your entire website, or for specific posts and comment types</p>
            <div class="disable_option dc-text__block mb30 mt30">
                <input type="radio" id="delete_everywhere" name="delete_mode" value="delete_everywhere" />
                <label for="delete_everywhere">Everywhere: Permanently delete all comments on your WordPress website</label>
                <p class="disable__option__description"><span class="danger">Warnings:</span> This will permanently delete comments everywhere on your website</p>
            </div>
            <div class="disable_option dc-text__block mb30">
                <input type="radio" id="selected_delete_types" name="delete_mode" value="selected_delete_types" />
                <label for="selected_delete_types">On Certain Post Types:</label>
                <div class="delete__checklist">
                    <?php
                    $types = $this->get_all_post_types();
                    foreach ($types as $key => $value) {
                        echo '<div class="delete__checklist__item">
                                            <input type="checkbox" id="delete__checklist__item-' . $key . '" name="delete_types[]" value="' . $key . '" ' . checked(in_array($key, $this->options['disabled_post_types']), true, false) . '>
                                            <label for="delete__checklist__item-' . $key . '">' . $value->labels->name . '</label>
                                        </div>';
                    }
                    ?>
                    <?php if ($this->networkactive) : ?>
                        <p class="indent" id="extradeletetypes">
                            <?php _e('Only the built-in post types appear above. If you want to disable comments on other custom post types on the entire network, you can supply a comma-separated list of post types below (use the slug that identifies the post type).', 'disable-comments'); ?>
                            <br /><label><?php _e('Custom post types:', 'disable-comments'); ?> <input type="text" name="delete_extra_post_types" size="30" value="<?php echo implode(', ', (array) $this->options['extra_post_types']); ?>" /></label></p>
                    <?php endif; ?>
                </div>
                <p class="disable__option__description"><span class="danger">Warnings:</span> This will remove existing comment entries for the selected post type(s) in the database and cannot be reverted without a database backups</p>
            </div>
            <div class="disable_option dc-text__block">
                <input type="radio" id="selected_delete_comment_types" name="delete_mode" value="selected_delete_comment_types" />
                <label for="selected_delete_comment_types">Delete Certain Comment Types:</label>
                <ul class="delete__feedback" id="listofdeletecommenttypes">
                    <?php
                    $commenttypes = $this->get_all_comment_types();
                    foreach ($commenttypes as $k => $v) {
                        echo "<li><label for='comment-type-$k'><input type='checkbox' name='delete_comment_types[]' value='$k' id='comment-type-$k'> {$v}</label></li>";
                    }
                    ?>
                </ul>
                <p class="disable__option__description"><span class="danger">Warnings:</span> This will remove existing comment entries for the selected comment type(s) in the database</p>
            </div>
        </div>
    <?php
    else :
    ?>
        <p><strong><?php _e('No comments available for deletion.', 'disable-comments'); ?></strong></p>
    <?php
    endif;
    ?>
    <!-- delete -->
    <p>
        <input class="button button__success" type="submit" name="delete" value="<?php _e('Delete Comments', 'disable-comments'); ?>">
    </p>
</form>