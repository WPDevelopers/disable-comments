<div class="template--wrapper background__grey">
    <div class="dc-text__block disable__comment__alert mb30">
        <div class="alert__content">
            <img src="<?php echo DC_ASSETS_URI; ?>img/icon-logo.png" alt="">
            <p>Want to help make Disable Comments even better?</p>
        </div>
        <div class="button__group">
            <a href="#" class="button button--sm button__success">Sure</a>
            <a href="#" class="button button--sm">No Thanks</a>
        </div>
    </div>

    <div class="disable__comment_block">
        <div class="disable__comment__nav__wrap">
            <p class="plugin__version">Version 5.1.1</p>
            <ul class="disable__comment__nav">
                <li class="disable__comment__nav__item">
                    <a href="#" class="disable__comment__nav__link">Disable Comments</a>
                </li>
                <li class="disable__comment__nav__item">
                    <a href="#" class="disable__comment__nav__link active">Delete Comments</a>
                </li>
            </ul>
        </div>
        <div class="disable__comment__tab">
            <div class="disable__comment__tab__item">
                <div class="dc-row">
                    <div class="dc-col-lg-9">
                        <div class="disable__comment__option mb50">
                            <h3 class="title">Settings</h3>
                            <p class="subtitle">Configure the settings below to disable comments globally or on specific types of post</p>
                            <div class="disable_option dc-text__block mb30 mt30">
                                <input type="radio" id="disable__globally" name="disable-option-item">
                                <label for="disable__globally">Everywhere: <span>Disable comments globally on your entire website</span></label>
                                <p class="disable__option__description"><span class="danger">Warnings:</span> This will disable comments from every page and post on your website. Use this setting if you do not want to show comments anywhere</p>
                            </div>
                            <div class="disable_option dc-text__block">
                                <input type="radio" id="disable__spacific" name="disable-option-item">
                                <label for="disable__spacific">On Specific Post Types:</label>
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
                                <p class="disable__option__description">This will disable comments from the selected post type(s) only. Comments will be visible on all other post types</p>
                            </div>
                        </div>
                        <div class="disable__comment__option mb50">
                            <h3 class="title">Disable Comments With API</h3>
                            <p class="subtitle">You can disable comments made on your website using WordPress specifications</p>
                            <div class="disable_option dc-text__block mt30">
                                <div class="disable__switchs">
                                    <div class="dissable__switch__item">
                                        <input type="checkbox" id="switch-xml" checked>
                                        <label for="switch-xml">
                                            <span class="switch">
                                                <span class="switch__text on">On</span>
                                                <span class="switch__text off">Off</span>
                                            </span>
                                            Disable Comments via XML-RPC
                                        </label>
                                    </div>
                                    <div class="dissable__switch__item">
                                        <input type="checkbox" id="switch-api">
                                        <label for="switch-api">
                                            <span class="switch">
                                                <span class="switch__text on">On</span>
                                                <span class="switch__text off">Off</span>
                                            </span>Disable Comments via REST API
                                        </label>
                                    </div>
                                </div>
                                <p class="disable__option__description">Turning on these settings will disable any comments made on your website via XML-RPC or REST API specifications</p>
                            </div>
                        </div>
                        <button class="button button__success">Save Changes</button>
                    </div>
                    <div class="dc-col-lg-3">
                        <div class="sidebar__widget__wrap">
                            <div class="dc-tutorials dc-text__block mb50">
                                <h3>Read Our Tutorials</h3>
                                <div class="tutorial__list">
                                    <div class="tutorial__item">
                                        <div class="icon">
                                            <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve">
                                                <g>
                                                    <g>
                                                        <g>
                                                            <path class="st0" d="M352.5,220c0-11-9-20-20-20h-206c-11,0-20,9-20,20s9,20,20,20h206C343.5,240,352.5,231,352.5,220z" />
                                                            <path class="st0" d="M126.5,280c-11,0-20,9-20,20s9,20,20,20h125.1c11,0,20-9,20-20s-9-20-20-20H126.5z" />
                                                            <path class="st0" d="M173.5,472h-66.9c-22.1,0-40-17.9-40-40V80c0-22.1,17.9-40,40-40h245.9c22.1,0,40,17.9,40,40v123
                                                                c0,11,9,20,20,20s20-9,20-20V80c0-44.1-35.9-80-80-80H106.6c-44.1,0-80,35.9-80,80v352c0,44.1,35.9,80,80,80h66.9
                                                                c11,0,20-9,20-20S184.5,472,173.5,472z" />
                                                            <path class="st0" d="M467.9,289.6c-23.4-23.4-61.5-23.4-84.8,0L273.2,399.1c-2.3,2.3-4.1,5.2-5,8.3l-23.9,78.7
                                                                c-2.1,7-0.3,14.6,4.8,19.8c3.8,3.9,9,6,14.3,6c1.8,0,3.6-0.2,5.3-0.7l80.7-22.4c3.3-0.9,6.4-2.7,8.8-5.1l109.6-109.4
                                                                C491.3,351,491.3,313,467.9,289.6z M333.8,451.8L293.2,463l11.9-39.1l74.1-73.9l28.3,28.3L333.8,451.8z M439.6,346.1l-3.9,3.9
                                                                l-28.3-28.3l3.9-3.9c7.8-7.8,20.5-7.8,28.3,0S447.4,338.3,439.6,346.1z" />
                                                            <path class="st0" d="M332.5,120h-206c-11,0-20,9-20,20s9,20,20,20h206c11,0,20-9,20-20S343.5,120,332.5,120z" />
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>
                                        </div>
                                        <h4><a href="#">What Does This Disable Comments Plugin Do</a></h4>
                                    </div>
                                    <div class="tutorial__item">
                                        <div class="icon">
                                            <svg version="1.1" id="Capa_6" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve">
                                                <g>
                                                    <g>
                                                        <g>
                                                            <path class="st0" d="M352.5,220c0-11-9-20-20-20h-206c-11,0-20,9-20,20s9,20,20,20h206C343.5,240,352.5,231,352.5,220z" />
                                                            <path class="st0" d="M126.5,280c-11,0-20,9-20,20s9,20,20,20h125.1c11,0,20-9,20-20s-9-20-20-20H126.5z" />
                                                            <path class="st0" d="M173.5,472h-66.9c-22.1,0-40-17.9-40-40V80c0-22.1,17.9-40,40-40h245.9c22.1,0,40,17.9,40,40v123
                                                                c0,11,9,20,20,20s20-9,20-20V80c0-44.1-35.9-80-80-80H106.6c-44.1,0-80,35.9-80,80v352c0,44.1,35.9,80,80,80h66.9
                                                                c11,0,20-9,20-20S184.5,472,173.5,472z" />
                                                            <path class="st0" d="M467.9,289.6c-23.4-23.4-61.5-23.4-84.8,0L273.2,399.1c-2.3,2.3-4.1,5.2-5,8.3l-23.9,78.7
                                                                c-2.1,7-0.3,14.6,4.8,19.8c3.8,3.9,9,6,14.3,6c1.8,0,3.6-0.2,5.3-0.7l80.7-22.4c3.3-0.9,6.4-2.7,8.8-5.1l109.6-109.4
                                                                C491.3,351,491.3,313,467.9,289.6z M333.8,451.8L293.2,463l11.9-39.1l74.1-73.9l28.3,28.3L333.8,451.8z M439.6,346.1l-3.9,3.9
                                                                l-28.3-28.3l3.9-3.9c7.8-7.8,20.5-7.8,28.3,0S447.4,338.3,439.6,346.1z" />
                                                            <path class="st0" d="M332.5,120h-206c-11,0-20,9-20,20s9,20,20,20h206c11,0,20-9,20-20S343.5,120,332.5,120z" />
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>
                                        </div>
                                        <h4><a href="#">What Does This Disable Comments Plugin Do</a></h4>
                                    </div>
                                    <div class="tutorial__item">
                                        <div class="icon">
                                            <svg version="1.1" id="Capa_7" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve">
                                                <g>
                                                    <g>
                                                        <g>
                                                            <path class="st0" d="M352.5,220c0-11-9-20-20-20h-206c-11,0-20,9-20,20s9,20,20,20h206C343.5,240,352.5,231,352.5,220z" />
                                                            <path class="st0" d="M126.5,280c-11,0-20,9-20,20s9,20,20,20h125.1c11,0,20-9,20-20s-9-20-20-20H126.5z" />
                                                            <path class="st0" d="M173.5,472h-66.9c-22.1,0-40-17.9-40-40V80c0-22.1,17.9-40,40-40h245.9c22.1,0,40,17.9,40,40v123
                                                                c0,11,9,20,20,20s20-9,20-20V80c0-44.1-35.9-80-80-80H106.6c-44.1,0-80,35.9-80,80v352c0,44.1,35.9,80,80,80h66.9
                                                                c11,0,20-9,20-20S184.5,472,173.5,472z" />
                                                            <path class="st0" d="M467.9,289.6c-23.4-23.4-61.5-23.4-84.8,0L273.2,399.1c-2.3,2.3-4.1,5.2-5,8.3l-23.9,78.7
                                                                c-2.1,7-0.3,14.6,4.8,19.8c3.8,3.9,9,6,14.3,6c1.8,0,3.6-0.2,5.3-0.7l80.7-22.4c3.3-0.9,6.4-2.7,8.8-5.1l109.6-109.4
                                                                C491.3,351,491.3,313,467.9,289.6z M333.8,451.8L293.2,463l11.9-39.1l74.1-73.9l28.3,28.3L333.8,451.8z M439.6,346.1l-3.9,3.9
                                                                l-28.3-28.3l3.9-3.9c7.8-7.8,20.5-7.8,28.3,0S447.4,338.3,439.6,346.1z" />
                                                            <path class="st0" d="M332.5,120h-206c-11,0-20,9-20,20s9,20,20,20h206c11,0,20-9,20-20S343.5,120,332.5,120z" />
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>
                                        </div>
                                        <h4><a href="#">What Does This Disable Comments Plugin Do</a></h4>
                                    </div>
                                </div>
                            </div>
                            <article class="blog__post">
                                <div class="thumb">
                                    <img src="<?php echo DC_ASSETS_URI; ?>img/blog/thumb-1.jpg" alt="">
                                    <a href="#" class="play__btn"><span></span></a>
                                </div>
                                <div class="blog__post__content">
                                    <h4><a href="#">How to Disable Comments in WordPress & Stop The Spammers</a></h4>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
            <div class="disable__comment__tab__item show">
                <div class="dc-row">
                    <div class="dc-col-lg-9">
                        <div class="disable__comment__option mb50">
                            <p class="subtitle"><span class="danger">Note:</span> These settings will permanently delete comments for your entire website, or for specific posts and comment types</p>
                            <div class="disable_option dc-text__block mb30 mt30">
                                <input type="radio" id="disable__everywhere" name="disable-option-item-2">
                                <label for="disable__everywhere">Everywhere: Permanently delete all comments on your WordPress website</label>
                                <p class="disable__option__description"><span class="danger">Warnings:</span> This will permanently delete comments everywhere on your website</p>
                            </div>
                            <div class="disable_option dc-text__block mb30">
                                <input type="radio" id="disable__post" name="disable-option-item-2">
                                <label for="disable__post">On Certain Post Types:</label>
                                <div class="disable__checklist">
                                    <div class="disable__checklist__item">
                                        <input type="checkbox" id="disable__checklist__item-post-2">
                                        <label for="disable__checklist__item-post-2">Posts</label>
                                    </div>
                                    <div class="disable__checklist__item">
                                        <input type="checkbox" id="disable__checklist__item-page-2">
                                        <label for="disable__checklist__item-page-2">Pages</label>
                                    </div>
                                    <div class="disable__checklist__item">
                                        <input type="checkbox" id="disable__checklist__item-media-2">
                                        <label for="disable__checklist__item-media-2">Media</label>
                                    </div>
                                    <div class="disable__checklist__item">
                                        <input type="checkbox" id="disable__checklist__item-doc-2">
                                        <label for="disable__checklist__item-doc-2">Docs</label>
                                    </div>
                                </div>
                                <p class="disable__option__description"><span class="danger">Warnings:</span> This will remove existing comment entries for the selected post type(s) in the database and cannot be reverted without a database backups</p>
                            </div>
                            <div class="disable_option dc-text__block">
                                <input type="radio" id="disable__certain" name="disable-option-item-2">
                                <label for="disable__certain">Delete Certain Comment Types:</label>
                                <div class="delete__feedback">
                                    <span class="delete__feedback__item"><a href="#">Comments</a></span>
                                    <span class="delete__feedback__item"><a href="#">Reviews</a></span>
                                </div>
                                <p class="disable__option__description"><span class="danger">Warnings:</span> This will remove existing comment entries for the selected comment type(s) in the database</p>
                            </div>
                        </div>
                    </div>
                    <div class="dc-col-lg-3">
                        <div class="sidebar__widget__wrap">
                            <div class="dc-tutorials dc-text__block mb50">
                                <h3>Read Our Tutorials</h3>
                                <div class="tutorial__list">
                                    <div class="tutorial__item">
                                        <div class="icon">
                                            <svg version="1.1" id="Capa_11" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve">
                                                <g>
                                                    <g>
                                                        <g>
                                                            <path class="st0" d="M352.5,220c0-11-9-20-20-20h-206c-11,0-20,9-20,20s9,20,20,20h206C343.5,240,352.5,231,352.5,220z" />
                                                            <path class="st0" d="M126.5,280c-11,0-20,9-20,20s9,20,20,20h125.1c11,0,20-9,20-20s-9-20-20-20H126.5z" />
                                                            <path class="st0" d="M173.5,472h-66.9c-22.1,0-40-17.9-40-40V80c0-22.1,17.9-40,40-40h245.9c22.1,0,40,17.9,40,40v123
                                                                c0,11,9,20,20,20s20-9,20-20V80c0-44.1-35.9-80-80-80H106.6c-44.1,0-80,35.9-80,80v352c0,44.1,35.9,80,80,80h66.9
                                                                c11,0,20-9,20-20S184.5,472,173.5,472z" />
                                                            <path class="st0" d="M467.9,289.6c-23.4-23.4-61.5-23.4-84.8,0L273.2,399.1c-2.3,2.3-4.1,5.2-5,8.3l-23.9,78.7
                                                                c-2.1,7-0.3,14.6,4.8,19.8c3.8,3.9,9,6,14.3,6c1.8,0,3.6-0.2,5.3-0.7l80.7-22.4c3.3-0.9,6.4-2.7,8.8-5.1l109.6-109.4
                                                                C491.3,351,491.3,313,467.9,289.6z M333.8,451.8L293.2,463l11.9-39.1l74.1-73.9l28.3,28.3L333.8,451.8z M439.6,346.1l-3.9,3.9
                                                                l-28.3-28.3l3.9-3.9c7.8-7.8,20.5-7.8,28.3,0S447.4,338.3,439.6,346.1z" />
                                                            <path class="st0" d="M332.5,120h-206c-11,0-20,9-20,20s9,20,20,20h206c11,0,20-9,20-20S343.5,120,332.5,120z" />
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>
                                        </div>
                                        <h4><a href="#">What Does This Disable Comments Plugin Do</a></h4>
                                    </div>
                                    <div class="tutorial__item">
                                        <div class="icon">
                                            <svg version="1.1" id="Capa_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve">
                                                <g>
                                                    <g>
                                                        <g>
                                                            <path class="st0" d="M352.5,220c0-11-9-20-20-20h-206c-11,0-20,9-20,20s9,20,20,20h206C343.5,240,352.5,231,352.5,220z" />
                                                            <path class="st0" d="M126.5,280c-11,0-20,9-20,20s9,20,20,20h125.1c11,0,20-9,20-20s-9-20-20-20H126.5z" />
                                                            <path class="st0" d="M173.5,472h-66.9c-22.1,0-40-17.9-40-40V80c0-22.1,17.9-40,40-40h245.9c22.1,0,40,17.9,40,40v123
                                                                c0,11,9,20,20,20s20-9,20-20V80c0-44.1-35.9-80-80-80H106.6c-44.1,0-80,35.9-80,80v352c0,44.1,35.9,80,80,80h66.9
                                                                c11,0,20-9,20-20S184.5,472,173.5,472z" />
                                                            <path class="st0" d="M467.9,289.6c-23.4-23.4-61.5-23.4-84.8,0L273.2,399.1c-2.3,2.3-4.1,5.2-5,8.3l-23.9,78.7
                                                                c-2.1,7-0.3,14.6,4.8,19.8c3.8,3.9,9,6,14.3,6c1.8,0,3.6-0.2,5.3-0.7l80.7-22.4c3.3-0.9,6.4-2.7,8.8-5.1l109.6-109.4
                                                                C491.3,351,491.3,313,467.9,289.6z M333.8,451.8L293.2,463l11.9-39.1l74.1-73.9l28.3,28.3L333.8,451.8z M439.6,346.1l-3.9,3.9
                                                                l-28.3-28.3l3.9-3.9c7.8-7.8,20.5-7.8,28.3,0S447.4,338.3,439.6,346.1z" />
                                                            <path class="st0" d="M332.5,120h-206c-11,0-20,9-20,20s9,20,20,20h206c11,0,20-9,20-20S343.5,120,332.5,120z" />
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>
                                        </div>
                                        <h4><a href="#">What Does This Disable Comments Plugin Do</a></h4>
                                    </div>
                                    <div class="tutorial__item">
                                        <div class="icon">
                                            <svg version="1.1" id="Capa_3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve">
                                                <g>
                                                    <g>
                                                        <g>
                                                            <path class="st0" d="M352.5,220c0-11-9-20-20-20h-206c-11,0-20,9-20,20s9,20,20,20h206C343.5,240,352.5,231,352.5,220z" />
                                                            <path class="st0" d="M126.5,280c-11,0-20,9-20,20s9,20,20,20h125.1c11,0,20-9,20-20s-9-20-20-20H126.5z" />
                                                            <path class="st0" d="M173.5,472h-66.9c-22.1,0-40-17.9-40-40V80c0-22.1,17.9-40,40-40h245.9c22.1,0,40,17.9,40,40v123
                                                                c0,11,9,20,20,20s20-9,20-20V80c0-44.1-35.9-80-80-80H106.6c-44.1,0-80,35.9-80,80v352c0,44.1,35.9,80,80,80h66.9
                                                                c11,0,20-9,20-20S184.5,472,173.5,472z" />
                                                            <path class="st0" d="M467.9,289.6c-23.4-23.4-61.5-23.4-84.8,0L273.2,399.1c-2.3,2.3-4.1,5.2-5,8.3l-23.9,78.7
                                                                c-2.1,7-0.3,14.6,4.8,19.8c3.8,3.9,9,6,14.3,6c1.8,0,3.6-0.2,5.3-0.7l80.7-22.4c3.3-0.9,6.4-2.7,8.8-5.1l109.6-109.4
                                                                C491.3,351,491.3,313,467.9,289.6z M333.8,451.8L293.2,463l11.9-39.1l74.1-73.9l28.3,28.3L333.8,451.8z M439.6,346.1l-3.9,3.9
                                                                l-28.3-28.3l3.9-3.9c7.8-7.8,20.5-7.8,28.3,0S447.4,338.3,439.6,346.1z" />
                                                            <path class="st0" d="M332.5,120h-206c-11,0-20,9-20,20s9,20,20,20h206c11,0,20-9,20-20S343.5,120,332.5,120z" />
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>
                                        </div>
                                        <h4><a href="#">What Does This Disable Comments Plugin Do</a></h4>
                                    </div>
                                </div>
                            </div>
                            <article class="blog__post">
                                <div class="thumb">
                                    <img src="<?php echo DC_ASSETS_URI; ?>img/blog/thumb-1.jpg" alt="">
                                    <a href="#" class="play__btn"><span></span></a>
                                </div>
                                <div class="blog__post__content">
                                    <h4><a href="#">How to Disable Comments in WordPress & Stop The Spammers</a></h4>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="more__items pt70">
        <div class="dc-row">
            <div class="dc-col">
                <div class="section__header">
                    <h2>Check Out Our Other Exciting Free WordPress Plugins</h2>
                </div>
            </div>
        </div>
        <div class="dc-row">
            <div class="dc-col-lg-4 dc-col-xs-6">
                <div class="dc-item__card_wrap">
                    <div class="dc-item__card">
                        <div class="thumb">
                            <img src="<?php echo DC_ASSETS_URI; ?>img/card/thumb-1.jpg" alt="">
                        </div>
                        <div class="card__content">
                            <h3>Essential Addons For Elementor</h3>
                            <p>Design stunning webpages using the best elements addons for Elementor pagebuilder</p>
                            <a href="#" class="button button--sm">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dc-col-lg-4 dc-col-xs-6">
                <div class="dc-item__card_wrap">
                    <div class="dc-item__card">
                        <div class="thumb">
                            <img src="<?php echo DC_ASSETS_URI; ?>img/card/thumb-2.jpg" alt="">
                        </div>
                        <div class="card__content">
                            <h3>NotificationX</h3>
                            <p>The ultimate WordPress marketing solution to skyrocket your website conversion rates</p>
                            <a href="#" class="button button--sm">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dc-col-lg-4 dc-col-xs-6">
                <div class="dc-item__card_wrap">
                    <div class="dc-item__card">
                        <div class="thumb">
                            <img src="<?php echo DC_ASSETS_URI; ?>img/card/thumb-3.jpg" alt="">
                        </div>
                        <div class="card__content">
                            <h3>BetterDocs</h3>
                            <p>Accelerate customer support with the most powerful WordPress knowledge base solution</p>
                            <a href="#" class="button button--sm">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dc-col-lg-4 dc-col-xs-6">
                <div class="dc-item__card_wrap">
                    <div class="dc-item__card">
                        <div class="thumb">
                            <img src="<?php echo DC_ASSETS_URI; ?>img/card/thumb-4.jpg" alt="">
                        </div>
                        <div class="card__content">
                            <h3>WP Scheduled Posts</h3>
                            <p>Powerful content management solution with Schedule Calendar, Auto Scheduler and more.</p>
                            <a href="#" class="button button--sm">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dc-col-lg-4 dc-col-xs-6">
                <div class="dc-item__card_wrap">
                    <div class="dc-item__card">
                        <div class="thumb">
                            <img src="<?php echo DC_ASSETS_URI; ?>img/card/thumb-5.jpg" alt="">
                        </div>
                        <div class="card__content">
                            <h3>ReviewX</h3>
                            <p>Boost sales with the ultimate multi-criteria reviews and ratings solution for WooCommerce</p>
                            <a href="#" class="button button--sm">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dc-col-lg-4 dc-col-xs-6">
                <div class="dc-item__card_wrap">
                    <div class="dc-item__card">
                        <div class="thumb">
                            <img src="<?php echo DC_ASSETS_URI; ?>img/card/thumb-6.jpg" alt="">
                        </div>
                        <div class="card__content">
                            <h3>EmbedPress</h3>
                            <p>Create high performing and engaging content by embedding anything on your WordPress site</p>
                            <a href="#" class="button button--sm">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer pt70 pb110">
        <div class="dc-row">
            <div class="dc-col">
                <div class="footer__content">
                    <img src="<?php echo DC_ASSETS_URI; ?>img/company-thumb.png" alt="">
                    <div class="footer__nav">
                        <ul>
                            <li><a href="#">About Us</a></li>
                            <li><a href="#">All Plugins</a></li>
                            <li><a href="#">Support Forum</a></li>
                            <li><a href="#">Docs</a></li>
                            <li><a href="#">Terms Of Service</a></li>
                            <li><a href="#">Privacy</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>