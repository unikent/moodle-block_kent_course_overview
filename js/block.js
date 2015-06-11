$(function() {
	// Show/hide.
	$('.teachers_show_hide').click(function() {
		if ($(this).hasClass('hide')) {
			var _this = this;
			$(this).siblings('.teachers').stop().slideUp('fast', function() {
				$(_this).removeClass('hide');
			});
		} else {
			$(this).addClass('hide');
			$(this).siblings('.teachers').stop().slideDown('fast');
		}
	});
});