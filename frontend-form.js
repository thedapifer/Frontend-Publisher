window.JPry_Front_End_Form = window.JPry_Front_End_Form || {};

(function (window, document, $, app, undefined) {
	'use strict';

	// This is populated by PHP
	app.l10n = window.jpry_front_end_form_config || {};

	app.cache = function () {
		app.$ = {};
		app.$.form = $(document.getElementById('front-end-wiki-form'));
		app.$.edit_form = $(document.getElementById('wiki_action')).parent();
		app.$.submit = $('input[name="submit-cmb"]');
	};

	app.init = function () {
		// Store/cache our selectors
		app.cache();

		app.$.form.on('submit', app.form_submit);
		app.$.edit_form.on('submit', app.form_submit);

		// add cancel button to the form
		app.add_cancel_button();
	};

	app.add_cancel_button = function () {
		// add cancel button to the form
		app.$.cancel_button = $('<input type="button" />').attr({
			'value': app.l10n.cancel_button_text,
			'class': 'cancel-button',
		});

		app.$.submit.after(app.$.cancel_button);

		// bind click event for cancel button
		app.$.cancel_button.on('click', app.cancel_button_click);
	};

	app.cancel_button_click = function () {
		// redirect the user if the user wants to leave the page
		if (window.confirm(app.l10n.cancel_message)) {
			window.location = app.l10n.redirect_url;
		}
	};

	app.form_submit = function () {
		// disable submit button to avoid duplicate entries
		app.$.submit.attr('disabled', 'disabled');
	};

	$(document).ready(app.init);

})(window, document, jQuery, JPry_Front_End_Form);

