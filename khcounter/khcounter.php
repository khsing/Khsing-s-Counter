<?php
/*
Plugin Name: khsing's Hit Counter
Plugin URI: http://p.khsing.net/khcounter
Description: Prints a count of the number of hits your blog has received, tracked in the memcached and database.
Version: 0.9
Author: Guixing Bai
Author URI: http://blog.khsing.net
*/
/*
Copyright (c) Guixing Bai <khsing.cn@gmail.com>

All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted (subject to the limitations in the
disclaimer below) provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.

 * Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the
   distribution.

 * Neither the name of <Owner Organization> nor the names of its
   contributors may be used to endorse or promote products derived
   from this software without specific prior written permission.

NO EXPRESS OR IMPLIED LICENSES TO ANY PARTY'S PATENT RIGHTS ARE
GRANTED BY THIS LICENSE.  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT
HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED
WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN
IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

define(KHC_NS,'khc_');
define(KHC_NS_DAY_VISITED, KHC_NS.'visited_');
define(KHC_NS_DAY_POSTVIEW, KHC_NS.'pv_');
define(KHC_NS_TOTAL_POSTVIEW, KHC_NS.'tpv_');
define(KHC_NS_SITE_PV,KHC_NS.'site');
define(KHC_NS_ALL_POSTID, KHC_NS . 'all_postid');
define(KHC_TTL, 2592000);
define(KHC_TTL_POSTID, 300);
define(KHC_TTL_OUTPUT, 180);

global $wpdb, $mc, $today, $todaystr, $khcounter_table_name;

$khcounter_table_name = $wpdb->prefix . 'khcounter';
$mc = new Memcache;
$mc->addServer(get_option('khc_mc_host'), get_option('khc_mc_port'));

$today = new DateTime();
$todaystr = $today->format('Y-m-d');

add_action('admin_init', 'khc_init');
add_action('admin_menu', 'khc_menu');
add_action('wp','khc_count');
add_action('widgets_init', 'KhcWidgetInit' );

register_activation_hook(__FILE__,'khcounter_install');
register_deactivation_hook(__FILE__,'khcounter_uninstall');

function khcounter_install()
{
      add_option('khc_data',0);
      add_option('khc_display_num',10);
      add_option('khc_mc_host','127.0.0.1');
      add_option('khc_mc_port','11211');
      add_option('khc_display_pv',0);
      add_option('khc_showdays_num',7);
      add_option('khc_before_ul','');
      add_option('khc_after_ul','');
}
function khcounter_uninstall()
{
      delete_option('khc_data');
      delete_option('khc_display_num');
      delete_option('khc_mc_host');
      delete_option('khc_mc_port');
      delete_option('khc_display_pv');
      delete_option('khc_showdays_num');
      delete_option('khc_before_ul');
      delete_option('khc_after_ul');
}

function khc_init()
{
      register_setting('wordpress-khcounter-group','khc_display_num','intval');
      register_setting('wordpress-khcounter-group','khc_display_pv','intval');
      register_setting('wordpress-khcounter-group','khc_mc_host');
      register_setting('wordpress-khcounter-group','khc_mc_port','intval');
      register_setting('wordpress-khcounter-group','khc_showdays_num','intval');
      register_setting('wordpress-khcounter-group','khc_before_ul','khc_strip_tag');
      register_setting('wordpress-khcounter-group','khc_after_ul','khc_strip_tag');
}

function khc_get_cache($key){
    global $mc;
    $re = $mc->get($key);
    /* Debug 
    if (substr($key,0,6) == "khc_pv"){
        error_log('get::'.$key . '::'.print_r($re,True));
    }
    */
    return $re;
}

function khc_set_cache($key,$value,$ttl=KHC_TTL){
    global $mc;
    $re = $mc->set($key,$value,FALSE,$ttl);
    /* debug
    if (substr($key,0,6) == "khc_pv"){
        error_log('set::'.$key.print_r($re,True)."::".$value);
    }
    */
    return $re;
}


function khc_strip_tag($str){
    return strip_tags($str,'<h2><h3><h4><h5><h6><h7><p><div><a><span><ul><li><img><ol>');
}


function khc_menu()
{
      add_options_page('khsing\'s Counter Options', 'Khsing Counter', 'administrator', 'khsing-counter', 'khc_options');
}

function khc_options()
{
      ?>
     <form method="post" action="options.php">
      <?php settings_fields( 'wordpress-khcounter-group' ); ?>
      <ul>
          <li><span>Display num:</span><input type="text" name="khc_display_num" value="<?php echo get_option('khc_display_num'); ?> " /></li>
          <li><span>Display pv:</span><input type="checkbox" name="khc_display_pv" value="1" <?php echo checked("1",get_option('khc_display_pv')); ?> " /></li>
          <li><span>Display Days:</span><input type="text" name="khc_showdays_num" value="<?php echo get_option('khc_showdays_num'); ?> " /></li>
          <li><span>Memcached Host:</span><input type="text" name="khc_mc_host" value="<?php echo get_option('khc_mc_host'); ?> " /></li>
          <li><span>Memcached Port:</span><input type="text" name="khc_mc_port" value="<?php echo get_option('khc_mc_port'); ?> " /></li>
          <li><span>Before UL:</span><textarea rows="3" cols="20" name="khc_before_ul"><?php echo get_option('khc_before_ul'); ?></textarea></li>
          <li><span>After UL:</span><textarea rows="3" cols="20" name="khc_after_ul"><?php echo get_option('khc_after_ul'); ?></textarea></li>
          <li><input type="submit" value="Save" /></li>
      </ul>
      </form>
      <?php
}

function khc_count(){
      global $post;
      if (is_front_page()){
          $this_post_id = 0;
      } else {
            $this_post_id = $post->ID;
      }
      khc_count_cache($this_post_id);
}

function khc_count_cache($pid){
    global $mc,$today,$todaystr;
    $day_key = KHC_NS_DAY_POSTVIEW . $todaystr . '_' . $pid;
    $total_key = KHC_NS_TOTAL_POSTVIEW . $pid;
    if ($pid !== FALSE){
        khc_counter($day_key,$pid);
        khc_counter($total_key,$pid);
    }
}

function khc_counter($key,$pid) {
    global $mc;
    $val = $mc->increment($key,1);
    //error_log('count::'.$key . '::' . print_r($val,TRUE));
    if ($val === FALSE){
        $val = khc_set_cache($key, 1, KHC_TTL);
        if ($val == FALSE){
            error_log("Err: Add postid failed:".$key);
            return FALSE;
        }
    }
    return TRUE;
}

function khc_compute_pop($postids=NULL){
    global $today,$todaystr,$mc;
    if ($postids === NULL){
        $postids = khc_get_cache(KHC_NS_ALL_POSTID);
    }
    $array_postid = array();
    for ($i = 0;$i <= get_option("khc_showdays_num"); $i++){
	if ($i === 0){
		$day = $today;
	} else {
        	$day = get_offset_day($today,1);
	}
        $day_str = $day->format('Y-m-d');
        if ($postids){
            foreach ($postids as $j){
                $k = KHC_NS_DAY_POSTVIEW . $day_str . '_' . $j;
                $dpv = khc_get_cache($k);
                if ($dpv){
                    if (array_key_exists($j, $array_postid)){
                        $array_postid[$j] += $dpv;
                    } else {
                        $array_postid[$j] = $dpv;
                    }
                }
            }
        }
    }
    arsort($array_postid,SORT_NUMERIC);
    return array_slice($array_postid,0,get_option('khc_display_num'),TRUE);
}

function get_offset_day($t,$offset=0){
    if($offset != 0){
        return $t->sub(new DateInterval('P' . $offset . 'D'));
    } else {
        return $t;
    }
}

function khc_get_all_posts(){
    global $post;
    $pids = khc_get_cache(KHC_NS_ALL_POSTID);
    //$pids = FALSE;
    if ($pids === FALSE){
        $args = array('post_status'=>'publish','post_type' => 'post','numberposts' => -1);
        $all_posts = get_posts( $args );
        $pids = array();
        foreach ($all_posts as $p){
            $pids[] = $p->ID;
        }
        khc_set_cache(KHC_NS_ALL_POSTID, $pids, KHC_TTL_POSTID);
    }
    return $pids;
}

function khc_widget_display(){
    $key = KHC_NS . 'pop_lastest';
    $htmlstr = khc_get_cache($key);
    //$htmlstr = FALSE;
    if ($htmlstr === FALSE){
        $pop = khc_compute_pop(khc_get_all_posts());
        $displayPv = (get_option('khc_display_pv')==1) ? true : false ;
        $htmlstr = "<ul>";
        foreach ($pop as $pid => $pv){
            if ($pid != 0){
                $p = get_post($pid);
                $htmlstr .= "<li><a href=\"" . get_permalink($pid) . "\">" . $p->post_title . "</a>";
                if ($displayPv){
                    $htmlstr .=  "(". $pv . ")";
                }
                $htmlstr .= "</li>";
            }
        }
        $htmlstr .= "</ul>";
        khc_set_cache($key,$htmlstr,KHC_TTL_OUTPUT);
    }
    return $htmlstr;
}

class KhcWidget extends WP_Widget {
    function KhcWidget() {
        parent::WP_Widget( false, $name = 'Khcounter Widget' );
    }

    function widget( $args, $instance ) {
        extract( $args );
        $title = apply_filters( 'widget_title', $instance['title'] );
        echo $before_widget;
        if ($title) {
            echo $before_title . $title . $after_title;
        }
        echo get_option('khc_before_ul');
        echo khc_widget_display();
        echo get_option('khc_after_ul');
        echo $after_widget;
    }

    function update( $new_instance, $old_instance ) {
        return $new_instance;
    }

    function form( $instance ) {
        $title = esc_attr( $instance['title'] );
    }
}

function KhcWidgetInit() {
    register_widget( 'KhcWidget' );
}
?>
