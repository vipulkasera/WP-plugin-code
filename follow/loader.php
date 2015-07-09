<?php
/*
 * @package Follow
 * @version 1.0
 * Plugin Name: Follow
 * Description: This plugin manages follow user/artist
 * Author: Widewalls
 * Plugin URI:  http://widewalls.ch
 * Version: 1.0
*/
ob_start();

class WDWLFollowController
{
    private $wpdb, $wdwlFollow;
    
	// This is the main constructor function
	public function __construct(){
        
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		
		// Add shortcode for follow button
		add_shortcode( "wdwl_follow_form", array( $this, "wdwl_follow_form" ) );
		add_action( 'wp_ajax_follow', array( $this, 'follow_ajax' ) );
	}

    // This is the main function to initialize required variables
	public function initialize(){
        require_once( 'macros.php' );
        $this->current_path = plugin_dir_path( __FILE__ );
        $this->classes_path = $this->current_path . 'classes/';

		$this->class_url = plugin_dir_url( __FILE__ );
        $this->js_url = $this->class_url . 'resources/js/';
		$this->images_url = $this->class_url . 'resources/images/';
    
        $this->load_classes();
        $this->wdwlFollow = new WDWLFollow();
	}

    function load_classes(){
        require_once( $this->classes_path . "WDWLFollow.php" );
    }

    //Plugin activation function
	function activate() {
        $this->initialize();
        $this->create_tables();
    }
    
    // Function to create required tables on plugin activation
	function create_tables() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
		$follow_table = $this->wdwlFollow->get_follow_table();

        $sql = array();
    
        $sql[] = "CREATE TABLE IF NOT EXISTS `$follow_table` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `following_id` int(11) NOT NULL,
                  `user_id` int(11) NOT NULL,
                  `type` tinyint(4) NOT NULL COMMENT '1 : Artist , 2 : b2buser ',
                  PRIMARY KEY (`id`)
                )";

        foreach( $sql as $query ){
            dbDelta( $query );
        }
    }

	// Function to display follow/unfollow button using shortcode
    function wdwl_follow_form( $params ){
      	$this->initialize();

		global $current_user;
		$action = '';
		$current_follow_status = 0;
	
		$follow_status_text = isset($params['follow_text'])?$params['follow_text']:'Follow';
		$unfollow_status_text = isset($params['unfollow_text'])?$params['unfollow_text']:'Unfollow';
		$follow_button_text = $follow_status_text;
	
		$form_name = 'wdwl_follow' . $params['type_id'] .'-'. $current_user->data->ID .'-'. $params['following_id'];
		
		
		$event_id = isset($params['event_id'])?$params['event_id']:1;

		if( ! is_user_logged_in() ){
	
			$action = get_permalink( LOGIN_PAGE_ID_TML );
	
		} else {
	
			$following_id = $params['following_id'];
			$type_id = $params['type_id'];
	
			if( $this->wdwlFollow->is_following( $current_user->data->ID, $following_id, $type_id ) ){
			$current_follow_status = 1;
				if( isset( $_POST[ $form_name ] ) ){
					$this->wdwlFollow->unfollow( $current_user->data->ID, $following_id, $type_id );
				$current_follow_status = 0;
				} else {
					$follow_button_text = $unfollow_status_text;
				}
			} else {
				if( isset( $_POST[$form_name] ) ){
					$this->wdwlFollow->follow( $current_user->data->ID, $following_id, $type_id );
				$current_follow_status = 1;
					$follow_button_text = $unfollow_status_text;
				}
			}
		}
	
		$class = ( !$current_follow_status ) ? "" : "unfollow";
		$input_class = ( !$current_follow_status ) ? "follow_new" : "unfollow_new";
		$icon_class = $input_class . '_heart_icon';
	
		$follow_button_title_tag = $follow_button_text;
		if( $follow_button_title_tag == '' ){
			$follow_button_title_tag =  ( $current_follow_status ) ? 'Unfollow':'Follow' ;
		}
		

		$datafollow = array( 
			'type_id' => $params['type_id'], 
			'user_id' => $current_user->data->ID, 
			'following_id' => $params['following_id'],
			'status_text' => array(
						'follow' => $follow_status_text,
						'unfollow' => $unfollow_status_text
			),
			'heart' => $params[ 'heart' ],
			'icon_class' => array(
						'follow' => 'follow_new_heart_icon',
						'unfollow' => 'unfollow_new_heart_icon'
			),
			'class' => array(
						'follow' => '',
						'unfollow' => "unfollow"
			),
			'input_class' => array(
						'follow' => 'follow_new',
						'unfollow' => "unfollow_new"
			),
			'is_following' => $current_follow_status
			
		);

		$data_follow_attr = json_encode($datafollow);
		
		if( isset( $params[ 'heart' ] ) ){ ?>
			<i class="icon-heart <?php _e( $icon_class ); ?>" title = "<?php _e( $follow_button_title_tag ); ?>"></i><?php
		} ?>

		<div class = "follow_wrapper <?php _e( $class ); ?>">
			<form id = "wdwl_follow_form" class = "wdwl_follow_form_class" action = "<?php _e( $action ); ?>" method = "post">
				<input  type="hidden" value="<?php _e($event_id); ?>" name = "event_id">
				<input  class = "<?php _e( $input_class.' '.$params['follow_btn_classes'] ); ?>" title = "<?php _e( $follow_button_title_tag ); ?>" type="submit" value="<?php _e( $follow_button_text ); ?>" name = "<?php _e( $form_name ); ?>" data-follow = '<?php _e( $data_follow_attr) ; ?>' >
			</form>
		</div>

		<?php
		wp_enqueue_script( 'follow_js', $this->js_url . 'follow.js' );	
		wp_localize_script( 'follow_js', 'follow', array( 'ajaxurl' => site_url() . '/custom-ajax.php', 'is_user_logged_in' => is_user_logged_in(), 'action' =>  $action, 'ajax' => $params['ajax'] ) );
	
	}

	// function to handle ajax request
    function follow_ajax(){
        $this->initialize();
		$response = array(
			'status' => 1,
			'messages' => array(
				'success' => array(),
				'error' => array()
			),
			'data' => array()
		);
        if( $follow_row_id = $this->wdwlFollow->is_following( $_POST['user_id'], $_POST['following_id'], $_POST['type_id'] ) ){
	        if( ! $this->wdwlFollow->unfollow( $_POST['user_id'], $_POST['following_id'], $_POST['type_id'] )){
				$response['status'] = 0;
		    } else {
				$response['data']['is_following'] = 0;
		    }
	    } else {
	        if( ! $this->wdwlFollow->follow( $_POST['user_id'], $_POST['following_id'], $_POST['type_id'] )){
				$response['status'] = 0;
		    } else {
				$response['data']['is_following'] = 1;
		    }
	    }
		echo json_encode( $response );	    
        die( '' );
    }

    // Function to get follow object in other scripts
	public function get_wdwlfollow_obj(){
        $this->initialize();
        return $this->wdwlFollow;
    }
}

$wDWLFollowController = new WDWLFollowController();
