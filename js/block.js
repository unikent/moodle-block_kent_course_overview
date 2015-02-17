$(function() {
	$('.course_adjust_visibility i').on('click', function() {
		var id = $(this).attr('data-id');
		var action = $(this).attr('data-action');

		$('.course_adjust_visibility i')
			.removeClass('fa-eye fa-eye-slash')
			.addClass('fa-spin fa-spinner');

		$.ajax({
			url: M.cfg.wwwroot + "/blocks/kent_course_overview/ajax/visibility.php",
			type: "POST",
			data: {
				'id': id,
				'action': action,
				'sesskey': M.cfg.sesskey
			},
			success: function() {
				$('.course_adjust_visibility i').removeClass('fa-spin fa-spinner');

				if (action == 'show') {
					$('.course_adjust_visibility i')
						.addClass('fa-eye')
						.attr('data-action', 'hide')
						.closest('.container')
							.removeClass('course_unavailable');
				} else {
					$('.course_adjust_visibility i')
						.addClass('fa-eye-slash')
						.attr('data-action', 'show')
						.closest('.container')
							.addClass('course_unavailable');
				}
			}
		});
	});


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