<?php

declare(strict_types=1);

/** Psalm 6.x vs 7.x issue diff over the perf corpus. See README.md. */

const USAGE = <<<'TXT'
usage: php version-diff.php fit-config <psalm.xml> <psalm config.xsd>
       php version-diff.php normalize <report.json> <project root> <out.issues.json>
       php version-diff.php report --in <dir> --classifications <file> --old <version> --new <version>
                                   --out-md <file> --out-summary <file> --out-json <file> [--run-url <url>]
       php version-diff.php validate <classifications.json>

TXT;

/** Verdicts allowed per side. */
const VERDICTS = [
    'new' => ['false-positive', 'true-positive', 'expected', 'unsure'],
    'gone' => ['false-negative', 'fixed-false-positive', 'expected', 'unsure'],
    'changed' => ['worse', 'better', 'neutral', 'unsure'],
];

const VERDICT_LABELS = [
    'false-positive' => 'faux positif 7.x',
    'true-positive' => 'vrai positif (manqué par 6.x)',
    'false-negative' => 'faux négatif 7.x',
    'fixed-false-positive' => 'faux positif 6.x corrigé',
    'expected' => 'changement voulu',
    'worse' => 'message moins bon',
    'better' => 'message meilleur',
    'neutral' => 'neutre',
    'unsure' => 'incertain',
];

/** Lists longer than this are cut in the job summary (GitHub caps it at 1 MiB); report.md has them all. */
const SUMMARY_LIST_LIMIT = 60;

function fail(string $message): never
{
    fwrite(STDERR, "version-diff: {$message}\n");
    exit(1);
}

/** @return array<mixed> */
function readJson(string $path): array
{
    $raw = is_file($path) ? file_get_contents($path) : false;
    if ($raw === false) {
        fail("cannot read {$path}");
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        fail("{$path} is not valid JSON: {$e->getMessage()}");
    }

    return is_array($data) ? $data : fail("{$path} is not a JSON object or array");
}

function writeJson(string $path, mixed $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json . "\n") === false) {
        fail("cannot write {$path}");
    }
}

/**
 * Drops the root attributes this Psalm's config.xsd does not know (e.g. ensureOverrideAttribute on an old 6.x):
 * Psalm rejects a config with unknown attributes.
 */
function fitConfig(string $configFile, string $xsdFile): void
{
    $xsd = file_get_contents($xsdFile);
    if ($xsd === false) {
        fail("cannot read {$xsdFile}");
    }

    preg_match_all('/<xs:attribute\s+name="([^"]+)"/', $xsd, $m);
    $known = array_flip($m[1]);

    $doc = new DOMDocument();
    if (!$doc->load($configFile)) {
        fail("cannot parse {$configFile}");
    }

    $root = $doc->documentElement ?? fail("{$configFile} has no root element");
    foreach (iterator_to_array($root->attributes) as $attribute) {
        if (!isset($known[$attribute->name])) {
            fwrite(STDERR, "version-diff: dropping {$attribute->name}, unknown to this Psalm\n");
            $root->removeAttribute($attribute->name);
        }
    }

    $doc->save($configFile);
}

/**
 * Deterministic issue list: project-relative paths, runtime numbers masked, the selected text kept (it identifies
 * an issue when the corpus ref moves and lines shift).
 */
function normalize(string $reportFile, string $root, string $outFile): int
{
    ini_set('memory_limit', '-1');
    $report = readJson($reportFile);
    $roots = array_values(array_unique(array_filter([rtrim($root, '/'), (string) realpath($root)])));
    $issues = [];
    foreach ($report as $issue) {
        $file = (string) ($issue['file_path'] ?? '');
        $message = (string) ($issue['message'] ?? '');
        $relative = null;
        foreach ($roots as $r) {
            if ($relative === null && str_starts_with($file, $r . '/')) {
                $relative = substr($file, strlen($r) + 1);
            }

            $message = str_replace($r . '/', '', $message);
        }

        $issues[] = [
            'type' => (string) ($issue['type'] ?? '?'),
            'severity' => (string) ($issue['severity'] ?? ''),
            'file' => $relative ?? (string) ($issue['file_name'] ?? $file),
            'line' => (int) ($issue['line_from'] ?? 0),
            'column' => (int) ($issue['column_from'] ?? 0),
            'message' => (string) preg_replace('/\d{9,}/', '#', $message),
            'text' => cutText((string) ($issue['selected_text'] ?? '')),
        ];
    }

    unset($report);
    usort($issues, static fn(array $a, array $b): int => [$a['file'], $a['line'], $a['column'], $a['type'],
        $a['message']] <=> [$b['file'], $b['line'], $b['column'], $b['type'], $b['message']]);
    writeJson($outFile, $issues);

    return count($issues);
}

function cutText(string $text): string
{
    $text = trim((string) preg_replace('/\s+/', ' ', $text));

    return mb_strlen($text) > 200 ? mb_substr($text, 0, 200) . '…' : $text;
}

/**
 * @param list<array<string, mixed>> $entries
 * @return list<string>
 */
function classificationProblems(array $entries): array
{
    $problems = [];
    foreach ($entries as $i => $e) {
        $where = "entry #{$i}";
        if (!is_array($e)) {
            $problems[] = "{$where}: not an object";
            continue;
        }

        $side = $e['side'] ?? null;
        if (!isset(VERDICTS[$side])) {
            $problems[] = "{$where}: side must be one of " . implode(', ', array_keys(VERDICTS));
            continue;
        }

        if (!is_string($e['type'] ?? null) || $e['type'] === '') {
            $problems[] = "{$where}: type is required";
        }

        if (!in_array($e['verdict'] ?? null, VERDICTS[$side], true)) {
            $problems[] = "{$where}: verdict for side {$side} must be one of " . implode(', ', VERDICTS[$side]);
        }

        if (!is_string($e['note'] ?? null) || trim($e['note']) === '') {
            $problems[] = "{$where}: note is required (why this verdict)";
        }

        if (isset($e['flaky']) && !is_bool($e['flaky'])) {
            $problems[] = "{$where}: flaky must be a boolean";
        }

        foreach (['project', 'file', 'text', 'message', 'message_pattern', 'link'] as $field) {
            if (isset($e[$field]) && !is_string($e[$field])) {
                $problems[] = "{$where}: {$field} must be a string";
            }
        }

        if (isset($e['message'], $e['message_pattern'])) {
            $problems[] = "{$where}: give message or message_pattern, not both";
        }

        if (isset($e['message_pattern']) && @preg_match($e['message_pattern'], '') === false) {
            $problems[] = "{$where}: message_pattern is not a valid PCRE pattern";
        }
    }

    return $problems;
}

/** @return list<array<string, mixed>> */
function loadClassifications(string $file): array
{
    $data = readJson($file);
    $entries = $data['entries'] ?? fail("{$file} has no entries list");
    $problems = classificationProblems($entries);
    if ($problems !== []) {
        fail("invalid {$file}:\n  " . implode("\n  ", $problems));
    }

    return array_values($entries);
}

/**
 * Every field the entry sets must match; project, file, text and message left out match anything.
 *
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $issue for side changed, the new issue (its old message is not matched)
 */
function entryMatches(array $entry, string $project, string $side, array $issue): bool
{
    if ($entry['side'] !== $side || $entry['type'] !== $issue['type']) {
        return false;
    }

    foreach (['project' => $project, 'file' => $issue['file'], 'text' => $issue['text'], 'message' => $issue['message']] as $field => $value) {
        if (isset($entry[$field]) && $entry[$field] !== $value) {
            return false;
        }
    }

    return !isset($entry['message_pattern']) || preg_match($entry['message_pattern'], $issue['message']) === 1;
}

function positionKey(array $issue): string
{
    return "{$issue['type']}\0{$issue['file']}\0{$issue['line']}\0{$issue['column']}";
}

function lineKey(array $issue): string
{
    return "{$issue['type']}\0{$issue['file']}\0{$issue['line']}";
}

function issueKey(array $issue): string
{
    return positionKey($issue) . "\0{$issue['message']}\0{$issue['severity']}";
}

/**
 * Multiset difference on (type, file, line, column, message, severity): both runs analyse the same commit, so a
 * position identifies an issue. An issue gone and one new of the same type on the same line is a changed one.
 *
 * @param list<array<string, mixed>> $old
 * @param list<array<string, mixed>> $new
 * @return array{new: list<array>, gone: list<array>, changed: list<array{old: array, new: array}>}
 */
function diffIssues(array $old, array $new): array
{
    $surplus = static function (array $from, array $against): array {
        $counts = [];
        foreach ($against as $issue) {
            $key = issueKey($issue);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $out = [];
        foreach ($from as $issue) {
            $key = issueKey($issue);
            if (($counts[$key] ?? 0) > 0) {
                $counts[$key]--;
            } else {
                $out[] = $issue;
            }
        }

        return $out;
    };

    $added = $surplus($new, $old);
    $removed = $surplus($old, $new);

    $changed = [];
    // Same column first, then same line: 7.x sometimes reports the same issue on another node of the line.
    foreach ([positionKey(...), lineKey(...)] as $keyOf) {
        $removedAt = [];
        foreach ($removed as $i => $issue) {
            $removedAt[$keyOf($issue)][] = $i;
        }

        $unpaired = [];
        foreach ($added as $issue) {
            $key = $keyOf($issue);
            if (($removedAt[$key] ?? []) !== []) {
                $i = array_shift($removedAt[$key]);
                $changed[] = ['old' => $removed[$i], 'new' => $issue];
                unset($removed[$i]);
            } else {
                $unpaired[] = $issue;
            }
        }

        $added = $unpaired;
    }

    return ['new' => $added, 'gone' => array_values($removed), 'changed' => $changed];
}

/**
 * @param array<string, string> $opts
 */
function report(array $opts): void
{
    ini_set('memory_limit', '-1');
    $entries = loadClassifications($opts['classifications']);
    $used = array_fill(0, count($entries), 0);
    // Candidates per side and type, in file order: the first match wins.
    $bySideAndType = [];
    foreach ($entries as $i => $entry) {
        $bySideAndType[$entry['side']][$entry['type']][] = $i;
    }

    $old = $opts['old'];
    $new = $opts['new'];

    // Layout from actions/download-artifact: <in>/<artifact>/<major>/<project>.json (+ .issues.json).
    $results = [];
    foreach (glob($opts['in'] . '/*/*/*.json') ?: [] as $file) {
        if (str_ends_with($file, '.issues.json')) {
            continue;
        }

        $r = readJson($file);
        $results[(string) $r['project']][(string) $r['major']] = $r + ['issues_file' => substr($file, 0, -5) . '.issues.json'];
    }

    ksort($results);
    $projects = [];
    foreach ($results as $name => $sides) {
        $p = [
            'project' => $name,
            'tag' => $sides['6']['tag'] ?? $sides['7']['tag'] ?? '?',
            'ref' => $sides['6']['ref'] ?? $sides['7']['ref'] ?? '?',
            'old' => slimResult($sides['6'] ?? null),
            'new' => slimResult($sides['7'] ?? null),
            'compared' => false,
        ];
        if (($sides['6']['status'] ?? '') === 'ok' && ($sides['7']['status'] ?? '') === 'ok') {
            $p['compared'] = true;
            $diff = diffIssues(readJson($sides['6']['issues_file']), readJson($sides['7']['issues_file']));
            foreach (['new', 'gone', 'changed'] as $side) {
                $p['classified'][$side] = [];
                $p['unclassified'][$side] = [];
                foreach ($diff[$side] as $item) {
                    $issue = $side === 'changed' ? $item['new'] : $item;
                    $hit = null;
                    foreach ($bySideAndType[$side][$issue['type']] ?? [] as $i) {
                        if (entryMatches($entries[$i], $name, $side, $issue)) {
                            $hit = $i;
                            break;
                        }
                    }

                    if ($hit === null) {
                        $p['unclassified'][$side][] = $item;
                    } else {
                        $used[$hit]++;
                        $p['classified'][$side][] = $item + ['verdict' => $entries[$hit]['verdict'], 'entry' => $hit];
                    }
                }
            }
        }

        $projects[$name] = $p;
    }

    // An entry matching nothing is stale, but only if a project it may cover was compared.
    $stale = [];
    foreach ($entries as $i => $entry) {
        if ($used[$i] > 0 || ($entry['flaky'] ?? false)) {
            continue;
        }

        $scope = $entry['project'] ?? null;
        $covered = $scope === null
            ? array_filter($projects, static fn(array $p): bool => $p['compared']) !== []
            : ($projects[$scope]['compared'] ?? false);
        if ($covered) {
            $stale[] = $entry + ['entry' => $i];
        }
    }

    $data = [
        'psalm' => ['old' => $old, 'new' => $new],
        'run_url' => $opts['run-url'] ?? null,
        'projects' => array_values($projects),
        'stale' => $stale,
    ];
    writeJson($opts['out-json'], $data);
    file_put_contents($opts['out-md'], renderMarkdown($data, null));
    file_put_contents($opts['out-summary'], renderMarkdown($data, SUMMARY_LIST_LIMIT));
}

/** @return array<string, mixed>|null */
function slimResult(?array $r): ?array
{
    if ($r === null) {
        return null;
    }

    unset($r['issues_file']);

    return $r;
}

function fmt(int $n): string
{
    return number_format($n, 0, ',', "\u{202F}");
}

function esc(string $text): string
{
    return str_replace(['|', '<', '>', '`'], ['\|', '&lt;', '&gt;', "'"], $text);
}

function where(array $issue): string
{
    return "`{$issue['file']}:{$issue['line']}:{$issue['column']}`";
}

/**
 * @param list<array> $items
 */
function issueList(array $items, string $side, ?int $limit): string
{
    $byType = [];
    foreach ($items as $item) {
        $byType[($side === 'changed' ? $item['new'] : $item)['type']][] = $item;
    }

    uksort($byType, static fn(string $a, string $b): int => [count($byType[$b]), $a] <=> [count($byType[$a]), $b]);
    $out = '';
    $shown = 0;
    foreach ($byType as $type => $list) {
        $out .= "\n**{$type}** (" . count($list) . ")\n\n";
        foreach ($list as $item) {
            if ($limit !== null && $shown >= $limit) {
                break 2;
            }

            $shown++;
            if ($side === 'changed') {
                $moved = $item['old']['column'] !== $item['new']['column'] ? " (colonne {$item['old']['column']})" : '';
                $out .= '- ' . where($item['new']) . "\n  - avant{$moved} : " . esc($item['old']['message'])
                    . "\n  - après : " . esc($item['new']['message']) . "\n";
            } else {
                $out .= '- ' . where($item) . ' ' . esc($item['message']) . "\n";
            }
        }
    }

    if ($limit !== null && $shown < count($items)) {
        $out .= "\n… " . fmt(count($items) - $shown) . " de plus dans `report.md` (artefact `version-diff-report`).\n";
    }

    return $out;
}

/** @param list<array> $classified */
function verdictCounts(array $classified): string
{
    $counts = [];
    foreach ($classified as $item) {
        $counts[$item['verdict']] = ($counts[$item['verdict']] ?? 0) + 1;
    }

    arsort($counts);

    return implode(', ', array_map(
        static fn(string $v, int $n): string => VERDICT_LABELS[$v] . ' ' . fmt($n),
        array_keys($counts),
        $counts,
    ));
}

function statusLine(string $version, ?array $r): string
{
    if ($r === null) {
        return "- Psalm {$version} : pas de résultat (job perdu ?)\n";
    }

    if ($r['status'] === 'ok') {
        return "- Psalm {$version} : " . fmt((int) $r['issue_count']) . " issues\n";
    }

    $tail = isset($r['log_tail']) && $r['log_tail'] !== ''
        ? "\n\n<details><summary>log</summary>\n\n```\n" . $r['log_tail'] . "\n```\n</details>\n"
        : '';

    return "- Psalm {$version} : 💥 {$r['status']} — " . esc((string) ($r['reason'] ?? '')) . "{$tail}\n";
}

function renderMarkdown(array $data, ?int $limit): string
{
    $old = $data['psalm']['old'];
    $new = $data['psalm']['new'];
    $md = "# Psalm {$old} → {$new}\n\n";
    if ($data['run_url'] !== null) {
        $md .= "[Run]({$data['run_url']}) · ";
    }

    $md .= "Corpus et refs : `bin/perf/corpus.yml` de vimeo/psalm master.\n\n";
    $md .= "| Projet | Version | {$old} | {$new} | Nouvelles non analysées | Disparues non analysées | Messages modifiés non analysés |\n";
    $md .= "|---|---|--:|--:|--:|--:|--:|\n";
    foreach ($data['projects'] as $p) {
        $count = static fn(?array $r): string => $r === null ? '—' : ($r['status'] === 'ok' ? fmt((int) $r['issue_count']) : "💥 {$r['status']}");
        $md .= "| {$p['project']} | {$p['tag']} | {$count($p['old'])} | {$count($p['new'])} | ";
        $md .= $p['compared']
            ? implode(' | ', array_map(static fn(string $s): string => fmt(count($p['unclassified'][$s])), ['new', 'gone', 'changed']))
            : '— | — | —';
        $md .= " |\n";
    }

    $staleBy = [];
    foreach ($data['stale'] as $entry) {
        $staleBy[$entry['project'] ?? '*'][$entry['side']][] = $entry;
    }

    foreach ($data['projects'] as $p) {
        $md .= "\n## {$p['project']} {$p['tag']}\n\n";
        $md .= statusLine($old, $p['old']) . statusLine($new, $p['new']);
        if (!$p['compared']) {
            $md .= "\nPas de comparaison : une des deux analyses n'a pas abouti.\n";
            continue;
        }

        foreach (['new' => 'Nouvelles', 'gone' => 'Disparues', 'changed' => 'Messages modifiés'] as $side => $label) {
            if ($p['classified'][$side] !== []) {
                $md .= "- {$label} déjà classées : " . fmt(count($p['classified'][$side])) . ' (' . verdictCounts($p['classified'][$side]) . ")\n";
            }
        }

        foreach ([
            'new' => 'Nouvelles issues non analysées',
            'gone' => 'Issues disparues non analysées',
            'changed' => 'Messages modifiés non analysés',
        ] as $side => $title) {
            $items = $p['unclassified'][$side];
            $md .= "\n### {$title} (" . fmt(count($items)) . ")\n";
            $md .= $items === [] ? "\nAucune.\n" : issueList($items, $side, $limit);
        }

        $md .= "\n" . staleLines($staleBy[$p['project']] ?? []);
    }

    if (isset($staleBy['*'])) {
        $md .= "\n## Classifications sans projet\n\n" . staleLines($staleBy['*']);
    }

    return $md;
}

/** @param array<string, list<array>> $bySide */
function staleLines(array $bySide): string
{
    $line = static function (string $label, array $entries): string {
        $text = "- {$label} : " . fmt(count($entries)) . "\n";
        foreach ($entries as $e) {
            $text .= "  - #{$e['entry']} {$e['type']} " . (isset($e['file']) ? "`{$e['file']}` " : '')
                . '(' . VERDICT_LABELS[$e['verdict']] . ') ' . esc($e['note']) . "\n";
        }

        return $text;
    };

    return $line('Nouvelles issues anciennement classifiées ayant disparu', $bySide['new'] ?? [])
        . $line('Issues disparues anciennement classifiées réapparues', $bySide['gone'] ?? [])
        . (isset($bySide['changed']) ? $line('Messages modifiés anciennement classifiés revenus à l\'identique ou disparus', $bySide['changed']) : '');
}

/**
 * @param list<string> $args
 * @return array<string, string>
 */
function options(array $args): array
{
    $opts = [];
    for ($i = 0; $i < count($args); $i++) {
        if (preg_match('/^--([a-z-]+)$/', $args[$i], $m) !== 1 || !isset($args[$i + 1])) {
            fail("unexpected argument '{$args[$i]}'\n" . USAGE);
        }

        $opts[$m[1]] = $args[++$i];
    }

    return $opts;
}

$args = array_slice($argv, 1);
$command = array_shift($args);
switch ($command) {
    case 'fit-config':
        count($args) === 2 || fail(USAGE);
        fitConfig(...$args);
        break;
    case 'normalize':
        count($args) === 3 || fail(USAGE);
        echo normalize(...$args), "\n";
        break;
    case 'validate':
        count($args) === 1 || fail(USAGE);
        echo count(loadClassifications($args[0])), " classifications OK\n";
        break;
    case 'report':
        $opts = options($args);
        foreach (['in', 'classifications', 'old', 'new', 'out-md', 'out-summary', 'out-json'] as $required) {
            isset($opts[$required]) || fail("missing --{$required}\n" . USAGE);
        }

        report($opts);
        break;
    default:
        fail(USAGE);
}
