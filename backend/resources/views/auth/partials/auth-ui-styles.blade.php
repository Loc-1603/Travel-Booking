<style>
body.auth-body {
    background: #f6f7fb;
}

.auth-page {
    --auth-card-width: 480px;
    --auth-text: #111827;
    --auth-muted: #5b6472;
    --auth-border: #e8ebf1;
    --auth-bg: #f6f7fb;
    --auth-accent: #4338ca;
    --auth-accent-soft: rgba(67, 56, 202, .10);
    min-height: 100vh;
    background: var(--auth-bg);
    color: var(--auth-text);
    font-family: "IBM Plex Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.auth-layout {
    min-height: 100vh;
}

.auth-form-col {
    display: flex;
    background:
        radial-gradient(46rem 32rem at 0% 0%, rgba(67, 56, 202, .10), transparent 62%),
        radial-gradient(36rem 28rem at 100% 100%, rgba(67, 56, 202, .06), transparent 62%),
        var(--auth-bg);
}

.auth-form-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 100vh;
    padding: 40px 32px;
}

.auth-card {
    width: 100%;
    max-width: var(--auth-card-width, 480px);
    padding: 40px;
    background: #fff;
    border: 1px solid var(--auth-border);
    border-radius: 16px;
    box-shadow: 0 24px 60px -32px rgba(17, 24, 39, .28), 0 2px 8px rgba(17, 24, 39, .04);
}

.auth-brand {
    margin-bottom: 24px;
    text-align: center;
}

.auth-brand-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: inherit;
    text-decoration: none;
}

.auth-brand-link:hover,
.auth-brand-link:focus {
    color: inherit;
    text-decoration: none;
}

.auth-brand-mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 15px;
    background: linear-gradient(135deg, #4338ca 0%, #4f46e5 55%, #6366f1 100%);
    color: #fff;
    font-size: 27px;
    box-shadow: 0 10px 20px -10px rgba(67, 56, 202, .45);
}

.auth-brand-logo {
    display: block;
    width: auto;
    max-width: 220px;
    height: auto;
    max-height: 52px;
    object-fit: contain;
}

.auth-brand-name {
    margin-top: 10px;
    color: var(--auth-text);
    font-size: 20px;
    font-weight: 700;
    line-height: 1.2;
}

.auth-brand-tagline {
    max-width: 320px;
    margin-top: 4px;
    color: var(--auth-muted);
    font-size: 14px;
    line-height: 1.5;
}

.auth-heading {
    margin-bottom: 24px;
    text-align: center;
}

.auth-title {
    margin: 0;
    color: var(--auth-text);
    font-size: clamp(1.35rem, 1.1rem + .6vw, 1.55rem);
    font-weight: 700;
    letter-spacing: -.01em;
    line-height: 1.3;
}

.auth-subtitle {
    margin: 8px 0 0;
    color: var(--auth-muted);
    font-size: 15px;
    line-height: 1.6;
}

.auth-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
    padding: 5px 10px;
    border-radius: 999px;
    background: var(--auth-accent-soft);
    color: var(--auth-accent);
    font-size: 12px;
    font-weight: 700;
    line-height: 1.4;
}

.auth-eyebrow .mdi {
    font-size: 15px;
}

.auth-status-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    margin-bottom: 14px;
    border-radius: 16px;
    background: var(--auth-accent-soft);
    color: var(--auth-accent);
    font-size: 26px;
}

.auth-form {
    margin-top: 24px;
}

.auth-field {
    margin-bottom: 16px;
}

.auth-label {
    margin-bottom: 6px;
    color: #1f2937;
    font-size: 14px;
    font-weight: 600;
}

.auth-required {
    color: #dc2626;
}

.auth-label-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 6px;
}

.auth-label-row .auth-label {
    margin-bottom: 0;
}

.auth-inline-link {
    color: var(--auth-accent);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
}

.auth-inline-link:hover {
    color: var(--auth-accent);
    text-decoration: underline;
}

.auth-card .form-control {
    min-height: 46px;
    padding: .6rem .85rem;
    border-color: #dde1e7;
    border-radius: 10px;
    background: #fff;
    box-shadow: none;
    color: var(--auth-text);
    font-size: 15px;
}

.auth-card textarea.form-control {
    min-height: 104px;
    resize: vertical;
}

.auth-card .form-control::placeholder {
    color: #8a93a2;
    opacity: 1;
}

.auth-card .form-control:focus {
    border-color: var(--auth-accent);
    box-shadow: none;
}

.auth-card .form-control.is-invalid {
    border-color: #dc2626;
}

.auth-card .form-control.is-invalid:focus {
    border-color: #dc2626;
    box-shadow: none;
}

.auth-field-error {
    margin-top: 6px;
    color: #dc2626;
    font-size: 13px;
    line-height: 1.45;
}

.auth-pass-inputgroup {
    flex-wrap: nowrap;
}

.auth-pass-inputgroup .form-control {
    border-right: 0;
    border-radius: 10px 0 0 10px;
}

.auth-pass-inputgroup .form-control:focus {
    z-index: 2;
}

.auth-pass-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 46px;
    border: 1px solid #dde1e7;
    border-left: 0;
    border-radius: 0 10px 10px 0;
    background: #fff;
    color: var(--auth-muted);
    font-size: 18px;
}

.auth-pass-toggle:hover {
    background: #f3f4f6;
    color: var(--auth-text);
}

.auth-pass-toggle:focus-visible {
    z-index: 3;
    outline: 2px solid var(--auth-accent);
    outline-offset: 2px;
}

.auth-row {
    margin-bottom: 20px;
}

.auth-card .form-check-label {
    color: #374151;
    font-size: 14px;
}

.auth-card .form-check-input {
    width: 16px;
    height: 16px;
    margin-top: .2em;
    border-color: #cfd4dc;
}

.auth-card .form-check-input:checked {
    border-color: var(--auth-accent);
    background-color: var(--auth-accent);
}

.auth-card .form-check-input:focus {
    border-color: var(--auth-accent);
    box-shadow: none;
}

.auth-card .btn.auth-submit {
    min-height: 46px;
    border-color: var(--auth-accent);
    border-radius: 10px;
    background: var(--auth-accent);
    color: #fff;
    font-size: 15px;
    font-weight: 600;
}

.auth-card .btn.auth-submit:hover,
.auth-card .btn.auth-submit:focus {
    border-color: var(--auth-accent);
    background: var(--auth-accent);
    color: #fff;
    filter: brightness(.92);
}

.auth-card .btn.auth-submit:focus-visible {
    box-shadow: 0 0 0 4px var(--auth-accent-soft);
}

.auth-card .btn.auth-submit:disabled {
    opacity: .55;
}

.auth-alert {
    margin-bottom: 16px;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.5;
}

.auth-footnote {
    margin-top: 20px;
    color: var(--auth-muted);
    font-size: 14px;
    line-height: 1.55;
    text-align: center;
}

.auth-footnote a {
    color: var(--auth-accent);
    font-weight: 600;
    text-decoration: none;
}

.auth-footnote a:hover {
    color: var(--auth-accent);
    text-decoration: underline;
}

.auth-footnote--muted {
    margin-top: 12px;
}

.auth-footnote--muted a {
    color: var(--auth-muted);
}

.auth-footnote--muted a:hover {
    color: var(--auth-accent);
}

.auth-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.auth-section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 24px 0 14px;
    padding-top: 20px;
    border-top: 1px solid #eef0f4;
    color: #374151;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.auth-section-title span:last-child {
    color: #8a93a2;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0;
    text-transform: none;
}

.auth-role-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.auth-role-card {
    --role: var(--auth-accent);
    --role-soft: var(--auth-accent-soft);
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border: 1px solid var(--auth-border);
    border-radius: 14px;
    background: #fff;
    color: inherit;
    text-decoration: none;
    transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.auth-role-card:hover {
    border-color: var(--role);
    background: var(--role-soft);
    box-shadow: 0 10px 24px -16px rgba(17, 24, 39, .35);
    color: inherit;
    text-decoration: none;
    transform: translateY(-1px);
}

.auth-role-card:focus-visible {
    outline: 2px solid var(--role);
    outline-offset: 2px;
}

.auth-role-icon {
    display: inline-flex;
    flex: 0 0 48px;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 13px;
    background: var(--role-soft);
    color: var(--role);
    font-size: 24px;
}

.auth-role-body {
    flex: 1 1 auto;
    min-width: 0;
}

.auth-role-title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    row-gap: 4px;
}

.auth-role-title {
    color: var(--auth-text);
    font-size: 16px;
    font-weight: 600;
    line-height: 1.3;
}

.auth-role-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--role-soft);
    color: var(--role);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .03em;
    line-height: 1.5;
    text-transform: uppercase;
}

.auth-role-desc {
    display: block;
    margin-top: 3px;
    color: var(--auth-muted);
    font-size: 14px;
    line-height: 1.5;
}

.auth-role-arrow {
    display: inline-flex;
    flex: 0 0 32px;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--role-soft);
    color: var(--role);
    font-size: 18px;
    transition: background-color .18s ease, color .18s ease, transform .18s ease;
}

.auth-role-card:hover .auth-role-arrow {
    background: var(--role);
    color: #fff;
    transform: translateX(2px);
}

.auth-visual {
    position: relative;
    height: 100%;
    min-height: 100vh;
    overflow: hidden;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}

.auth-visual-overlay {
    position: absolute;
    inset: 0;
    z-index: 0;
    background: linear-gradient(135deg, rgba(15, 23, 42, .78) 0%, rgba(67, 56, 202, .50) 55%, rgba(49, 46, 129, .32) 100%);
    box-shadow: inset 0 0 120px rgba(15, 23, 42, .35);
}

.auth-visual-shapes {
    position: absolute;
    inset: 0;
    z-index: 1;
    margin: 0;
    padding: 0;
    overflow: hidden;
    pointer-events: none;
}

.auth-visual-shapes li {
    position: absolute;
    bottom: -160px;
    display: block;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, .12);
    animation: authShapeRise 24s linear infinite;
}

.auth-visual-shapes li:nth-child(1) {
    right: 8%;
    width: 42px;
    height: 42px;
    animation-delay: 1s;
    animation-duration: 22s;
}

.auth-visual-shapes li:nth-child(2) {
    right: 18%;
    width: 110px;
    height: 110px;
    animation-delay: 4s;
    animation-duration: 28s;
    opacity: .55;
}

.auth-visual-shapes li:nth-child(3) {
    right: 38%;
    width: 70px;
    height: 70px;
    animation-delay: 8s;
}

.auth-visual-shapes li:nth-child(4) {
    right: 52%;
    width: 130px;
    height: 130px;
    animation-delay: 2s;
    animation-duration: 32s;
    opacity: .45;
}

.auth-visual-shapes li:nth-child(5) {
    right: 68%;
    width: 56px;
    height: 56px;
    animation-delay: 10s;
    animation-duration: 26s;
}

.auth-visual-shapes li:nth-child(6) {
    right: 82%;
    width: 90px;
    height: 90px;
    animation-delay: 6s;
    animation-duration: 30s;
}

.auth-visual-shapes li:nth-child(7) {
    right: 28%;
    width: 150px;
    height: 150px;
    animation-delay: 14s;
    animation-duration: 36s;
    opacity: .35;
}

.auth-visual-shapes li:nth-child(8) {
    right: 60%;
    width: 44px;
    height: 44px;
    animation-delay: 17s;
    animation-duration: 20s;
}

.auth-visual-shapes li:nth-child(9) {
    right: 74%;
    width: 100px;
    height: 100px;
    animation-delay: 12s;
    animation-duration: 33s;
    opacity: .5;
}

.auth-visual-shapes li:nth-child(10) {
    right: 92%;
    width: 64px;
    height: 64px;
    animation-delay: 20s;
    animation-duration: 40s;
}

@keyframes authShapeRise {
    0% {
        opacity: 0;
        transform: translate3d(0, 0, 0) rotate(0deg);
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: .85;
    }
    100% {
        opacity: 0;
        transform: translate3d(-40px, -120vh, 0) rotate(-600deg);
    }
}

@media (min-width: 992px) {
    .auth-visual-col {
        display: block;
    }
}

@media (max-width: 991.98px) {
    .auth-form-panel {
        min-height: 100dvh;
        padding: 24px 16px;
    }

    .auth-card {
        padding: 28px 22px;
        border-radius: 14px;
    }
}

@media (max-width: 575.98px) {
    .auth-form-panel {
        padding: 16px;
    }

    .auth-card {
        padding: 24px 18px;
    }

    .auth-brand {
        margin-bottom: 20px;
    }

    .auth-heading {
        margin-bottom: 20px;
    }

    .auth-title {
        font-size: 22px;
    }

    .auth-subtitle {
        font-size: 14px;
    }

    .auth-role-card {
        gap: 12px;
        padding: 14px;
    }

    .auth-role-arrow {
        flex-basis: 30px;
        width: 30px;
        height: 30px;
        font-size: 16px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .auth-role-card,
    .auth-role-arrow {
        transition: none;
    }

    .auth-visual-shapes li {
        animation: none;
    }

    .auth-role-card:hover {
        transform: none;
    }

    .auth-role-card:hover .auth-role-arrow {
        transform: none;
    }
}
</style>
