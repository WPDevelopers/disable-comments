<div id="disablecommentswrap" class="disablecommentswrap background__grey">
    <?php do_action( 'disable_comments_notice' ); ?>
    <div class="disable__comment_block">
        <div class="disable__comment__nav__wrap">
            <p class="plugin__version"><?php echo _e('Version', 'disable-comments') . ' ' . DC_VERSION; ?></p>
            <ul class="disable__comment__nav">
                <li class="disable__comment__nav__item">
                    <a href="#" class="disable__comment__nav__link active"><?php _e('Disable Comments', 'disable-comments'); ?></a>
                </li>
                <li class="disable__comment__nav__item">
                    <a href="#" class="disable__comment__nav__link"><?php _e('Delete Comments', 'disable-comments'); ?></a>
                </li>
            </ul>
        </div>
        <div class="dc-row">
            <div class="dc-col-lg-9">
                <div class="disable__comment__tab">
                    <div class="disable__comment__tab__item show">
                        <?php include DC_PLUGIN_VIEWS_PATH . 'partials/_disable.php'; ?>
                    </div>
                    <div class="disable__comment__tab__item">
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