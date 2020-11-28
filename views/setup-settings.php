<div id="disablecommentssetupwrap" class="disablecommentswrap background__grey dc-quick__setup__wrap">
    <div class="dc-container">
        <div class="dc-row">
            <div class="dc-col">
                <div class="dc-quick__setup">
                    <div class="dc-logo dc-logo__center mb50">
                        <img src="<?php echo esc_url(DC_ASSETS_URI . 'img/logo.png'); ?>" alt="">
                    </div>
                </div>
            </div>
        </div>
        <div class="dc-row">
            <div class="dc-col">
                <div class="mb30">
                    <ul class="dc-quick__setup__nav">
                        <li class="quick__setup__item active">
                            <a href="#" class="quick__setup__link"><?php _e('GETTING STARTED', 'disable-comments'); ?></a>
                        </li>
                        <li class="quick__setup__item">
                            <a href="#" class="quick__setup__link"><?php _e('Disable comments', 'disable-comments'); ?></a>
                        </li>
                        <li class="quick__setup__item">
                            <a href="#" class="quick__setup__link"><?php _e('FINALIZE', 'disable-comments'); ?></a>
                        </li>
                    </ul>
                </div>
                <div id="disableCommentSetupSettings" class="dc-quick__setup__step dc-text__block__big__pad">
                    <div id="dcqTabBody_1" class="dc-quick__step__item">
                        <div class="quick__setup__item__header mb30">
                            <h2><?php _e('Getting Started', 'disable-comments'); ?></h2>
                            <p><?php _e('Easily get started with this easy setup wizard and complete setting up your Disable Comments', 'disable-comments'); ?></p>
                        </div>
                        <div class="dc-video__area">
                            <iframe width="622" height="350" src="https://www.youtube.com/embed/J9AteKzQpPs" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        </div>
                        <div class="dc-form__group">
                            <input name="dc_is_optin" type="checkbox" value="1" id="dc_option" checked>
                            <label for="dc_option">
                                <?php _e('Want to help make Disable Comments even better?', 'disable-comments'); ?>
                                <a id="whatWeCollect" href="#"><?php _e('What we collect.', 'disable-comments'); ?></a>
                                <p id="whatWeCollectMessage"><?php _e('We collect non-sensitive diagnostic data and plugin usage information. Your site URL, WordPress & PHP version, plugins, themes and email address to send you the discount coupon. This data lets us make sure this plugin always stays compatible with the most popular plugins and themes. No spam, I promise.', 'disable-comments'); ?></p>
                            </label>
                        </div>
                    </div>
                    <div id="dcqTabBody_2" class="dc-quick__step__item">
                        <?php include DC_PLUGIN_VIEWS_PATH . 'partials/_disable.php'; ?>
                    </div>
                    <div id="dcqTabBody_3" class="dc-quick__step__item">
                        <div class="quick__setup__item__header">
                            <h2><?php _e('Great Job!', 'disable-comments'); ?></h2>
                            <p><?php _e('You are ready to go! You can manage your settings anytime from Settings > Disable Comments', 'disable-comments'); ?></p>
                        </div>
                        <div class="finalize-thumb">
                            <img src="<?php echo esc_url(DC_ASSETS_URI . 'img/finalize-thumb.png'); ?>" alt="">
                        </div>
                        <div class="doc__button__wrap">
                            <a href="<?php echo esc_url('https://wpdeveloper.net/docs-category/disable-comments/'); ?>" class="doc__button" target="_blank" rel="nofollow"><?php _e('VISIT OUR DOCUMENTATION PAGE', 'disable-comments'); ?></a>
                        </div>
                    </div>
                    <div class="button__group mt30">
                        <a id="dcQuickPreviousBtn" href="#" class="button button--sm"><?php _e('Previous', 'disable-comments'); ?></a>
                        <a id="dcQuickNextBtn" href="#" class="button button__success"><?php _e('Next', 'disable-comments'); ?></a>
                        <a id="dcQuickSkipBtn" href="#" class="button button--sm"><?php _e('Skip', 'disable-comments'); ?></a>
                    </div>
                </div>

                <div class="footer__content mt50">
                    <a href="<?php echo $this->settings_page_url() . '&cancel=setup'; ?>" class="cancel-dc-setup"><?php _e('Return to Dashboard', 'disable-comments'); ?></a>
                    <?php
                        include DC_PLUGIN_VIEWS_PATH . 'partials/_menu.php';
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>