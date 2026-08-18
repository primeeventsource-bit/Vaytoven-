{{--
    Browser and home-screen icons.

    Included by every layout that owns a <head> rather than left to the
    implicit /favicon.ico fetch, because the implicit fetch only covers the
    .ico — Safari never reads SVG icons, and iOS ignores everything except
    apple-touch-icon.

    The SVG is listed first and modern browsers prefer it: it is one file that
    stays sharp at every size, including the 128px icon a pinned tab uses.
    The .ico stays for the browsers that will not read SVG.
--}}
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#D63384">
