(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    const media = event.target.closest('.publication-youtube-card__media');
    if (!media || media.dataset.playing === '1') return;

    let videoId = '';
    try {
      const url = new URL(media.href, window.location.href);
      if (!['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'].includes(url.hostname)) return;
      videoId = url.hostname === 'youtu.be'
        ? url.pathname.replace(/^\/+/, '').split('/')[0]
        : url.searchParams.get('v') || '';
    } catch (error) {
      return;
    }

    if (!/^[A-Za-z0-9_-]{11}$/.test(videoId)) return;
    event.preventDefault();
    media.dataset.playing = '1';

    const iframe = document.createElement('iframe');
    iframe.src = 'https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1';
    iframe.title = media.getAttribute('aria-label') || 'Film YouTube';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    iframe.allowFullscreen = true;
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    media.replaceChildren(iframe);
  });
})();
