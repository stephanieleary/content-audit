jQuery( document ).ready( function(){
/* for post screens */
   jQuery( '.datepicker' ).datepicker( {
      dateFormat : 'm/d/y'
   } );
 /* for media screens */
jQuery( 'tr.audit_expiration input.text' ).datepicker( {
      dateFormat : 'm/d/y'
   } );

} );