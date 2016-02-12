(function($, $window, $document, $body, Site) {
	'use strict';
	$.extend(Site, {
		// DOM ready code
		init: function() {
			// Unversal functionality
			this.play_later();
			this.select();
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

				ga('send', {
				  hitType: 'event',
				  eventCategory: 'Release',
				  eventAction: 'Adding',
				  eventLabel: albumID
				});

				$.ajax({
					url: '/spotify/add-tracks/'+albumID+'/',
					type: 'GET',
//					data: { album: albumID },
					success: function(data) {
						$this.parent().find('img').css('opacity', '0.3');
						$this.hide().before('<p class="success">'+data+'</p>');
						working = false;
					},
					error: function(data) {
						$this.prepend('<p class="error">Error: '+data.statusText+'</p>');
						working = false;
					}
				});
			});
		},
		select: function() {
			$('#genres').select2({
				placeholder: "Select a genre",
				tags: true,
				tokenSeparators: [',', ' ']
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
