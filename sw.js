// Does nothing on purpose: no caching, every page comes from the server. Browsers only offer to install a site
// as an app when it has a service worker with a fetch handler.
self.addEventListener('fetch', () => {});
