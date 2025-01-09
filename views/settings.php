<div class="wrap">
    <div id="disablecommentswrap" class="disablecommentswrap background__grey">
        <?php do_action('disable_comments_notice'); ?>
        <div class="disable__comment_block">
            <div class="disable__comment__nav__wrap">
                <p class="plugin__version"><?php echo esc_html__('Version', 'disable-comments') . ' ' . esc_html(DC_VERSION); ?></p>
                <ul class="disable__comment__nav">
                    <li id="disableCommentsNav" class="disable__comment__nav__item">
                        <a href="#disableComments" class="disable__comment__nav__link active"><?php echo esc_html(_x('Disable Comments', 'Tab Name','disable-comments')); ?></a>
                    </li>
                    <li id="deleteCommentsNav" class="disable__comment__nav__item">
                        <a href="#deleteComments" class="disable__comment__nav__link"><?php echo esc_html(_x('Delete Comments', 'Tab Name', 'disable-comments')); ?></a>
                    </li>
                </ul>
            </div>
            <div class="dc-row">
                <div class="dc-col-lg-9">
                    <div class="disable__comment__tab">
                        <div id="disableComments" class="disable__comment__tab__item show">
                            <?php include DC_PLUGIN_VIEWS_PATH . 'partials/_disable.php'; ?>
                        </div>
                        <div id="deleteComments" class="disable__comment__tab__item">
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
