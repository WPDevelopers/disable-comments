<div id="disablecommentswrap" class="disablecommentswrap background__grey">
    <div class="dc-text__block disable__comment__alert mb30">
        <div class="alert__content">
            <img src="<?php echo DC_ASSETS_URI; ?>img/icon-logo.png" alt="">
            <p><?php _e('Want to help make Disable Comments even better?', 'disable-comments') ?></p>
        </div>
        <div class="button__group">
            <a href="#" class="button button--sm button__success"><?php _e('Sure', 'disable-comments'); ?></a>
            <a href="#" class="button button--sm"><?php _e('No Thanks', 'disable-comments'); ?></a>
        </div>
    </div>
    <div class="disable__comment_block">
        <div class="disable__comment__nav__wrap">
            <p class="plugin__version">Version 5.1.1</p>
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
                        <?php include DC_PLUGIN_VIEWS_PATH . 'partial/disable.php'; ?>
                    </div>
                    <div class="disable__comment__tab__item">
                        <?php include DC_PLUGIN_VIEWS_PATH . 'partial/delete.php'; ?>
                    </div>
                </div>
            </div>
            <div class="dc-col-lg-3">
                <?php include DC_PLUGIN_VIEWS_PATH . 'partial/sidebar.php'; ?>
            </div>
            <div>
            </div>
            <?php
            include DC_PLUGIN_VIEWS_PATH . 'partial/product.php';
            include DC_PLUGIN_VIEWS_PATH . 'partial/footer.php';
            ?>
        </div>