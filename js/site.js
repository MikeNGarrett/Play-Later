(function($, $window, $document, $body, Site) {
	'use strict';
	$.extend(Site, {
		// DOM ready code
		init: function() {
			// Unversal functionality
			this.play_later();
		},
		// window on load code
		load: function() {
		},
		play_later: function () {
			var working = false;
			$('.play-later').click(function(event) {
				event.preventDefault();
				if( working ) {
					return;
				}
				working = true;
				var $this = $(this);
				$(this).css('color', '#CECECE').text('Adding Album');

				var albumID = $(this).data('album');

				$.ajax({
					url: 'spotify/addTracks.php',
					type: 'GET',
					data: { album: albumID },
					success: function(data) {
						$this.parent().find('img').css('opacity', '0.3');
						$this.hide().before('<p class="success">'+data+'</p>');
						working = false;
					},
					error: function(data) {
						$this.prepend('<p class="error">Error: '+data.statusText+'. Conact <a href="https://twitter.com/mikengarrett" target="_blank">Mike</a></p>');
						working = false;
					}
				});
			});

		}
	});

	// Make our namespace globally accessible
	window.Site = Site;

	// Run initialization script on DOM ready
	$document.ready(function ( ) {
		Site.init();
	});

	// Defer scripts to window on load event
	$window.on('load', function ( ) {
		Site.load();
	});

})(jQuery, jQuery(window), jQuery(document), jQuery(document.body), window.Site || {});
