jQuery(document).ready(function(){
	
	initialize_icon_click();

	if( follow.is_user_logged_in && follow.ajax ){
		if(typeof follow_forms == 'undefined'){
			follow_forms = {}
		}

		forms = jQuery('.wdwl_follow_form_class');
		if(forms.length){
			for( i = 0; i < forms.length; i++){
				update_follow_data( jQuery(forms[i]) );	
			}
		}

	

		jQuery('.wdwl_follow_form_class').live('submit',function(){
			name = jQuery(this).children('input[type="submit"]').attr('name');
			current_form = jQuery(this);
		
			jQuery.ajax( {
			    url : follow.ajaxurl,
			    type : 'POST',
			    data: { 
					action: 'follow',
					user_id: follow_forms[name]['user_id'],
					following_id: follow_forms[name]['following_id'],
					type_id: follow_forms[name]['type_id'],
					is_following: follow_forms[name].is_following
			    },
			    beforeSend : function( formData, jqForm, options ){
					current_form.children('input[type="submit"]').attr( 'disabled','true' );
					jQuery('.icon-heart').die('click');
			    },  
			    success: function( response, statusText, xhr, form ){
				response_obj = JSON.parse(response);
				if( response_obj.status ){
					follow_forms[name].is_following = response_obj.data.is_following;
					if( response_obj.data.is_following ){
						current_form.children('input[type="submit"]').val( follow_forms[name].status_text.unfollow );
						current_form.children('input[type="submit"]').attr( 'title',follow_forms[name].status_text.unfollow );

						current_form.children('input[type="submit"]').removeClass( follow_forms[name].input_class.follow );
						current_form.children('input[type="submit"]').addClass( follow_forms[name].input_class.unfollow );

						current_form.parent().removeClass( follow_forms[name].class.follow );
						current_form.parent().addClass( follow_forms[name].class.unfollow );

						current_form.parent().siblings('i').removeClass( follow_forms[name].icon_class.follow );
						current_form.parent().siblings('i').addClass( follow_forms[name].icon_class.unfollow );
		
					
					} else {
						current_form.children('input[type="submit"]').val( follow_forms[name].status_text.follow );
						current_form.children('input[type="submit"]').attr( 'title',follow_forms[name].status_text.follow );

						current_form.children('input[type="submit"]').removeClass( follow_forms[name].input_class.unfollow );
						current_form.children('input[type="submit"]').addClass( follow_forms[name].input_class.follow );

						current_form.parent().removeClass( follow_forms[name].class.unfollow );
						current_form.parent().addClass( follow_forms[name].class.follow );

						current_form.parent().siblings('i').removeClass( follow_forms[name].icon_class.unfollow );
						current_form.parent().siblings('i').addClass( follow_forms[name].icon_class.follow );

					}
				}
			
			
			    },
			    error: function( response, statusText, xhr, form ){
			
			    },
			    complete: function( response, statusText, xhr, form ){
				initialize_icon_click();
				current_form.children('input[type="submit"]').removeAttr( 'disabled' );
			    }
			} );
		
			return false;
		});

		jQuery(document).on('DOMNodeInserted', function(event) {
		    follow_form = jQuery(event.target).find('.wdwl_follow_form_class');
		    if(follow_form.length){
			update_follow_data( jQuery(follow_form) );
		    }	
		});
	}

	
});

function initialize_icon_click(){
	jQuery('.icon-heart').live('click',function(){
		if( follow.is_user_logged_in ){
			//jQuery(this).siblings('.follow_wrapper').children('#wdwl_follow_form').submit();
			jQuery(this).siblings('.follow_wrapper').children('#wdwl_follow_form').children('input[type="submit"]').click();			
		} else {
			window.location.replace( follow.action );

		}
	});
}

function update_follow_data( follow_form ){
	name = follow_form.children('input[type="submit"]').attr('name');
	data_follow = follow_form.children('input[type="submit"]').attr('data-follow');
	
	follow_forms[name] = JSON.parse(data_follow);
	follow_form.children('input[type="submit"]').removeAttr('data-follow');
}
