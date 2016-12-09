(function($) {

	'use strict';

	if (typeof _mncf7 == 'undefined' || _mncf7 === null) {
		return;
	}

	_mncf7 = $.extend({
		cached: 0
	}, _mncf7);

	$.fn.mncf7InitForm = function() {
		this.ajaxForm({
			beforeSubmit: function(arr, $form, options) {
				$form.mncf7ClearResponseOutput();
				$form.find('[aria-invalid]').attr('aria-invalid', 'false');
				$form.find('img.ajax-loader').css({ visibility: 'visible' });
				return true;
			},
			beforeSerialize: function($form, options) {
				$form.find('[placeholder].placeheld').each(function(i, n) {
					$(n).val('');
				});
				return true;
			},
			data: { '_mncf7_is_ajax_call': 1 },
			dataType: 'json',
			success: $.mncf7AjaxSuccess,
			error: function(xhr, status, error, $form) {
				var e = $('<div class="ajax-error"></div>').text(error.message);
				$form.after(e);
			}
		});

		if (_mncf7.cached) {
			this.mncf7OnloadRefill();
		}

		this.mncf7ToggleSubmit();

		this.find('.mncf7-submit').mncf7AjaxLoader();

		this.find('.mncf7-acceptance').click(function() {
			$(this).closest('form').mncf7ToggleSubmit();
		});

		this.find('.mncf7-exclusive-checkbox').mncf7ExclusiveCheckbox();

		this.find('.mncf7-list-item.has-free-text').mncf7ToggleCheckboxFreetext();

		this.find('[placeholder]').mncf7Placeholder();

		if (_mncf7.jqueryUi && ! _mncf7.supportHtml5.date) {
			this.find('input.mncf7-date[type="date"]').each(function() {
				$(this).datepicker({
					dateFormat: 'yy-mm-dd',
					minDate: new Date($(this).attr('min')),
					maxDate: new Date($(this).attr('max'))
				});
			});
		}

		if (_mncf7.jqueryUi && ! _mncf7.supportHtml5.number) {
			this.find('input.mncf7-number[type="number"]').each(function() {
				$(this).spinner({
					min: $(this).attr('min'),
					max: $(this).attr('max'),
					step: $(this).attr('step')
				});
			});
		}

		this.find('.mncf7-character-count').mncf7CharacterCount();

		this.find('.mncf7-validates-as-url').change(function() {
			$(this).mncf7NormalizeUrl();
		});

		this.find('.mncf7-recaptcha').mncf7Recaptcha();
	};

	$.mncf7AjaxSuccess = function(data, status, xhr, $form) {
		if (! $.isPlainObject(data) || $.isEmptyObject(data)) {
			return;
		}

		var $responseOutput = $form.find('div.mncf7-response-output');

		$form.mncf7ClearResponseOutput();

		$form.find('.mncf7-form-control').removeClass('mncf7-not-valid');
		$form.removeClass('invalid spam sent failed');

		if (data.captcha) {
			$form.mncf7RefillCaptcha(data.captcha);
		}

		if (data.quiz) {
			$form.mncf7RefillQuiz(data.quiz);
		}

		if (data.invalids) {
			$.each(data.invalids, function(i, n) {
				$form.find(n.into).mncf7NotValidTip(n.message);
				$form.find(n.into).find('.mncf7-form-control').addClass('mncf7-not-valid');
				$form.find(n.into).find('[aria-invalid]').attr('aria-invalid', 'true');
			});

			$responseOutput.addClass('mncf7-validation-errors');
			$form.addClass('invalid');

			$(data.into).trigger('mncf7:invalid');
			$(data.into).trigger('invalid.mncf7'); // deprecated

		} else if (1 == data.spam) {
			$form.find('[name="g-recaptcha-response"]').each(function() {
				if ('' == $(this).val()) {
					var $recaptcha = $(this).closest('.mncf7-form-control-wrap');
					$recaptcha.mncf7NotValidTip(_mncf7.recaptcha.messages.empty);
				}
			});

			$responseOutput.addClass('mncf7-spam-blocked');
			$form.addClass('spam');

			$(data.into).trigger('mncf7:spam');
			$(data.into).trigger('spam.mncf7'); // deprecated

		} else if (1 == data.mailSent) {
			$responseOutput.addClass('mncf7-mail-sent-ok');
			$form.addClass('sent');

			if (data.onSentOk) {
				$.each(data.onSentOk, function(i, n) { eval(n) });
			}

			$(data.into).trigger('mncf7:mailsent');
			$(data.into).trigger('mailsent.mncf7'); // deprecated

		} else {
			$responseOutput.addClass('mncf7-mail-sent-ng');
			$form.addClass('failed');

			$(data.into).trigger('mncf7:mailfailed');
			$(data.into).trigger('mailfailed.mncf7'); // deprecated
		}

		if (data.onSubmit) {
			$.each(data.onSubmit, function(i, n) { eval(n) });
		}

		$(data.into).trigger('mncf7:submit');
		$(data.into).trigger('submit.mncf7'); // deprecated

		if (1 == data.mailSent) {
			$form.resetForm();
		}

		$form.find('[placeholder].placeheld').each(function(i, n) {
			$(n).val($(n).attr('placeholder'));
		});

		$responseOutput.append(data.message).slideDown('fast');
		$responseOutput.attr('role', 'alert');

		$.mncf7UpdateScreenReaderResponse($form, data);
	};

	$.fn.mncf7ExclusiveCheckbox = function() {
		return this.find('input:checkbox').click(function() {
			var name = $(this).attr('name');
			$(this).closest('form').find('input:checkbox[name="' + name + '"]').not(this).prop('checked', false);
		});
	};

	$.fn.mncf7Placeholder = function() {
		if (_mncf7.supportHtml5.placeholder) {
			return this;
		}

		return this.each(function() {
			$(this).val($(this).attr('placeholder'));
			$(this).addClass('placeheld');

			$(this).focus(function() {
				if ($(this).hasClass('placeheld'))
					$(this).val('').removeClass('placeheld');
			});

			$(this).blur(function() {
				if ('' == $(this).val()) {
					$(this).val($(this).attr('placeholder'));
					$(this).addClass('placeheld');
				}
			});
		});
	};

	$.fn.mncf7AjaxLoader = function() {
		return this.each(function() {
			var loader = $('<img class="ajax-loader" />')
				.attr({ src: _mncf7.loaderUrl, alt: _mncf7.sending })
				.css('visibility', 'hidden');

			$(this).after(loader);
		});
	};

	$.fn.mncf7ToggleSubmit = function() {
		return this.each(function() {
			var form = $(this);

			if (this.tagName.toLowerCase() != 'form') {
				form = $(this).find('form').first();
			}

			if (form.hasClass('mncf7-acceptance-as-validation')) {
				return;
			}

			var submit = form.find('input:submit');
			if (! submit.length) return;

			var acceptances = form.find('input:checkbox.mncf7-acceptance');
			if (! acceptances.length) return;

			submit.removeAttr('disabled');
			acceptances.each(function(i, n) {
				n = $(n);
				if (n.hasClass('mncf7-invert') && n.is(':checked')
				|| ! n.hasClass('mncf7-invert') && ! n.is(':checked')) {
					submit.attr('disabled', 'disabled');
				}
			});
		});
	};

	$.fn.mncf7ToggleCheckboxFreetext = function() {
		return this.each(function() {
			var $wrap = $(this).closest('.mncf7-form-control');

			if ($(this).find(':checkbox, :radio').is(':checked')) {
				$(this).find(':input.mncf7-free-text').prop('disabled', false);
			} else {
				$(this).find(':input.mncf7-free-text').prop('disabled', true);
			}

			$wrap.find(':checkbox, :radio').change(function() {
				var $cb = $('.has-free-text', $wrap).find(':checkbox, :radio');
				var $freetext = $(':input.mncf7-free-text', $wrap);

				if ($cb.is(':checked')) {
					$freetext.prop('disabled', false).focus();
				} else {
					$freetext.prop('disabled', true);
				}
			});
		});
	};

	$.fn.mncf7CharacterCount = function() {
		return this.each(function() {
			var $count = $(this);
			var name = $count.attr('data-target-name');
			var down = $count.hasClass('down');
			var starting = parseInt($count.attr('data-starting-value'), 10);
			var maximum = parseInt($count.attr('data-maximum-value'), 10);
			var minimum = parseInt($count.attr('data-minimum-value'), 10);

			var updateCount = function($target) {
				var length = $target.val().length;
				var count = down ? starting - length : length;
				$count.attr('data-current-value', count);
				$count.text(count);

				if (maximum && maximum < length) {
					$count.addClass('too-long');
				} else {
					$count.removeClass('too-long');
				}

				if (minimum && length < minimum) {
					$count.addClass('too-short');
				} else {
					$count.removeClass('too-short');
				}
			};

			$count.closest('form').find(':input[name="' + name + '"]').each(function() {
				updateCount($(this));

				$(this).keyup(function() {
					updateCount($(this));
				});
			});
		});
	};

	$.fn.mncf7NormalizeUrl = function() {
		return this.each(function() {
			var val = $.trim($(this).val());

			if (val && ! val.match(/^[a-z][a-z0-9.+-]*:/i)) { // check the scheme part
				val = val.replace(/^\/+/, '');
				val = 'http://' + val;
			}

			$(this).val(val);
		});
	};

	$.fn.mncf7NotValidTip = function(message) {
		return this.each(function() {
			var $into = $(this);

			$into.find('span.mncf7-not-valid-tip').remove();
			$into.append('<span role="alert" class="mncf7-not-valid-tip">' + message + '</span>');

			if ($into.is('.use-floating-validation-tip *')) {
				$('.mncf7-not-valid-tip', $into).mouseover(function() {
					$(this).mncf7FadeOut();
				});

				$(':input', $into).focus(function() {
					$('.mncf7-not-valid-tip', $into).not(':hidden').mncf7FadeOut();
				});
			}
		});
	};

	$.fn.mncf7FadeOut = function() {
		return this.each(function() {
			$(this).animate({
				opacity: 0
			}, 'fast', function() {
				$(this).css({'z-index': -100});
			});
		});
	};

	$.fn.mncf7OnloadRefill = function() {
		return this.each(function() {
			var url = $(this).attr('action');

			if (0 < url.indexOf('#')) {
				url = url.substr(0, url.indexOf('#'));
			}

			var id = $(this).find('input[name="_mncf7"]').val();
			var unitTag = $(this).find('input[name="_mncf7_unit_tag"]').val();

			$.getJSON(url,
				{ _mncf7_is_ajax_call: 1, _mncf7: id, _mncf7_request_ver: $.now() },
				function(data) {
					if (data && data.captcha) {
						$('#' + unitTag).mncf7RefillCaptcha(data.captcha);
					}

					if (data && data.quiz) {
						$('#' + unitTag).mncf7RefillQuiz(data.quiz);
					}
				}
			);
		});
	};

	$.fn.mncf7RefillCaptcha = function(captcha) {
		return this.each(function() {
			var form = $(this);

			$.each(captcha, function(i, n) {
				form.find(':input[name="' + i + '"]').clearFields();
				form.find('img.mncf7-captcha-' + i).attr('src', n);
				var match = /([0-9]+)\.(png|gif|jpeg)$/.exec(n);
				form.find('input:hidden[name="_mncf7_captcha_challenge_' + i + '"]').attr('value', match[1]);
			});
		});
	};

	$.fn.mncf7RefillQuiz = function(quiz) {
		return this.each(function() {
			var form = $(this);

			$.each(quiz, function(i, n) {
				form.find(':input[name="' + i + '"]').clearFields();
				form.find(':input[name="' + i + '"]').siblings('span.mncf7-quiz-label').text(n[0]);
				form.find('input:hidden[name="_mncf7_quiz_answer_' + i + '"]').attr('value', n[1]);
			});
		});
	};

	$.fn.mncf7ClearResponseOutput = function() {
		return this.each(function() {
			$(this).find('div.mncf7-response-output').hide().empty().removeClass('mncf7-mail-sent-ok mncf7-mail-sent-ng mncf7-validation-errors mncf7-spam-blocked').removeAttr('role');
			$(this).find('span.mncf7-not-valid-tip').remove();
			$(this).find('img.ajax-loader').css({ visibility: 'hidden' });
		});
	};

	$.fn.mncf7Recaptcha = function() {
		return this.each(function() {
			var events = 'mncf7:spam mncf7:mailsent mncf7:mailfailed';
			$(this).closest('div.mncf7').on(events, function(e) {
				if (recaptchaWidgets && grecaptcha) {
					$.each(recaptchaWidgets, function(index, value) {
						grecaptcha.reset(value);
					});
				}
			});
		});
	};

	$.mncf7UpdateScreenReaderResponse = function($form, data) {
		$('.mncf7 .screen-reader-response').html('').attr('role', '');

		if (data.message) {
			var $response = $form.siblings('.screen-reader-response').first();
			$response.append(data.message);

			if (data.invalids) {
				var $invalids = $('<ul></ul>');

				$.each(data.invalids, function(i, n) {
					if (n.idref) {
						var $li = $('<li></li>').append($('<a></a>').attr('href', '#' + n.idref).append(n.message));
					} else {
						var $li = $('<li></li>').append(n.message);
					}

					$invalids.append($li);
				});

				$response.append($invalids);
			}

			$response.attr('role', 'alert').focus();
		}
	};

	$.mncf7SupportHtml5 = function() {
		var features = {};
		var input = document.createElement('input');

		features.placeholder = 'placeholder' in input;

		var inputTypes = ['email', 'url', 'tel', 'number', 'range', 'date'];

		$.each(inputTypes, function(index, value) {
			input.setAttribute('type', value);
			features[value] = input.type !== 'text';
		});

		return features;
	};

	$(function() {
		_mncf7.supportHtml5 = $.mncf7SupportHtml5();
		$('div.mncf7 > form').mncf7InitForm();
	});

})(jQuery);
