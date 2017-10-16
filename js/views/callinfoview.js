/* global Marionette, Handlebars */

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

(function(OC, OCA, Marionette, Handlebars, $, _) {

	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var TEMPLATE =
		'<h3 class="room-name">{{displayName}}</h3>' +
		'{{#if showShareLink}}' +
		'	<div class="clipboardButton"><span class="icon icon-clippy"></span></div>' +
		'{{/if}}' +
		'{{#if canModerate}}' +
		'	<div>' +
		'		<input name="linkCheckbox" id="linkCheckbox" class="checkbox linkCheckbox" value="1" {{#if isPublic}} checked="checked"{{/if}} type="checkbox">' +
		'		<label for="linkCheckbox">' + t('spreed', 'Share link') + '</label>' +
		'		{{#if isPublic}}' +
		'			<div class="clipboardButton"><span class="icon icon-clippy"></span></div>' +
		'		{{/if}}' +
		'	</div>' +
		'{{/if}}';

	var CallInfoView  = Marionette.View.extend({

		tagName: 'div',

		template: Handlebars.compile(TEMPLATE),

		templateContext: function() {
			var canModerate = this.model.get('participantType') === 1 || this.model.get('participantType') === 2;
			return $.extend(this.model.toJSON(), {
				canModerate: canModerate,
				isPublic: this.model.get('type') === 3,
				showShareLink: !canModerate && this.model.get('type') === 3,
				isNameEditable: canModerate && this.model.get('type') !== 1,
				isDeletable: canModerate && (Object.keys(this.model.get('participants')).length > 2 || this.model.get('numGuests') > 0)
			});
		},

		ui: {
			'clipboardButton': '.clipboardButton',
			'renameButton': '.renameButton',
			'linkCheckbox': '.linkCheckbox'
		},

		events: {
			'change @ui.linkCheckbox': 'toggleLinkCheckbox'
		},

		modelEvents: {
			'change:displayName': function() {
				this.render();
			},
			'change:type': function() {
				this.render();
			}
		},

		onRender: function() {
			var roomURL = OC.generateUrl('/call/' + this.model.get('token')),
				completeURL = window.location.protocol + '//' + window.location.host + roomURL;

			this.ui.clipboardButton.attr('value', completeURL);
			this.ui.clipboardButton.attr('data-clipboard-text', completeURL);
			this.ui.clipboardButton.tooltip({
				placement: 'bottom',
				trigger: 'hover',
				title: t('spreed', 'Copy')
			});
			this.initClipboard();
		},

		toggleLinkCheckbox: function() {
			var shareLink = this.ui.linkCheckbox.attr('checked') === 'checked';

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.model.get('token') + '/public',
				type: shareLink ? 'POST' : 'DELETE',
				success: function() {
					OCA.SpreedMe.app.syncRooms();
				}
			});
		},

		initClipboard: function() {
			var clipboard = new Clipboard('.clipboardButton');
			clipboard.on('success', function(e) {
				var $input = $(e.trigger);
				$input.tooltip('hide')
					.attr('data-original-title', t('core', 'Copied!'))
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function() {
					$input.tooltip('hide')
						.attr('data-original-title', t('core', 'Copy'))
						.tooltip('fixTitle');
				}, 3000);
			});
			clipboard.on('error', function (e) {
				var $input = $(e.trigger);
				var actionMsg = '';
				if (/iPhone|iPad/i.test(navigator.userAgent)) {
					actionMsg = t('core', 'Not supported!');
				} else if (/Mac/i.test(navigator.userAgent)) {
					actionMsg = t('core', 'Press ⌘-C to copy.');
				} else {
					actionMsg = t('core', 'Press Ctrl-C to copy.');
				}

				$input.tooltip('hide')
					.attr('data-original-title', actionMsg)
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function () {
					$input.tooltip('hide')
						.attr('data-original-title', t('spreed', 'Copy'))
						.tooltip('fixTitle');
				}, 3000);
			});
		}
	});

	OCA.SpreedMe.Views.CallInfoView = CallInfoView;

})(OC, OCA, Marionette, Handlebars, $, _);
