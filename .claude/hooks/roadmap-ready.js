// Front maturity — single implementation of spec 08 § 11.3 (chantier 17). Called by the
// `SessionStart` hook (to display) and by the `/roadmap` skill (to decide) — same script,
// never a rule in both places. Sub-commands: `scan` (default), `pause [reason]`, `resume`.
// Never blocks: on any failure it prints what it can and exits 0 (spec 08 § 11.3).
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { execFileSync, execSync } = require('node:child_process');
const { locksDir, pausedMarker } = require('./lib');

// -- Named thresholds, each justified where it decides something (AGENTS.md rule 2) --

// Integration limit, not a platform one: `ci` is required in `strict` mode (each merge puts
// the others behind, rebase every time) and the Codex review quota empties in an evening
// (spec 08 § 8, § 11.4). Assumed adaptation, revised at the journal once a full pass runs
// without `blocked`.
const MAX_CONCURRENT_FRONTS = 3;

// `git fetch origin main` budget before any scan (spec 08 § 11.1): without it `origin/main`
// is as old as Jonathan's last command and condition 2 below judges a dead state.
const FETCH_TIMEOUT_MS = 10_000;

// Total scan budget at session start (spec 08 § 11.1): the platform's documented
// `SessionStart` default of 600 s is unacceptable at startup; 20 s covers the bounded fetch
// above plus a handful of `gh` calls.
const ROADMAP_SCAN_TIMEOUT_MS = 20_000;

// = 2 × MAX_CONCURRENT_FRONTS (spec 08 § 11.6.6): two full waves of fronts: beyond that the
// branch is manifestly too slow for the queue and re-running it only occupies a slot. Not
// enforced by this script (the front counts its own mechanical rebases) — recorded here
// because it is cited by `.claude/agents/front.md`.
const REBASE_MECHANICAL_MAX = 6;

// -- Root / disk facts --------------------------------------------------------------------

function repoRoot() {
  return process.env.CLAUDE_PROJECT_DIR || process.cwd();
}

function defaultIsPaused(root) {
  return fs.existsSync(pausedMarker(root));
}

function defaultLockedIds(root) {
  try {
    return fs.readdirSync(locksDir(root));
  } catch {
    return [];
  }
}

// The only git write this script (or the session, per spec 08 § 11.1) performs: refreshing
// remote-tracking refs. Never touches a checkout.
function defaultFetchOriginMain(root) {
  try {
    execSync('git fetch --quiet origin main', { cwd: root, timeout: FETCH_TIMEOUT_MS, stdio: ['ignore', 'ignore', 'ignore'] });
    return true;
  } catch {
    return false;
  }
}

function defaultSpecOnOriginMain(root, prefix) {
  try {
    const files = execSync('git ls-tree -r --name-only origin/main -- docs/specs', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    return files.split('\n').some((f) => path.basename(f).startsWith(`${prefix}-`));
  } catch {
    return false;
  }
}

function ghJson(args) {
  const out = execFileSync('gh', args, { encoding: 'utf8', timeout: ROADMAP_SCAN_TIMEOUT_MS, stdio: ['ignore', 'pipe', 'ignore'] });
  return JSON.parse(out || '[]');
}

function defaultToImplementIssues() {
  return ghJson(['issue', 'list', '--label', 'to-implement', '--state', 'open', '--json', 'number,title,body,labels']);
}

function defaultOpenPRs() {
  return ghJson(['pr', 'list', '--state', 'open', '--json', 'number,headRefName,body,labels']);
}

function defaultBlockedPROpen() {
  return ghJson(['pr', 'list', '--state', 'open', '--label', 'blocked', '--json', 'number']).length > 0;
}

function defaultBlockedIssueOpen() {
  return ghJson(['issue', 'list', '--state', 'open', '--label', 'blocked', '--json', 'number']).length > 0;
}

function defaultDependencyIssueView(issueNumber) {
  try {
    return ghJson(['issue', 'view', String(issueNumber), '--json', 'state,closedByPullRequestsReferences']);
  } catch {
    return null;
  }
}

// -- ROADMAP.md parsing (spec 08 § 11.3 condition 2, § 11.9) -------------------------------

// One row per markdown table line of ROADMAP.md; header and separator rows are skipped
// because their first cell is not a number. Columns: # | Chantier | Spec | Dépend de |
// Sorties | Issue | État.
function parseRoadmap(text) {
  const rows = [];
  for (const line of text.split('\n')) {
    if (!line.trim().startsWith('|')) continue;
    const cells = line
      .split('|')
      .slice(1, -1)
      .map((c) => c.trim());
    if (cells.length < 7 || !/^\d+$/.test(cells[0])) continue;
    rows.push({ num: Number(cells[0]), title: cells[1], spec: cells[2], dependsOn: cells[3], outputs: cells[4], issue: cells[5], state: cells[6] });
  }
  return rows;
}

// ROADMAP.md is append-only: the last row of a chantier wins for its état; "Sorties" and
// "Issue" keep the value of the last row that declared one (état-only rows write `—`) —
// spec 08 § 11.3 condition 2.
function latestByChantier(rows) {
  const map = new Map();
  for (const row of rows) {
    const prev = map.get(row.num);
    map.set(row.num, {
      num: row.num,
      spec: row.spec,
      dependsOn: row.dependsOn !== '—' ? row.dependsOn : prev ? prev.dependsOn : row.dependsOn,
      outputs: row.outputs !== '—' ? row.outputs : prev ? prev.outputs : row.outputs,
      issue: row.issue !== '—' ? row.issue : prev ? prev.issue : row.issue,
      state: row.state,
    });
  }
  return map;
}

function parseDependsOn(cell) {
  if (!cell || cell === '—') return [];
  const out = [];
  for (const part of cell.split(',')) {
    const trimmed = part.trim();
    const range = /^(\d+)\s*[-–]\s*(\d+)$/.exec(trimmed);
    if (range) {
      for (let n = Number(range[1]); n <= Number(range[2]); n++) out.push(n);
    } else if (/^\d+$/.test(trimmed)) {
      out.push(Number(trimmed));
    }
  }
  return out;
}

function parseIssueNumber(cell) {
  const m = /#(\d+)/.exec(cell || '');
  return m ? Number(m[1]) : null;
}

// All two-digit spec numbers cited at the head of a comma-separated "Spec" cell
// ("00 § 4.1" → ["00"], "03 § Médiane…, 02 § Ratio…" → ["03", "02"]).
function parseSpecPrefixes(cell) {
  if (!cell) return [];
  return [...new Set((cell.match(/\b(\d{2})\b/g) || []))];
}

function parseOutputs(cell) {
  if (!cell || cell === '—') return [];
  const backticked = [...cell.matchAll(/`([^`]+)`/g)].map((m) => m[1]);
  return backticked.length ? backticked : cell.split(',').map((s) => s.trim()).filter(Boolean);
}

function normalizePath(p) {
  return p.replace(/\/$/, '');
}

function outputsOverlap(a, b) {
  const na = normalizePath(a);
  const nb = normalizePath(b);
  return na === nb || na.startsWith(`${nb}/`) || nb.startsWith(`${na}/`);
}

function anyOverlap(outputsA, outputsSet) {
  return outputsA.some((a) => [...outputsSet].some((b) => outputsOverlap(a, b)));
}

// Branch token: `(^|[-/])<n>([-/]|$)` — same rule as guard-git.js.
function branchIssueNumber(headRefName) {
  const m = new RegExp('(?:^|[-/])(\\d+)(?:[-/]|$)').exec(headRefName || '');
  return m ? Number(m[1]) : null;
}

function closesNumbers(body) {
  return [...((body || '').matchAll(/closes\s+#(\d+)/gi))].map((m) => Number(m[1]));
}

// -- Core, pure decision (spec 08 § 11.3) ---------------------------------------------------
//
// All GitHub-backed facts are injected so this function is testable without the network
// (spec 08 § 11.9): `toImplementIssues`, `openPRs`, `blockedPROpen`, `blockedIssueOpen` and
// `dependencyIssueView` come from `gh` in production, from fixtures in tests. `fetchOriginMain`,
// `specOnOriginMain`, `isPaused` and `lockedIds` touch real disk/git and default to the real
// thing; tests run them against a disposable repository (like guard-git.test.js) rather than
// mocking git itself.
function scan(opts) {
  const root = opts.root || repoRoot();
  const fetchOriginMain = opts.fetchOriginMain || (() => defaultFetchOriginMain(root));
  const specOnOriginMain = opts.specOnOriginMain || ((prefix) => defaultSpecOnOriginMain(root, prefix));
  const isPaused = opts.isPaused || (() => defaultIsPaused(root));
  const lockedIds = opts.lockedIds || (() => defaultLockedIds(root));
  const toImplementIssues = opts.toImplementIssues || defaultToImplementIssues;
  const openPRs = opts.openPRs || defaultOpenPRs;
  const blockedPROpen = opts.blockedPROpen || defaultBlockedPROpen;
  const blockedIssueOpen = opts.blockedIssueOpen || defaultBlockedIssueOpen;
  const dependencyIssueView = opts.dependencyIssueView || defaultDependencyIssueView;

  const result = { paused: false, remoteUnknown: false, blockedReason: null, ready: [], toSpecify: [], gaps: [], notReady: [] };

  if (isPaused()) {
    result.paused = true;
    return result;
  }
  if (blockedPROpen()) {
    result.blockedReason = 'une PR ouverte porte le label blocked';
    return result;
  }
  if (blockedIssueOpen()) {
    result.blockedReason = 'une issue ouverte porte le label blocked';
    return result;
  }
  if (!fetchOriginMain()) {
    result.remoteUnknown = true;
    return result;
  }

  let roadmapText;
  try {
    roadmapText = fs.readFileSync(path.join(root, 'ROADMAP.md'), 'utf8');
  } catch {
    return result; // ROADMAP.md unreadable/absent: empty, silent (spec 08 § 11.3 cas dégradés).
  }

  const chantiers = latestByChantier(parseRoadmap(roadmapText));
  const issueToChantier = new Map();
  for (const c of chantiers.values()) {
    const n = parseIssueNumber(c.issue);
    if (n !== null) issueToChantier.set(n, c.num);
  }

  let issues, prs;
  try {
    issues = toImplementIssues();
    prs = openPRs();
  } catch {
    return result; // `gh` off-line or failing: silent, empty (condition 13).
  }

  const lockedSet = new Set(lockedIds());
  const openPRIssueNumbers = new Set();
  const busyChantierNums = new Set();
  for (const pr of prs) {
    const nums = new Set([branchIssueNumber(pr.headRefName), ...closesNumbers(pr.body)].filter((n) => n !== null));
    for (const n of nums) {
      openPRIssueNumbers.add(n);
      if (issueToChantier.has(n)) busyChantierNums.add(issueToChantier.get(n));
    }
  }
  for (const id of lockedSet) {
    if (/^\d+$/.test(id)) {
      const n = Number(id);
      if (issueToChantier.has(n)) busyChantierNums.add(issueToChantier.get(n));
    }
  }
  const busyOutputs = new Set();
  for (const num of busyChantierNums) {
    const c = chantiers.get(num);
    if (c) parseOutputs(c.outputs).forEach((o) => busyOutputs.add(o));
  }

  const candidates = [];
  for (const issue of issues) {
    if ((issue.labels || []).some((l) => l.name === 'blocked')) continue; // condition 1
    const chantierNum = issueToChantier.get(issue.number);
    if (chantierNum === undefined) continue; // no ROADMAP row for this issue: silent, skipped
    const chantier = chantiers.get(chantierNum);

    // Condition 6: spec present on origin/main.
    const prefixes = parseSpecPrefixes(chantier.spec);
    if (prefixes.length === 0 || !prefixes.every((p) => specOnOriginMain(p))) {
      result.toSpecify.push({ issue: issue.number, chantier: chantierNum });
      continue;
    }

    // Condition 2: each cited dependency is merged, GitHub state first, ROADMAP repli second.
    let blockedByDependency = null;
    for (const depNum of parseDependsOn(chantier.dependsOn)) {
      const dep = chantiers.get(depNum);
      if (!dep) {
        blockedByDependency = `dépendance ${depNum} absente de ROADMAP.md`;
        break;
      }
      const depIssueNum = parseIssueNumber(dep.issue);
      if (depIssueNum !== null) {
        const view = dependencyIssueView(depIssueNum);
        const merged = !!(view && view.state === 'CLOSED' && Array.isArray(view.closedByPullRequestsReferences) && view.closedByPullRequestsReferences.some((pr) => pr.state === 'MERGED'));
        if (!merged) {
          blockedByDependency = `dépendance ${depNum} (issue #${depIssueNum}) fermée sans PR mergée`;
          result.gaps.push(blockedByDependency);
          break;
        }
      } else if (!/mergé/i.test(dep.state || '')) {
        blockedByDependency = `dépendance ${depNum} (repli ROADMAP.md) non mergée`;
        break;
      }
    }
    if (blockedByDependency) {
      result.notReady.push({ issue: issue.number, chantier: chantierNum, reason: blockedByDependency });
      continue;
    }

    // Condition 3: no lock on this issue.
    if (lockedSet.has(String(issue.number))) {
      result.notReady.push({ issue: issue.number, chantier: chantierNum, reason: `verrou feature-locks/${issue.number} présent` });
      continue;
    }

    // Condition 4: no open PR for this issue.
    if (openPRIssueNumbers.has(issue.number)) {
      result.notReady.push({ issue: issue.number, chantier: chantierNum, reason: `PR ouverte pour #${issue.number}` });
      continue;
    }

    candidates.push({ issue: issue.number, chantier: chantierNum, outputs: parseOutputs(chantier.outputs) });
  }

  candidates.sort((a, b) => a.issue - b.issue); // deterministic order of service (§ 11.4)

  const availableSlots = Math.max(0, MAX_CONCURRENT_FRONTS - busyChantierNums.size);
  const retainedOutputs = new Set(busyOutputs);
  for (const candidate of candidates) {
    if (result.ready.length >= availableSlots) {
      result.notReady.push({ issue: candidate.issue, chantier: candidate.chantier, reason: `plafond MAX_CONCURRENT_FRONTS=${MAX_CONCURRENT_FRONTS} atteint` });
      continue;
    }
    if (anyOverlap(candidate.outputs, retainedOutputs)) {
      result.notReady.push({ issue: candidate.issue, chantier: candidate.chantier, reason: 'sorties partagées avec un front déjà ouvert ou retenu' });
      continue;
    }
    candidate.outputs.forEach((o) => retainedOutputs.add(o));
    result.ready.push(candidate);
  }

  return result;
}

// -- Report rendering (the table injected by SessionStart, read by the skill) --------------

function render(result) {
  const lines = [];
  if (result.paused) {
    lines.push('roadmap en pause (marqueur roadmap-paused présent).');
    return lines.join('\n');
  }
  if (result.blockedReason) {
    lines.push(`roadmap gelée : ${result.blockedReason}.`);
    return lines.join('\n');
  }
  if (result.remoteUnknown) {
    lines.push("roadmap gelée : état distant inconnu (git fetch origin main a échoué ou dépassé le délai).");
    return lines.join('\n');
  }
  if (result.ready.length === 0) {
    lines.push('aucun front prêt.');
  } else {
    lines.push('fronts prêts et retenus :');
    for (const c of result.ready) lines.push(`  - #${c.issue} (chantier ${c.chantier})`);
  }
  if (result.notReady.length > 0) {
    lines.push('fronts non retenus :');
    for (const c of result.notReady) lines.push(`  - #${c.issue} (chantier ${c.chantier}) — ${c.reason}`);
  }
  if (result.toSpecify.length > 0) {
    lines.push('à spécifier (spec absente de origin/main) :');
    for (const c of result.toSpecify) lines.push(`  - #${c.issue} (chantier ${c.chantier})`);
  }
  if (result.gaps.length > 0) {
    lines.push('écarts :');
    for (const g of result.gaps) lines.push(`  - ${g}`);
  }
  return lines.join('\n');
}

// -- CLI -------------------------------------------------------------------------------------

function main() {
  const [sub, arg] = process.argv.slice(2);
  const root = repoRoot();
  try {
    if (sub === 'pause') {
      fs.mkdirSync(path.dirname(pausedMarker(root)), { recursive: true });
      fs.writeFileSync(pausedMarker(root), `${new Date().toISOString()} ${arg || 'pause'}\n`);
      process.stdout.write(`roadmap en pause (${arg || 'pause'}).\n`);
    } else if (sub === 'resume') {
      fs.rmSync(pausedMarker(root), { force: true });
      process.stdout.write('roadmap reprise.\n');
    } else {
      process.stdout.write(`${render(scan({ root }))}\n`);
    }
  } catch (err) {
    // Never blocks (spec 08 § 11.3): print what happened, exit 0 regardless.
    process.stdout.write(`roadmap-ready: erreur non bloquante (${err.message}).\n`);
  }
  process.exit(0);
}

if (require.main === module) main();

module.exports = {
  MAX_CONCURRENT_FRONTS,
  FETCH_TIMEOUT_MS,
  ROADMAP_SCAN_TIMEOUT_MS,
  REBASE_MECHANICAL_MAX,
  parseRoadmap,
  latestByChantier,
  parseDependsOn,
  parseIssueNumber,
  parseSpecPrefixes,
  parseOutputs,
  outputsOverlap,
  scan,
  render,
};
