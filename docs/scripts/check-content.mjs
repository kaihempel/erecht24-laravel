// Verifies EN pages against specs/009-readme-to-starlight-en/contracts/page-contract.md.
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const root = new URL('../src/content/docs/en/', import.meta.url).pathname;
const configFile = new URL('../../config/erecht24.php', import.meta.url).pathname;
const reserved = new Set(['project/troubleshooting', 'reference/exceptions', 'project/changelog']);

const files = [];
const rec = (d) => {
	for (const f of readdirSync(d)) {
		const p = join(d, f);
		statSync(p).isDirectory() ? rec(p) : files.push(p);
	}
};
rec(root);

const stem = (p) => relative(root, p).replace(/\.mdx?$/, '');
const stems = new Set(files.map(stem));
const errors = [];

for (const file of files) {
	const id = stem(file);
	if (reserved.has(id)) continue;
	const raw = readFileSync(file, 'utf8');
	const m = raw.match(/^---\n([\s\S]*?)\n---\n?([\s\S]*)$/);
	const err = (msg) => errors.push(`${relative(root, file)}: ${msg}`);
	if (!m) {
		err('missing frontmatter');
		continue;
	}
	const [, fm, body] = m;
	for (const key of ['title', 'description']) {
		if (!new RegExp(`^${key}:\\s*(?!["']{2}\\s*$)\\S`, 'm').test(fm)) err(`frontmatter ${key} missing or empty`);
	}
	const outside = body.replace(/^[ \t]*(```|~~~)[\s\S]*?^[ \t]*\1.*$/gm, '');
	if (/^# /m.test(outside)) err('body contains an H1 heading');
	if (/Coming soon/i.test(body)) err('placeholder text "Coming soon"');
	for (const [, target] of outside.matchAll(/\]\(([^)\s]+)/g)) {
		if (/README/i.test(target)) err(`README link: ${target}`);
		if (/^(https?:|mailto:)/.test(target)) continue;
		if (!target.startsWith('/en/')) {
			err(`internal link must start with /en/: ${target}`);
			continue;
		}
		const path = target.slice(4).split(/[#?]/)[0].replace(/\/$/, '') || 'index';
		if (!stems.has(path) && !stems.has(`${path}/index`)) err(`broken internal link: ${target}`);
	}
	if (id === 'guides/setup' && !(/import\s*\{[^}]*\bSteps\b/.test(body) && /<Steps>/.test(body)))
		err('must import and use Steps');
	if (id === 'usage/facade-and-inertia' && !(/import\s*\{[^}]*\bTabs\b/.test(body) && /<Tabs[\s>]/.test(body)))
		err('must import and use Tabs');
	if (id === 'index' && !/<(CardGrid|Steps)>/.test(body)) err('must use CardGrid or Steps');
	if (id === 'reference/configuration') {
		const names = new Set(readFileSync(configFile, 'utf8').match(/ERECHT24_[A-Z_]+/g) ?? []);
		for (const n of names) if (!body.includes(`\`${n}\``)) err(`missing env variable \`${n}\``);
	}
}

if (errors.length) {
	console.error(errors.join('\n'));
	process.exit(1);
}
console.log(`docs content ok (${files.length} EN files)`);
