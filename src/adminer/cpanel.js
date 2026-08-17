'use strict';

const cpanelSession = /^\/cpsess\w+\//;

if (cpanelSession.test(location.pathname) && navigator.serviceWorker) {
	const tail = location.pathname.replace(cpanelSession, '/');
	// a path differing from this one in the token alone is this same Adminer in an earlier session
	const stale = url => {
		const path = new URL(url).pathname;
		return path != location.pathname && cpanelSession.test(path) && path.replace(cpanelSession, '/') == tail;
	};
	navigator.serviceWorker.getRegistrations().then(registrations => registrations.forEach(registration => stale(registration.scope) && registration.unregister()));
	// the cache is named after the Adminer version so all sessions share it, only the entries of the stale paths can go
	caches.keys().then(keys => keys.forEach(key => key.startsWith('adminer-') && caches.open(key).then(cache => cache.keys().then(requests => requests.forEach(request => stale(request.url) && cache.delete(request))))));
}
