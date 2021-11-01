

<div class="">
<div class="d__flex mb15 space__between">
    <div class='subsite__checklist__item' style="flex: 1 1 200px;">
        <input
            type='checkbox'
            class='check-all'
            id='sites__option__<?php echo $type;?>__check__all'
            name='disabled_sites[all]'
            value='1'
        >
        <label for='sites__option__<?php echo $type;?>__check__all'>
            <b><?php _e('Select All', 'disable-comments'); ?></b>
            <small>(0 selected)</small>
        </label>
    </div>
    <div class="mb10" style="text-align: right; flex: 0 0 200px;">
        <div class="icon__input">
            <span class="icon">
                <img src="<?php echo esc_url(DC_ASSETS_URI . 'img/search.svg'); ?>" alt="">
            </span>
            <input type="text" class="form__control w-100" placeholder="Search...">
        </div>
    </div>
</div>
<div class="sites_list">

</div>
<div class="d__flex space__between">
    <div class="d__flex item__number__controller">
        <p>Show Items:</p>
        <div class="dc-select">
            <span class="icon"></span>
            <select class="form__control">
                <option value="">10</option>
                <option value="">20</option>
                <option value="">30</option>
                <option value="">200</option>
            </select>
        </div>
    </div>
    <div class="has-pagination"></div>
</div>
</div>

