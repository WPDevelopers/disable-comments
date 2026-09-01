<div class="wrap">
    <div id="disablecommentswrap" class="disablecommentswrap background__grey">
        <?php do_action('disable_comments_notice'); ?>
        <div class="disable__comment_block">
            <div class="disable__comment__nav__wrap">
                <p class="plugin__version"><?php echo esc_html__('Version', 'disable-comments') . ' ' . esc_html(DC_VERSION); ?></p>
                <ul class="disable__comment__nav" role="tablist">
                    <li id="disableCommentsNav" class="disable__comment__nav__item" role="presentation">
                        <a href="#disableComments"
                            class="disable__comment__nav__link active"
                            role="tab"
                            aria-selected="true"
                            aria-controls="disableComments"
                            tabindex="0">
                            <?php echo esc_html(_x('Disable Comments', 'Tab Name', 'disable-comments')); ?>
                        </a>
                    </li>
                    <li id="deleteCommentsNav" class="disable__comment__nav__item" role="presentation">
                        <a href="#deleteComments"
                            class="disable__comment__nav__link"
                            role="tab"
                            aria-selected="false"
                            aria-controls="deleteComments"
                            tabindex="0">
                            <?php echo esc_html(_x('Delete Comments', 'Tab Name', 'disable-comments')); ?>
                        </a>
                    </li>
                    <?php
                    // Last in the tab strip. Its panel is not last in the DOM —
                    // it has to stay inside the settings <form> next to the
                    // Disable panel, and a form cannot wrap two elements with
                    // the Delete panel sitting between them. Harmless for a
                    // tablist: only the active panel is rendered, and the
                    // aria-controls / aria-labelledby pair carries the
                    // relationship rather than source order.
                    ?>
                    <li id="advancedSettingsNav" class="disable__comment__nav__item" role="presentation">
                        <a href="#advancedSettings"
                            class="disable__comment__nav__link"
                            role="tab"
                            aria-selected="false"
                            aria-controls="advancedSettings"
                            tabindex="0">
                            <?php echo esc_html(_x('Settings', 'Tab Name', 'disable-comments')); ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="dc-row">
                <div class="dc-col-lg-9">
                    <div class="disable__comment__tab">
                        <?php
                        // One <form> spanning both settings tabs, deliberately.
                        // A settings-screen save runs apply_settings() with
                        // $preserve_existing = false, which clears every option
                        // the payload omits — so two forms would mean saving
                        // one tab wiped the other tab's settings. A panel
                        // hidden by the tab CSS still serializes; only
                        // `disabled` fields are left out.
                        ?>
                        <form id="disableCommentSaveSettings"
                            action="#"
                            aria-label="<?php esc_attr_e('Disable Comments Settings', 'disable-comments'); ?>">
                            <div id="disableComments"
                                class="disable__comment__tab__item show"
                                role="tabpanel"
                                aria-labelledby="disableCommentsNav"
                                >
                                <?php include DC_PLUGIN_VIEWS_PATH . 'partials/_disable.php'; ?>
                            </div>
                            <div id="advancedSettings"
                                class="disable__comment__tab__item"
                                role="tabpanel"
                                aria-labelledby="advancedSettingsNav"
                                >
                                <?php include DC_PLUGIN_VIEWS_PATH . 'partials/_settings.php'; ?>
                            </div>
                        </form>
                        <div id="deleteComments"
                            class="disable__comment__tab__item"
                            role="tabpanel"
                            aria-labelledby="deleteCommentsNav"
                            >
                            <?php include DC_PLUGIN_VIEWS_PATH . 'partials/_delete.php'; ?>
                        </div>
                    </div>
                </div>
                <div class="dc-col-lg-3">
                    <?php include DC_PLUGIN_VIEWS_PATH . 'partials/_sidebar.php'; ?>
                </div>
                <div>
                </div>
                <?php include DC_PLUGIN_VIEWS_PATH . 'partials/_footer.php'; ?>
            </div>
        </div>