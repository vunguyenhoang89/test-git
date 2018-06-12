<?php

add_action('save_post', 'social_save_post');
//add_action('publish_post', 'social_auto_publish_event');

/* Each time user commits post */
function social_save_post($post_id)
{
    /* Check whether $_POST existed FB */
    if (isset($_POST['xyz_smap_post_permission'])) {
        $social_auto_publish_data['xyz_smap_post_permission'] = $_POST['xyz_smap_post_permission'];
    }

    if (isset($_POST['xyz_smap_fb_date'])) {
        $social_auto_publish_data['xyz_smap_fb_date'] = $_POST['xyz_smap_fb_date'];
    }

    if (isset($_POST['xyz_smap_fb_hour'])) {
        $social_auto_publish_data['xyz_smap_fb_hour'] = $_POST['xyz_smap_fb_hour'];
    }

    if (isset($_POST['xyz_smap_fb_minute'])) {
        $social_auto_publish_data['xyz_smap_fb_minute'] = $_POST['xyz_smap_fb_minute'];
    }

    if (isset($_POST['xyz_smap_message'])) {
        $social_auto_publish_data['xyz_smap_message'] = $_POST['xyz_smap_message'];
    }

    /* Check whether $_POST existed TW */
    if (isset($_POST['xyz_smap_twpost_permission'])) {
        $social_auto_publish_data['xyz_smap_twpost_permission'] = $_POST['xyz_smap_twpost_permission'];
    }

    if (isset($_POST['xyz_smap_tw_date'])) {
        $social_auto_publish_data['xyz_smap_tw_date'] = $_POST['xyz_smap_tw_date'];
    }

    if (isset($_POST['xyz_smap_tw_hour'])) {
        $social_auto_publish_data['xyz_smap_tw_hour'] = $_POST['xyz_smap_tw_hour'];
    }

    if (isset($_POST['xyz_smap_tw_minute'])) {
        $social_auto_publish_data['xyz_smap_tw_minute'] = $_POST['xyz_smap_tw_minute'];
    }

    if (isset($_POST['xyz_smap_twmessage_title'])) {
        $social_auto_publish_data['xyz_smap_twmessage_title'] = $_POST['xyz_smap_twmessage_title'];
    }

    if (isset($_POST['xyz_smap_twmessage'])) {
        $social_auto_publish_data['xyz_smap_twmessage'] = $_POST['xyz_smap_twmessage'];
    }

    $social_auto_publish_data = !empty($social_auto_publish_data) ? $social_auto_publish_data : array();
    $meta_key = 'sparkxlab_social_auto_publish';
    crud_post_meta($social_auto_publish_data, $post_id, $meta_key);

    social_auto_publish_event($post_id);
    if (isset($_POST['xyz_smap_post_permission'])) {
        spark_clear_FB_scrape($post_id);
    }
}

/* Auto publish post to social */
function social_auto_publish_event($post_id) {
    $social_auto_publish_data = get_post_meta($post_id,"sparkxlab_social_auto_publish", true);
    $social_auto_publish_fb_once = get_post_meta($post_id,"sparkxlab_social_auto_publish_fb_once", true);
    $social_auto_publish_tw_once = get_post_meta($post_id,"sparkxlab_social_auto_publish_tw_once", true);
    $social_auto_publish_fb_once = !empty($social_auto_publish_fb_once) ? $social_auto_publish_fb_once : 0;
    $social_auto_publish_tw_once = !empty($social_auto_publish_tw_once) ? $social_auto_publish_tw_once : 0;

    /* Facebook */
    if (isset($social_auto_publish_data['xyz_smap_post_permission']) && $social_auto_publish_data['xyz_smap_post_permission'] == 1
        && $social_auto_publish_fb_once == 0) {
        $publish_time_gmt = get_publish_time_gmt($social_auto_publish_data['xyz_smap_fb_date'],
            $social_auto_publish_data['xyz_smap_fb_hour'], $social_auto_publish_data['xyz_smap_fb_minute']);
        social_auto_publish_cron($post_id, $publish_time_gmt);
    }

    /* Twitter */
    if (isset($social_auto_publish_data['xyz_smap_twpost_permission']) && $social_auto_publish_data['xyz_smap_twpost_permission'] == 1
        && $social_auto_publish_tw_once == 0) {
        $publish_time_gmt = get_publish_time_gmt($social_auto_publish_data['xyz_smap_tw_date'],
            $social_auto_publish_data['xyz_smap_tw_hour'], $social_auto_publish_data['xyz_smap_tw_minute']);
        social_auto_publish_cron($post_id, $publish_time_gmt, 'twitter');
    }
}

/* Convert to GMT */
function get_publish_time_gmt($date, $hour, $minute) {
    $y = substr($date, 0, 4);
    $m = substr($date, 4, 2);
    $d = substr($date, 6, 2);
    $publish_time = mktime($hour, $minute, 59, $m, $d, $y);
    $publish_time_gmt = strtotime( get_gmt_from_date( date( 'Y-m-d H:i:s', $publish_time ) ) . ' GMT' );

    return $publish_time_gmt;
}

/* Create or update meta post */
function crud_post_meta($new_meta_value, $post_id, $meta_key)
{
    /* Get the meta value of the custom field key. */
    $meta_value = get_post_meta($post_id, $meta_key, true);

    /* If a new meta value was added and there was no previous value, add it. */
    if ($new_meta_value && '' == $meta_value)
        add_post_meta($post_id, $meta_key, $new_meta_value, true);

    /* If the new meta value does not match the old value, update it. */
    elseif ($new_meta_value && $new_meta_value != $meta_value)
        update_post_meta($post_id, $meta_key, $new_meta_value);

    /* If there is no new meta value but an old value exists, delete it. */
    elseif ('' == $new_meta_value && $meta_value)
        delete_post_meta($post_id, $meta_key, $meta_value);
}

function social_auto_publish_cron($post_id, $publish_time, $social_type = 'facebook') {
    /* Remove existing cron event for this post if one exists */
    $hook_name = 'social_auto_publish_hook_'. $social_type;
    wp_clear_scheduled_hook( $hook_name, array( $post_id ) );
    /* Schedule the auto publish */
    wp_schedule_single_event($publish_time, $hook_name, array( $post_id ));
}

add_action( 'social_auto_publish_hook_facebook', 'auto_publish_hook_facebook', 20 );
function auto_publish_hook_facebook($post_ID) {
    $sparkxlab_social_auto_publish = get_post_meta($post_ID, "sparkxlab_social_auto_publish", true);

    $post_permissin = 0;
    if(isset($sparkxlab_social_auto_publish['xyz_smap_post_permission'])) {
        $post_permissin = $sparkxlab_social_auto_publish['xyz_smap_post_permission'];
    }

    if (!$post_permissin) {
        return;
    }

    $get_post_meta = get_post_meta( $post_ID, "xyz_smap", true );
    if( $get_post_meta != 1 ) {
        add_post_meta( $post_ID, "xyz_smap", "1" );
    }

    global $current_user;
    get_currentuserinfo();

    $appsecret = get_option('xyz_smap_application_secret');
    $useracces_token = get_option('xyz_smap_fb_token');

    $message = '';
    if(isset($sparkxlab_social_auto_publish['xyz_smap_message'])) {
        $message = $sparkxlab_social_auto_publish['xyz_smap_message'];
    }

    $posting_method = get_option('xyz_smap_po_method');
    if(isset($sparkxlab_social_auto_publish['xyz_smap_po_method'])) {
        $posting_method = $sparkxlab_social_auto_publish['xyz_smap_po_method'];
    }

    $appid = get_option('xyz_smap_application_id');

    $postpp = get_post($post_ID);
    global $wpdb;
    $entries0 = $wpdb->get_results( 'SELECT user_nicename FROM '.$wpdb->prefix.'users WHERE ID='.$postpp->post_author);
    foreach( $entries0 as $entry ) {
        $user_nicename = $entry->user_nicename;
    }

    if ( $postpp->post_status == 'publish' ) {
        $posttype = $postpp->post_type;
        $fb_publish_status=array();
        if ($posttype=="page") {
            $xyz_smap_include_pages = get_option('xyz_smap_include_pages');
            if( $xyz_smap_include_pages == 0 ) {;
                return;
            }
        }

        if($posttype=="post") {
            $xyz_smap_include_posts=get_option('xyz_smap_include_posts');
            if( $xyz_smap_include_posts == 0 ){
                return;
            }

            $xyz_smap_include_categories=get_option('xyz_smap_include_categories');
            if( $xyz_smap_include_categories != "All" )
            {
                $carr1=explode(',', $xyz_smap_include_categories);

                $defaults = array('fields' => 'ids');
                $carr2= wp_get_post_categories( $post_ID, $defaults );
                $retflag=1;
                foreach ($carr2 as $key=>$catg_ids)
                {
                    if(in_array($catg_ids, $carr1)) {
                        $retflag=0;
                    }
                }

                if($retflag==1) {
                    return;
                }
            }
        }

        include_once ABSPATH.'wp-admin/includes/plugin.php';
        $pluginName = 'bitly/bitly.php';

        if (is_plugin_active($pluginName)) {
            remove_all_filters('post_link');
        }
        $link = get_permalink($postpp->ID);
        $link = replace_permalink($link);

        $xyz_smap_apply_filters=get_option('xyz_smap_std_apply_filters');
        $ar2=explode(",",$xyz_smap_apply_filters);
        $con_flag = $exc_flag = $tit_flag = 0;
        if(isset($ar2[0])) {
            if($ar2[0]==1) {
                $con_flag=1;
            }
        }

        if(isset($ar2[1])) {
            if($ar2[1]==2) {
                $exc_flag=1;
            }
        }

        if(isset($ar2[2])) {
            if($ar2[2]==3) {
                $tit_flag=1;
            }
        }

        $content = $postpp->post_content;
        if($con_flag==1) {
            $content = apply_filters('the_content', $content);
        }
        
        $excerpt = spark_get_post_excerpt($post_ID);
        if($exc_flag==1) {
            $excerpt = apply_filters('the_excerpt', $excerpt);
        }

        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $content);
        $excerpt = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $excerpt);

        if($excerpt=="")
        {
            if($content!="")
            {
                $content1=$content;
                $content1=strip_tags($content1);
                $content1=strip_shortcodes($content1);

                $excerpt=implode(' ', array_slice(explode(' ', $content1), 0, 50));
            }
        }
        else
        {
            $excerpt=strip_tags($excerpt);
            $excerpt=strip_shortcodes($excerpt);
        }
        $description = $content;

        $description_org = $description;
        $attachmenturl=xyz_smap_getimage($post_ID, $postpp->post_content);
        if($attachmenturl != "") {
            $image_found=1;
        } else {
            $image_found = 0;
        }

        $name = $postpp->post_title;
        $caption = html_entity_decode(get_bloginfo('title'), ENT_QUOTES, get_bloginfo('charset'));
        if($tit_flag==1) {
            $name = apply_filters('the_title', $name);
        }

        $name = strip_tags($name);
        $name = strip_shortcodes($name);

        $description = strip_tags($description);
        $description = strip_shortcodes($description);

        $description = str_replace("&nbsp;","",$description);

        $excerpt = str_replace("&nbsp;","",$excerpt);

        if( $useracces_token != "" && $appsecret != "" && $appid != "" && $post_permissin == 1 ) {
            $descriptionfb_li = xyz_smap_string_limit($description, 10000);

            $user_page_id = get_option('xyz_smap_fb_numericid');

            $xyz_smap_pages_ids=get_option('xyz_smap_pages_ids');
            if( $xyz_smap_pages_ids == "" ) {
                $xyz_smap_pages_ids = -1;
            }

            $xyz_smap_pages_ids1 = explode(",", $xyz_smap_pages_ids);

            foreach ($xyz_smap_pages_ids1 as $key => $value)
            {
                if( $value != -1 )
                {
                    $value1 = explode("-", $value);
                    $acces_token = $value1[1];
                    $page_id = $value1[0];
                }
                else
                {
                    $acces_token=$useracces_token;
                    $page_id = $user_page_id;
                }

                $fb = new SMAPFacebook(array(
                    'appId'  => $acces_token,
                    'secret' => $appsecret,
                    'cookie' => true
                ));

                /*$scrape = $fb->api('/','post',array(
                    'access_token' => $acces_token,
                    'id'=>$link,
                    'scrape'=>'true'
                ));*/
                $message1=str_replace('{POST_TITLE}', $name, $message);
                $message2=str_replace('{BLOG_TITLE}', $caption,$message1);
                $message3=str_replace('{PERMALINK}', $link, $message2);
                $message4=str_replace('{POST_EXCERPT}', $excerpt, $message3);
                $message5=str_replace('{POST_CONTENT}', $description, $message4);
                $message5=str_replace('{USER_NICENAME}', $user_nicename, $message5);

                $message5=str_replace("&nbsp;", "", $message5);

                $disp_type="feed";
                if($posting_method==1) //attach
                {
                    $attachment = array('message' => $message5,
                        'access_token' => $acces_token,
                        'link' => $link,
                        'name' => $name,
                        'caption' => $caption,
                        'description' => $descriptionfb_li,
                        'actions' => array(array('name' => $name,
                            'link' => $link)),
                        'picture' => $attachmenturl

                    );
                }
                else if($posting_method==2)  //share link
                {
                    $attachment = array('message' => $message5,
                        'access_token' => $acces_token,
                        'link' => $link,
                        'name' => $name,
                        'caption' => $caption,
                        'description' => $descriptionfb_li,
                        'picture' => $attachmenturl


                    );
                }
                else if($posting_method==3) //simple text message
                {
                    $attachment = array('message' => $message5,
                        'access_token' => $acces_token
                    );

                }
                else if($posting_method==4 || $posting_method==5) //text message with image 4 - app album, 5-timeline
                {
                    if($attachmenturl!="")
                    {

                        if($posting_method==5)
                        {
                            try{
                                $albums = $fb->api("/$page_id/albums", "get", array('access_token'  => $acces_token));
                            }
                            catch(Exception $e)
                            {
                                $fb_publish_status[$page_id."/albums"]=$e->getMessage();
                            }
                            foreach ($albums["data"] as $album) {
                                if ($album["type"] == "wall") {
                                    $timeline_album = $album; break;
                                }
                            }
                            if (isset($timeline_album) && isset($timeline_album["id"])) $page_id = $timeline_album["id"];
                        }


                        $disp_type="photos";
                        $attachment = array('message' => $message5,
                            'access_token' => $acces_token,
                            'url' => $attachmenturl

                        );
                    }
                    else
                    {
                        $attachment = array('message' => $message5,
                            'access_token' => $acces_token

                        );
                    }

                }
                try{
                    $result = $fb->api('/'.$page_id.'/'.$disp_type.'/', 'post', $attachment);
                } catch(Exception $e) {
                    $fb_publish_status[$page_id."/".$disp_type]=$e->getMessage();
                }

            }

            if(count($fb_publish_status)>0) {
                $fb_publish_status_insert=serialize($fb_publish_status);
            }
            else {
                $fb_publish_status_insert=1;
                crud_post_meta(1, $post_ID, 'sparkxlab_social_auto_publish_fb_once');
                $sparkxlab_social_auto_publish['xyz_smap_post_permission'] = 0;
                $meta_key = 'sparkxlab_social_auto_publish';
                crud_post_meta($sparkxlab_social_auto_publish, $post_ID, $meta_key);
            }

            $time = time();
            $post_fb_options = array(
                'postid'	=>	$post_ID,
                'acc_type'	=>	"Facebook"."[ ".$attachmenturl."]",
                'publishtime'	=>	$time,
                'status'	=>	$fb_publish_status_insert
            );

            $smap_fb_update_opt_array = array();

            $smap_fb_arr_retrive = get_option('xyz_smap_fbap_post_logs');

            if (!empty($smap_fb_arr_retrive) && is_array($smap_fb_arr_retrive)) {
                if (count($smap_fb_arr_retrive) > 10 ) {
                    array_shift($smap_fb_arr_retrive);
                }

                $smap_fb_update_opt_array = array_merge($smap_fb_update_opt_array, $smap_fb_arr_retrive);
            }
            array_push($smap_fb_update_opt_array, $post_fb_options);
            update_option('xyz_smap_fbap_post_logs', $smap_fb_update_opt_array);
        }
    }

}
add_action( 'social_auto_publish_hook_twitter', 'auto_publish_hook_twitter', 20 );
function auto_publish_hook_twitter($post_ID) {
    $sparkxlab_social_auto_publish = get_post_meta($post_ID, "sparkxlab_social_auto_publish", true);

    $post_twitter_permission = 0;
    if(isset($sparkxlab_social_auto_publish['xyz_smap_twpost_permission'])) {
        $post_twitter_permission = $sparkxlab_social_auto_publish['xyz_smap_twpost_permission'];
    }

    if (!$post_twitter_permission) {
        return;
    }

    $tappid = get_option('xyz_smap_twconsumer_id');
    $tappsecret = get_option('xyz_smap_twconsumer_secret');
    $twid = get_option('xyz_smap_tw_id');
    $taccess_token = get_option('xyz_smap_current_twappln_token');
    $taccess_token_secret = get_option('xyz_smap_twaccestok_secret');
    $messagetopost = '';
    $messagefromDB = '';
    $messageTitle = '';
    if(isset($sparkxlab_social_auto_publish['xyz_smap_twmessage'])) {
        $messagefromDB = $sparkxlab_social_auto_publish['xyz_smap_twmessage'];
    }

    if(!empty($sparkxlab_social_auto_publish['xyz_smap_twmessage_title'])) {
        $messageTitle = $sparkxlab_social_auto_publish['xyz_smap_twmessage_title'];
        $messagetopost = $messageTitle."\n".'{PERMALINK}'; // FIXME
    }
    else{
        $messagetopost = '{POST_TITLE}{PERMALINK}'; // FIXME
    }

    $post_twitter_image_permission = 1;
    /*if(isset($sparkxlab_social_auto_publish['xyz_smap_twpost_image_permission'])) {
        $post_twitter_image_permission = $sparkxlab_social_auto_publish['xyz_smap_twpost_image_permission'];
    }*/

    $postpp = get_post($post_ID);
    global $wpdb;
    $entries0 = $wpdb->get_results( 'SELECT user_nicename FROM '.$wpdb->prefix.'users WHERE ID='.$postpp->post_author);
    foreach( $entries0 as $entry ) {
        $user_nicename = $entry->user_nicename;
    }

    if ( $postpp->post_status == 'publish' ) {
        $posttype = $postpp->post_type;
        if ($posttype=="page") {
            $xyz_smap_include_pages = get_option('xyz_smap_include_pages');
            if( $xyz_smap_include_pages == 0 ) {;
                return;
            }
        }

        if($posttype=="post") {
            $xyz_smap_include_posts=get_option('xyz_smap_include_posts');
            if( $xyz_smap_include_posts == 0 ){
                return;
            }

            $xyz_smap_include_categories=get_option('xyz_smap_include_categories');
            if( $xyz_smap_include_categories != "All" )
            {
                $carr1=explode(',', $xyz_smap_include_categories);

                $defaults = array('fields' => 'ids');
                $carr2= wp_get_post_categories( $post_ID, $defaults );
                $retflag=1;
                foreach ($carr2 as $key=>$catg_ids)
                {
                    if(in_array($catg_ids, $carr1)) {
                        $retflag=0;
                    }
                }

                if($retflag==1) {
                    return;
                }
            }
        }

        include_once ABSPATH.'wp-admin/includes/plugin.php';
        $pluginName = 'bitly/bitly.php';

        if (is_plugin_active($pluginName)) {
            remove_all_filters('post_link');
        }
        $link = get_permalink($postpp->ID);
        $link = replace_permalink($link);



        $xyz_smap_apply_filters=get_option('xyz_smap_std_apply_filters');
        $ar2=explode(",",$xyz_smap_apply_filters);
        $con_flag = $exc_flag = $tit_flag = 0;
        if(isset($ar2[0])) {
            if($ar2[0]==1) {
                $con_flag=1;
            }
        }

        if(isset($ar2[1])) {
            if($ar2[1]==2) {
                $exc_flag=1;
            }
        }

        if(isset($ar2[2])) {
            if($ar2[2]==3) {
                $tit_flag=1;
            }
        }

        $content = $postpp->post_content;
        if($con_flag==1) {
            $content = apply_filters('the_content', $content);
        }

        $excerpt = spark_get_post_excerpt($post_ID);
        if($exc_flag==1) {
            $excerpt = apply_filters('the_excerpt', $excerpt);
        }

        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $content);
        $excerpt = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $excerpt);

        if($excerpt=="")
        {
            if($content!="")
            {
                $content1=$content;
                $content1=strip_tags($content1);
                $content1=strip_shortcodes($content1);

                $excerpt=implode(' ', array_slice(explode(' ', $content1), 0, 50));
            }
        }
        else
        {
            $excerpt=strip_tags($excerpt);
            $excerpt=strip_shortcodes($excerpt);
        }
        $description = $content;

        $description_org = $description;
        $attachmenturl=xyz_smap_getimage($post_ID, $postpp->post_content);
        if($attachmenturl != "") {
            $image_found=1;
        } else {
            $image_found = 0;
        }

        $name = $postpp->post_title;
        $caption = html_entity_decode(get_bloginfo('title'), ENT_QUOTES, get_bloginfo('charset'));
        if($tit_flag==1) {
            $name = apply_filters('the_title', $name);
        }

        $name = strip_tags($name);
        $name = strip_shortcodes($name);

        $description = strip_tags($description);
        $description = strip_shortcodes($description);

        $description = str_replace("&nbsp;","",$description);

        $excerpt = str_replace("&nbsp;","",$excerpt);

        if ($taccess_token != "" && $taccess_token_secret != "" && $tappid != "" && $tappsecret != "" && $post_twitter_permission == 1) {
            /**** image up start ****/
            $img_status = "";
            if ($post_twitter_image_permission == 1) {
                $img = array();
                if ($attachmenturl != "") {
                    $img = wp_remote_get($attachmenturl);

                }

                if (is_array($img)) {
                    if (isset($img['body']) && trim($img['body']) != '') {
                        $image_found = 1;
                        if (($img['headers']['content-length']) && trim($img['headers']['content-length']) != '') {
                            $img_size = $img['headers']['content-length'] / (1024 * 1024);
                            if ($img_size > 3) {
                                $image_found = 0;
                                $img_status = "Image skipped(greater than 3MB)";
                            }
                        }

                        $img = $img['body'];
                    } else {
                        $image_found = 0;
                    }
                }

            }
            /**** Twitter upload image end *****/

            $messagetopost = str_replace("&nbsp;", "", $messagetopost);

            preg_match_all("/{(.+?)}/i", $messagetopost, $matches);
            $matches1 = $matches[1];
            $substring = "";
            $islink = 0;
            $issubstr = 0;
            $len = 118;
            if ($image_found == 1) {
                $len = $len - 24;
            }

            foreach ($matches1 as $key => $val) {
                $val = "{" . $val . "}";
                if ($val == "{POST_TITLE}") {
                    $replace = $name;
                }
                if ($val == "{POST_CONTENT}") {
                    $replace = $description;
                }
                if ($val == "{PERMALINK}") {
                    $replace = "{PERMALINK}";
                    $islink = 1;
                }
                if ($val == "{POST_EXCERPT}") {
                    $replace = $excerpt;
                }
                if ($val == "{BLOG_TITLE}") {
                    $replace = $caption;
                }

                if ($val == "{USER_NICENAME}") {
                    $replace = $user_nicename;
                }

                $append = mb_substr($messagetopost, 0, mb_strpos($messagetopost, $val));

                if (mb_strlen($append) < ($len - mb_strlen($substring))) {
                    $substring .= $append;
                } else if ($issubstr == 0) {
                    $avl = $len - mb_strlen($substring) - 4;
                    if ($avl > 0) {
                        $substring .= mb_substr($append, 0, $avl) . "...";
                    }

                    $issubstr = 1;
                }

                if ($replace == "{PERMALINK}") {
                    $chkstr = mb_substr($substring, 0, -1);
                    if ($chkstr != " ") {
                        $substring .= " " . $replace;
                        $len = $len + 12;
                    } else {
                        $substring .= $replace;
                        $len = $len + 11;
                    }
                } else {
                    if (mb_strlen($replace) < ($len - mb_strlen($substring))) {
                        $substring .= $replace;
                    } else if ($issubstr == 0) {
                        $avl = $len - mb_strlen($substring) - 4;
                        if ($avl > 0) {
                            $substring .= mb_substr($replace, 0, $avl) . "...";
                        }
                        $issubstr = 1;
                    }
                }
                $messagetopost = mb_substr($messagetopost, mb_strpos($messagetopost, $val) + strlen($val));
            }

            if ($islink == 1) {
                $substring = str_replace('{PERMALINK}', $link, $substring);
            }
            $substring = $substring."\n".$messagefromDB;
            $twobj = new SMAPTwitterOAuth(array('consumer_key' => $tappid, 'consumer_secret' => $tappsecret, 'user_token' => $taccess_token, 'user_secret' => $taccess_token_secret, 'curl_ssl_verifypeer' => get_option('xyz_smap_peer_verification') ? true : false));

            $tw_publish_status = array();
            if ($image_found == 1 && $post_twitter_image_permission == 1) {
                $resultfrtw = $twobj->request('POST', 'https://api.twitter.com/1.1/statuses/update_with_media.json', array('media[]' => $img, 'status' => $substring), true, true);

                if ($resultfrtw != 200) {
                    if ($twobj->response['response'] != "") {
                        $tw_publish_status["statuses/update_with_media"] = print_r($twobj->response['response'], true);
                    }
                    else {
                        $tw_publish_status["statuses/update_with_media"] = $resultfrtw;
                    }
                }

            } else {
                $resultfrtw = $twobj->request('POST', $twobj->url('1.1/statuses/update.json'), array('status' => $substring));

                if ($resultfrtw != 200) {
                    if ($twobj->response['response'] != "") {
                        $tw_publish_status["statuses/update"] = print_r($twobj->response['response'], true);
                    }
                    else {
                        $tw_publish_status["statuses/update"] = $resultfrtw;
                    }
                } else if ($img_status != "") {
                    $tw_publish_status["statuses/update_with_media"] = $img_status;
                }
            }

            if (count($tw_publish_status) > 0) {
                $tw_publish_status_insert = serialize($tw_publish_status);
            } else {
                $tw_publish_status_insert = 1;
                crud_post_meta(1, $post_ID, 'sparkxlab_social_auto_publish_tw_once');
                $social_auto_publish_data = get_post_meta($post_ID,"sparkxlab_social_auto_publish", true);
                $sparkxlab_social_auto_publish['xyz_smap_twpost_permission'] = 0;
                $meta_key = 'sparkxlab_social_auto_publish';
                crud_post_meta($sparkxlab_social_auto_publish, $post_ID, $meta_key);
            }

            $time = time();
            $post_tw_options = array(
                'postid' => $post_ID,
                'acc_type' => "Twitter"."[ ".$attachmenturl."]",
                'publishtime' => $time,
                'status' => $tw_publish_status_insert
            );

            $smap_tw_update_opt_array = array();

            $smap_tw_arr_retrive = get_option('xyz_smap_twap_post_logs');

            if (!empty($smap_tw_arr_retrive) && is_array($smap_tw_arr_retrive)) {
                if (count($smap_tw_arr_retrive) > 10 ) {
                    array_shift($smap_tw_arr_retrive);
                }
                $smap_tw_update_opt_array = array_merge($smap_tw_update_opt_array, $smap_tw_arr_retrive);
            }
            array_push($smap_tw_update_opt_array, $post_tw_options);
            update_option('xyz_smap_twap_post_logs', $smap_tw_update_opt_array);
        }
    }
}

function replace_permalink($string){
    $pattern = '/^http(s?):\/\/post\.(.*?)/';
    $replacement = 'http$1://';
    $string = preg_replace($pattern, $replacement, $string);
    return $string;
}

function spark_clear_FB_scrape($post_ID){
    $postpp = get_post($post_ID);
    if ( $postpp->post_status == 'publish' ) {
        $sparkxlab_social_auto_publish = get_post_meta($post_ID, "sparkxlab_social_auto_publish", true);

        $post_permissin = 0;
        if(isset($sparkxlab_social_auto_publish['xyz_smap_post_permission'])) {
            $post_permissin = $sparkxlab_social_auto_publish['xyz_smap_post_permission'];
        }

        if (!$post_permissin) {
            return;
        }
        $appsecret = get_option('xyz_smap_application_secret');
        $useracces_token = get_option('xyz_smap_fb_token');
        $appid = get_option('xyz_smap_application_id');
        $posttype = $postpp->post_type;
        if ($posttype == "page") {
            $xyz_smap_include_pages = get_option('xyz_smap_include_pages');
            if ($xyz_smap_include_pages == 0) {
                ;
                return;
            }
        }

        $link = get_permalink($postpp->ID);
        $link = replace_permalink($link);

        if ($useracces_token != "" && $appsecret != "" && $appid != "" ) {

            $user_page_id = get_option('xyz_smap_fb_numericid');

            $xyz_smap_pages_ids = get_option('xyz_smap_pages_ids');
            if ($xyz_smap_pages_ids == "") {
                $xyz_smap_pages_ids = -1;
            }

            $xyz_smap_pages_ids1 = explode(",", $xyz_smap_pages_ids);

            foreach ($xyz_smap_pages_ids1 as $key => $value) {
                if ($value != -1) {
                    $value1 = explode("-", $value);
                    $acces_token = $value1[1];
                    $page_id = $value1[0];
                } else {
                    $acces_token = $useracces_token;
                    $page_id = $user_page_id;
                }

                $fb = new SMAPFacebook(array(
                    'appId' => $acces_token,
                    'secret' => $appsecret,
                    'cookie' => true
                ));

                $scrape = $fb->api('/', 'post', array(
                    'access_token' => $acces_token,
                    'id' => $link,
                    'scrape' => 'true'
                ));
            }
        }
    }
}

add_action('publish_post', 'clear_FB_scrape_schedule_post');
function clear_FB_scrape_schedule_post($ID){
    spark_clear_FB_scrape($ID);
}
