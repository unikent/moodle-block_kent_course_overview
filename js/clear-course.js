jQuery(document).ready(function() {

    jQuery(".course_clear_optns").each( function() {

    	jQuery(this).click( function(e) {

    		e.preventDefault();	

            var button = jQuery(this);
            var o = {};
            o['course'] = button.attr('href').replace("#", "");

		    $("#dialog_clear_error").dialog({
				 autoOpen: false,
				 title: 'Clear module error',
				 modal: true,
				 buttons: {
					"OK": function() {
						$(this).dialog("close");
					}
				 }
			});

	        $("#dialog_sure").dialog({
	             autoOpen: false,
	             title: 'Clear module confirmation',
	             draggable: false,
	             modal: true,
	             buttons: {
	                "Yes": function() {
	                    $(this).dialog("close");
						jQuery.blockUI({ message: 
							'<div class="blockui_loading">Please wait, clearing module.  This may take a little while.</div>' });

						return jQuery.ajax({
							url: M.cfg.wwwroot + "/local/rollover/clear.php",
				            type: "POST",
				            data: o,
                            statusCode: {
                                201: function(data, s) {
               						button.after('<p><b>Module cleared</b></p>');
									button.remove();
									jQuery.unblockUI();
									location.reload();
                                },
                                500: function(data, s) {
									button.after('<p><b>Error!</b></p>');
									button.remove();
									jQuery.unblockUI();
									$("#dialog_clear_error").dialog("open");
                                }
                            },
							error: function(x, t, m){
								button.after('<p><b>Error!</b></p>');
								button.remove();
								jQuery.unblockUI();
								$("#dialog_clear_error").dialog("open");
							},
							timeout: 60000 //60 Seconds max to try and clear
						});
				                }
				                , "No": function() {
				                    $(this).dialog("close");
				                }
				            }
			});

 			$("#dialog_sure").dialog("open");

    	});

    });

});