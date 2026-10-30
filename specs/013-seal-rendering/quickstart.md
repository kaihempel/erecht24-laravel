# Quickstart

1. `ERECHT24_IMPRINT_SEAL=light-horizontal` and/or `ERECHT24_PRIVACY_POLICY_SEAL=dark-vertical` in `.env`.
2. Make sure the seal images are available under `public/vendor/erecht24/` (asset publishing, issue #2).
3. Sync texts, then render `<x-erecht24::imprint />` or `<x-erecht24::privacy-policy />`; the seal appears centered below the text.
4. Verify: `composer test && composer lint && composer analyse`.
