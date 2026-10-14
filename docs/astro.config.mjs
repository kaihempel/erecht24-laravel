// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import { defaultLocale, sections } from './sections.mjs';

// https://astro.build/config
export default defineConfig({
	// TODO(#11): set real docs domain; override via DOCS_SITE_URL until then
	site: process.env.DOCS_SITE_URL ?? 'https://example.invalid',
	redirects: { '/': `/${defaultLocale}/` },
	// Allow the changelog page to import ../CHANGELOG.md from the repo root.
	vite: { server: { fs: { allow: ['..'] } } },
	integrations: [
		starlight({
			title: 'erecht24-laravel',
			defaultLocale,
			locales: {
				en: { label: 'English' },
				de: { label: 'Deutsch', lang: 'de' },
			},
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/kaihempel/erecht24-laravel' }],
			editLink: { baseUrl: 'https://github.com/kaihempel/erecht24-laravel/edit/main/docs/' },
			sidebar: sections.map(({ directory, label, de }) => ({
				label,
				translations: { de },
				items: [{ autogenerate: { directory } }],
			})),
		}),
	],
});
