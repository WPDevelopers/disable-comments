<?php

/**
 * Implements example command.
 */
class Disable_Comment_Command
{

    public function __construct($dc_instance)
    {
        $this->dc_instance = $dc_instance;

        $post_types = array_keys($this->dc_instance->get_all_post_types());
        $comment_types       = array_keys($this->dc_instance->get_all_comment_types());

        $disable_synopsis = array(
            array(
                'type'        => 'assoc',
                'name'        => 'mode',
                'description' => 'Configure the settings to disable comments globally or on specific types of post.',
                'optional'    => true,
                'default'     => 'everywhere',
                'options'     => ['everywhere', 'selected_types'],
            ),
            array(
                'type'        => 'assoc',
                'name'        => 'disabled-types',
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
wp disable-comments settings --mode=everywhere 
wp disable-comments settings --mode=selected_types --disabled-types=post
wp disable-comments settings --mode=selected_types --disabled-types=page --add
wp disable-comments settings --mode=selected_types --disabled-types=attachment --remove
wp disable-comments settings --xmlrpc --rest-api 
wp disable-comments settings --xmlrpc=false --rest-api=false ",
        ]);

        $delete_synopsis = array(
            array(
                'type'        => 'assoc',
                'name'        => 'mode',
                'description' => 'Configure the settings to Delete Comments globally or on specific types of post.',
                'optional'    => true,
                // 'default'     => 'everywhere',
                'options'     => ['delete_everywhere', 'selected_delete_types', 'selected_delete_comment_types'],
            ),
            array(
                'type'        => 'assoc',
                'name'        => 'deleted-types',
                'description' => 'Remove existing comments entries for the selected post type(s) in the database and cannot be reverted without a database backups.',
                'optional'    => true,
                // 'default'     => '',
                'options'     => $post_types,
            ),
            array(
                'type'        => 'assoc',
                'name'        => 'delete-comment-types',
                'description' => 'Remove existing comment entries for the selected comment type(s) in the database and cannot be reverted without a database backups.',
                'optional'    => true,
                // 'default'     => '',
                'options'     => $comment_types,
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
wp disable-comments delete --mode=delete_everywhere 
wp disable-comments delete --mode=selected_delete_types --deleted-types=post,page
wp disable-comments delete --mode=selected_delete_types --deleted-types=post,page  --extra-post-types=contact
wp disable-comments delete --mode=selected_delete_comment_types --delete-comment-types=comment "
        ]);

    }

    /**
     * Disable Comments on your website.
     *
     */
    function disable($args, $assoc_args)
    {
        $msg = "";
        $disable_comments_settings = array();
        $mode = WP_CLI\Utils\get_flag_value($assoc_args, 'mode');
        $types = WP_CLI\Utils\get_flag_value($assoc_args, 'disabled-types');
        $add = WP_CLI\Utils\get_flag_value($assoc_args, 'add');
        $remove = WP_CLI\Utils\get_flag_value($assoc_args, 'remove');
        $extra_post_types = WP_CLI\Utils\get_flag_value($assoc_args, 'extra-post-types');
        $remove_xmlrpc_comments = WP_CLI\Utils\get_flag_value($assoc_args, 'xmlrpc');
        $remove_rest_API_comments = WP_CLI\Utils\get_flag_value($assoc_args, 'rest-api');

        if ($mode === 'everywhere') {
            $disable_comments_settings['mode'] = 'remove_everywhere';
            $msg .= "Comments disabled everywhere. ";
        } elseif(!empty($types)) {
            $disable_comments_settings['mode'] = 'selected_types';
            $_types = array_map('trim', explode(',', $types));
            $disabled_post_types = $this->dc_instance->get_disabled_post_types();
        
            if(!empty($add)){
                $_types = array_unique(array_merge($disabled_post_types, $_types));
            }
            if(!empty($remove)){
                $_types = array_diff($disabled_post_types, $_types);
            }

            $disable_comments_settings['disabled_types'] = $_types;
            $msg .= "Comments disabled for \"$types\". ";
        }

        // for network.
        if(!empty($extra_post_types)){
            $disable_comments_settings['extra_post_types'] = $extra_post_types;
            $msg .= "Custom post types: \"$extra_post_types\". ";
        }

        if(isset($remove_xmlrpc_comments)){
            $disable_comments_settings['remove_xmlrpc_comments'] = $remove_xmlrpc_comments;
            $msg .= "Disable Comments via XML-RPC. ";
        }
        if(isset($remove_rest_API_comments)){
            $disable_comments_settings['remove_rest_API_comments'] = $remove_rest_API_comments;
            $msg .= "Disable Comments via REST API. ";
        }

        $this->dc_instance->disable_comments_settings($disable_comments_settings);

        WP_CLI::success($msg);
    }

    /**
     * Deletes Comments on your website.
     *
     */
    function delete($args, $assoc_args)
    {
        $msg = "";
        $delete_comments_settings = array('delete' => true);
        $delete_mode = WP_CLI\Utils\get_flag_value($assoc_args, 'mode');
        $selected_delete_types = WP_CLI\Utils\get_flag_value($assoc_args, 'deleted-types');
        $delete_extra_post_types = WP_CLI\Utils\get_flag_value($assoc_args, 'extra-post-types');
        $delete_comment_types = WP_CLI\Utils\get_flag_value($assoc_args, 'delete-comment-types');

        if ($delete_mode === 'delete_everywhere') {
            $delete_comments_settings['delete_mode'] = 'delete_everywhere';
        } elseif($delete_mode === 'selected_delete_types' || !empty($selected_delete_types)) {
            $delete_comments_settings['delete_mode'] = 'selected_delete_types';
            $delete_comments_settings['delete_types'] = array_map('trim', explode(',', $selected_delete_types));
        } elseif($delete_mode === 'selected_delete_comment_types' || !empty($delete_comment_types)) {
            $delete_comments_settings['delete_mode'] = 'selected_delete_comment_types';
            $delete_comments_settings['delete_comment_types'] = array_map('trim', explode(',', $delete_comment_types));
        }
        else{
            WP_CLI::error("Please provide valid parameters. \nSee 'wp help dc delete' for more information.");

        }

        // for network.
        if(!empty($delete_extra_post_types)){
            $delete_comments_settings['delete_extra_post_types'] = $delete_extra_post_types;
        }

        ob_start();
        $this->dc_instance->delete_comments_settings($delete_comments_settings);
        $msg = wp_strip_all_tags(ob_get_clean());
        WP_CLI::success($msg);
    }
}
