/**
 * Shopp TinyMCE Plugin
 * @author Jonathan Davis
 * @copyright Copyright © 2008, Ingenesis Limited, All rights reserved.
 */
(function() {
	// Load plugin specific language pack
	tinymce.PluginManager.requireLangPack('Shopp');

	tinymce.create('tinymce.plugins.Shopp', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
			ed.addCommand('mceShopp', function() {
				ed.windowManager.open({
					file : url + '/dialog.php',
					width : 320,
					height : 200,
					inline : 1
				}, {
					plugin_url : url // Plugin absolute URL
				});
			});

			// Register button
			ed.addButton('Shopp', {
				title : 'Shopp.desc',
				cmd : 'mceShopp',
				image : url + '/shopp.png'
			});

		}
	});

	// Register plugin
	tinymce.PluginManager.add('Shopp', tinymce.plugins.Shopp);
})();