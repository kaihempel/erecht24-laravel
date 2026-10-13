// Verifies EN/DE pages have identical file names and every section directory is in the sidebar.
import { readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { sections } from '../sections.mjs';

const root = new URL('../src/content/docs/', import.meta.url).pathname;
const list = (loc) => {
	const base = join(root, loc);
	const out = [];
	const rec = (d) => {
		for (const f of readdirSync(d)) {
			const p = join(d, f);
			statSync(p).isDirectory() ? rec(p) : out.push(relative(base, p));
		}
	};
	rec(base);
	return out.map((f) => f.replace(/\.mdx?$/, '')).sort();
};

const dupes = (l) => l.filter((f, i) => l.indexOf(f) !== i);
const en = list('en');
const de = list('de');
const errors = [];
for (const [loc, l] of [['en', en], ['de', de]]) for (const f of dupes(l)) errors.push(`duplicate page (.md and .mdx) in ${loc}: ${f}`);
for (const f of en) if (!de.includes(f)) errors.push(`missing in de: ${f}`);
for (const f of de) if (!en.includes(f)) errors.push(`missing in en: ${f}`);

const dirs = new Set(en.filter((f) => f.includes('/')).map((f) => f.split('/')[0]));
const known = new Set(sections.map((s) => s.directory));
for (const d of dirs) if (!known.has(d)) errors.push(`directory not in sidebar sections: ${d}`);
for (const d of known) if (!dirs.has(d)) errors.push(`sidebar section without directory: ${d}`);

if (errors.length) {
	console.error(errors.join('\n'));
	process.exit(1);
}
console.log(`docs parity ok (${en.length} pages per locale)`);
