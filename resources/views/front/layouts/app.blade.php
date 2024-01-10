<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8">
    <title> @yield('title') </title>
    <!-- SEO Meta Tags-->
    <meta name="description" content="@yield('description')">
    <meta name="author" content="AG media">
    @stack('meta_tags')
    <!-- Viewport-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=2.0" />
    <!-- Favicon and Touch Icons-->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ config('settings.images_domain') . 'favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ config('settings.images_domain') . 'favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ config('settings.images_domain') . 'favicon-16x16.png' }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ config('settings.images_domain') . 'apple-touch-icon.png' }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ config('settings.images_domain') . 'favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ config('settings.images_domain') . 'favicon-16x16.png' }}">
    <link rel="mask-icon" href="{{ config('settings.images_domain') . 'safari-pinned-tab.svg' }}" color="#2d2224">
    <meta name="msapplication-TileColor" content="#2d2224">
    <meta name="theme-color" content="#ffffff">


<style>
    @import url('https://fonts.googleapis.com/css2?family=Merriweather:wght@300;@charset "UTF-8";

    *,
    *::before,
    *::after {
    box-sizing: border-box;
    }

    body {
    margin: 0;
    font-family: var(--cz-body-font-family);
    font-size: var(--cz-body-font-size);
    font-weight: var(--cz-body-font-weight);
    line-height: var(--cz-body-line-height);
    color: var(--cz-body-color);
    text-align: var(--cz-body-text-align);
    background-color: var(--cz-body-bg);
    -webkit-text-size-adjust: 100%;
    }

    .bckshelf {
        background-image: url(../media/img/slider-bck.png);
        height: 460px;
        width: 460px;
        text-align: center;
        background-position-y: 3px;
        padding-top: 20px;
        background-repeat: no-repeat;
        margin: 0 auto;
    }

    .bckshelf img {
        max-height: 380px;
        margin: 0 auto;
        margin-top: 10px;
    }

    @media (max-width: 500px) {
        .bckshelf {
            background-image: url(../media/img/slider-bck.png);
            height: 340px;
            width: 350px;
            text-align: center;
            background-position-y: 3px;
            padding-top: 10px;
            background-repeat: no-repeat;
            margin: 0 auto;
            background-position: center bottom;
            background-size: cover;
        }

        .bckshelf img {
            max-height: 280px;
            margin: 0 auto;
            margin-top: 10px;
        }
    }

    h2,
    .h1 {
        margin-top: 0;
        margin-bottom: 0.75rem;
        font-weight: 600;
        line-height: 1.2;
        color: #3d2c25;
    }

    .h1 {
        font-size: calc(1.375rem + 1.5vw);
    }

    h2 {
        font-size: calc(1.325rem + 0.9vw);
    }

    p {
        margin-top: 0;
        margin-bottom: 1rem;
    }

    ul {
        padding-left: 2rem;
    }

    ul {
        margin-top: 0;
        margin-bottom: 1rem;
    }

    a {
        color: var(--cz-link-color);
        text-decoration: none;
    }

    img {
        vertical-align: middle;
    }

    button {
        border-radius: 0;
    }

    input,
    button {
        margin: 0;
        font-family: inherit;
        font-size: inherit;
        line-height: inherit;
    }

    button {
        text-transform: none;
    }



    button,
    [type=button],
    [type=submit] {
        -webkit-appearance: button;
    }



    ::-moz-focus-inner {
        padding: 0;
        border-style: none;
    }

    ::-webkit-datetime-edit-fields-wrapper,
    ::-webkit-datetime-edit-text,
    ::-webkit-datetime-edit-minute,
    ::-webkit-datetime-edit-hour-field,
    ::-webkit-datetime-edit-day-field,
    ::-webkit-datetime-edit-month-field,
    ::-webkit-datetime-edit-year-field {
        padding: 0;
    }

    ::-webkit-inner-spin-button {
        height: auto;
    }

    ::-webkit-search-decoration {
        -webkit-appearance: none;
    }

    ::-webkit-color-swatch-wrapper {
        padding: 0;
    }

    ::-webkit-file-upload-button {
        font: inherit;
        -webkit-appearance: button;
    }

    ::file-selector-button {
        font: inherit;
        -webkit-appearance: button;
    }

    ::file-selector-button {
        font: inherit;
        -webkit-appearance: button;
    }

    .container {
        --cz-gutter-x: 1.875rem;
        --cz-gutter-y: 0;
        width: 100%;
        padding-right: calc(var(--cz-gutter-x) * 0.5);
        padding-left: calc(var(--cz-gutter-x) * 0.5);
        margin-right: auto;
        margin-left: auto;
    }

    @media (min-width: 500px) {
        .container {
            max-width: 100%;
        }
    }

    .form-control {
        display: block;
        width: 100%;
        padding: 0.625rem 1rem;
        font-size: 0.9375rem;
        font-weight: 400;
        line-height: 1.5;
        color: #2d2224;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #d8ac63;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        border-radius: 0.3125rem;
        box-shadow: inset 0 1px 2px transparent;
    }

    @media (prefers-reduced-motion: reduce) {

    }

    .form-control::-webkit-date-and-time-value {
        height: 1.5em;
    }

    .form-control::-moz-placeholder {
        color: #7d879c;
        opacity: 1;
    }

    .form-control::-webkit-file-upload-button {
        padding: 0.625rem 1rem;
        margin: -0.625rem -1rem;
        -webkit-margin-end: 1rem;
        margin-inline-end: 1rem;
        color: #373f50;
        background-color: #f6f9fc;
        border-color: inherit;
        border-style: solid;
        border-width: 0;
        border-inline-end-width: 1px;
        border-radius: 0;
    }

    @media (prefers-reduced-motion: reduce) {

    }

    .input-group {
        position: relative;
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        width: 100%;
    }

    .input-group > .form-control {
        position: relative;
        flex: 1 1 auto;
        width: 1%;
        min-width: 0;
    }

    .input-group .btn {
        position: relative;
        z-index: 2;
    }

    .input-group:not(.has-validation) > :not(:last-child):not(.dropdown-toggle):not(.dropdown-menu):not(.form-floating) {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }

    .input-group > :not(:first-child):not(.dropdown-menu):not(.valid-tooltip):not(.valid-feedback):not(.invalid-tooltip):not(.invalid-feedback) {
        margin-left: -1px;
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    .btn {
        --cz-btn-padding-x: 1.375rem;
        --cz-btn-padding-y: 0.625rem;
        --cz-btn-font-family: ;
        --cz-btn-font-size: 0.9375rem;
        --cz-btn-font-weight: normal;
        --cz-btn-line-height: 1.5;
        --cz-btn-color: #2d2224;
        --cz-btn-bg: transparent;
        --cz-btn-border-width: 1px;
        --cz-btn-border-color: transparent;
        --cz-btn-border-radius: 0.3125rem;
        --cz-btn-hover-border-color: transparent;
        --cz-btn-box-shadow: unset;
        --cz-btn-disabled-opacity: 0.65;
        --cz-btn-focus-box-shadow: 0 0 0 0 rgba(var(--cz-btn-focus-shadow-rgb), .5);
        display: inline-block;
        padding: var(--cz-btn-padding-y) var(--cz-btn-padding-x);
        font-family: var(--cz-btn-font-family);
        font-size: var(--cz-btn-font-size);
        font-weight: 600;
        line-height: var(--cz-btn-line-height);
        color: var(--cz-btn-color);
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
        border: var(--cz-btn-border-width) solid var(--cz-btn-border-color);
        border-radius: var(--cz-btn-border-radius);
        background-color: var(--cz-btn-bg);
        box-shadow: var(--cz-btn-box-shadow);
    }

    @media (prefers-reduced-motion: reduce) {

    }

    .btn-primary {
        --cz-btn-color: #000;
        --cz-btn-bg: #d8ac63;
        --cz-btn-border-color: #d8ac63;
        --cz-btn-hover-color: #000;
        --cz-btn-hover-bg: #916f36;
        --cz-btn-hover-border-color: #916f36;
        --cz-btn-focus-shadow-rgb: 216, 89, 90;
        --cz-btn-active-color: #000;
        --cz-btn-active-bg: #916f36;
        --cz-btn-active-border-color: #916f36;
        --cz-btn-active-shadow: unset;
        --cz-btn-disabled-color: #000;
        --cz-btn-disabled-bg: #916f36;
        --cz-btn-disabled-border-color: #916f36;
    }

    .btn-lg {
        --cz-btn-padding-y: 0.75rem;
        --cz-btn-padding-x: 1.5rem;
        --cz-btn-font-size: 1.0625rem;
        --cz-btn-border-radius: 0.4375rem;
    }

    .collapse:not(.show) {
        display: none;
    }

    .nav-link {
        display: block;
        padding: var(--cz-nav-link-padding-y) var(--cz-nav-link-padding-x);
        font-size: .9375em;
        font-weight: 600;
        color: var(--cz-nav-link-color);
    }

    @media (prefers-reduced-motion: reduce) {

    }

    .navbar {
        --cz-navbar-padding-x: 0;
        --cz-navbar-padding-y: 0.50rem;
        --cz-navbar-color: #111;
        --cz-navbar-hover-color: var(--cz-primary);
        --cz-navbar-disabled-color: #7d879c;
        --cz-navbar-active-color: var(--cz-primary);
        --cz-navbar-brand-padding-y: 0.625rem;
        --cz-navbar-brand-margin-end: 1rem;
        --cz-navbar-brand-font-size: 1.75rem;
        --cz-navbar-brand-color: #373f50;
        --cz-navbar-brand-hover-color: #373f50;
        --cz-navbar-nav-link-padding-x: 0.50rem;
        --cz-navbar-toggler-padding-y: 0.75rem;
        --cz-navbar-toggler-padding-x: 0.75rem;
        --cz-navbar-toggler-font-size: 1rem;
        --cz-navbar-toggler-icon-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='%234b566b' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        --cz-navbar-toggler-border-color: transparent;
        --cz-navbar-toggler-border-radius: 0;
        --cz-navbar-toggler-focus-width: 0;
        position: relative;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        padding: var(--cz-navbar-padding-y) var(--cz-navbar-padding-x);
    }

    .navbar > .container {
        display: flex;
        flex-wrap: inherit;
        align-items: center;
        justify-content: space-between;
    }

    .navbar-brand {
        padding-top: var(--cz-navbar-brand-padding-y);
        padding-bottom: var(--cz-navbar-brand-padding-y);
        margin-right: var(--cz-navbar-brand-margin-end);
        font-size: var(--cz-navbar-brand-font-size);
        color: var(--cz-navbar-brand-color);
        white-space: nowrap;
    }

    .navbar-nav {
        --cz-nav-link-padding-x: 0;
        --cz-nav-link-padding-y: 0.75rem;
        --cz-nav-link-font-weight: normal;
        --cz-nav-link-color: var(--cz-navbar-color);
        --cz-nav-link-hover-color: var(--cz-navbar-hover-color);
        --cz-nav-link-disabled-color: var(--cz-navbar-disabled-color);
        display: flex;
        flex-direction: column;
        padding-left: 0;
        margin-bottom: 0;
        list-style: none;
    }

    .navbar-collapse {
        flex-basis: 100%;
        flex-grow: 1;
        align-items: center;
    }

    .navbar-toggler {
        padding: var(--cz-navbar-toggler-padding-y) var(--cz-navbar-toggler-padding-x);
        font-size: var(--cz-navbar-toggler-font-size);
        line-height: 1;
        color: var(--cz-navbar-color);
        background-color: transparent;
        border: var(--cz-border-width) solid var(--cz-navbar-toggler-border-color);
        border-radius: var(--cz-navbar-toggler-border-radius);
    }

    @media (prefers-reduced-motion: reduce) {

    }

    .navbar-toggler-icon {
        display: inline-block;
        width: 1.5em;
        height: 1.5em;
        vertical-align: middle;
        background-image: var(--cz-navbar-toggler-icon-bg);
        background-repeat: no-repeat;
        background-position: center;
        background-size: 100%;
    }

    .navbar-dark {
        --cz-navbar-color: rgba(255, 255, 255, 0.65);
        --cz-navbar-hover-color: #fff;
        --cz-navbar-disabled-color: rgba(255, 255, 255, 0.35);
        --cz-navbar-active-color: #fff;
        --cz-navbar-brand-color: #fff;
        --cz-navbar-brand-hover-color: #fff;
        --cz-navbar-toggler-border-color: transparent;
        --cz-navbar-toggler-icon-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255,255,255,0.65%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }

    .card {
        --cz-card-spacer-y: 1.25rem;
        --cz-card-spacer-x: 1.25rem;
        --cz-card-title-spacer-y: 0.75rem;
        --cz-card-border-width: 1px;
        --cz-card-border-color: rgb(208, 199, 183);
        --cz-card-border-radius: 0.4375rem;
        --cz-card-box-shadow: ;
        --cz-card-inner-border-radius: calc(0.4375rem - 1px);
        --cz-card-cap-padding-y: 0.625rem;
        --cz-card-cap-padding-x: 1.25rem;
        --cz-card-cap-bg: transparent;
        --cz-card-cap-color: ;
        --cz-card-height: ;
        --cz-card-color: ;
        --cz-card-bg: #fff;
        --cz-card-img-overlay-padding: 1rem;
        --cz-card-group-margin: 0.9375rem;
        position: relative;
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: var(--cz-card-height);
        word-wrap: break-word;
        background-color: var(--cz-card-bg);
        background-clip: border-box;
        border: var(--cz-card-border-width) solid var(--cz-card-border-color);
        border-radius: var(--cz-card-border-radius);
        box-shadow: var(--cz-card-box-shadow);
    }

    .d-inline-block {
        display: inline-block !important;
    }

    .d-flex {
        display: flex !important;
    }

    .d-none {
        display: none !important;
    }

    .shadow-sm {
        box-shadow: 0 0.125rem 0.3rem -0.0625rem rgba(0, 0, 0, 0.03), 0 0.275rem 0.75rem -0.0625rem rgba(0, 0, 0, 0.06) !important;
    }

    .position-relative {
        position: relative !important;
    }

    .position-absolute {
        position: absolute !important;
    }

    .top-50 {
        top: 50% !important;
    }

    .start-0 {
        left: 0 !important;
    }

    .translate-middle-y {
        transform: translateY(-50%) !important;
    }

    .border-0 {
        border: 0 !important;
    }

    .flex-shrink-0 {
        flex-shrink: 0 !important;
    }

    .flex-wrap {
        flex-wrap: wrap !important;
    }

    .justify-content-center {
        justify-content: center !important;
    }

    .justify-content-between {
        justify-content: space-between !important;
    }

    .align-items-center {
        align-items: center !important;
    }

    .mx-auto {
        margin-right: auto !important;
        margin-left: auto !important;
    }

    .my-3 {
        margin-top: 1rem !important;
        margin-bottom: 1rem !important;
    }

    .mt-0 {
        margin-top: 0 !important;
    }

    .mt-2 {
        margin-top: 0.5rem !important;
    }

    .me-0 {
        margin-right: 0 !important;
    }

    .me-1 {
        margin-right: 0.25rem !important;
    }

    .me-2 {
        margin-right: 0.5rem !important;
    }

    .me-4 {
        margin-right: 1.5rem !important;
    }

    .me-auto {
        margin-right: auto !important;
    }

    .mb-1 {
        margin-bottom: 0.25rem !important;
    }

    .mb-2 {
        margin-bottom: 0.5rem !important;
    }

    .mb-3 {
        margin-bottom: 1rem !important;
    }

    .ms-2 {
        margin-left: 0.5rem !important;
    }

    .ms-3 {
        margin-left: 1rem !important;
    }

    .mt-n1 {
        margin-top: -0.25rem !important;
    }

    .me-n1 {
        margin-right: -0.25rem !important;
    }

    .p-0 {
        padding: 0 !important;
    }

    .px-4 {
        padding-right: 1.5rem !important;
        padding-left: 1.5rem !important;
    }

    .py-2 {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }

    .pt-3 {
        padding-top: 1rem !important;
    }

    .pb-0 {
        padding-bottom: 0 !important;
    }

    .pb-1 {
        padding-bottom: 0.25rem !important;
    }

    .pb-3 {
        padding-bottom: 1rem !important;
    }

    .fs-base {
        font-size: 1rem !important;
    }

    .fs-sm {
        font-size: 0.875rem !important;
    }

    .text-center {
        text-align: center !important;
    }

    .text-nowrap {
        white-space: nowrap !important;
    }

    .text-primary {
        --cz-text-opacity: 1;
        color: rgba(var(--cz-primary-rgb), var(--cz-text-opacity)) !important;
    }

    .text-dark {
        --cz-text-opacity: 1;
        color: rgba(var(--cz-dark-rgb), var(--cz-text-opacity)) !important;
    }

    .text-muted {
        --cz-text-opacity: 1;
        color: #504a4b !important;
    }

    .bg-secondary {
        --cz-bg-opacity: 1;
        background-color: #fbf9f5 !important;
    }

    .bg-light {
        --cz-bg-opacity: 1;
        background-color: rgba(var(--cz-light-rgb), var(--cz-bg-opacity)) !important;
    }

    .bg-dark {
        --cz-bg-opacity: 1;
        background-color: rgba(var(--cz-dark-rgb), var(--cz-bg-opacity)) !important;
    }

    .rounded-0 {
        border-radius: 0 !important;
    }

    .rounded-3 {
        border-radius: var(--cz-border-radius-lg) !important;
    }

    .rounded-start {
        border-bottom-left-radius: var(--cz-border-radius) !important;
        border-top-left-radius: var(--cz-border-radius) !important;
    }

    @media (min-width: 500px) {
        .d-sm-block {
            display: block !important;
        }

        .d-sm-none {
            display: none !important;
        }

        .py-sm-3 {
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
        }
    }

    html * {
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    html,
    body {
        height: 100%;
    }

    body {
        display: flex;
        flex-direction: column;
    }



    img {
        max-width: 100%;
        height: auto;
        vertical-align: middle;
    }

    @media (max-width: 991.98px) {
        body {
            padding-top: 0 !important;
        }
    }

    @font-face {
        font-family: "cartzilla-icons";
        src: url(../fonts/cartzilla-icons.ttf?ufvuz0) format("truetype"), url(../fonts/cartzilla-icons.woff?ufvuz0) format("woff"), url(../fonts/cartzilla-icons.svg?ufvuz0#cartzilla-icons) format("svg");
        font-weight: normal;
        font-style: normal;
        font-display: block;
    }

    [class^=ci-],
    [class*=" ci-"] {
        display: inline-block;
        /* use !important to prevent issues with browser extensions that change fonts */
        font-family: "cartzilla-icons" !important;
        speak: never;
        font-style: normal;
        font-weight: normal;
        font-variant: normal;
        text-transform: none;
        line-height: 1;
    }

    .ci-arrow-right:before {
        content: "\e90a";
    }

    .ci-bookmark:before {
        content: "\e915";
    }

    .ci-facebook:before {
        content: "\e938";
    }

    .ci-instagram:before {
        content: "\e94a";
    }

    .ci-mail:before {
        content: "\e956";
    }

    .ci-search:before {
        content: "\e972";
    }

    .ci-star-filled:before {
        content: "\e97f";
    }

    .ci-user-circle:before {
        content: "\e98c"}.btn-primary{--cz-btn-color:#2d2224;--cz-btn-hover-color:#e6d1ab;--cz-btn-active-color:#e6d1ab;--cz-btn-hover-bg:#2d2224;--cz-btn-active-bg:#2d2224;--cz-btn-hover-border-color:#2d2224;--cz-btn-active-border-color:#2d2224;--cz-btn-disabled-color:#fff}.btn-primary.btn-shadow{--cz-btn-box-shadow:0 .5rem 1.125rem -.5rem rgba(45,34,36,.9);box-shadow:var(--cz-btn-box-shadow)}.btn>i{margin-top:-.1875rem;vertical-align:middle}.input-group .position-absolute{z-index:5}.input-group .position-absolute+.form-control{padding-left:2.5rem}.navbar-brand{display:inline-block;font-weight:500;vertical-align:middle}.navbar-brand>img{display:block}.navbar-nav .nav-item{margin-bottom:.667rem;border-radius:.3125rem}.navbar-nav .nav-link{padding:.667rem 1.125rem;font-weight:500}.navbar-tool{position:relative;display:flex;align-items:center}.navbar-tool-icon-box{position:relative;width:2.875rem;height:2.875rem;border-radius:50%;line-height:2.625rem;text-align:center}.navbar-tool-icon{font-size:1.25rem;line-height:2.875rem}@media (max-width:767.98px){.search-box{display:none}}.navbar-dark .nav-item{background-color:rgba(255,255,255,.05)}.navbar-dark .navbar-tool-icon-box{color:#e6d1ab;background-color:#2D2224}.topbar{display:flex;align-items:center;justify-content:space-between;padding:.625rem 0;font-size:.875rem}.topbar>.container{display:flex;align-items:center;justify-content:space-between}.topbar .topbar-text,.topbar .topbar-link{display:inline-block;margin-bottom:0;text-decoration:none!important}.topbar .topbar-link>i{margin-right:.375rem;font-size:1.15em;vertical-align:middle}.topbar-dark .topbar-text,.topbar-dark .topbar-link{color:#2d2224}.topbar-dark .topbar-link>i{color:var(--cz-primary)}.tns-carousel{position:relative}.tns-carousel .tns-carousel-inner{position:relative;display:flex;overflow:hidden;touch-action:manipulation;opacity:0}.tns-carousel .tns-carousel-inner>*{-webkit-backface-visibility:hidden;backface-visibility:hidden}.star-rating{display:inline-block;white-space:nowrap;line-height:1;vertical-align:middle}.star-rating .star-rating-icon{display:inline-block;margin-right:.1875rem;color:#aeb4be;font-size:.75rem;vertical-align:middle}.star-rating .star-rating-icon.active{color:#fea569}
</style>
    <!-- Vendor Styles including: Font Icons, Plugins, etc.-->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <!-- Main Theme Styles + Bootstrap-->
   {{--    <link rel="stylesheet" media="screen" href="{{ asset(config('settings.images_domain') . 'css/theme.css?v=1.91') }}"> ---}}
    @if (config('app.env') == 'production')
        @yield('google_data_layer')
        <!-- Google Tag Manager -->
            <!-- Google Tag Manager -->

            <!-- End Google Tag Manager -->

        <!-- Global site tag (gtag.js) - Google Analytics -->
     <!--   <script async src="https://www.googletagmanager.com/gtag/js?id=xxxxxxx"></script>-->
    @endif

    @stack('css_after')

    @if (config('app.env') == 'production')

    @endif

    <style>
        [v-cloak] { display:none !important; }
    </style>

</head>
<!-- Body-->
<body class="bg-secondary">

@if (config('app.env') == 'production')
    <!-- Google Tag Manager (noscript) -->

    <!-- End Google Tag Manager (noscript) -->
@endif


<!-- Light topbar -->
<div class="topbar topbar-dark  bg-light position-relative" style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
    <div class="container">

        <div class="topbar-text text-nowrap  d-inline-block">
            <span class=" me-1">Podrška</span>
            <a class="topbar-link" href="tel:00385917627441">091 762 7441</a>
        </div>
        <div class="topbar-text  d-none  d-md-inline-block">Besplatna dostava za sve narudžbe iznad 70 €</div>
        <div class="ms-3 text-nowrap ">
            <a class="topbar-link me-2 d-inline-block" aria-label="Follow us on facebook" href="https://www.facebook.com/antikavrijatvremeplov">
                <i class="ci-facebook"></i>
            </a>

            <a class="topbar-link me-2 d-inline-block" aria-label="Follow us on instagram" href="https://www.instagram.com/antiqueshopvremeplov/">
                <i class="ci-instagram"></i>
            </a>

            <a class="topbar-link me-0 d-inline-block" aria-label="Email us" href="mailto:info@antiqueshop.hr">
                <i class="ci-mail"></i>
            </a>

        </div>
    </div>

</div>




<div id="agapp">
    @include('front.layouts.partials.header')

    @yield('content')

    @include('front.layouts.partials.footer')

    @include('front.layouts.partials.handheld')
</div>

<!-- Back To Top Button-->
<a class="btn-scroll-top" href="#top" aria-label="Scroll to top" data-scroll><span class="btn-scroll-top-tooltip text-muted fs-sm me-2"></span><i class="btn-scroll-top-icon ci-arrow-up"></i></a>
<!-- Vendor Styles including: Font Icons, Plugins, etc.-->
<link rel="stylesheet" media="screen" href="{{ asset(config('settings.images_domain') . 'css/tiny-slider.css?v=1.2') }}"/>
<!-- Vendor scrits: js libraries and plugins-->
<script src="{{ asset('js/jquery/jquery-2.1.1.min.js?v=1.2') }}"></script>
<script src="{{ asset('js/bootstrap.bundle.min.js?v=1.2') }}"></script>
<script src="{{ asset('js/tiny-slider.js?v=1.2') }}"></script>
<script src="{{ asset('js/smooth-scroll.polyfills.min.js?v=1.2') }}"></script>
<script src="{{ asset('js/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
<script src="{{ asset('js/shufflejs/dist/shuffle.min.js') }}"></script>
<!-- Main theme script-->

<script src="{{ asset('js/cart.js?v=2.1.3') }}"></script>

<script src="{{ asset('js/theme.min.js') }}"></script>

<script>
    $(() => {
        $('#search-input').on('keyup', (e) => {
            if (e.keyCode == 13) {
                e.preventDefault();
                $('search-form').submit();
            }
        })
    });
</script>






@stack('js_after')

<script>var cb = function() {
        var l = document.createElement('link'); l.rel = 'stylesheet';
        l.href = '{{ asset(config('settings.images_domain') . 'css/theme.css?v=1.91') }}';
        var h = document.getElementsByTagName('head')[0]; h.parentNode.insertBefore(l, h);
    };
    var raf = requestAnimationFrame || mozRequestAnimationFrame ||
        webkitRequestAnimationFrame || msRequestAnimationFrame;
    if (raf) raf(cb);
    else window.addEventListener('load', cb);</script>

</body>
</html>
