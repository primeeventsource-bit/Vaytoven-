// Google Ads base tag, shared by Blade pages and the Capacitor web interface.
(() => {
    if (window.vaytovenGoogleTagInitialized) return;
    window.vaytovenGoogleTagInitialized = true;

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', 'AW-18384124631');

    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=AW-18384124631';
    document.head.appendChild(script);
})();
