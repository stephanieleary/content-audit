jQuery( document ).ready( function() {
	// make this work for elements added in the future, i.e. quick edit fields
	jQuery( document ).on( 'focus', '.content_audit_owner', function(){	
		function split( val ) {
			return val.split( /,\s*/ );
		}
		function extractLast( term ) {
			return split( term ).pop();
		}	
		jQuery( '.content_audit_owner' ).each( function(){ 
			var position = { offset: '0, -1' };
			if ( typeof isRtl !== 'undefined' && isRtl ) {
				position.my = 'right top';
				position.at = 'right bottom';
			}
			jQuery( this ).autocomplete( { 
				source: ajaxurl + '?action=content-owner-search&autocomplete_type=search&autocomplete_field=user_login',
				focus: function() {
					// prevent value inserted on focus
					return false;
				},
				
				select: function( event, ui ) {
					var terms = split( this.value );
					// remove the current input
					terms.pop();
					// add the selected item
					terms.push( ui.item.value );
					// add placeholder to get the comma-and-space at the end
					terms.push( "" );
					this.value = terms.join( ", " );
					return false;
				},
				
				delay:     500, 
				minLength: 2, 
				multiple: true,
				position:  position, 
					open: function() { 
						jQuery( this ).addClass( 'open' ); 
					}, 
					close: function() { 
						jQuery( this ).removeClass( 'open' ); 
					} 
			} ); 
		} );
	} );
} );