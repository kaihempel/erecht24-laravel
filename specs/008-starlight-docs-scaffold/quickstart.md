# Quickstart: Verify the docs scaffold

```bash
cd docs && npm ci && npm run build      # must exit 0
npm run dev                             # open /en/ and /de/, check switcher + 5 groups

# EN/DE parity (no output expected)
diff <(cd src/content/docs/en && find . -type f | sort) \
     <(cd src/content/docs/de && find . -type f | sort)

# Ignored artefacts (no output expected)
cd .. && git status --porcelain docs | grep -E 'node_modules|dist|\.astro'

# Archive excludes docs (no output expected)
git archive HEAD | tar -t | grep '^docs/'
```

Also confirm `lint.yml`/`tests.yml` are unchanged (`git diff main -- .github/workflows/lint.yml .github/workflows/tests.yml`).
