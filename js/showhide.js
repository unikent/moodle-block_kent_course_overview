jQuery(document).ready(function($) {

	function showhide(button, content) {
		button.click(function() {
			if($(this).hasClass('hide')) {
				var _this = this;
				$(this).siblings(content).stop().slideUp('fast', function() {
					$(_this).removeClass('hide');
				});
			} else {
				$(this).addClass('hide');
				$(this).siblings(content).stop().slideDown('fast');
			}
		});
	}

	showhide($('.teachers_show_hide'), '.teachers');
});

