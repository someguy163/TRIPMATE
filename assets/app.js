// Loading feedback for every action: a bar on top while a page loads or changes, a "loading" screen when that is slow,
// a spinner on the pressed button, and no double submits.
(function () {
	const bar = document.getElementById('topbar'), screen = document.getElementById('pageloader');
	let slow;
	const busy = el => { if (el) { el.classList.add('busy'); el.setAttribute('aria-busy', 'true'); } };

	// a page change started: run the bar, and if it is still going after 0.7s, cover the page with the loading screen
	const start = () => {
		if (bar) {
			bar.classList.remove('run', 'done');
			void bar.offsetWidth; // restart the animation if it was already running
			bar.classList.add('run');
		}
		clearTimeout(slow);
		slow = setTimeout(() => { if (screen) screen.hidden = false; }, 700);
	};
	// this page finished loading: fill the bar up and let it fade
	const finish = () => {
		clearTimeout(slow);
		if (screen) screen.hidden = true;
		if (!bar) return;
		bar.classList.remove('run');
		bar.classList.add('done');
		setTimeout(() => bar.classList.remove('done'), 800);
	};
	const reset = () => {
		clearTimeout(slow);
		if (screen) screen.hidden = true;
		if (bar) bar.classList.remove('run', 'done');
		document.querySelectorAll('.busy').forEach(el => { el.classList.remove('busy'); el.removeAttribute('aria-busy'); });
		document.querySelectorAll('form[data-sending]').forEach(f => delete f.dataset.sending);
	};

	if (document.readyState === 'complete') finish(); else window.addEventListener('load', finish);

	document.addEventListener('submit', e => {
		const f = e.target;
		if (e.defaultPrevented) return;                         // cancelled by confirm(), or handled with fetch
		if (f.dataset.sending) { e.preventDefault(); return; }  // a second click / Enter while the first is still going
		f.dataset.sending = '1';
		busy(e.submitter || f.querySelector('button'));
		start();
	});

	document.addEventListener('click', e => {
		const a = e.target.closest('a[href]');
		if (!a || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		if (a.target === '_blank' || a.hasAttribute('download') || a.getAttribute('href').charAt(0) === '#') return;
		if (a.classList.contains('btn')) busy(a);
		start();
	});

	// the back button can restore this page from cache with the spinner / loading screen still on
	window.addEventListener('pageshow', e => { if (e.persisted) reset(); });

	// for code that submits a form itself (form.submit() does not fire a submit event)
	window.tmLoading = { start, busy, form(f) { f.dataset.sending = '1'; busy(f.querySelector('button')); start(); } };
})();

// installable app: a do-nothing service worker (the browsers want one before they offer "install"),
// and the install button on the home page, shown only when the browser says it can install
(function () {
	if ('serviceWorker' in navigator) navigator.serviceWorker.register(new URL('../sw.js', document.currentScript.src)).catch(() => {});
	let ev;
	window.addEventListener('beforeinstallprompt', e => {
		e.preventDefault(); ev = e;
		const b = document.getElementById('installBtn');
		if (b) { b.hidden = false; b.onclick = () => { b.hidden = true; ev.prompt(); }; }
	});
})();

// light / dark: follows the device until the person picks one with the top bar button (kept in this browser)
(function () {
	const root = document.documentElement, btn = document.getElementById('themeBtn'), device = matchMedia('(prefers-color-scheme: dark)');
	const now = () => root.dataset.theme || (device.matches ? 'dark' : 'light');
	const paint = () => {
		const t = now();
		document.querySelectorAll('meta[name=theme-color]').forEach(m => m.remove()); // the browser bar colour follows the page
		const m = document.createElement('meta'); m.name = 'theme-color'; m.content = t === 'dark' ? '#0E1519' : '#F2F6F7'; document.head.append(m);
		if (btn) { const l = t === 'dark' ? '일반 모드로 바꾸기' : '다크 모드로 바꾸기'; btn.textContent = t === 'dark' ? '☀️' : '🌙'; btn.setAttribute('aria-label', l); btn.title = l; }
		document.dispatchEvent(new Event('themechange')); // the map pins take their colours from the theme
	};
	if (btn) btn.onclick = () => { root.dataset.theme = now() === 'dark' ? 'light' : 'dark'; try { localStorage.setItem('theme', root.dataset.theme); } catch (_) {} paint(); };
	device.addEventListener('change', paint);
	paint();
})();
