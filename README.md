# Psalm version diff

Runs the latest Psalm 6.x release and the latest 7.x release on the perf-report corpus (`bin/perf/corpus.yml` of
vimeo/psalm master: same projects, same commits, same generated config) and lists the issues that differ between
them. Differences already classified in `classifications.json` are counted, not listed, so each run only shows what
still needs a look.

This branch is orphan: it holds only the tool. The corpus and the config generator (`bin/perf/perf.php config`) are
read from vimeo/psalm master at run time.

## Running

- Every push to `version-diff` runs `.github/workflows/version-diff.yml`, so pushing new classifications re-runs it.
- Re-run without a change: `gh run rerun <id> --repo orklah/psalm`, or push an empty commit.
- Both versions run on PHP 8.5, one thread, `--no-cache`, at three times the perf timeout.
- Project dependencies come from `locks/<project>/<ref>.lock` (`composer install`); without one, the run resolves them and the report artifact holds the lock to commit.

Results: the run's job summary (lists cut at 60 issues per section) and the `version-diff-report` artifact:

- `report.md`: the full report;
- `diff.json`: per project, the unclassified `new` / `gone` / `changed` issues, the classified ones with their verdict
  and entry index, and the `stale` entries.

```bash
gh run download <id> --repo orklah/psalm -n version-diff-report -D out/
```

## What counts as a difference

Both versions analyse the same commit, so issues are compared on type, file, line, column, message and severity.
An issue gone and one new of the same type on the same line (same column first) is **changed**, not a new and a gone one.

## Classifying

`classifications.json` holds `entries`, matched against every difference; the first matching entry wins.

```json
{
    "project": "symfony",
    "side": "new",
    "type": "PossiblyNullArgument",
    "file": "src/Symfony/Component/Console/Application.php",
    "text": "$this->getName()",
    "message": "Argument 1 of strlen cannot be null, possibly null value provided",
    "verdict": "false-positive",
    "note": "getName() is non-null after the isset() on line 120; 7.x loses the assertion through the ternary",
    "link": "https://github.com/vimeo/psalm/issues/12345"
}
```

- `side`: `new` (only in 7.x), `gone` (only in 6.x) or `changed` (message changed in place; matched on the new
  message).
- `type`, `side`, `verdict` and `note` are required. `project`, `file`, `text` (the selected code, whitespace
  collapsed) and `message` are optional: left out, they match anything, so one entry can cover a family. Use
  `message_pattern` (PCRE) instead of `message` for messages that vary.
- No line numbers: entries survive corpus bumps that shift code.

| side | verdicts |
|---|---|
| `new` | `false-positive` (7.x regression), `true-positive` (real issue 6.x missed), `expected` (intended change: new check, renamed issue, level), `unsure` |
| `gone` | `false-negative` (7.x regression), `fixed-false-positive` (6.x was wrong), `expected`, `unsure` |
| `changed` | `worse`, `better`, `neutral`, `unsure` |

An entry that matches nothing in a compared project is **stale**: a new issue classified earlier that 7.x no longer
reports, or a gone issue that 7.x reports again. The report counts them per project; remove them once understood.
`"flaky": true` marks an entry for issues that come and go between runs of the same Psalm version (6.21.3 picks
the `DuplicateClass` copy of WordPress's duplicated SimplePie classes by scan order): it is never stale.

Check the file before pushing:

```bash
php bin/version-diff.php validate classifications.json
```
