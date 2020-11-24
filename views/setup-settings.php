<div id="disablecommentssetupwrap" class="disablecommentswrap background__grey dc-quick__setup__wrap">
    <div class="dc-container">
        <div class="dc-row">
            <div class="dc-col">
                <div class="dc-quick__setup">
                    <div class="dc-logo dc-logo__center mb100">
                        <img src="<?php echo DC_ASSETS_URI; ?>img/logo.png" alt="">
                    </div>
                </div>
            </div>
        </div>
        <div class="dc-row">
            <div class="dc-col">
                <div class="mb30">
                    <ul class="dc-quick__setup__nav">
                        <li class="quick__setup__item active">
                            <a href="#" class="quick__setup__link">GETTING STARTED</a>
                        </li>
                        <li class="quick__setup__item">
                            <a href="#" class="quick__setup__link">Disable comments</a>
                        </li>
                        <li class="quick__setup__item">
                            <a href="#" class="quick__setup__link">Delete comments</a>
                        </li>
                        <li class="quick__setup__item">
                            <a href="#" class="quick__setup__link">FINALIZE</a>
                        </li>
                    </ul>
                </div>
                <div class="dc-quick__setup__step dc-text__block__big__pad">
                    <div id="dcqTabBody_1" class="dc-quick__step__item">
                        <div class="quick__setup__item__header mb50">
                            <h2>Getting Started</h2>
                            <p>Easily get started with this easy setup wizard and complete setting up your Knowledge Base.</p>
                        </div>
                        <div class="dc-video__area">
                            <a href="#" class="play__btn"><span></span></a>
                        </div>
                        <div class="dc-form__group">
                            <input type="checkbox" id="feedback__checkbox">
                            <label for="feedback__checkbox">Want to help make Disable Comments even better?</label>
                        </div>
                    </div>
                    <div id="dcqTabBody_2" class="dc-quick__step__item">
                        <div class="quick__setup__item__header mb50">
                            <h2>Disable Comments</h2>
                            <p>Easily get started with this easy setup wizard and complete setting up your Knowledge Base.</p>
                        </div>
                        <div class="disable_option dc-text__block mb30 mt30">
                            <input type="radio" id="disable__globally" name="disable-option-item">
                            <label for="disable__globally">Everywhere: <span>Permanently delete all comments on your WordPress website</span></label>
                            <p class="disable__option__description"><span class="danger">Warnings:</span> This will permanently delete comments everywhere on your website</p>
                        </div>
                        <div class="disable_option dc-text__block">
                            <input type="radio" id="disable__post" name="disable-option-item">
                            <label for="disable__post">On Certain Post Types:</label>
                            <div class="disable__checklist">
                                <div class="disable__checklist__item">
                                    <input type="checkbox" id="disable__checklist__item-post">
                                    <label for="disable__checklist__item-post">Posts</label>
                                </div>
                                <div class="disable__checklist__item">
                                    <input type="checkbox" id="disable__checklist__item-page">
                                    <label for="disable__checklist__item-page">Pages</label>
                                </div>
                                <div class="disable__checklist__item">
                                    <input type="checkbox" id="disable__checklist__item-media">
                                    <label for="disable__checklist__item-media">Media</label>
                                </div>
                                <div class="disable__checklist__item">
                                    <input type="checkbox" id="disable__checklist__item-doc">
                                    <label for="disable__checklist__item-doc">Docs</label>
                                </div>
                            </div>
                            <p class="disable__option__description"><span class="danger">Warnings:</span> This will remove existing comment entries for the selected post type(s) in the database and cannot be reverted without a database backups</p>
                        </div>
                    </div>
                    <div id="dcqTabBody_3" class="dc-quick__step__item">
                        <div class="quick__setup__item__header mb50">
                            <h2>Delete Comments</h2>
                            <p>Easily get started with this easy setup wizard and complete setting up your Knowledge Base.</p>
                        </div>
                        <div class="disable_option dc-text__block mb50 mt30">
                            <input type="radio" id="delete__globally" name="delete-option-item">
                            <label for="delete__globally">Everywhere: <span>Permanently delete all comments on your WordPress website</span></label>
                            <p class="disable__option__description"><span class="danger">Warnings:</span> This will permanently delete comments everywhere on your website</p>
                        </div>
                        <div class="disable_option dc-text__block">
                            <input type="radio" id="delete__post" name="delete-option-item">
                            <label for="delete__post">On Certain Post Types:</label>
                            <div class="disable__checklist">
                                <div class="disable__checklist__item">
                                    <input type="checkbox" id="delete__checklist__item-post">
                                    <label for="delete__checklist__item-post">Posts</label>
                                </div>
                                <div class="disable__checklist__item">
                                    <input type="checkbox" id="delete__checklist__item-page">
                                    <label for="delete__checklist__item-page">Pages</label>
                                </div>
                                <div class="disable__checklist__item">
                                    <input type="checkbox" id="delete__checklist__item-media">
                                    <label for="delete__checklist__item-media">Media</label>
                                </div>
                                <div class="disable__checklist__item">
                                    <input type="checkbox" id="delete__checklist__item-doc">
                                    <label for="delete__checklist__item-doc">Docs</label>
                                </div>
                            </div>
                            <p class="disable__option__description"><span class="danger">Warnings:</span> This will remove existing comment entries for the selected post type(s) in the database and cannot be reverted without a database backups</p>
                        </div>
                    </div>
                    <div id="dcqTabBody_4" class="dc-quick__step__item">
                        <div class="quick__setup__item__header">
                            <h2>Great Job!</h2>
                            <p>Your documentation page is ready! Make sure to add more articles and assign them to proper categories and you are good to go.</p>
                        </div>
                        <div class="finalize-thumb">
                            <img src="<?php echo DC_ASSETS_URI; ?>img/finalize-thumb.png" alt="">
                        </div>
                        <div class="doc__button__wrap">
                            <a href="#" class="doc__button">VISIT YOUR DOCUMENTATION PAGE</a>
                        </div>
                    </div>
                    <div class="button__group mt50">
                        <a id="dcQuickPreviousBtn" href="#" class="button button--sm">Previous</a>
                        <a id="dcQuickNextBtn" href="#" class="button button__success">Next</a>
                        <a id="dcQuickSkipBtn" href="#" class="button button--sm">Skip</a>
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