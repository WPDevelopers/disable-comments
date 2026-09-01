<?php

/**
 * Implements example command.
 */
class Disable_Comment_Command
{
	public $dc_instance;

    public function __construct($dc_instance)
    {
        $this->dc_instance = $dc_instance;

        $post_types    = array_keys($this->dc_instance->get_all_post_types());
        $comment_types = array_keys($this->dc_instance->get_all_comment_types());
        $post_types[] = $comment_types[] = 'all';

        $disable_synopsis = array(
            array(
                'type'        => 'assoc',
                'name'        => 'types',
                'description' => 'Disable comments from the selected post type(s) only.',
                'optional'    => true,
                'options'     => $post_types,
            ),
            array(
                'type'        => 'flag',
                'name'        => 'xmlrpc',
                'description' => 'Disable Comments via XML-RPC.',
                'optional'    => true,
            ),
            array(
                'type'        => 'flag',
                'name'        => 'rest-api',
                'description' => 'Disable Comments via REST API.',
                'optional'    => true,
            ),
            array(
                'type'        => 'flag',
                'name'        => 'add',
                'description' => 'Check specified checkbox in On Specific Post Types.', // check specified checkbox in `On Specific Post Types:`
                'optional'    => true,
            ),
            array(
                'type'        => 'flag',
                'name'        => 'remove',
                'description' => 'Uncheck specified checkbox in `On Specific Post Types.', // uncheck specified checkbox in `On Specific Post Types:`
                'optional'    => true,
            ),
            array(
                'type'        => 'flag',
                'name'        => 'disable-avatar',
                'description' => 'This will change Avatar state from your entire site.', // uncheck specified checkbox in `On Specific Post Types:`
                'optional'    => true,
            ),
        );
        if ($this->dc_instance->networkactive){
            $disable_synopsis[] = array(
                'type'        => 'assoc',
                'name'        => 'extra-post-types',
                'description' => 'If you want to disable comments on other custom post types on the entire network, you can supply a comma-separated list of post types below (use the slug that identifies the post type.',
                'optional'    => true,
            );
        }
        WP_CLI::add_command('disable-comments settings', [$this, 'disable'], [
            'synopsis' => $disable_synopsis,
            'when' => 'after_wp_load',
            'longdesc' =>   "## EXAMPLES
wp disable-comments settings --types=post
wp disable-comments settings --types=page --add
wp disable-comments settings --types=attachment --remove
wp disable-comments settings --xmlrpc --rest-api
wp disable-comments settings --xmlrpc=false --rest-api=false ",
        ]);

        $delete_synopsis = array(
            array(
                'type'        => 'assoc',
                'name'        => 'types',
                'description' => 'Remove existing comments entries for the selected post type(s) in the database and cannot be reverted without a database backups.',
                'optional'    => true,
                'options'     => $post_types,
            ),
            array(
                'type'        => 'assoc',
                'name'        => 'comment-types',
                'description' => 'Remove existing comment entries for the selected comment type(s) in the database and cannot be reverted without a database backups.',
                'optional'    => true,
                'options'     => $comment_types,
            ),
            array(
                'type'        => 'flag',
                'name'        => 'spam',
                'description' => 'Permanently delete all spam comments on your WordPress website.',
                'optional'    => true,
            ),
            array(
                'type'        => 'flag',
                'name'        => 'dry-run',
                'description' => 'Report how many comments would be deleted and exit without deleting anything.',
                'optional'    => true,
            ),
            array(
                'type'        => 'assoc',
                'name'        => 'export',
                'description' => 'Write the matching comments to this CSV file before deleting. Combine with --dry-run to export without deleting.',
                'optional'    => true,
            ),
        );
        if (!$this->dc_instance->networkactive){
            $delete_synopsis[] = array(
                'type'        => 'assoc',
                'name'        => 'extra-post-types',
                'description' => 'If you want to disable comments on other custom post types on the entire network, you can supply a comma-separated list of post types below (use the slug that identifies the post type.',
                'optional'    => true,
            );
        }
        WP_CLI::add_command('disable-comments delete', [$this, 'delete'], [
            'synopsis' => $delete_synopsis,
            'when' => 'after_wp_load',
            'longdesc' =>   "## EXAMPLES
wp disable-comments delete --types=post,page
wp disable-comments delete --types=post,page  --extra-post-types=contact
wp disable-comments delete --comment-types=comment
wp disable-comments delete --types=post --dry-run
wp disable-comments delete --spam --export=spam-backup.csv "
        ]);

        WP_CLI::add_command('disable-comments export', [$this, 'export'], [
            'when' => 'after_wp_load',
            'synopsis' => array(
                array(
                    'type'        => 'assoc',
                    'name'        => 'file',
                    'description' => 'Write the settings to this file instead of standard output.',
                    'optional'    => true,
                ),
            ),
            'longdesc' => "## EXAMPLES
wp disable-comments export
wp disable-comments export --file=dc-settings.json",
        ]);

        WP_CLI::add_command('disable-comments import', [$this, 'import'], [
            'when' => 'after_wp_load',
            'synopsis' => array(
                array(
                    'type'        => 'assoc',
                    'name'        => 'file',
                    'description' => 'Path to a settings file written by `wp disable-comments export`.',
                    'optional'    => false,
                ),
                array(
                    'type'        => 'flag',
                    'name'        => 'dry-run',
                    'description' => 'Report what would change and write nothing.',
                    'optional'    => true,
                ),
            ),
            'longdesc' => "## EXAMPLES
wp disable-comments import --file=dc-settings.json --dry-run
wp disable-comments import --file=dc-settings.json",
        ]);
    }

    /**
     * Exports Disable Comments settings as JSON.
     *
     * @when after_wp_load
     */
    function export($args, $assoc_args)
    {
        // Read from whichever store the matching import would write, by the
        // same rule import() uses below. Without it a network-wide install
        // exports the current blog's option - or, on a blog the network has
        // not disabled, the blank effective config the constructor substitutes.
        $is_network_ctx = (bool) ($this->dc_instance->networkactive && $this->dc_instance->sitewide_settings !== '1');

        $json = wp_json_encode($this->dc_instance->export_settings($is_network_ctx), JSON_PRETTY_PRINT);
        $file = WP_CLI\Utils\get_flag_value($assoc_args, 'file');

        if (empty($file)) {
            WP_CLI::line($json);
            return;
        }

        if (false === file_put_contents($file, $json)) {
            WP_CLI::error(sprintf('Could not write to %s.', $file));
        }

        WP_CLI::success(sprintf('Settings written to %s', $file));
    }

    /**
     * Imports Disable Comments settings from a JSON file.
     *
     * @when after_wp_load
     */
    function import($args, $assoc_args)
    {
        $file    = WP_CLI\Utils\get_flag_value($assoc_args, 'file');
        $dry_run = (bool) WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run');

        if (empty($file) || !is_readable($file)) {
            WP_CLI::error(sprintf('Cannot read %s.', $file));
        }

        // Route to whichever option store this install actually reads. On a
        // network-wide install the default (false) would write the current
        // blog's option while every request keeps reading the network one —
        // reporting success and changing nothing anyone sees.
        $is_network_ctx = (bool) ($this->dc_instance->networkactive && $this->dc_instance->sitewide_settings !== '1');

        $result = $this->dc_instance->import_settings(file_get_contents($file), $dry_run, $is_network_ctx);

        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }

        // Before the no-change return: a file requesting only unregistered post
        // types has an empty diff and a populated unknown list, and "already
        // matches" would hide that none of it takes effect.
        if (!empty($result['unknown_post_types'])) {
            WP_CLI::warning(sprintf(
                'Not registered on this site, so they will have no effect: %s',
                implode(', ', $result['unknown_post_types'])
            ));
        }

        if (empty($result['changes'])) {
            WP_CLI::success('Nothing to change — this site already matches that file.');
            return;
        }

        $rows = array();
        foreach ($result['changes'] as $key => $change) {
            $rows[] = array(
                'setting' => $key,
                'from'    => $this->format_setting_value($change['from']),
                'to'      => $this->format_setting_value($change['to']),
            );
        }
        WP_CLI\Utils\format_items('table', $rows, array('setting', 'from', 'to'));

        if ($dry_run) {
            WP_CLI::success('Dry run: nothing was changed.');
            return;
        }

        WP_CLI::success('Settings imported.');
    }

    /**
     * Render a setting value for the diff table.
     */
    private function format_setting_value($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            if (empty($value)) {
                return '(none)';
            }

            // conditional_rules is a list of rule arrays, and implode() on a
            // nested value emits an array-to-string notice and prints the word
            // "Array" — noise in output automation reads, and an operator
            // reviewing a rule change learns nothing from it. Anything nested
            // goes out as JSON so the diff shows what actually changed.
            foreach ($value as $item) {
                if (is_array($item)) {
                    return wp_json_encode(array_values($value));
                }
            }

            return implode(', ', $value);
        }

        return (string) $value;
    }

    /**
     * Disable Comments on your website.
     *
     * @when after_wp_load
     */
    function disable($args, $assoc_args)
    {
        $msg = "";
        $disable_comments_settings = array();
        $types = WP_CLI\Utils\get_flag_value($assoc_args, 'types');
        $add = WP_CLI\Utils\get_flag_value($assoc_args, 'add');
        $remove = WP_CLI\Utils\get_flag_value($assoc_args, 'remove');
        $extra_post_types = WP_CLI\Utils\get_flag_value($assoc_args, 'extra-post-types');
        $remove_xmlrpc_comments = WP_CLI\Utils\get_flag_value($assoc_args, 'xmlrpc');
        $remove_rest_API_comments = WP_CLI\Utils\get_flag_value($assoc_args, 'rest-api');
        $disable_avatar = WP_CLI\Utils\get_flag_value($assoc_args, 'disable-avatar');

        if ($types === 'all') {
            $disable_comments_settings['mode'] = 'remove_everywhere';
            $msg .= __( 'Comments is disabled everywhere. ', 'disable-comments' );
        } elseif(!empty($types) ) {
            $disable_comments_settings['mode'] = 'selected_types';
            $_types = array_map('trim', explode(',', $types));
            $disabled_post_types = $this->dc_instance->get_disabled_post_types();
            // translators: %s: post types to be disabled
            $new_msg = sprintf( __( 'Comments disabled for %s. ', 'disable-comments' ), $types );
            if(!empty($add)){
                $_types = array_unique(array_merge($disabled_post_types, $_types));
                // translators: %s: post types to be disabled
                $new_msg = sprintf( __( 'Comments disabled for %s. ', 'disable-comments' ), $types );
            }
            if(!empty($remove)){
                $_types = array_diff($disabled_post_types, $_types);
                // translators: %s: post types to be enabled
                $new_msg = sprintf( __( 'Comments enabled for %s. ', 'disable-comments' ), $types );
            }

            $msg = $new_msg;
            $disable_comments_settings['disabled_types'] = $_types;
        }

        // for network.
        if(!empty($extra_post_types)){
            $disable_comments_settings['extra_post_types'] = $extra_post_types;
            // translators: %s: post types to be disabled in network
            $msg .= sprintf( __( 'Custom post types: %s. ', 'disable-comments' ), $extra_post_types );
        }

        if(isset($remove_xmlrpc_comments)){
            $disable_comments_settings['remove_xmlrpc_comments'] = $remove_xmlrpc_comments;
            if($remove_xmlrpc_comments && $remove_xmlrpc_comments !== 'false'){
                $msg .= __( 'Disable Comments via XML-RPC. ', 'disable-comments' );
            }
            else{
                $msg .= __( 'Enabled Comments via XML-RPC. ', 'disable-comments' );
            }
        }
        if(isset($remove_rest_API_comments)){
            $disable_comments_settings['remove_rest_API_comments'] = $remove_rest_API_comments;
            if($remove_rest_API_comments && $remove_rest_API_comments !== 'false'){
                $msg .= __( 'Disable Comments via REST API. ', 'disable-comments' );
            }
            else{
                $msg .= __( 'Enabled Comments via REST API. ', 'disable-comments' );
            }
        }
        if($disable_avatar != null){
            $disable_comments_settings['disable_avatar'] = $disable_avatar;
            if($disable_avatar && $disable_avatar !== 'false'){
                $msg .= __( 'Disabled Avatar on your entire site. ', 'disable-comments' );
            }
            else{
                $msg .= __( 'Enabled Avatar on your entire site. ', 'disable-comments' );
            }
        }

        $this->dc_instance->disable_comments_settings($disable_comments_settings);

        WP_CLI::success($msg);
    }

    /**
     * Deletes Comments on your website.
     *
     * @when after_wp_load
     */
    function delete($args, $assoc_args)
    {
        $msg = "";
        $delete_comments_settings = array('delete' => true);
        $selected_delete_types = WP_CLI\Utils\get_flag_value($assoc_args, 'types');
        $delete_extra_post_types = WP_CLI\Utils\get_flag_value($assoc_args, 'extra-post-types');
        $delete_comment_types = WP_CLI\Utils\get_flag_value($assoc_args, 'comment-types');
        $delete_spam_types = WP_CLI\Utils\get_flag_value($assoc_args, 'spam');


        if ( $delete_comment_types === 'all' || $selected_delete_types === 'all' ) {
            $delete_comments_settings['delete_mode'] = 'delete_everywhere';
        } elseif( !empty($selected_delete_types)) {
            $delete_comments_settings['delete_mode'] = 'selected_delete_types';
            $delete_comments_settings['delete_types'] = array_map('trim', explode(',', $selected_delete_types));
        } elseif(!empty($delete_comment_types)) {
            $delete_comments_settings['delete_mode'] = 'selected_delete_comment_types';
            $delete_comments_settings['delete_comment_types'] = array_map('trim', explode(',', $delete_comment_types));
        } elseif(!empty($delete_spam_types)) {
            $delete_comments_settings['delete_mode'] = 'delete_spam';
        } else{
            WP_CLI::error("Please provide valid parameters. \nSee 'wp help dc delete' for more information.");
        }

        // for network.
        if(!empty($delete_extra_post_types)){
            $delete_comments_settings['delete_extra_post_types'] = $delete_extra_post_types;
        }

        $dry_run = WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run');
        $export  = WP_CLI\Utils\get_flag_value($assoc_args, 'export');

        // Null unless an export runs below, and declared out here rather than
        // inside that branch: it is passed to the delete either way, and an
        // ordinary delete must not emit an undefined-variable warning into
        // output that automation reads.
        $ceilings = null;

        // Export before deleting, so a mistaken delete is still recoverable
        // from the file. Runs for a dry run too - that is the point of it.
        //
        // A destination, if there is one, is a non-empty string. Spelled out
        // rather than as empty(), which calls the perfectly legal filename "0"
        // false and would skip the export in silence while running the
        // irreversible delete uncapped, having been asked for a backup.
        //
        // Not a null check either: get_flag_value() answers null when the flag
        // is absent, but WP-CLI's --no-export spelling supplies it as boolean
        // false, and false is neither null nor ''. That reached fopen(false)
        // and killed the command with a ValueError before it deleted or
        // previewed anything. is_string() covers absent, negated and empty
        // alike, and still lets "0" through.
        if (is_string($export) && '' !== $export) {
            // Streamed straight to the destination. export_comments_csv()
            // buffers the whole file in memory first, which would undo the
            // batching on exactly the high-volume sites that reach for this.
            $handle = fopen($export, 'w');
            if (false === $handle) {
                WP_CLI::error(sprintf('Could not write the export to %s. Nothing was deleted.', $export));
            }

            // A destination can open fine and then fail mid-write - a disk
            // filling up, most obviously. Treating a truncated file as a
            // complete backup and then deleting against it is the one failure
            // mode this flag exists to prevent, so nothing below runs unless
            // every byte landed.
            try {
                $rows = $this->dc_instance->stream_comments_csv($handle, $delete_comments_settings);
            } catch (Exception $e) {
                fclose($handle);
                WP_CLI::error(sprintf('Export to %s failed: %s. Nothing was deleted.', $export, $e->getMessage()));
            }

            if (false === fclose($handle)) {
                WP_CLI::error(sprintf('Could not finish writing %s. Nothing was deleted.', $export));
            }

            $ceilings = $this->dc_instance->get_last_export_ceilings();

            WP_CLI::log(sprintf('Exported %d comment(s) to %s', $rows, $export));
        }

        if (!empty($dry_run)) {
            $preview = $this->dc_instance->count_comments_for_delete($delete_comments_settings);

            $rows = array();
            foreach ($preview['breakdown'] as $label => $count) {
                $rows[] = array('target' => $label, 'comments' => $count);
            }
            if (!empty($rows)) {
                WP_CLI\Utils\format_items('table', $rows, array('target', 'comments'));
            }

            WP_CLI::success(sprintf('Dry run: %d comment(s) would be deleted. Nothing was deleted.', $preview['total']));
            return;
        }

        $logged_msg = $this->dc_instance->delete_comments_settings($delete_comments_settings, $ceilings);
        WP_CLI::success( is_array($logged_msg) ? implode( "\n", $logged_msg ) : $logged_msg );
    }
}
