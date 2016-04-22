//mostly from http://rachelcarden.com/2012/03/manage-wordpress-posts-using-bulk-edit-and-quick-edit/
( function( $ ) {

   // we create a copy of the WP inline edit post function
   var $wp_inline_edit = inlineEditPost.edit;
   // and then we overwrite the function with our own code
   inlineEditPost.edit = function( id ) {

      // "call" the original WP edit function
      // we don't want to leave WordPress hanging
      $wp_inline_edit.apply( this, arguments );

      // now we take care of our business

      // get the post ID
      var $post_id = 0;
      if ( typeof( id ) == 'object' )
         $post_id = parseInt( this.getId( id ) );

      if ( $post_id > 0 ) {

         // define the edit row
         var $edit_row = $( '#edit-' + $post_id );

         // get the content owner
	 	var $content_owner = $( '#_content_audit_owner-' + $post_id ).attr( 'title' );
	 	//alert( $content_owner );
		// get the expiration date
		var $content_expiration = $( '#_content_audit_expiration_date-' + $post_id ).attr( 'title' );
		// get the notes
		var $content_notes = $( '#_content_audit_notes-' + $post_id ).text();
		
	 	// populate the content owner
		$( 'select#_content_audit_owner option[value="' + $content_owner + '"]', $edit_row ).attr( 'selected', 'selected' );
		// populate the content expiration
		$edit_row.find( 'input[name="_content_audit_expiration_date"]' ).val( $content_expiration );
		// populate the notes
		$edit_row.find( 'input[name="_content_audit_notes"]' ).val( $content_notes );
      }

   };

	$( '#bulk_edit' ).live( 'click', function() {

	   // define the bulk edit row
	   var $bulk_row = $( '#bulk-edit' );

	   // get the selected post ids that are being edited
	   var $post_ids = new Array();
	   $bulk_row.find( '#bulk-titles' ).children().each( function() {
	      $post_ids.push( $( this ).attr( 'id' ).replace( /^( title )/i, '' ) );
	   } );

	  	// get the owner
	  	var $content_owner = $bulk_row.find( 'input[name="_content_audit_owner"]' ).val();
	  	// get the expiration date
		var $content_expiration = $bulk_row.find( 'input[name="_content_audit_expiration_date"]' ).val();
		// get the notes
		var $content_notes = $bulk_row.find( 'input[name="_content_audit_notes"]' ).val();

	   // save the data
	   $.ajax( {
	      url: ajaxurl, // this is a variable that WordPress has already defined for us
	      type: 'POST',
	      async: false,
	      cache: false,
	      data: {
	         action: 'content_audit_save_bulk_edit', // this is the name of our WP AJAX function that we'll set up next
	         post_ids: $post_ids, // and these are the parameters we're passing to our function
		 	 _content_audit_owner: $content_owner,
			_content_audit_expiration_date: $content_expiration,
			_content_audit_notes: $content_notes,
	      }
	   } );

	} );

} )( jQuery );
