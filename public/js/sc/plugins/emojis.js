/**
 * SCEditor Paragraph Formatting Plugin
 * http://www.sceditor.com/
 *
 * Copyright (C) 2011-2024, Sam Clarke (samclarke.com)
 *
 * SCEditor is licensed under the MIT license:
 *	http://www.opensource.org/licenses/mit-license.php
 *
 * @fileoverview SCEditor Paragraph Formatting Plugin
 * @author Sam Clarke
 */
/*global EmojiMart*/
(function (sceditor) {
	'use strict';

	sceditor.plugins.emojis = function () {
		const base = this;

		/**
		 * Function for the exec and txtExec properties
		 *
		 * @param  {node} caller
		 * @private
		 */
		var emojisCmd = function (caller) {
			const editor = this,
				content = document.createElement('div'),
				emojis = editor.opts.emojis || [];

			content.className = "sceditor-emojis-div";

			if (!emojis.length) {
				const pickerOptions = { onEmojiSelect: handleSelect };
				const picker = new EmojiMart.Picker(pickerOptions);

				content.appendChild(picker);
			} else {
				sceditor.utils.each(emojis,
					function (_, emoji) {
						const emojiElem = document.createElement('span');

						emojiElem.className = 'sceditor-option';

						emojiElem.appendChild(document.createTextNode(emoji));

						emojiElem.addEventListener('click', function (e) {
							editor.closeDropDown(true);
							editor.insert(e.target.textContent);
							e.preventDefault();
						});

						content.appendChild(emojiElem);
					});
			}

			editor.createDropDown(caller, 'emojis', content);

			function handleSelect(emoji) {
				editor.insert(emoji.native);

				editor.closeDropDown(true);
			}
		};

		base.init = function () {
			this.commands.emojis = {
				exec: emojisCmd,
				txtExec: emojisCmd,
				tooltip: 'Insert emoji',
				shortcut: 'Ctrl+E'
			};
		};
	};
})(sceditor);
