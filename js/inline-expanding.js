/*
 * inline-expanding.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/inline-expanding.js
 *
 * Released under the MIT license
 * Copyright (c) 2012-2013 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/inline-expanding.js';
 *
 */

$(document).ready(function(){
	'use strict';

	var DEFAULT_MAX = 5;  // default maximum image loads
	var inline_expand_post = function() {
		var link = this.getElementsByTagName('a');

		var loadingQueue = (function () {
			var MAX_IMAGES = localStorage.inline_expand_max || DEFAULT_MAX;   // maximum number of images to load concurrently, 0 to disable
			var loading = 0;                                                  // number of images that is currently loading
			var waiting = [];                                                 // waiting queue

			var enqueue = function (ele) {
				waiting.push(ele);
			};
			var dequeue = function () {
				return waiting.shift();
			};
			var update = function() {
				var ele;
				while (loading < MAX_IMAGES || MAX_IMAGES === 0) {
					ele = dequeue();
					if (ele) {
						++loading;
						ele.deferred.resolve();
					} else {
						return;
					}
				}
			};
			return {
				remove: function (ele) {
					var i = waiting.indexOf(ele);
					if (i > -1) {
						waiting.splice(i, 1);
					}
					if ($(ele).data('imageLoading') === 'true') {
						$(ele).data('imageLoading', 'false');
						clearTimeout(ele.timeout);
						--loading;
					}
				},
				add: function (ele) {
					ele.deferred = $.Deferred();
					ele.deferred.done(function () {
						var $loadstart = $.Deferred();
						var thumb = ele.childNodes[0];
						var img = ele.childNodes[1];

						var onLoadStart = function (img) {
							if (img.naturalWidth) {
								$loadstart.resolve(img, thumb);
							} else {
								return (ele.timeout = setTimeout(onLoadStart, 30, img));
							}
						};

						$(img).one('load', function () {
							$.when($loadstart).done(function () {
								//  once fully loaded, update the waiting queue
								--loading;
								$(ele).data('imageLoading', 'false');
								update();
							});
						});
						$loadstart.done(function (img, thumb) {
							thumb.style.display = 'none';
							img.style.display = '';
						});

						img.setAttribute('src', ele.href);
						$(ele).data('imageLoading', 'true');
						ele.timeout = onLoadStart(img);
					});

					if (loading < MAX_IMAGES || MAX_IMAGES === 0) {
						++loading;
						ele.deferred.resolve();
					} else {
						enqueue(ele);
					}

				}
			};
		})();

		var getMediaUrl = function(a) {
			var href = a.getAttribute('href') || '';
			var vMatch = href.match(/[\?&]v=([^&]+)/);
			if (vMatch) {
				return decodeURIComponent(vMatch[1]);
			}
			return href;
		};

		var isLoopEnabled = function() {
			if (typeof window.getVichanOption === 'function') {
				return window.getVichanOption('loop_media', true);
			}
			try {
				var v = localStorage.getItem('vichan_opt_loop_media');
				if (v !== null) return v === '1' || v === 'true';
			} catch (e) {}
			return true;
		};

		var getVolumeSetting = function() {
			var vol = 1.0;
			if (typeof window.getVichanOption === 'function') {
				vol = window.getVichanOption('media_volume', 100) / 100;
			} else {
				try {
					var v = localStorage.getItem('vichan_opt_media_volume');
					if (v !== null) vol = parseInt(v, 10) / 100;
				} catch (e) {}
			}
			if (isNaN(vol) || vol < 0) return 0.0;
			if (vol > 1.0) return 1.0;
			return vol;
		};

		var setupMediaLink = function(linkEl) {
			linkEl.onclick = function(e) {
				if (e.which == 2 || e.ctrlKey || e.metaKey || e.shiftKey) // open in new tab
					return true;

				e.preventDefault();
				e.stopPropagation();

				var curThumb = this.querySelector('.post-image') || this.querySelector('img, video') || this.childNodes[0];
				if (curThumb && curThumb.classList && curThumb.classList.contains('hidden'))
					return false;

				if (!$(this).data('mediaExpanded')) {
					$(this).data('mediaExpanded', 'true');
					if (curThumb) curThumb.style.display = 'none';

					var url = getMediaUrl(this);
					var pathname = this.pathname || url;
					var isVideo = /\.(webm|mp4|mkv)(?:$|[?#])/i.test(url) || /\.(webm|mp4|mkv)(?:$|[?#])/i.test(pathname);
					var isAudio = /\.(mp3|ogg)(?:$|[?#])/i.test(url) || /\.(mp3|ogg)(?:$|[?#])/i.test(pathname);

					var loop = isLoopEnabled();
					var vol = getVolumeSetting();

					var container = document.createElement('div');
					container.className = isVideo ? 'inline-video-container' : 'inline-audio-container';
					container.style.display = 'inline-block';
					container.style.verticalAlign = 'top';
					container.style.margin = '4px 0';

					var collapseBar = document.createElement('div');
					collapseBar.className = 'media-collapse-bar';
					collapseBar.style.marginBottom = '6px';
					collapseBar.style.display = 'flex';
					collapseBar.style.alignItems = 'center';
					collapseBar.style.gap = '6px';

					var collapseBtn = document.createElement('a');
					collapseBtn.href = 'javascript:void(0)';
					collapseBtn.className = isVideo ? 'video-collapse-btn' : 'audio-collapse-btn';
					collapseBtn.style.fontSize = '85%';
					collapseBtn.style.fontWeight = 'bold';
					collapseBtn.style.cursor = 'pointer';
					collapseBtn.style.textDecoration = 'none';
					collapseBtn.innerHTML = '<img src="' + (window.configRoot || '/') + 'static/collapse.gif" alt="[-]" style="vertical-align:middle;margin-right:4px;">[ Collapse ' + (isVideo ? 'Video' : 'Audio') + ' ]';
					collapseBar.appendChild(collapseBtn);

					var media;
					if (isVideo) {
						media = document.createElement('video');
						media.src = url;
						media.controls = true;
						media.autoplay = true;
						media.loop = loop;
						media.volume = vol;
						media.style.display = 'block';
						media.style.maxWidth = '100%';
						media.style.maxHeight = '85vh';
						media.style.borderRadius = '2px';
					} else {
						media = document.createElement('audio');
						media.src = url;
						media.controls = true;
						media.autoplay = true;
						media.loop = loop;
						media.volume = vol;
						media.style.display = 'block';
						media.style.width = '100%';
						media.style.maxWidth = '450px';
					}

					// Loop seamlessly on ended event
					media.addEventListener('ended', function() {
						if (isLoopEnabled()) {
							this.currentTime = 0;
							this.play().catch(function() {});
						}
					});

					container.appendChild(collapseBar);
					container.appendChild(media);
					this.parentNode.insertBefore(container, this.nextSibling);

					var self = this;
					var collapse = function() {
						try {
							media.pause();
						} catch (err) {}
						container.remove();
						if (curThumb) curThumb.style.display = '';
						$(self).removeData('mediaExpanded');
					};

					collapseBtn.onclick = function(ev) {
						ev.preventDefault();
						ev.stopPropagation();
						collapse();
					};

					var playPromise = media.play();
					if (playPromise !== undefined) {
						playPromise.catch(function() {
							if (isVideo) {
								media.muted = true;
								media.play().catch(function() {});
							}
						});
					}
				}
				return false;
			};
		};

		for (var i = 0; i < link.length; i++) {
			var a = link[i];
			var thumb = a.querySelector('.post-image') || (a.childNodes && a.childNodes[0] && a.childNodes[0].className && a.childNodes[0].className.match(/post-image/) ? a.childNodes[0] : null);
			if (!thumb) continue;

			var mediaUrl = getMediaUrl(a);
			var pathname = a.pathname || mediaUrl;
			var isVideo = /\.(webm|mp4|mkv)(?:$|[?#])/i.test(mediaUrl) || /\.(webm|mp4|mkv)(?:$|[?#])/i.test(pathname);
			var isAudio = /\.(mp3|ogg)(?:$|[?#])/i.test(mediaUrl) || /\.(mp3|ogg)(?:$|[?#])/i.test(pathname);

			if (isVideo || isAudio) {
				setupMediaLink(a);
				continue;
			}


			if (!a.className.match(/file/)) {
				a.onclick = function(e) {
					var img, post_body, still_open, canvas, scroll;
					var thumb = this.querySelector('.post-image') || this.childNodes[0];
					var padding = 5;
					var boardlist = $('.boardlist')[0];
					

					if (thumb.className == 'hidden')
						return false;
					if (e.which == 2 || e.ctrlKey) //  open in new tab
						return true;
					if (!$(this).data('expanded')) {

						if (~this.parentNode.className.indexOf('multifile'))
							$(this).data('width', this.parentNode.style.width);

						this.parentNode.removeAttribute('style');
						$(this).data('expanded', 'true');

						if (thumb.tagName === 'CANVAS') {
							canvas = thumb;
							thumb = this.querySelector('img.post-image') || thumb.nextElementSibling || thumb.nextSibling;
							this.removeChild(canvas);
							if (thumb && thumb.dataset) delete thumb.dataset.gifFrozen;
						}

						thumb.style.opacity = '0.4';
						thumb.style.filter = 'alpha(opacity=40)';

						img = document.createElement('img');
						img.className = 'full-image';
						img.style.display = 'none';
						img.setAttribute('alt', 'Fullsized image');
						this.appendChild(img);

						loadingQueue.add(this);
					} else {
						loadingQueue.remove(this);

						scroll = false;

						//  scroll to thumb if not triggered by 'shrink all image'
						if (e.target.className == 'full-image') {
							scroll = true;
						}

						if (~this.parentNode.className.indexOf('multifile'))
							this.parentNode.style.width = $(this).data('width');

						thumb.style.opacity = '';
						thumb.style.display = '';
						var fullImg = this.querySelector('.full-image');
						if (fullImg) fullImg.remove();
						else if (thumb.nextSibling) this.removeChild(thumb.nextSibling);
						$(this).removeData('expanded');
						delete thumb.style.filter;


						//  do the scrolling after page reflow
						if (scroll) {
							post_body = $(thumb).parentsUntil('form > div').last();

							//  on multifile posts, determin how many other images are still expanded
							still_open = post_body.find('.post-image').filter(function(){
								return $(this).parent().data('expanded') == 'true';
							}).length;

							//  deal with differnt boards' menu styles
							if ($(boardlist).css('position') == 'fixed')
								padding += boardlist.getBoundingClientRect().height;

							if (still_open > 0) {
								if (thumb.getBoundingClientRect().top - padding < 0)
									$(document).scrollTop($(thumb).parent().parent().offset().top - padding);
							} else {
								if (post_body[0].getBoundingClientRect().top - padding < 0)
									$(document).scrollTop(post_body.offset().top - padding);
							}
						}

						if ((localStorage.no_animated_gif === 'true' || (typeof window.getVichanOption === 'function' && !window.getVichanOption('animate_gif', true))) && typeof window.unanimate_gif === 'function') {
							window.unanimate_gif(thumb);
						}
					}
					return false;
				};
			}
		}
	};

	//  setting up user option
	if (window.Options && Options.get_tab('general')) {
		Options.extend_tab('general', '<span id="inline-expand-max">'+ _('Number of simultaneous image downloads (0 to disable): ') + 
										'<input type="number" step="1" min="0" size="4"></span>');
		$('#inline-expand-max input')
			.css('width', '50px')
			.val(localStorage.inline_expand_max || DEFAULT_MAX)
			.on('change', function (e) {
				// validation in case some fucktard tries to enter a negative floating point number
				var n = parseInt(e.target.value);
				var val = (n<0) ? 0 : n;

				localStorage.inline_expand_max = val;
			});
	}

	if (window.jQuery) {
		$('div[id^="thread_"]').each(inline_expand_post);

		// allow to work with auto-reload.js, etc.
		$(document).on('new_post', function(e, post) {
			inline_expand_post.call(post);
		});
	} else {
		inline_expand_post.call(document);
	}
});
