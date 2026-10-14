// Verifies EN and DE pages against the page contracts in specs/009-* and specs/010-*.
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import GithubSlugger from 'github-slugger';

const configFile = new URL('../../config/erecht24.php', import.meta.url).pathname;
const exceptionsDir = new URL('../../src/Exceptions/', import.meta.url).pathname;
const reserved = new Set(['project/changelog']);
const labels = {
	en: { exceptions: ['Extends', 'Thrown when', 'Handling'], troubleshooting: ['Symptom', 'Cause', 'Fix'] },
	de: { exceptions: ['Erbt von', 'Wird geworfen, wenn', 'Behandlung'], troubleshooting: ['Symptom', 'Ursache', 'Lösung'] },
};
// Splits a body into H2 sections: [{ heading, text }].
const sections = (body) =>
	stripCode(body)
		.split(/^## /m)
		.slice(1)
		.map((chunk) => ({ heading: chunk.split('\n')[0].trim(), text: chunk }));
// True when the bold labels appear in the given order (each as `**Label**` or `**Label:**`).
const inOrder = (text, names, colon) => {
	let at = -1;
	for (const n of names) {
		const i = text.indexOf(colon ? `**${n}:**` : `**${n}**`, at + 1);
		if (i === -1) return false;
		at = i;
	}
	return true;
};
const stripCode = (s) => s.replace(/^[ \t]*(```|~~~)[\s\S]*?^[ \t]*\1.*$/gm, '');
const decodeAnchor = (a) => {
	try {
		return a === undefined ? a : decodeURIComponent(a);
	} catch {
		return a;
	}
};
const count = (s, re) => (s.match(re) ?? []).length;

const collect = (root) => {
	const files = [];
	const rec = (d) => {
		for (const f of readdirSync(d)) {
			const p = join(d, f);
			statSync(p).isDirectory() ? rec(p) : files.push(p);
		}
	};
	rec(root);
	return files;
};
const split = (file) => readFileSync(file, 'utf8').match(/^---\n([\s\S]*?)\n---\n?([\s\S]*)$/);
const slugs = (body) => {
	const slugger = new GithubSlugger();
	return new Set([...stripCode(body).matchAll(/^#{1,6}\s+(.+?)\s*#*$/gm)].map(([, h]) => slugger.slug(h.replace(/[`*_]/g, ''))));
};
const shape = (body) => {
	const o = stripCode(body);
	const h = {};
	for (let i = 1; i <= 6; i++) h[i] = count(o, new RegExp(`^#{${i}}\\s`, 'gm'));
	const a = {};
	for (const t of ['note', 'tip', 'caution', 'danger']) a[t] = count(o, new RegExp(`^[ \\t]*:::${t}\\b`, 'gm'));
	return JSON.stringify({ h, a, card: count(o, /<Card\b/g), tab: count(o, /<TabItem\b/g), steps: count(o, /<Steps\b/g) });
};

const errors = [];
const locales = { en: 'Coming soon', de: 'Demnächst verfügbar' };
const dirOf = (l) => new URL(`../src/content/docs/${l}/`, import.meta.url).pathname;
const stemOf = (root, p) => relative(root, p).replace(/\.mdx?$/, '');
const enFiles = collect(dirOf('en'));
const enByStem = new Map(enFiles.map((f) => [stemOf(dirOf('en'), f), f]));
let total = 0;

for (const [locale, placeholder] of Object.entries(locales)) {
	const root = dirOf(locale);
	const files = collect(root);
	total += files.length;
	const byStem = new Map(files.map((f) => [stemOf(root, f), f]));
	for (const file of files) {
		const id = stemOf(root, file);
		if (reserved.has(id)) continue;
		const m = split(file);
		const err = (msg) => errors.push(`${locale}/${relative(root, file)}: ${msg}`);
		if (!m) {
			err('missing frontmatter');
			continue;
		}
		const [, fm, body] = m;
		for (const key of ['title', 'description']) {
			if (!new RegExp(`^${key}:\\s*(?!["']{2}\\s*$)\\S`, 'm').test(fm)) err(`frontmatter ${key} missing or empty`);
		}
		const outside = stripCode(body);
		if (/^# /m.test(outside)) err('body contains an H1 heading');
		if (new RegExp(placeholder, 'i').test(body) || /Coming soon/i.test(body)) err('placeholder text');
		for (const [, target] of outside.matchAll(/\]\(([^)\s]+)/g)) {
			if (/README/i.test(target)) err(`README link: ${target}`);
			if (/^(https?:|mailto:)/.test(target)) continue;
			if (!target.startsWith(`/${locale}/`)) {
				err(`internal link must start with /${locale}/: ${target}`);
				continue;
			}
			const [pathPart, anchor] = [target.slice(locale.length + 2).split(/[?#]/)[0].replace(/\/$/, '') || 'index', decodeAnchor(target.split('#')[1]?.split('?')[0])];
			const hit = byStem.get(pathPart) ?? byStem.get(`${pathPart}/index`);
			if (!hit) {
				err(`broken internal link: ${target}`);
				continue;
			}
			if (anchor) {
				const t = split(hit);
				if (t && !slugs(t[2]).has(anchor)) err(`anchor not found on target page: ${target}`);
			}
		}
		if (id === 'guides/setup' && !(/import\s*\{[^}]*\bSteps\b/.test(body) && /<Steps>/.test(body)))
			err('must import and use Steps');
		if (id === 'usage/facade-and-inertia' && !(/import\s*\{[^}]*\bTabs\b/.test(body) && /<Tabs[\s>]/.test(body)))
			err('must import and use Tabs');
		if (id === 'index' && !/<(CardGrid|Steps)>/.test(body)) err('must use CardGrid or Steps');
		if (id === 'reference/exceptions') {
			const found = sections(body);
			const classes = readdirSync(exceptionsDir).filter((f) => f.endsWith('.php')).map((f) => f.replace(/\.php$/, ''));
			for (const cls of classes) {
				const sec = found.find((x) => x.heading === `\`${cls}\``);
				if (!sec) err(`missing H2 section for \`${cls}\``);
				else if (!inOrder(sec.text, labels[locale].exceptions, false)) err(`section \`${cls}\` must contain ${labels[locale].exceptions.map((l) => `**${l}**`).join(', ')} in order`);
			}
		}
		if (id === 'project/troubleshooting') {
			const found = sections(body);
			if (found.length < 9) err(`needs at least 9 H2 entries, found ${found.length}`);
			for (const sec of found)
				if (!inOrder(sec.text, labels[locale].troubleshooting, true)) err(`entry "${sec.heading}" must contain ${labels[locale].troubleshooting.map((l) => `**${l}:**`).join(', ')} in order`);
		}
		if (id === 'guides/deployment' && !outside.includes(`](/${locale}/project/troubleshooting/`)) err('must link to the troubleshooting page');
		if (id === 'reference/configuration') {
			const names = new Set(readFileSync(configFile, 'utf8').match(/ERECHT24_[A-Z_]+/g) ?? []);
			for (const n of names) if (!body.includes(`\`${n}\``)) err(`missing env variable \`${n}\``);
		}
		if (locale === 'de' && enByStem.has(id)) {
			const en = split(enByStem.get(id));
			if (en && shape(en[2]) !== shape(body)) err(`structure differs from EN (headings/asides/components): DE ${shape(body)} vs EN ${shape(en[2])}`);
		}
	}
}

if (errors.length) {
	console.error(errors.join('\n'));
	process.exit(1);
}
console.log(`docs content ok (${total} files, locales: ${Object.keys(locales).join(', ')})`);
