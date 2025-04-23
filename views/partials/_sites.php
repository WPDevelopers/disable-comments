<div class="sites-selection" role="region" aria-labelledby="sites-selection-heading">
    <div class="d__flex mb15 space__between">
        <div class="subsite__checklist__item checkbox-style" style="flex: 1 1 200px;">
            <input type="checkbox"
                class="check-all"
                id="sites__option__<?php echo esc_attr($type); ?>__check__all"
                name="disabled_sites[all]"
                value="1"
                aria-label="<?php esc_attr_e('Select or deselect all sites', 'disable-comments'); ?>"
                aria-controls="sites-list">
            <label for="sites__option__<?php echo esc_attr($type); ?>__check__all">
                <i class="icon" tabindex="0"></i>
                <b><?php esc_html_e('Select All', 'disable-comments'); ?></b>
                <span class="selected-count" aria-live="polite">
                    (<?php esc_html_e('0 selected', 'disable-comments'); ?>)
                </span>
            </label>
        </div>

        <div class="mb10 search-container" style="text-align: right; flex: 0 0 230px;">
            <div class="icon__input sub__site_control">
                <label for="site-search" class="visually-hidden">
                    <?php esc_html_e('Search sites', 'disable-comments'); ?>
                </label>
                <span class="icon" aria-hidden="true">
                    <img src="<?php echo esc_url(DC_ASSETS_URI . 'img/search.svg'); ?>"
                        alt=""
                        role="presentation">
                </span>
                <input type="text"
                    id="site-search"
                    class="form__control w-100 sub-site-search"
                    placeholder="<?php esc_attr_e('Search by domain name...', 'disable-comments'); ?>"
                    aria-controls="sites-list"
                    style="padding-right: 35px;">
            </div>
        </div>
    </div>

    <div id="sites-list"
        class="sites_list"
        role="group"
        aria-label="<?php esc_attr_e('List of available sites', 'disable-comments'); ?>">
        <div class="nothing-found" role="alert" aria-live="polite">
            <p><?php esc_html_e('No subsite found', 'disable-comments'); ?></p>
        </div>
    </div>

    <div class="d__flex space__between">
        <div class="d__flex item__number__controller sub__site_control page__size__wrapper">
            <label for="page-size" class="show-items-label">
                <?php esc_html_e('Show Items:', 'disable-comments'); ?>
            </label>
            <div class="dc-select">
                <span class="icon" aria-hidden="true"></span>
                <select id="page-size"
                    class="form__control page__size"
                    tabindex="0"
                    aria-label="<?php esc_attr_e('Number of items to display per page', 'disable-comments'); ?>">
                    <option value="20"><?php esc_html_e('20', 'disable-comments'); ?></option>
                    <option value="50" selected><?php esc_html_e('50', 'disable-comments'); ?></option>
                    <option value="100"><?php esc_html_e('100', 'disable-comments'); ?></option>
                    <option value="200"><?php esc_html_e('200', 'disable-comments'); ?></option>
                </select>
            </div>
        </div>
        <div class="has-pagination" role="navigation" aria-label="<?php esc_attr_e('Sites pagination', 'disable-comments'); ?>"></div>
    </div>
</div>