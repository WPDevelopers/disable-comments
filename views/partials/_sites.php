<div class="sites__options remove__checklist">
    <div class='remove__checklist__item'>
        <input
            type='checkbox'
            class='check-all'
            id='sites__option__{$type}__check__all'
            name='disabled_sites[all]'
            value='1'
        >
        <label for='sites__option__{$type}__check__all'>
            <b><?php _e('Select All', 'disable-comments'); ?></b>
            <small>(0 selected)</small>
        </label>
    </div>

    <?php
    $sub_sites = get_sites([
        'number' => 0,
    ]);
    foreach ($sub_sites as $sub_site) {
        $sub_site_id = $sub_site->blog_id;
        $blog        = get_blog_details($sub_site_id);
        $is_checked  = checked(!empty($disabled_site_options["site_$sub_site_id"]), true, false);
        echo "
        <div class='remove__checklist__item'>
            <input type='hidden' name='disabled_sites[site_$sub_site_id]' value='0' />
            <input
                type='checkbox'
                id='sites__option__{$type}__$sub_site_id'
                class='site_option'
                name='disabled_sites[site_$sub_site_id]'
                value='1'
                $is_checked
            />
            <label for='sites__option__{$type}__$sub_site_id'>
                {$blog->blogname}
            </label>
        </div>
        ";
    }
    ?>
</div>