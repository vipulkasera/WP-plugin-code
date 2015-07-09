<?php
/*
 * @package Follow
 * @version 1.0
 * Plugin Name: Follow
 * Description: This class manages data get and set for follow plugin
 * Author: Widewalls
 * Plugin URI:  http://widewalls.ch
 * Version: 1.0
*/

class WDWLFollow
{
    private $wpdb, $table_follow, $default_image_url;

	// Define required constants
    const 
        ARTIST_TYPE_ID  = 1,
        B2BUSER_TYPE_ID = 2,
        B2CUSER_TYPE_ID = 3,
        EVENT_TYPE_ID   = 4,
        BOARD_TYPE_ID   = 5,
        TRAVEL_TYPE_ID  = 6,
		ARTWORK_TYPE_ID = 7;

    // This is the main constructor function
	public function __construct(){
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_follow = $wpdb->prefix . 'wdwlfollow';
        $this->default_image_url = get_stylesheet_directory_uri().'/images/default-featured-image/default-location-300x181.jpg';
    }
    
    //function to get follow table name
	public function get_follow_table(){
        return $this->table_follow;
    }

    // Function to check if user is currently following or not
	public function is_following( $user_id, $following_id, $type_id ){
        $query = $this->wpdb->prepare( "SELECT `id` FROM `" . $this->table_follow . "`
            WHERE `user_id` = %d
            AND `following_id` = %d
            AND `type` = %d", $user_id, $following_id, $type_id );
        
		$row = $this->wpdb->get_row( $query );
        
        if( empty( $row ) ){
            return $row->id;
        } else {
            return 1;
        }
    }

    // Function to perform follow action
	public function follow( $user_id, $following_id, $type_id ){
        $result = $this->wpdb->insert( $this->table_follow,
                        array(
                            'user_id' => $user_id,
                            'following_id' => $following_id,
                            'type' => $type_id
                        ),
                        array(
                            '%d',
                            '%d',
                            '%d'
                        )
                    );
		
		// Extra functions to record activities
		if( function_exists('update_user_activity') ){
			
			$params = array( 'user_id' => $user_id, 'post_id' => $following_id, 'type' => $type_id );
			
			update_user_activity( 'follow', $params );
			
		}
		
		if( function_exists('analytic_activity_record_insert') ){

			analytic_activity_record_insert( '', $user_id, $type_id, ANALYTIC_TOOL_ACTIVITY_FOLLOW, $following_id );

		}

        return $result;
 
    }

    // Function to perform unfollow action
	public function unfollow( $user_id, $following_id, $type_id){
        $result = $this->wpdb->delete($this->table_follow,
			array(
				'user_id' => $user_id,
				'following_id' => $following_id,
				'type' => $type_id
			),
			array(
				'%d',
				'%d',
				'%d'
			)
		);
	
		// Extra functions to record activities
        if( function_exists('update_user_activity') ){

			$params = array( 'user_id' => $user_id, 'post_id' => $following_id, 'type' => $type_id );

			update_user_activity( 'unfollow', $params );

		}

		if( function_exists('analytic_activity_record_insert') ){

			analytic_activity_record_insert( '', $user_id, $type_id, ANALYTIC_TOOL_ACTIVITY_UNFOLLOW, $following_id );

		}

        return $result;

    }

    //Function to get what user is currently following
	public function get_user_follows( $user_id ){
		if( isset( $params[ 'size' ] ) ){
            $offset = isset( $params[ 'page_no' ] ) ? $params[ 'size' ] * ( $params[ 'page_no' ] ):0 ;
            $limit_query  = 'LIMIT '. $offset . ',' . $params[ 'size' ];
        }

        $query = $this->wpdb->prepare( "
                SELECT `tp`.`ID` as `artist_id`,`post_title` FROM `" . $this->table_follow . "` as `tf`
                JOIN `" . $this->wpdb->posts . "` as `tp` ON `tf`.`following_id` = `tp`.`ID`
                WHERE `user_id` = %d
                AND `type` = %d", $user_id, self::ARTIST_TYPE_ID );
        $artists = $this->wpdb->get_results( $query );

        $query = $this->wpdb->prepare( "
                SELECT `tu`.`ID` as `user_id`,`display_name` FROM `" . $this->table_follow . "` as `tf`
                JOIN `" . $this->wpdb->users . "` as `tu` ON `tf`.`following_id` = `tu`.`ID`
                WHERE `user_id` = %d
                AND `type` = %d", $user_id, self::B2BUSER_TYPE_ID );
        $b2busers = $this->wpdb->get_results( $query );

        foreach( $artists as $artist ){
            $artist->thumbnail = get_the_post_thumbnail( $artist->artist_id, 'art' );
            if( empty( $artist->thumbnail ) || trim( $artist->thumbnail) == '' ){
                $artist->thumbnail = '<img width="250" height="272" src="' . $this->default_image_url . '">';
            }
        }

        foreach( $b2busers as $artist ){
            
			$artist->thumbnail = get_avatar( $artist->user_id, 272, $this->default_image_url, $artist->display_name );
            
        }

        $results = array(
                'artists' => $artists,
                'b2busers' => $b2busers
            );

        return $results;
    }

    
	// Function to get what user is following with pagination
	public function get_user_follows_paginated( $user_id, $params = array() ){
        
        $limit_query = '';
        $b2busers = '';
        $reset_page = 0;
		$default_offset = isset( $params[ 'default_offset' ] ) ? $params[ 'default_offset' ] : 0;
		$queries = array();
	
        $get_b2b    = isset( $params[ 'get_b2busers' ] ) ? $params[ 'get_b2busers' ] : 1 ;
        $get_artist = isset( $params[ 'get_artists' ] ) ? $params[ 'get_artists' ] : 1 ;
        
        if( isset( $params[ 'size' ] ) ){
            $offset = isset( $params[ 'page_no' ] ) ? $params[ 'size' ] * ( $params[ 'page_no' ] ):0 ;
            $limit_query  = 'LIMIT '. $offset . ',' . $params[ 'size' ];
        }

		if( $get_artist ){
			$query = $this->wpdb->prepare( "
					SELECT `tp`.`ID` as `artist_id`,`post_title` FROM `" . $this->table_follow . "` as `tf`
					JOIN `" . $this->wpdb->posts . "` as `tp` ON `tf`.`following_id` = `tp`.`ID`
					WHERE `user_id` = %d
					AND `type` = %d " . $limit_query, $user_id, self::ARTIST_TYPE_ID );
				$artists = $this->wpdb->get_results( $query );
			$queries[] = $query;
			if( sizeof( $artists ) > 0 ) {
				foreach( $artists as $artist ){
					$artist->thumbnail = get_the_post_thumbnail( $artist->artist_id, 'art' );
					if( empty( $artist->thumbnail ) || trim( $artist->thumbnail) == '' ){
						$artist->thumbnail = '<img width="250" height="272" src="' . $this->default_image_url . '">';
					}
				}
			}
	
			if( sizeof( $artists ) < $params[ 'size' ] ) {
				//$get_b2b    = 1;
					$get_artist = 0;
				$params[ 'page_no' ] = 0;
				$params[ 'size' ] = $params[ 'size' ] - sizeof( $artists );
				$reset_page = $params[ 'size' ];
			}
		} 

		if( $get_b2b ){
	
			if( isset( $params[ 'size' ] ) ){
				$offset = isset( $params[ 'page_no' ] ) ? $params[ 'size' ] * ( $params[ 'page_no' ] ) + $default_offset : 0 ;
				$limit_query  = 'LIMIT '. $offset . ',' . $params[ 'size' ];
			}
	
			$query = $this->wpdb->prepare( "
			SELECT `tu`.`ID` as `user_id`,`display_name`,`user_nicename` FROM `" . $this->table_follow . "` as `tf`
			JOIN `" . $this->wpdb->users . "` as `tu` ON `tf`.`following_id` = `tu`.`ID`
			WHERE `user_id` = %d
			AND `type` = %d " . $limit_query, $user_id, self::B2BUSER_TYPE_ID );
			$queries[] = $query;
					$b2busers = $this->wpdb->get_results( $query );
	
			if( !empty( $b2busers ) ) {
				foreach( $b2busers as $artist ){
					$artist->thumbnail = get_avatar( $artist->user_id, 272, $this->default_image_url, $artist->display_name );
				}
				
			}
		}     

        $results = array(
			'artists' => $artists,
			'b2busers' => $b2busers,
			'get_b2busers' => $get_b2b,
			'get_artists' => $get_artist,
			'reset_page' => $reset_page,
			'queries' => $queries
        );

        return $results;
    }
    
    
	//Functin to get user's following events
	public function get_user_events_followed( $user_id, $params = array() ){
        
		$limit_query = '';
	    
		$query = $this->wpdb->prepare( "SELECT `tp`.`ID` as `event_id` FROM `" . $this->table_follow . "` as `tf` JOIN `" . $this->wpdb->posts . "` as `tp` ON `tf`.`following_id` = `tp`.`ID` WHERE `user_id` = %d AND `type` = %d ORDER BY `tp`.`ID` DESC " . $limit_query, $user_id, self::EVENT_TYPE_ID );

        $events = $this->wpdb->get_results( $query );

        return $events;
    }
	
	
	//Function to get user's following artworks
	public function get_user_artwork_followed( $user_id, $params = array() ){
        
		$limit_query = '';
		
		$query = $this->wpdb->prepare( " SELECT `tp`.`ID` as `arty_id` FROM `" . $this->table_follow . "` as `tf` JOIN `" . $this->wpdb->posts . "` as `tp` ON `tf`.`following_id` = `tp`.`ID` WHERE `user_id` = %d AND `type` = %d ORDER BY `tp`.`ID` DESC " . $limit_query, $user_id, self::ARTWORK_TYPE_ID );

        $artworks = $this->wpdb->get_results( $query );

        return $artworks;
    }
    
    
	//Function to get user's following classifieds
	public function get_user_classifieds_followed( $user_id, $params = array() ) {
      
		$limit_query = '';
		if( isset( $params[ 'size' ] ) ){
			$offset = isset( $params[ 'page_no' ] ) ? $params[ 'size' ] * ( $params[ 'page_no' ] ):0 ;
			$limit_query  = 'LIMIT '. $offset . ',' . $params[ 'size' ];
		}
  
        $query = $this->wpdb->prepare( "SELECT `ID` , `post_title`, `post_content`, `post_date` FROM `" . $this->wpdb->posts . "` where `ID` IN ( SELECT `following_id` FROM `" . $this->table_follow . "` where `user_id` = %d AND `type` = %d) ORDER BY `ID` DESC " . $limit_query , $user_id, self::TRAVEL_TYPE_ID );
        $travels = $this->wpdb->get_results( $query );

        return $travels;
    }
	
    
	// Function to get user's folowing boards
	public function get_user_boards_followed( $user_id, $limit, $offset ) {

		if( class_exists( 'PinBoardPlugin' ) ) {
            global $pbp;
            $board_manager = $pbp->get_board_manager();
            $pin_manager   = $pbp->get_pin_manager();
        } else {
		    return ;
		}

       
        $query = $this->wpdb->prepare( "SELECT `following_id` FROM `" . $this->table_follow . "` as `ft` JOIN `". $board_manager->get_board_table() ."` as `bt` ON  `bt`.`id` = `ft`.`following_id` where `ft`.`user_id` = %d AND `type` = %d and `bt`.`publish_status` = 1" , $user_id, self::BOARD_TYPE_ID );
 
        $boards = $this->wpdb->get_results( $query );
        
        if( sizeof( $boards ) > 0 ) {
            
            $followed_board = array();

            foreach( $boards as $board ) {

                $followed_board[] = $board->following_id; 

            }

            $followed_boards_id = implode(",", $followed_board);

        } else {

            $followed_boards_id = '';

        }
        
        $boards = $this->get_pins_by_board( $followed_boards_id, $limit, $offset );
        
        return $boards;
    }

    
	//Function to get all pins by boards
	public function get_pins_by_board( $board_id, $limit, $offset ){
        
        global $wpdb, $current_user;
        
        $board_pins = array();
        $btp        = $wpdb->prefix . 'board_to_pin';
        
        if( ! $board_id ){

            return ;

        }

        if( class_exists( 'PinPostType' ) ){

            $pin_post_type = PinPostType::get_instance();

        } else {

            return ;

        }
    
        if( class_exists( 'PinBoardPlugin' ) ) {

            global $pbp;
            $board_manager = $pbp->get_board_manager();
            $pin_manager   = $pbp->get_pin_manager();
        } else {

            return ;

        }
        
        $query = $wpdb->prepare( 'SELECT `p`.`ID`, `post_title`, `post_content`, `post_date`, `board_id`, `board_name`, `slug`, `user_id`, `type`, `external_reference_id`, `thumb`, `video_url` FROM `' . $wpdb->posts . '` as `p` JOIN `' . $btp .'` as `btp` ON `p`.`ID` = `btp`.`pin_id` JOIN `'. $board_manager->get_board_table() .'` as `bt` ON  `bt`.`id` = `btp`.`board_id` WHERE `post_type` = "' . $pin_post_type->get_post_type() . '" AND `board_id` IN ('. $board_id .') ORDER BY `p`.`post_date` DESC limit %d,%d', $offset, $limit );

        $pins = $wpdb->get_results( $query, ARRAY_A );
        
        if( sizeof($pins) > 0 ) {

            foreach( $pins as $pin ){

                $pin['permalink'] = get_permalink( $pin['ID'] );

		if( in_array( $pin['type'], array( 2,4 ) ) ){

                    $pin['thumb'] = $pin['thumb'];

                } else {

                    $pin['thumb'] = array_shift( wp_get_attachment_image_src( get_post_thumbnail_id( $pin['ID'] ),  THUMBNAIL_MEDIUM  ) );

                }
                if( ! $pin['thumb'] ){

                    $pin['thumb'] = '';

                }
                $pin_obj = (object) $pin;
                
                $pin['board_url'] = $board_manager->get_board_permalink( $pin_obj );
                $pin['external_permalink'] = $pin_manager->external_reference_url( $pin_obj );
                $pin['editable'] = ( $current_user->data->ID == $pin['user_id'] ) ? 1 : 0 ;
            
                $board_pins[] = $pin;
            }  

        }
        
        return $board_pins;

    }
    
}