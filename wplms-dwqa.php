<?php
/**
 * Plugin Name: WPLMS DW Q&A Add-On
 * Plugin URI: http://www.vibethemes.com/
 * Description: Integrates DW Q&A with WPLMS
 * Author: VibeThemes
 * Version: 1.2
 * Author URI: https://vibethemes.com/
 * License: GNU AGPLv3
 * License URI: http://www.gnu.org/licenses/agpl-3.0.html
 */


/* ===== INTEGRATION with DW Q&A PLUGIN =========
 * 1. Add Course Nav Menu using the DW Question Connect , post meta field : vibe_course for post_type
 * 2. Add Questions list below Units using After Unit content hook.
 *==============================================*/


 
class WPLMS_DWQA {

   public function __construct(){
      add_action( 'plugins_loaded', array($this,'language_locale'));
      add_filter('wplms_course_nav_menu',array($this,'wplms_course_nav_menu_dw_qna'));    
      add_action('wplms_load_templates',array($this,'wplms_course_dwqna_question_list'));
      add_action('wplms_after_every_unit',array($this,'wplms_qna_unit_questions'),10,1);

      add_filter('wplms_course_locate_template',array($this,'wplms_dwqa_template_fitler'),10,2); 

      add_action( 'dwqa_submit_question_ui',array($this, 'wplms_dwqa_course_unit'));
      add_action('dwqa_add_question',array($this,'wplms_dwqa_add_question_connect_unit'),10,2);
      add_filter('wplms_course_metabox',array($this,'add_dwqa_vibe_metaboxes'));
      add_filter('custom_meta_box_type',array($this,'add_wplms_dwqa_tag'),10,5);
   }

   function language_locale(){
      $locale = apply_filters("plugin_locale", get_locale(), 'wplms-dwqa');
        if ( file_exists( dirname( __FILE__ ) . '/languages/wplms-dwqa-' . $locale . '.mo' ) ){
            load_textdomain( 'wplms-dwqa', dirname( __FILE__ ) . '/languages/wplms-dwqa-' .$locale . '.mo' );
        }
   }

   function wplms_course_nav_menu_dw_qna($course_menu){
      $link = bp_get_course_permalink();
      $course_menu['questions']=array(
                                    'id' => 'dwqna',
                                    'label'=>__('Questions','wplms-dwqa'),
                                    'action' => 'questions',
                                    'link'=> $link,
                                );
      return $course_menu;
    }

    function wplms_dwqa_template_fitler($template,$action){
      if($action == 'questions'){ 
          $template= array(get_template_directory('course/single/plugins.php'));
      }
      return $template;
    }
    function wplms_course_dwqna_question_list(){
      global $wpdb;
      $course_id=get_the_ID();
      if(!isset($_GET['action']) || ($_GET['action'] != 'questions') || !(in_array('dw-question-answer/dw-question-answer.php', apply_filters('active_plugins', get_option('active_plugins')))))
        return;
      do_action('wplms-question-course-load');
      echo '<h3 class="heading">'.__('Questions & Answers','wplms-dwqa').'</h3>';
      
      $qna_course_tag = get_post_meta($course_id,'vibe_course_qna_tag',true);
      if(isset($qna_course_tag))
        echo do_shortcode('[dwqa-list-questions-with-taxonomy taxonomy_tag="'.$qna_course_tag.'"]');  
      else
        echo do_shortcode('[dwqa-list-questions]');
    }

    function wplms_qna_unit_questions($unit_id){
      if(!post_type_exists('dwqa-question'))
        return;

        $post_per_page = 5;
        if(function_exists('vibe_get_option')){
          $post_per_page = vibe_get_option('loop_number');
        }
        $course_id= $_COOKIE['course'];
        $query_args = apply_filters('wplms_dwqna_unit_query',array(
          'post_type' => 'dwqa-question',
          'orderby'=>'meta_value_num',
          'meta_key' => '_dwqa_votes',
          'order' => 'DESC',
          'post_per_page' => $post_per_page,
          'meta_query' => array(
                                  array(
                                      'key' => 'vibe_question_unit',
                                      'value' => $unit_id,
                                      'compare' => '=',
                                    )
            )
          )
        );
        $qna_course_tag = 1;
        if(isset($course_id)){
          $qna_course_tag = get_post_meta($course_id,'vibe_course_qna_tag',true);
          if(isset($qna_course_tag)){
            $query_args['tax_query'] = array(
                                        array(
                                            'taxonomy' => 'dwqa-question_tag',
                                            'field' => 'slug',
                                            'terms' => $qna_course_tag 
                                        )
                                    );
          }
        }
        $the_questions = new WP_Query($query_args);
        echo '<div class="widget">
          <h3 class="heading">'.__('Questions & Answers','wplms-dwqa');
          echo '<small><a href="'.(isset($course_id)?get_permalink($course_id).'?action=questions':get_post_type_archive_link('dwqa-question').'?uid='.$unit_id).'" target="_blank" class="'.(isset($course_id)?'dwqa-ajax-question-list':'').'"><i class="icon-question"></i><strong>'.__('All Questions','wplms-dwqa').'</strong></small></a>
        </h3>';
        if($the_questions->have_posts()):
          echo '<ul class="dwqa-unit-questions-list">';
          while($the_questions->have_posts()):$the_questions->the_post();
            $votes = get_post_meta(get_the_ID(),'_dwqa_votes',true);
            echo '<li ><a href="'.get_permalink().'" class="dwqa-ajax-ask-question">'.get_the_title().'</a><span class="right"><i class="icon-fontawesome-webfont-18"></i> '.(($votes)?$votes:0).'</span></li>';
          endwhile;
          echo '</ul>';
        endif;
        global $dwqa_options;
        if ( isset( $dwqa_options['pages']['submit-question']) ) {
          $submit_link = get_permalink( $dwqa_options['pages']['submit-question'] );
          echo "<script>
          jQuery(document).ready(function(){
            jQuery('.dwqa-ajax-ask-question').magnificPopup({
                type: 'ajax',
                alignTop: true,
                fixedContentPos: true,
                fixedBgPos: true,
                overflowY: 'auto',
                closeBtnInside: true,
                preloader: false,
                midClick: true,
                removalDelay: 300,
                mainClass: 'my-mfp-zoom-in',
                callbacks: {
                   parseAjax: function( mfpResponse ) {
                    mfpResponse.data = jQuery(mfpResponse.data).find('#content .content');
                  },
                  ajaxContentAdded: function() {
                    jQuery('#vibe_question_unit').val(".$unit_id.");
                    jQuery('#question-tag').val('".$qna_course_tag."');
                  }
                }
              });  
            });
          </script><style>.mfp-ajax-holder .mfp-content{width:60%;}.mfp-ajax-holder .mfp-content .content{padding-left:60px;}.js .tmce-active .wp-editor-area{color:#444 !important;}</style>";
          echo '<a href="'.$submit_link.'?uid='.$unit_id.'&course_tag='.$qna_course_tag.'" class="button dwqa-ajax-ask-question">'.__('Ask Question','wplms-dwqa').'</a>
          </div>';
        }
    }

    function wplms_dwqa_course_unit(){
      ?>
      <div class="question-course-settings clearfix">
        <div class="register-select-question">
          <input type="hidden" name="vibe_question_unit" id="vibe_question_unit" value ="" />
        </div>
      </div>
      <?php
    }
    function wplms_dwqa_add_question_connect_unit($new_question,$user_id){
       $vibe_question_unit = $_POST['vibe_question_unit'];
       if(isset($vibe_question_unit) && is_numeric($vibe_question_unit)){
         update_post_meta($new_question,'vibe_question_unit',$vibe_question_unit);
       }
    }
    function add_dwqa_vibe_metaboxes($metabox){
      $prefix = 'vibe_';
      $metabox[] =  array( // Single checkbox
          'label' => __('DWQA Tag Slug','wplms-dwqa'), // <label>
          'desc'  => __('Enter dwqa question tag slug for this course.','wplms-dwqa'), // description
          'id'  => $prefix.'course_qna_tag',
          'type' => 'dwqa-tags',
          'std'   => ''
        );
      return $metabox;
    }
    function add_wplms_dwqa_tag($type,$meta,$id,$desc,$post_type){
      if($type == 'dwqa-tags'){
        $args = array('hide_empty'=>false);
        $terms = get_terms('dwqa-question_tag',$args);
        if(isset($terms) && is_array($terms)){
          echo '<select name="' . $id . '" id="' . $id . '" class="select"><option value="">'.__('Select Question Tag','wplms-dwqa').'<option>';

          if($meta == '' || !isset($meta)){$meta=$std;}
          foreach($terms as $term){
            echo '<option' . selected( esc_attr( $meta ), $term->slug, false ) . ' value="' . $term->slug . '">' . $term->name . '</option>';
          }
          echo '</select><br />' . $desc;
        }
      }
      return $type;
    }

}

if(class_exists('WPLMS_DWQA')){ 
  $wplms_dwqa = new WPLMS_DWQA();;
}

add_shortcode( 'dwqa-list-questions-with-taxonomy', 'dwqa_archive_question_shortcode');
function dwqa_archive_question_shortcode( $atts ) {
  global $script_version, $dwqa_sript_vars;
  
  extract( shortcode_atts( array(
      'taxonomy_category' => '',//Use slug
      'taxonomy_tag' => '',//Use slug
  ), $atts, 'bartag' ) );
  
  ob_start();
  ?>
      <div class="dwqa-container" >
          <div id="archive-question" class="dw-question">
              <div class="dwqa-list-question">
                  <div class="loading"></div>
                  <div class="dwqa-search">
                      <form action="" class="dwqa-search-form">
                          <input class="dwqa-search-input" placeholder="<?php _e('Search','wplms-dwqa') ?>">
                          <span class="dwqa-search-submit fa fa-search show"></span>
                          <span class="dwqa-search-loading dwqa-hide"></span>
                          <span class="dwqa-search-clear fa fa-times dwqa-hide"></span>
                      </form>
                  </div>
                  <div class="filter-bar">
                      <?php wp_nonce_field( '_dwqa_filter_nonce', '_filter_wpnonce', false ); ?>
                      <?php  
                          global $dwqa_options;
                          $submit_question_link = get_permalink( $dwqa_options['pages']['submit-question'] );
                      ?>
                      <?php if( $dwqa_options['pages']['submit-question'] && $submit_question_link ) { 
                       $qna_course_tag = get_post_meta(get_the_ID(),'vibe_course_qna_tag',true);
                        ?>
                      <form action="<?php echo $submit_question_link ?>" method="post">
                        <input type="hidden" name="question-tag" value="<?php echo $qna_course_tag; ?>" />
                        <input type="submit" class="dwqa-btn dwqa-btn-success" value="<?php _e('Ask a question','wplms-dwqa') ?>" />
                      </form>
                      <?php } ?>
                      <div class="filter">
                          <li class="status">
                              <?php  
                                  $selected = isset($_GET['status']) ? $_GET['status'] : 'all';
                              ?>
                              <ul>
                                  <li><?php _e('Status:','wplms-dwqa') ?></li>
                                  <li class="<?php echo $selected == 'all' ? 'active' : ''; ?> status-all" data-type="all">
                                      <a href="#"><?php _e( 'All','wplms-dwqa' ); ?></a>
                                  </li>

                                  <li class="<?php echo $selected == 'open' ? 'active' : ''; ?> status-open" data-type="open">
                                      <a href="#"><?php echo current_user_can( 'edit_posts' ) ? __( 'Need Answer','wplms-dwqa' ) : __( 'Open','wplms-dwqa' ); ?></a>
                                  </li>
                                  <li class="<?php echo $selected == 'replied' ? 'active' : ''; ?> status-replied" data-type="replied">
                                      <a href="#"><?php _e( 'Answered','wplms-dwqa' ); ?></a>
                                  </li>
                                  <li class="<?php echo $selected == 'resolved' ? 'active' : ''; ?> status-resolved" data-type="resolved">
                                      <a href="#"><?php _e( 'Resolved','wplms-dwqa' ); ?></a>
                                  </li>
                                  <li class="<?php echo $selected == 'closed' ? 'active' : ''; ?> status-closed" data-type="closed">
                                      <a href="#"><?php _e( 'Closed','wplms-dwqa' ); ?></a>
                                  </li>
                                  <?php if( dwqa_current_user_can( 'edit_question' ) ) : ?>
                                  <li class="<?php echo $selected == 'overdue' ? 'active' : ''; ?> status-overdue" data-type="overdue"><a href="#"><?php _e('Overdue','wplms-dwqa') ?></a></li>
                                  <li class="<?php echo $selected == 'pending-review' ? 'active' : ''; ?> status-pending-review" data-type="pending-review"><a href="#"><?php _e('Queue','wplms-dwqa') ?></a></li>

                                  <?php endif; ?>
                              </ul>
                          </li>
                      </div>
                      <div class="filter sort-by">
                              <div class="filter-by-category select">
                                  <?php 
                                      $selected = false;
                                      if( $taxonomy_category ) {
                                          $term = get_term_by( 'slug', $taxonomy_category, 'dwqa-question_category' );
                                          $selected = $term->term_id;
                                      }
                                      $selected_label = __('Select a category','wplms-dwqa');
                                      if( $selected  && $selected != 'all' ) {
                                          $selected_term = get_term_by( 'id', $selected, 'dwqa-question_category' );
                                          $selected_label = $selected_term->name;
                                      }
                                  ?>
                                  <span class="current-select"><?php echo $selected_label; ?></span>
                                  <ul id="dwqa-filter-by-category" class="category-list" data-selected="<?php echo $selected; ?>">
                                  <?php  
                                      wp_list_categories( array(
                                          'show_option_all'   =>  __('All','wplms-dwqa'),
                                          'show_option_none'  => __('Empty','wplms-dwqa'),
                                          'taxonomy'          => 'dwqa-question_category',
                                          'hide_empty'        => 0,
                                          'show_count'        => 0,
                                          'title_li'          => '',
                                          'walker'            => new Walker_Category_DWQA
                                      ) );
                                  ?>  
                                  </ul>
                              </div>
                          <?php 
                              $tag_field = '';
                              if( $taxonomy_tag ) {
                                  $selected = false;
                                  
                                  $term = get_term_by( 'slug', $taxonomy_tag, 'dwqa-question_tag');
                                  $selected = $term->term_id;
                                  if( isset( $selected )  &&  $selected != 'all' ) {
                                      $tag_field = '<input type="hidden" name="dwqa-filter-by-tags" id="dwqa-filter-by-tags" value="'.$selected.'" >';
                                  }
                              } 
                              $tag_field = apply_filters( 'dwqa_filter_bar', $tag_field ); 
                              echo $tag_field;
                          ?>
                          <ul class="order">
                              <li class="most-reads <?php echo isset($_GET['orderby']) && $_GET['orderby'] == 'views' ? 'active' : ''; ?>"  data-type="views" >
                                  <span><?php _e('View', 'wplms-dwqa') ?></span> <i class="fa fa-sort <?php echo isset($_GET['orderby']) && $_GET['orderby'] == 'views' ? 'icon-sort-up' : ''; ?>"></i>
                              </li>
                              <li class="most-answers <?php echo isset($_GET['orderby']) && $_GET['orderby'] == 'answers' ? 'active' : ''; ?>" data-type="answers" >
                                  <span href="#"><?php _e('Answer', 'wplms-dwqa') ?></span> <i class="fa fa-sort <?php echo isset($_GET['orderby']) && $_GET['orderby'] == 'answers' ? 'fa-sort-up' : ''; ?>"></i>
                              </li>
                              <li class="most-votes <?php echo isset($_GET['orderby']) && $_GET['orderby'] == 'votes' ? 'active' : ''; ?>" data-type="votes" >
                                  <span><?php _e('Vote', 'wplms-dwqa') ?></span> <i class="fa fa-sort <?php echo isset($_GET['orderby']) && $_GET['orderby'] == 'votes' ? 'fa-sort-up' : ''; ?>"></i>
                              </li>
                          </ul>
                          <?php  
                              global $dwqa_general_settings;
                              $posts_per_page = isset($dwqa_general_settings['posts-per-page']) ?  $dwqa_general_settings['posts-per-page'] : get_query_var( 'posts_per_page' );
                          ?>
                          <input type="hidden" id="dwqa_filter_posts_per_page" name="posts_per_page" value="<?php echo $posts_per_page; ?>">
                      </div>
                  </div>
                  
                  <?php do_action( 'dwqa-before-question-list' ); ?>

                  <?php  do_action('dwqa-prepare-archive-posts');?>
                  <?php if ( have_posts() ) : ?>
                  <div class="questions-list">
                  <?php while ( have_posts() ) : the_post(); ?>
                      <?php dwqa_load_template( 'content', 'question' ); ?>
                  <?php endwhile; ?>
                  </div>
                  <div class="archive-question-footer">
                  <?php 
                      if( $taxonomy == 'dwqa-question_category' ) { 
                          $args = array(
                              'post_type' => 'dwqa-question',
                              'posts_per_page'    =>  -1,
                              'tax_query' => array(
                                  array(
                                      'taxonomy' => $taxonomy,
                                      'field' => 'slug',
                                      'terms' => $term_name
                                  )
                              )
                          );
                          $query = new WP_Query( $args );
                          $total = $query->post_count;
                      } else if( 'dwqa-question_tag' == $taxonomy ) {

                          $args = array(
                              'post_type' => 'dwqa-question',
                              'posts_per_page'    =>  -1,
                              'tax_query' => array(
                                  array(
                                      'taxonomy' => $taxonomy,
                                      'field' => 'slug',
                                      'terms' => $term_name
                                  )
                              )
                          );
                          $query = new WP_Query( $args );
                          $total = $query->post_count;
                      } else {
                          $total = wp_count_posts( 'dwqa-question' );
                          $total = $total->publish;
                      }

                      $number_questions = $total;

                      $number = get_query_var( 'posts_per_page' );

                      $pages = ceil( $number_questions / $number );
                      
                      if( $pages > 1 ) {
                  ?>
                      <div class="pagination">
                          <ul data-pages="<?php echo $pages; ?>" >
                              <?php  
                                  $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
                                  $i = 0;
                                  echo '<li class="prev';
                                  if( $i == 0 ) {
                                      echo ' dwqa-hide';
                                  }
                                  echo '"><a href="javascript:void()">'.__('Prev', 'wplms-dwqa').'</a></li>';
                                  $link = get_post_type_archive_link( 'dwqa-question' );
                                  $start = $paged - 2;
                                  $end = $paged + 2;

                                  if( $end > $pages ) {
                                      $end = $pages;
                                      $start = $pages -  5;
                                  }

                                  if( $start < 1 ) {
                                      $start = 1;
                                      $end = 5;
                                      if( $end > $pages ) {
                                          $end = $pages;
                                      }
                                  }
                                  if( $start > 1 ) {
                                      echo '<li><a href="'.add_query_arg('paged',1,$link).'">1</a></li><li class="dot"><span>...</span></li>';
                                  }
                                  for ($i=$start; $i <= $end; $i++) { 
                                      $current = $i == $paged ? 'class="active"' : '';
                                      if( $i == 1 ) {
                                          echo '<li '.$current.'><a href="'.$link.'">'.$i.'</a></li>';
                                      }else{
                                          echo '<li '.$current.'><a href="'.add_query_arg('paged', $i, $link).'">'.$i.'</a></li>';
                                      }
                                  }

                                  if( $i - 1 < $pages ) {
                                      echo '<li class="dot"><span>...</span></li><li><a href="'.add_query_arg('paged',$pages,$link).'">'.$pages.'</a></li>';
                                  }
                                  echo '<li class="next';
                                  if( $paged == $pages ) {
                                      echo ' dwqa-hide';
                                  }
                                  echo '"><a href="javascript:void()">'.__('Next', 'wplms-dwqa') .'</a></li>';

                              ?>
                          </ul>
                      </div>
                      <?php } ?>
                      <?php if( $dwqa_options['pages']['submit-question'] && $submit_question_link ) { 
                        $qna_course_tag = get_post_meta(get_the_ID(),'vibe_course_qna_tag',true);
                        ?>
                      <form action="<?php echo $submit_question_link ?>" method="post">
                        <input type="hidden" name="question-tag" value="<?php echo $qna_course_tag; ?>" />
                        <input type="submit" class="dwqa-btn dwqa-btn-success" value="<?php _e('Ask a question','wplms-dwqa') ?>" />
                      </form>
                      <?php } ?>
                  </div>
                  <?php else: ?>
                      <?php
                          if( ! dwqa_current_user_can('read_question') ) {
                              echo '<div class="alert">'.__('You do not have permission to view questions','wplms-dwqa').'</div>';
                          }
                          echo '<p class="not-found">';
                           _e('Sorry, but nothing matched your filter.', 'wplms-dwqa' );
                           if( is_user_logged_in() ) {
                              global $dwqa_options;
                              if( isset($dwqa_options['pages']['submit-question']) ) {
                                  
                                  $submit_link = get_permalink( $dwqa_options['pages']['submit-question'] );
                                  if( $submit_link ) {
                                      printf('%s <a href="">%s</a>',
                                          __('You can ask question','wplms-dwqa'),
                                          $submit_link,
                                          __('here','wplms-dwqa')
                                      );
                                  }
                              }
                           } else {
                              printf('%s <a href="%s">%s</a>',
                                  __('Please','wplms-dwqa'),
                                  wp_login_url( get_post_type_archive_link( 'dwqa-question' ) ),
                                  __('Login','wplms-dwqa')
                              );

                              $register_link = wp_register('', '',false);
                              if( ! empty($register_link) && $register_link  ) {
                                  echo __(' or','wplms-dwqa').' '.$register_link;
                              }
                              _e(' to submit question.','wplms-dwqa');
                              wp_login_form();
                           }

                          echo  '</p>';
                      ?>
                  <?php endif; ?>
                  <?php do_action( 'dwqa-after-archive-posts' ); ?>
              </div>
          </div>
      </div>
      <?php
      $html = ob_get_contents();
      ob_end_clean();
      wp_enqueue_script( 'dwqa-questions-list', plugins_url('dwqa-questions-list.js',__FILE__), array( 'jquery' ), $script_version, true );
      wp_localize_script( 'dwqa-questions-list', 'dwqa', $dwqa_sript_vars );
      return $html;
  }


 /*==============================================
 *         END DW Q&A PLUGIN INTEGRATION 
 *==============================================*/

?>