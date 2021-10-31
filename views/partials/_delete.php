<form id="deleteCommentSettings" action="#">
    <?php
    if ($this->get_all_comments_number() > 0) :
    ?>


        <div class="disable__comment__option mb50">
            <?php if(is_network_admin()):?>
            <div class="disable_option sites_list_wrapper dc-text__block mb30 mt30" data-type="delete">
                <h3>Delete comments in the following sites:</h3>
                <?php
                    $type = 'delete';
                    include DC_PLUGIN_VIEWS_PATH . 'partials/_sites.php';
                ?>
                <p class="disable__option__description"><span class="danger"><?php _e('Note:', 'disable-comments'); ?></span> <?php _e('Select your sub-sites where you want to delete comments.', 'disable-comments'); ?></p>
            </div>

            <?php endif;?>
            <p class="subtitle"><span class="danger"><?php _e('Note:', 'disable-comments'); ?></span> <?php _e('These settings will permanently delete comments for your entire website, or for specific posts and comment types.', 'disable-comments'); ?></p>
            <div class="disable_option dc-text__block mb30 mt30">
                <input type="radio" id="delete_everywhere" name="delete_mode" value="<?php echo esc_attr('delete_everywhere'); ?>" <?php checked($this->options['remove_everywhere']); ?> />
                <label for="delete_everywhere"><?php _e('Everywhere:', 'disable-comments'); ?> <span><?php _e('Permanently delete all comments on your WordPress website', 'disable-comments'); ?></span></label>
                <p class="disable__option__description"><span class="danger"><?php _e('Warnings:', 'disable-comments'); ?></span> <?php _e('This will permanently delete comments everywhere on your website.', 'disable-comments'); ?></p>
            </div>
            <div class="disable_option dc-text__block mb30">
                <input type="radio" id="selected_delete_types" name="delete_mode" value="<?php echo esc_attr('selected_delete_types'); ?>" <?php checked(!$this->options['remove_everywhere']); ?> />
                <label for="selected_delete_types"><?php _e('On Certain Post Types:', 'disable-comments'); ?></label>
                <div id="delete__post__types" class="delete__checklist">
                    <?php
                    $types = $this->get_all_post_types();
                    foreach ($types as $key => $value) {
                        echo '<div class="delete__checklist__item">
                                            <input type="checkbox" id="delete__checklist__item-' . $key . '" name="delete_types[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $this->options['disabled_post_types']), true, false) . '>
                                            <label for="delete__checklist__item-' . $key . '">' . $value->labels->name . '</label>
                                        </div>';
                    }
                    ?>
                    <?php if ($this->networkactive && is_network_admin()) :
                        $extradeletetypes = implode(', ', (array) $this->options['extra_post_types']);
                    ?>
                        <p class="indent subtitle" id="extradeletetypes">
                            <?php _e('Only the built-in post types appear above. If you want to disable comments on other custom post types on the entire network, you can supply a comma-separated list of post types below (use the slug that identifies the post type).', 'disable-comments'); ?>
                            <br /> <br /><label><?php _e('Custom post types:', 'disable-comments'); ?> <input type="text" class="form__control" name="delete_extra_post_types" size="30" value="<?php echo esc_attr($extradeletetypes); ?>" /></label></p>
                    <?php endif; ?>
                </div>
                <p class="disable__option__description"><span class="danger"><?php _e('Warnings:', 'disable-comments') ?></span> <?php _e('This will remove existing comment entries for the selected post type(s) in the database and cannot be reverted without a database backups.', 'disable-comments'); ?></p>
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
                <p class="disable__option__description"><span class="danger"><?php _e('Warnings:', 'disable-comments'); ?></span> <?php _e('Deleting comments by comment type will remove existing comment entries of the selected comment type(s) in the database and cannot be reverted without a database backup.', 'disable-comments'); ?></p>
            </div>
            <h4 class="total-comments"><?php _e('Total Comments:', 'disable-comments'); ?> <?php echo $this->get_all_comments_number(); ?></h4>
        </div>
        <?php if(is_network_admin()):?>
            <input type="hidden" name="is_network_admin" value="1">
        <?php endif;?>
        <!-- save -->
        <button class="button button__delete button__fade"><?php _e('Delete Comments', 'disable-comments'); ?></button>
    <?php
    else :
    ?>
        <div class="delete-comments-not-found">
            <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 1200 800" style="enable-background:new 0 0 1200 800;" xml:space="preserve">
                <style type="text/css">
                    .st0 {
                        fill: #EEEEEE;
                    }

                    .st1 {
                        fill: #BDBDBD;
                    }

                    .st2 {
                        fill: url(#SVGID_1_);
                    }

                    .st3 {
                        fill: #F2DAD4;
                    }

                    .st4 {
                        fill: #192B59;
                    }

                    .st5 {
                        fill: url(#SVGID_2_);
                    }

                    .st6 {
                        fill: url(#SVGID_3_);
                    }

                    .st7 {
                        fill: #043146;
                    }
                </style>
                <g>
                    <g>
                        <g>
                            <path class="st0" d="M403.5,365.2c5.6,7.9,1,18.2-4.6,24c-6.9,7.1-13.8,6-17,12.3c-3.8,7.6,4.3,13.2,0.1,21.2
				c-2.6,4.9-6.2,3.7-11.4,9.9c-6.3,7.6-0.1,9.7-4.6,15.2c-14.4,17.8-20.2,5.4-29,10.1c-11.4,6.1-19,11.2-24.6,10
				c-21.9-4.8-15.2-19.2-25.8-24.7c-14.4-7.5-21.4-38.2-11.5-49c5.2-5.7,10.3-6.4,10.7-11.1c0.7-6.8-9.6-8.8-10.6-17
				c-1.2-9.3,11.1-13.8,9.3-21.6c-1.8-7.6-14.4-7.4-15.2-12.8c-1.2-8.3,25.8-23.1,53.7-22.2c13.4,0.4,35.1,4.8,38.8,15.9
				c2.1,6.4-3.4,10.6-0.3,18.9c2.2,6,7.2,9.4,13.7,13.8C381.4,362.3,393.6,351.2,403.5,365.2z" />
                            <path class="st1" d="M336.7,360.3c4.4-0.2,6.6,29.1,4.1,33c-2.6,3.8-7.3,6.7-7.7,10.9c-0.3,4.2,1.6,78.6,3.1,77.5
				c1.6-1.1,14.4-20.6,11.9-25.1c-2.6-4.5-5.5-11.6-4.9-17.5c0.6-5.9,0.6-14.4,2.3-14.2c1.7,0.2,0.3,14.8,1.4,17.6
				c1.2,2.8,1.4,5,3,5.1c1.7,0,1.7-15.4,4.2-15.1c2.5,0.3,0.8,23-0.1,27.2c-0.9,4.2-3.8,18.7-6.6,24c-2.7,5.3-11.1,10.9-11.3,12.8
				c-0.2,1.8,1.1,45.1,1.1,45.1l-21.9,0.4c0,0,8.1-85.4,6.3-88.3c-1.8-2.8-13.2-25.9-15.7-28.3c-2.4-2.4-3.9-16.7-2.6-16.9
				c1.3-0.2,2,13.8,4.7,14c2.7,0.2,4.1-27.1,6.1-26.9c1.9,0.2-1.9,28.4-0.8,30.1c1,1.7,7.1,12.2,9.2,11.9c2-0.2-1.1-76.8,0.6-77.7
				c1.7-0.9,3-0.1,4.1,7.9c1.1,8,3.1,25.5,4.3,25.5c1.2,0,4.2-2.7,4.3-4.4C335.9,387.2,334.7,360.3,336.7,360.3z" />
                        </g>
                        <g>
                            <path class="st0" d="M810.6,447.2c-3.2,4.5-0.6,10.3,2.6,13.6c3.9,4,7.8,3.4,9.6,7c2.2,4.3-2.4,7.5,0,12c1.5,2.8,3.5,2.1,6.5,5.6
				c3.6,4.3,0.1,5.5,2.6,8.6c8.2,10.1,11.4,3.1,16.4,5.7c6.4,3.4,10.8,6.4,13.9,5.7c12.4-2.7,8.6-10.9,14.6-14
				c8.1-4.2,12.1-21.6,6.5-27.7c-2.9-3.2-5.8-3.6-6.1-6.3c-0.4-3.9,5.4-5,6-9.6c0.7-5.3-6.3-7.8-5.3-12.2c1-4.3,8.2-4.2,8.6-7.3
				c0.7-4.7-14.6-13.1-30.4-12.6c-7.6,0.2-19.9,2.7-21.9,9c-1.2,3.6,1.9,6,0.2,10.7c-1.3,3.4-4.1,5.3-7.8,7.8
				C823.1,445.6,816.2,439.3,810.6,447.2z" />
                            <path class="st1" d="M848.5,444.4c-2.5-0.1-3.8,16.5-2.3,18.7c1.4,2.2,4.2,3.8,4.3,6.2c0.2,2.4-0.9,44.5-1.8,43.9
				c-0.9-0.6-8.2-11.6-6.7-14.2c1.5-2.6,3.1-6.5,2.8-9.9c-0.3-3.4-0.3-8.1-1.3-8c-0.9,0.1-0.2,8.4-0.8,10c-0.7,1.6-0.8,2.8-1.7,2.9
				c-0.9,0-1-8.7-2.4-8.5c-1.4,0.2-0.5,13,0,15.4c0.5,2.4,2.2,10.6,3.7,13.6c1.6,3,6.3,6.2,6.4,7.2c0.1,1-0.6,25.6-0.6,25.6
				l12.4,0.2c0,0-4.6-48.4-3.6-50c1-1.6,7.5-14.7,8.9-16c1.4-1.3,2.2-9.5,1.5-9.6c-0.7-0.1-1.1,7.8-2.7,8
				c-1.5,0.1-2.3-15.4-3.4-15.2c-1.1,0.1,1.1,16.1,0.5,17.1c-0.6,1-4,6.9-5.2,6.8c-1.1-0.1,0.6-43.5-0.4-44c-1-0.5-1.7-0.1-2.3,4.5
				c-0.6,4.5-1.8,14.5-2.5,14.4c-0.7,0-2.4-1.5-2.4-2.5C848.9,459.6,849.6,444.5,848.5,444.4z" />
                        </g>

                        <linearGradient id="SVGID_1_" gradientUnits="userSpaceOnUse" x1="627.1564" y1="438.5578" x2="627.1564" y2="669.9148" gradientTransform="matrix(-1 0 0 1 1200 0)">
                            <stop offset="0" style="stop-color:#E5E5E5" />
                            <stop offset="0.4764" style="stop-color:#F4F4F4" />
                            <stop offset="1" style="stop-color:#FFFFFF" />
                        </linearGradient>
                        <ellipse class="st2" cx="572.8" cy="652.7" rx="416.2" ry="147.3" />
                    </g>
                    <g>
                        <path class="st3" d="M586,492.8c-1,1.9-2,4.2-2,5.7c0,1.5-0.2,7.1,0,7.8c0.2,0.7,6.7,1.8,9.4,1.6c2.7-0.2,5.1-2.8,5.3-3.4
			c0.2-0.6,0.5-9,0-10C598.2,493.5,586,492.8,586,492.8z" />
                        <path class="st3" d="M553,495.8c-0.4,1.6-0.5,5.6-0.3,6.6c0.3,1,1.4,2.8,1.4,4.6c0,1.8,13,1.7,13.5,0.6c0.5-1.1,0.5-4.1,0.5-5.3
			c-0.1-1.2,1.1-3.4,0.8-4.5c-0.4-1.2-1.8-3.4-3.2-3.7C564.3,493.7,553,495.8,553,495.8z" />
                        <path class="st4" d="M531.3,306.1c-2.6,9.7-5.1,40.1-3.3,52.4c1.8,12.3,6.5,46.1,6.5,61c0,14.9,5.2,39.3,7.6,48.2
			c2.4,8.9,6,28.5,7.9,28.9c1.9,0.4,4.9,2.1,10.5,1.1c5.6-1.1,7.2-2.5,7.3-4.7c0.1-2.3-3.9-27.8-4.6-33.9
			c-0.6-6.1-4.7-31.8-4.9-35.2c-0.2-3.4,3.3-15.5,3.7-22.6c0.4-7.1,2.2-32.5,3.2-35.9c1.1-3.4,3.6-19.8,4.7-19.9
			c1.1-0.1,7.6,19.8,8.6,26.2c1,6.4,5.9,48,7.1,50.5c1.2,2.5-0.4,17.1-1.2,22.5c-0.8,5.4-1,48-0.1,49.1c0.9,1.1,5.7,3.2,8.8,3.2
			c3.2,0,8.3-1.3,9-2.9c0.8-1.6-0.3-7.5,0.6-10.4c0.8-2.9,8.7-33,9.1-40.2c0.4-7.3-1.7-35.1-2-42c-0.3-6.9,2.9-27.6,3.6-33.2
			c0.7-5.5,3.2-28.5,3.2-33.9c0-5.4-1-21.9-4.3-25.7C609.3,304.9,531.3,306.1,531.3,306.1z" />
                        <path class="st3" d="M659.1,216.4c1.3-0.9,3.5-2.6,3.8-3.7c0.4-1.2,1.7-4.3,3.2-5c1.5-0.7,4.6-1.7,6-2.2c1.4-0.5,5-2.6,5.7-2.9
			c0.7-0.3,2.8-0.7,3.3,0.4c0.6,1.1-0.2,3.4-1.7,4c-1.5,0.6-5,1.7-4.7,2.5c0.3,0.8,3.7,1.6,6.4,0.3c2.7-1.3,7.3-2.5,8.3-3.1
			c1.1-0.6,9-3.8,9.8-3.8c0.8,0,1.6,0.4,2,1.1c0.4,0.8,2.7-1.1,3.5,0.2c0.7,1.3-1,2-1.1,2.3c-0.1,0.4,1,0.8,1,1.7
			c0,0.9-1.4,1.5-3.6,2.5c-2.2,1-12.2,7.3-13.8,8.2c-1.6,0.8-4.9,2.6-8.9,2.9c-4.1,0.4-9,0.6-10.1,2s-3.9,5.4-4.6,5.6
			c-0.7,0.2-5.3,1-6.4-1.8C656.1,224.9,659.1,216.4,659.1,216.4z" />
                        <path class="st3" d="M486.6,209.4c-1.3-0.9-3.5-2.6-3.8-3.7c-0.4-1.2-1.7-4.3-3.2-5c-1.5-0.7-4.6-1.7-6-2.2
			c-1.4-0.5-5-2.6-5.7-2.9c-0.7-0.3-2.8-0.7-3.3,0.4c-0.6,1.1,0.2,3.4,1.7,4c1.5,0.6,5,1.7,4.7,2.5c-0.3,0.8-3.7,1.6-6.4,0.3
			c-2.7-1.3-7.3-2.5-8.3-3.1c-1.1-0.6-9-3.8-9.8-3.8c-0.8,0-1.6,0.4-2,1.1c-0.4,0.8-2.7-1.1-3.5,0.2c-0.7,1.3,1,2,1.1,2.3
			c0.1,0.4-1,0.8-1,1.7c0,0.9,1.4,1.5,3.6,2.5c2.2,1,12.2,7.3,13.8,8.2c1.6,0.8,4.9,2.6,8.9,2.9c4.1,0.4,9,0.6,10.1,2
			c1.2,1.5,2,3.3,2.7,3.5c0.7,0.2,5.3,1,6.4-1.8C487.7,215.8,486.6,209.4,486.6,209.4z" />
                        <linearGradient id="SVGID_2_" gradientUnits="userSpaceOnUse" x1="542.5208" y1="121.3129" x2="589.0369" y2="281.8409">
                            <stop offset="0" style="stop-color:#EC0314" />
                            <stop offset="0.9944" style="stop-color:#AF1431" />
                        </linearGradient>
                        <path class="st5" d="M560.3,166.8c-5.5,0.7-27.2,3.3-31,11.3c-3.8,7.9-7.2,26.9-9,32.3c-1.8,5.3-5.3,17.9-6.8,17.9
			s-26.5-19.3-27-19.3c-0.5,0,0.6,4.6-2.3,7.7c-2.9,3-5.1,2.2-4.5,3.7c0.5,1.5,14.1,17.8,17.6,22.5c3.6,4.7,18.8,17.2,22.6,17
			c3.8-0.2,16.8-19.2,17.7-19.2c1,0-1.2,30.4-2.9,36.8c-1.7,6.4-9,34.5-7.1,37c2,2.5,23.3,9.7,39.8,10.2c16.5,0.5,50.1-5.6,50.7-9.9
			c0.6-4.3-6.9-27.9-6.9-37.4c0-9.5-2.1-43.3-0.7-43.5c1.4-0.2,10.6,25.8,19.7,25.2c9-0.5,27.7-19.4,29.7-23c2-3.6,6-7.4,4.9-8
			c-1.1-0.5-4.9-4-4.9-6.7s0.3-6.1-0.9-5.5c-1.2,0.5-8.8,7.3-12,9.2c-3.2,1.8-8.3,4.7-8.9,3.6c-0.6-1.1-10.4-19.5-11.2-23.2
			c-0.7-3.7-5.6-28.7-9.8-31c-4.1-2.2-23.5-9.1-26.8-9.2C587.1,165.3,560.3,166.8,560.3,166.8z" />
                        <linearGradient id="SVGID_3_" gradientUnits="userSpaceOnUse" x1="569.2911" y1="149.3214" x2="576.8388" y2="175.3686">
                            <stop offset="0" style="stop-color:#F2BFAD" />
                            <stop offset="1" style="stop-color:#F2DAD4" />
                        </linearGradient>
                        <path class="st6" d="M560,160c0,2.6,1.4,6.1,0.3,6.9c-1,0.7,4.9,16.7,10.6,17.1c5.7,0.3,15.9-15.6,16.1-17.3
			c0.2-1.8-0.5-8.1-0.7-8.4C586.2,158,560,160,560,160z" />
                        <path class="st4" d="M552.2,138.6c-1-6.9-3.2-13.4-1.7-20.1c1.5-6.7,6.3-14.9,16.3-16c10-1,14.7,2.8,19.7,9.1
			c4.9,6.3,4.3,26.4,3,27.7c-1.3,1.3-9.7,5.3-19.2,5.4C560.6,144.9,552.2,138.6,552.2,138.6z" />
                        <path class="st3" d="M556.1,148.9c0,0,2,10.9,3.4,12c1.4,1.2,8.8,7.1,13.6,6.9c4.7-0.2,14.4-7,15-10.9c0.6-3.9,0-11.4,0.4-11.2
			c0.4,0.1,3,0.4,3.5-3.6c0.5-4.1,0.4-10.4-1.2-10.5c-1.6-0.1-2.4,5.9-3.2,5.9c-0.9,0-1-8.6-2.5-10.5c-1.5-1.9-11.7-3.9-13.2-3.9
			c-1.4,0-8,8.6-16.5,9.9c0,0,1.7,6.8,0.5,6.9c-1.3,0.1-3-5.4-4.2-5.4s-1.4,5.6-0.9,9.3c0.5,3.7,2.6,5.8,3.3,5.8
			C554.8,149.5,555.3,148.4,556.1,148.9z" />
                        <path class="st7" d="M599.5,507c1.2,1.7,1,6.2,3.3,10.5c2.3,4.3,4.1,8,4.2,11c0.1,3.1-3.1,8-7.5,8.7c-4.4,0.7-9.4,1-11.7-0.2
			c-2.2-1.2-4.9-3.2-4.9-6.5c0-3.3,0.5-9.6,0-10.7c-0.5-1.1-1-4.3-1-5.9c0-1.6-0.4-6.4,0.5-7.5c0.9-1.2,4.5-3.6,8.2-3.5
			C594.2,502.9,598.5,505.5,599.5,507z" />
                        <path class="st7" d="M552.7,507c-1.2,1.7-1,6.2-3.3,10.5c-2.3,4.3-4.1,8-4.2,11c-0.1,3.1,3.1,8,7.5,8.7c4.4,0.7,9.4,1,11.7-0.2
			c2.2-1.2,4.9-3.2,4.9-6.5c0-3.3-0.5-9.6,0-10.7c0.5-1.1,1-4.3,1-5.9c0-1.6,0.4-6.4-0.5-7.5c-0.9-1.2-4.5-3.6-8.2-3.5
			C558,502.9,553.7,505.5,552.7,507z" />
                    </g>
                    <g>
                        <g>
                            <path class="st0" d="M468.6,207.2l-162.9-63.4c-5.3-2.1-7.9-8-5.9-13.3L348.2,6.2c2.1-5.3,8-7.9,13.3-5.9l162.9,63.4
				c5.3,2.1,7.9,8,5.9,13.3l-48.4,124.3C479.8,206.6,473.9,209.2,468.6,207.2z" />
                            <path class="st1" d="M323.8,99.4L357,14.3c1.2-3,4.6-4.5,7.6-3.3L515,69.4c3,1.2,4.5,4.6,3.3,7.6l-33.1,85.2l-25.7-74.4
				c-1.9-5.5-8.4-7.9-13.4-5l-53.5,31.3c-4.7,2.7-10.7,0.4-12.3-4.8L372.5,84c-1.9-6.1-9-8.8-14.4-5.5L323.8,99.4z" />
                            <path class="st0" d="M421.3,65.8c-3.4,8.6-13.1,12.9-21.7,9.5c-8.6-3.4-12.9-13.1-9.5-21.7c3.4-8.6,13.1-12.9,21.7-9.5
				C420.4,47.5,424.7,57.2,421.3,65.8z" />
                        </g>
                    </g>
                </g>
            </svg>

            <p class="error-message"><strong><?php _e('No comments are available for deletion.', 'disable-comments'); ?></strong></p>
        </div>
    <?php
    endif;
    ?>
</form>