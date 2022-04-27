<?php

/**
 * Implements example command.
 */
class Disable_Comment_Command
{

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
wp disable-comments delete --comment-types=comment "
        ]);

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

        $logged_msg = $this->dc_instance->delete_comments_settings($delete_comments_settings);
        WP_CLI::success( is_array($logged_msg) ? implode( "\n", $logged_msg ) : $logged_msg );
    }
}
