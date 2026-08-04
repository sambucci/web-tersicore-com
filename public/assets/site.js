/* Fleet external-links handler. Lives in a file and loads with src: the
   Content-Security-Policy refuses inline script, so an inline block would die
   silently. Off-site links open in a new tab per the fleet standing choice. */
(function () {
  'use strict';
  var host = window.location.hostname;
  var links = document.querySelectorAll('a[href^="http://"], a[href^="https://"], a[href^="//"]');
  for (var i = 0; i < links.length; i++) {
    var a = links[i];
    if (a.hostname && a.hostname !== host) {
      a.setAttribute('target', '_blank');
      a.setAttribute('rel', 'noopener noreferrer');
    }
  }
})();
