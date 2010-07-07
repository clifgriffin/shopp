/*!
 * editor_plugin.js - Shopp TinyMCE Plugin
 * Copyright © 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */
(function() {
	// Load plugin specific language pack
	// tinymce.PluginManager.requireLangPack('Shopp');

	tinymce.create('tinymce.plugins.Shopp', {
		init : function(ed, url) {
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
				title : ed.getLang('Shopp.desc'),
				cmd : 'mceShopp',
				image : url + '/shopp.png'
			});

		}
	});

	// Register plugin
	tinymce.PluginManager.add('Shopp', tinymce.plugins.Shopp);

})();
if ( typeof( tinyMCE ) != 'undefined' && typeof(ShoppDialog) != 'undefined')
	tinyMCE.addI18n(tinyMCEPreInit.mceInit.language + '.Shopp', ShoppDialog);

// tinyMCE.addI18n('en.Shopp',{
// 	title : 'Insert from Shopp…',
// 	desc : 'Insert a product or category from Shopp…'
// });
