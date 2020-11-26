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
                            <p><?php _e('Easily get started with this easy setup wizard and complete setting up your Knowledge Base.', 'disable-comments'); ?></p>
                        </div>
                        <div class="dc-video__area">
                            <iframe width="650" height="350" src="https://www.youtube.com/embed/2g9CapDFtkI" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        </div>
                        <div class="dc-form__group">
                            <input name="dc_is_optin" type="checkbox" value="1" id="dc_option" checked>
                            <label for="dc_option"><?php _e('Want to help make Disable Comments even better?', 'disable-comments'); ?></label>
                        </div>
                    </div>
                    <div id="dcqTabBody_2" class="dc-quick__step__item">
                        <?php include DC_PLUGIN_VIEWS_PATH . 'partials/_disable.php'; ?>
                    </div>
                    <div id="dcqTabBody_3" class="dc-quick__step__item">
                        <div class="quick__setup__item__header">
                            <h2><?php _e('Great Job!', 'disable-comments'); ?></h2>
                            <p><?php _e('Your documentation page is ready! Make sure to add more articles and assign them to proper categories and you are good to go.', 'disable-comments'); ?></p>
                        </div>
                        <div class="finalize-thumb">
                            <img src="<?php echo esc_url(DC_ASSETS_URI . 'img/finalize-thumb.png'); ?>" alt="">
                        </div>
                        <div class="doc__button__wrap">
                            <a href="#" class="doc__button"><?php _e('VISIT YOUR DOCUMENTATION PAGE', 'disable-comments'); ?></a>
                        </div>
                    </div>
                    <div class="button__group mt30">
                        <a id="dcQuickPreviousBtn" href="#" class="button button--sm"><?php _e('Previous', 'disable-comments'); ?></a>
                        <a id="dcQuickNextBtn" href="#" class="button button__success"><?php _e('Next', 'disable-comments'); ?></a>
                        <a id="dcQuickSkipBtn" href="#" class="button button--sm"><?php _e('Skip', 'disable-comments'); ?></a>
                    </div>
                </div>

                <div class="footer__content mt80">
                    <?php
                    include DC_PLUGIN_VIEWS_PATH . 'partials/_menu.php';
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>