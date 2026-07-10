/**
 * Awesome Code Snippets Pro Max - Admin Scripts
 *
 * @package Awesome_Code_Snippets_Pro_Max
 */

/* global jQuery, wp, _, acspmAdmin */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Initialize CodeMirror editors
		if (typeof wp !== 'undefined' && wp.codeEditor) {
			initSnippetEditor();
			initHeaderFooterEditors();
		}

		// Show/hide custom hook field
		initCustomHookToggle();

		// Space-key activation for the role="button" status toggle
		initStatusToggleKeyboard();

		// Prevent duplicate submits (double-click / slow connection)
		initSubmitLock();
	});

	/**
	 * A link with role="button" is announced as a button, so screen reader and
	 * keyboard users expect Space to activate it. Native <a> only fires on Enter,
	 * so wire Space to follow the link.
	 */
	function initStatusToggleKeyboard() {
		$(document).on('keydown', '.acspm-status-toggle', function(e) {
			if (e.key === ' ' || e.key === 'Spacebar' || e.which === 32) {
				e.preventDefault();
				window.location = this.href;
			}
		});
	}

	/**
	 * Disable the submit button once a snippet/header-footer form is submitted so
	 * a double-click can't create duplicate snippets or double-save.
	 */
	function initSubmitLock() {
		$('form').on('submit', function() {
			var $submit = $(this).find('input[type="submit"], button[type="submit"]');
			if (!$submit.length) {
				return;
			}
			// Let the value still post, then lock it on the next tick.
			window.setTimeout(function() {
				$submit.prop('disabled', true).attr('aria-disabled', 'true');
			}, 0);
		});
	}

	/**
	 * Initialize the snippet code editor with mode switching
	 */
	function initSnippetEditor() {
		var $textarea = $('#snippet_code');
		if (!$textarea.length) {
			return;
		}

		var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
		editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
			mode: 'php',
			lineNumbers: true,
			lineWrapping: true,
			indentUnit: 4,
			tabSize: 4,
			indentWithTabs: true,
			extraKeys: {
				'Esc': function(cm) {
					cm.getInputField().blur();
				}
			}
		});

		var editor = wp.codeEditor.initialize($textarea, editorSettings);

		// Add keyboard trap escape hint below the editor
		$textarea.closest('td').append(
			$('<p class="description acspm-editor-hint">').text(
				acspmAdmin.i18n.editorEscapeHint
			)
		);

		// Update CodeMirror mode when code type changes
		$('#snippet_code_type').on('change', function() {
			var mode = 'php';
			switch ($(this).val()) {
				case 'js':
					mode = 'javascript';
					break;
				case 'css':
					mode = 'css';
					break;
				case 'php':
				default:
					mode = 'php';
					break;
			}
			editor.codemirror.setOption('mode', mode);
		});

		// Trigger initial mode set
		$('#snippet_code_type').trigger('change');
	}

	/**
	 * Initialize header/footer code editors
	 */
	function initHeaderFooterEditors() {
		var $header = $('#acspm_header_code');
		var $footer = $('#acspm_footer_code');

		if (!$header.length && !$footer.length) {
			return;
		}

		var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
		editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
			mode: 'htmlmixed',
			lineNumbers: true,
			lineWrapping: true,
			indentUnit: 4,
			tabSize: 4,
			indentWithTabs: true,
			extraKeys: {
				'Esc': function(cm) {
					cm.getInputField().blur();
				}
			}
		});

		if ($header.length) {
			wp.codeEditor.initialize($header, editorSettings);
			$header.after(
				$('<p class="description acspm-editor-hint">').text(
					acspmAdmin.i18n.editorEscapeHint
				)
			);
		}

		if ($footer.length) {
			wp.codeEditor.initialize($footer, editorSettings);
			$footer.after(
				$('<p class="description acspm-editor-hint">').text(
					acspmAdmin.i18n.editorEscapeHint
				)
			);
		}
	}

	/**
	 * Toggle custom hook field visibility with focus management
	 */
	function initCustomHookToggle() {
		$('#snippet_location').on('change', function() {
			var $row = $('.acspm-custom-hook-row');
			if ($(this).val() === 'custom') {
				$row.addClass('is-visible');
				$('#snippet_custom_hook').trigger('focus');
			} else {
				$row.removeClass('is-visible');
			}
		});
	}

})(jQuery);
