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
