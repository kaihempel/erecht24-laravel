// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// https://astro.build/config
export default defineConfig({
	// TODO(#11): set real docs domain
	site: 'https://example.invalid',
	redirects: { '/': '/en/' },
	integrations: [
		starlight({
			title: 'erecht24-laravel',
			defaultLocale: 'en',
			locales: {
				en: { label: 'English' },
				de: { label: 'Deutsch', lang: 'de' },
			},
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/kaihempel/erecht24-laravel' }],
			editLink: { baseUrl: 'https://github.com/kaihempel/erecht24-laravel/edit/main/docs/' },
			sidebar: [
				{ label: 'Getting Started', translations: { de: 'Erste Schritte' }, items: [{ autogenerate: { directory: 'getting-started' } }] },
				{ label: 'Guides', translations: { de: 'Anleitungen' }, items: [{ autogenerate: { directory: 'guides' } }] },
				{ label: 'Usage', translations: { de: 'Verwendung' }, items: [{ autogenerate: { directory: 'usage' } }] },
				{ label: 'Reference', translations: { de: 'Referenz' }, items: [{ autogenerate: { directory: 'reference' } }] },
				{ label: 'Project', translations: { de: 'Projekt' }, items: [{ autogenerate: { directory: 'project' } }] },
			],
		}),
	],
});
