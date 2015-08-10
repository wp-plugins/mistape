( function ( $ ) {

	$( document ).ready( function () {

		// remove tab query arg
		$('input[name="_wp_http_referer"]').val(function(i,v){
			return v.replace(/&tab=.*/i, '');
		});

		// mail recipient switch
		$( 'input[id^="mistape_email_recipient_type-"]' ).change( function () {
			var checked=$( 'input[id^="mistape_email_recipient_type-"]:checked' ).val();
			$.when( $( 'div[id^="mistape_email_recipient_list-"]:not([id$=checked])' ).slideUp( 'fast' ) ).then( function() {
				$( '#mistape_email_recipient_list-' + checked ).slideDown( 'fast' );
			});
		} );

		// shortcode option
		$( '#mistape_shortcode_option' ).change( function () {
			if ( $( this ).is(':checked') ) {
				$( '#mistape_shortcode_help' ).slideDown( 'fast' );
			} else {
				$( '#mistape_shortcode_help' ).slideUp( 'fast' );
			}
		} );

		// caption format switch
		$( '#mistape_register_shortcode, input[id^="mistape_caption_format-"]' ).change( function () {
			if ( $( '#mistape_register_shortcode' ).is(':checked') || $( 'input[id^="mistape_caption_format-"]:checked' ).val() === 'image' ) {
				$( '#mistape_caption_image' ).slideDown( 'fast' );
			} else {
				$( '#mistape_caption_image' ).slideUp( 'fast' );
			}
		} );

		// Tab switching without reload
		$( '.nav-tab' ).click( function (ev) {
			ev.preventDefault();
			if ( ! $(this).hasClass('nav-tab-active') ) {
				$(this).siblings().removeClass('nav-tab-active');
				$(this).addClass('nav-tab-active');
				$('.mistape-tab-contents').hide();
				$('#' + $(this).data('bodyid')).show();
				ChangeUrl($(this).text(), $(this).attr('href'));
			}
		} );

		var ChangeUrl=function(title, url) {
			if (typeof (history.pushState) != "undefined") {
				var obj = { Title: title, Url: url };
				history.pushState(obj, obj.Title, obj.Url);
			}
		}

	} );

} )( jQuery );