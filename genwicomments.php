<?php

/**
 * recent.php
 *
 * @author      Mick Thompson <mick@genwi.com>
 * @copyright   (c) 2008 Genwi. All rights reserved.
 * @license     http://www.opensource.org/licenses/bsd-license.php
 * @package     WordPress
 * @subpackage  Plugins 
 */

// {{{ WordPress Description of plugin
/*
Plugin Name: GenwiComments
Plugin URI: http://www.genwi.com/commentplugins/wordpress
Description: This plugin is used to fetch comments that have been made on blog posts from your wordpress blog on the site Genwi. <b>Requires:</b> PHP5 and the following <a href="http://pear.php.net/">PEAR</a> packages: <a href="http://pear.php.net/package/DB">DB</a>, <a href="http://pear.php.net/package/HTTP_Request">HTTP_Request</a></b>
Author: Mick Thompson
Version: 1.0
Author URI: http://www.genwi.com/
*/
// }}}

//require_once 'PEAR.php';

//require_once 'HTTP/Request.php';

define('GENWI_COMMENT_URI', 'http://www.genwi.com/json/jcomments.aspx?action=blogcomments&feedurl=');
define('GENWI_ITEMINFO_URI', 'http://www.genwi.com/json/jcomments.aspx?action=iteminfo&itemid=');
define('GENWI_BASE_URI', 'http://www.genwi.com/');
define('GENWI_PATH', ABSPATH . 'wp-content/plugins/genwicomments');
/**
 * recent_do_update
 *
 * @author      Mick Thompson <mick@genwi.com> 
 * @return boolean
 */
function recent_do_update()
{
    global $user_level;
    get_currentuserinfo();
    //if ($user_level < 8) {
      //  return false;
    //}

    static $lastUpdate = null;
    static $doUpdate = null;

    if (is_null($lastUpdate)) {
        $lastUpdate = get_option('genwi_last_update');
    }

    if (is_null($doUpdate)) {
        $doUpdate   = (time() - (10)); // Update every 10mins
    }

    // Allow people to force an update
    $force = (isset($_GET['forceUpdate']) && $_GET['forceUpdate'] == 1);
    if ($lastUpdate == false || $lastUpdate <= $doUpdate || $force) {
        return true;
    }
    
    return false;
}


/**
 * genwi_comments_get_comments
 *
 * @author      Mick Thompson <mick@genwi.com>
 * @return      void
 */
function genwi_comments_get_comments()
{
    global $table_prefix, $user_level, $wpdb;

    get_currentuserinfo();
    //if ($user_level < 8) {
    //    return;
    //}

    if (recent_do_update()) {
        update_option('genwi_last_update',time());

        $isgenwienabled = get_option('genwi_comments_enabled');
        
        if($isgenwienabled != "true")
            return;

        $rss_url = get_bloginfo('rss2_url');
        $url  =  GENWI_COMMENT_URI . urlencode($rss_url);
        //var_dump($url);
        //var_dump($rss_url);
        //exit;
        
      $contents = wp_remote_fopen($url);
//      var_dump($contents);
      

        $comments = json_decode($contents);
        
        //var_dump($comments);
        
        
          
          foreach($comments->Comments as $com){
            
          //  echo($com->CommentId."  --  ");
            
            
          /*
                      genwi_commentid bigint(20) not null,
                      wpcommentid bigint(20) not null,
                      genwi_id varchar(255) not null,
                      genwi_screenname varchar(512) not null,
                      genwi_userprofileimgsrc varchar(512) not null,
                      genwi_totalvotes int not null Default 0,
                      genwi_itemid bigint(20) not null,
                      genwi_hasreplies int not null DEFAULT 0,
          */
          
          $hasrepliesint = 0;
          if($com->HasReplies == "true")
              $hasrepliesint = 1;
          $wpdb->query($wpdb->prepare( "INSERT INTO {$table_prefix}genwi_comments (genwi_commentid, genwi_id, genwi_screenname, genwi_userprofileimgsrc, genwi_totalvotes, genwi_itemid, genwi_hasreplies)
                                 VALUES( %d, %s, %s, %s, %d, %d, %d)
                                 ON DUPLICATE KEY UPDATE genwi_totalvotes = VALUES(genwi_totalvotes), genwi_hasreplies = VALUES(genwi_hasreplies)
                                       ", $com->CommentId, $com->GenwiUserId, $com->Screenname, $com->UserProfileImgSrc, $com->TotalVotes, $com->ItemId, $hasrepliesint) );
            
          }

//do a select for all genwi_comments that are not linked to a wp comment.
$wpdb->flush();
$unfiledcomments = $wpdb->get_results("SELECT genwi_commentid, genwi_itemid FROM {$table_prefix}genwi_comments WHERE wpcommentid = 0");

//var_dump($unfiledcomments);
//echo("unfiled count".count($unfiledcomments));
if(count($unfiledcomments) < 1)
    return;

$needed_item_lookup = array();

foreach ($unfiledcomments as $acomt) {
        if(array_search($acomt->genwi_itemid, $needed_item_lookup)=== false){
            array_push($needed_item_lookup, $acomt->genwi_itemid);
        }
}
//var_dump($unfiledcomments);
$iteminfoarray = array();

//For each of those, select ask genwi based on the itemid what item it belongs to.  compare item_guid to link.
foreach($needed_item_lookup as $needitem){
  //  echo($needitem." -- ");
    $itemcontents = wp_remote_fopen(GENWI_ITEMINFO_URI.$needitem);
        $inforesponse = json_decode($itemcontents);
        if($inforesponse->status == "OK"){        
          $iteminfoarray[] = $inforesponse->comment;
        }    
}
//var_dump($iteminfoarray);
//TODO: comment replies.
foreach ($unfiledcomments as $acomt) {
    
    foreach($iteminfoarray as $iteminfo){
        if($iteminfo->ItemId == ((int)$acomt->genwi_itemid)){
            $acomt->GUID = $iteminfo->GUID;    
            break;
        }
    }
  //  echo("GUID = ".$acomt->GUID);
    if($acomt->GUID != ""){
        
        $postid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid = %s", $acomt->GUID));
    //    echo("PostID = $postid");
        foreach($comments->Comments as $com){
            if($com->CommentId == ((int)$acomt->genwi_commentid)){
                $acomt->commment_screenname = $com->Screenname;
                $acomt->commment_genwiuserid = $com->GenwiUserId;
                $acomt->commment_text = $com->Text;
                break;
            }
        }
      //  echo('commentdata');
        //var_dump($acomt->commment_screenname);
        if(isset($acomt->commment_screenname)){
            
            $commentdata["comment_post_ID"] = $postid;
            $commentdata["comment_author"] =  $acomt->commment_screenname;//pop with Genwi screen name 
            $commentdata["comment_author_email"] = "";
            $commentdata["comment_author_url"] = "http://www.genwi.com/people/".$acomt->commment_genwiuserid; //pop with genwi user profile page
            $commentdata["comment_content"] = $acomt->commment_text;
            $commentid = new_genwi_comment($commentdata);
            if(isset($commentid))
                $wpdb->query( $wpdb->prepare( "UPDATE {$table_prefix}genwi_comments SET wpcommentid = %d WHERE genwi_commentid = %d", $commentid, $acomt->genwi_commentid));
        }
    }
}

    //$comment_post_ID, $comment_author, $comment_author_email, $comment_author_url, $comment_author_IP,
    //$comment_date, $comment_date_gmt, $comment_content, $comment_approved, $comment_agent, $comment_type,
    //$comment_parent, $user_id
    
    //for each, then add a comment to the wp_comment table. and update the genwi_comment table.

        exit;
          
          
          /*  $req =& new HTTP_Request($url);
            $response = $req->sendRequest();
            if (PEAR::isError($response)) {
                return $response;
            }

            $body = $req->getResponseBody();
            $xml = simplexml_load_string($body);
            if (!$xml instanceof SimpleXMLElement) {
                $error = PEAR::raiseError('Could not parse XML: '.$url);
                return $error;
            }

            foreach ($xml->channel->item as $item) {
                $sql[] = "REPLACE INTO {$table_prefix}recent_diggs
                              SET id=?,
                              url=?,
                              title=?,
                              posted=FROM_UNIXTIME(?)";
    
                $arr[] = array(md5($item->link), 
                               (string)$item->link,
                               (string)$item->title,
                               strtotime((string)$item->pubDate));
    
            }
    
            $db->autoCommit(false);
            foreach ($sql as $key => $query) {
                $result = $db->query($query, $arr[$key]);
                if (PEAR::isError($result)) {
                    $db->rollback();
                    break;
                } 
            }
            $db->commit();
            $db->autoCommit(true);*/
        
    }
}




/**
 * Parses and adds a new comment to the database.
 *
 * {@internal Missing Long Description}}
 *
 * @since 1.5.0
 * @uses apply_filters() Calls 'preprocess_comment' hook on $commentdata parameter array before processing
 * @uses do_action() Calls 'comment_post' hook on $comment_ID returned from adding the comment and if the comment was approved.
 * @uses wp_filter_comment() Used to filter comment before adding comment
 * @uses wp_allow_comment() checks to see if comment is approved.
 * @uses wp_insert_comment() Does the actual comment insertion to the database
 *
 * @param array $commentdata Contains information on the comment
 * @return int The ID of the comment after adding.
 */
function new_genwi_comment( $commentdata ) {
	$commentdata = apply_filters('preprocess_comment', $commentdata);

	$commentdata['comment_post_ID'] = (int) $commentdata['comment_post_ID'];
	$commentdata['user_ID']         = (int) 0;

	$commentdata['comment_author_IP'] = "0.0.0.0";
	$commentdata['comment_agent']     = "Genwi";
        
	$commentdata['comment_date']     = current_time('mysql');
	$commentdata['comment_date_gmt'] = current_time('mysql', 1);

	$commentdata = wp_filter_comment($commentdata);

	//$commentdata['comment_approved'] = wp_allow_comment($commentdata);

	$comment_ID = wp_insert_comment($commentdata);

	do_action('comment_post', $comment_ID, $commentdata['comment_approved']);

	if ( 'spam' !== $commentdata['comment_approved'] ) { // If it's spam save it silently for later crunching
		if ( '0' == $commentdata['comment_approved'] )
			wp_notify_moderator($comment_ID);

		$post = &get_post($commentdata['comment_post_ID']); // Don't notify if it's your own comment
                //TODO: Add a Genwi comment notify option?
		if ( get_option('comments_notify') && $commentdata['comment_approved'] && $post->post_author != $commentdata['user_ID'] )
			wp_notify_postauthor($comment_ID, $commentdata['comment_type']);
	}
        
	return $comment_ID;
}


/**
 * genwi_comments_options_subpanel
 *
 * @author      Mick Thompson <mick@genwi.com> 
 * @return void
 */
function genwi_comments_options_subpanel()
{

    if (count($_POST)) {
        foreach ($_POST as $var => $val) {
            if ($var == 'enable_genwi_comments') {
                update_option('genwi_comments_enabled',"true");
            }else if($var == 'disable_genwi_comments'){
                update_option('genwi_comments_enabled',"false");
            }
        }
    }
    
    include_once(GENWI_PATH.'/forms/admin_options.php');
}

function genwi_author_add_image($param)
{
    global $comment, $table_prefix, $wpdb;
    $genwi_comment = $wpdb->get_row($wpdb->prepare("SELECT genwi_commentid, genwi_id, genwi_screenname, genwi_userprofileimgsrc, genwi_totalvotes, genwi_itemid, genwi_hasreplies FROM {$table_prefix}genwi_comments WHERE wpcommentid = %s", $comment->comment_ID));
    if(isset($genwi_comment)){
        return $param."&nbsp;<a href='".GENWI_BASE_URI."read/".$genwi_comment->genwi_itemid."'><img src='".get_bloginfo('url')."/wp-content/plugins/genwicomments/genwi_badge.png' /></a>";
    }else{
        return $param;
    }
    
}

function genwi_comment_add_replies($comment_text){
    global $comment, $table_prefix, $wpdb;
    $genwi_comment = $wpdb->get_row($wpdb->prepare("SELECT genwi_commentid, genwi_id, genwi_screenname, genwi_userprofileimgsrc, genwi_totalvotes, genwi_itemid, genwi_hasreplies FROM {$table_prefix}genwi_comments WHERE wpcommentid = %s", $comment->comment_ID));
    if((isset($genwi_comment))&&($genwi_comment->genwi_hasreplies > 0)){
           return $comment_text."<br /><br /><span style='font-size:1.2em;'><a href='".GENWI_BASE_URI."read/".$genwi_comment->genwi_itemid."/comments'>View Replies to this comment on Genwi -&gt;</a></span>";
    }else{
        return $comment_text;
    }
}

/** 
 * recent_options_menu 
 * 
 * @author      Mick Thompson <mick@genwi.com> 
 * @return      void 
 */
function genwi_options_menu()
{    
    if (function_exists('add_options_page')) {
        add_options_page('Genwi Comments','Genwi Comments',10,basename(__FILE__),'genwi_comments_options_subpanel');
    }
}

/**
 * recent_install
 *
 * This installes the genwi_comments table, They hold additional Genwi specific comment data.
 *
 * @author      Mick Thompson <mick@genwi.com>
 * @return      void
 */
function genwicomment_install() {
    global $table_prefix, $wpdb, $user_level;

    get_currentuserinfo();
    if ($user_level < 8) {
        return;
    }
    
    $table = $table_prefix . 'genwi_comments';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        $sql = array();
        $sql[] = "CREATE TABLE $table (
                      genwi_commentid bigint(20) not null,
                      wpcommentid bigint(20) not null,
                      genwi_id varchar(255) not null,
                      genwi_screenname varchar(512) not null,
                      genwi_userprofileimgsrc varchar(512) not null,
                      genwi_totalvotes int not null Default 0,
                      genwi_itemid bigint(20) not null,
                      genwi_hasreplies int not null DEFAULT 0,
                      PRIMARY KEY (genwi_commentid)
                  ) ENGINE=InnoDB CHARSET=utf8";

        require_once ABSPATH . 'wp-admin/upgrade-functions.php';
        dbDelta($sql);
    }
}

if (isset($_GET['activate']) && $_GET['activate'] == 'true') {
   add_action('init', 'genwicomment_install');
}

add_action('admin_menu', 'genwi_options_menu');
add_action('init', 'genwi_comments_get_comments');
add_filter('get_comment_author_link',  'genwi_author_add_image');
add_filter('get_comment_text', 'genwi_comment_add_replies');
//add_filter('the_posts', 'recent_the_posts');

?>
