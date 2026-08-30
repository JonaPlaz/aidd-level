// Front maturity — single implementation of spec 08 § 11.3 (chantier 17). Called by the
// `SessionStart` hook (to display) and by the `/roadmap` skill (to decide) — same script,
// never a rule in both places. Sub-commands: `scan` (default), `pause [reason]`, `resume`,
// `lock` / `unlock` (the roadmap-selector exclusive lock, spec 08 § 11.5).
// Never blocks on a scan: on any failure it prints what it can and exits 0. `lock` is the one
// exception — its exit code is the mutual-exclusion signal the skill acts on (loser exits).
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
// above plus a handful of `gh` calls. A single deadline is set once per scan and every `gh`
// call receives what remains of it (chantier 17, correction #51): a scan that overruns is
// degraded (empty output), never silently truncated.
const ROADMAP_SCAN_TIMEOUT_MS = 20_000;

// = 2 × MAX_CONCURRENT_FRONTS (spec 08 § 11.6.6): two full waves of fronts: beyond that the
// branch is manifestly too slow for the queue and re-running it only occupies a slot. Not
// enforced by this script (the front counts its own mechanical rebases) — recorded here
// because it is cited by `.claude/agents/front.md`.
const REBASE_MECHANICAL_MAX = 6;

// A `roadmap-selector` lock older than this, or whose recorded pid is no longer running, has
// no live owner (the invocation that posed it died in route) — spec 08 § 11.5: "un verrou
// roadmap-* trouvé au démarrage d'une session … est signalé au journal et retiré". Three scan
// budgets: long enough that a live selection window never trips it, short enough that a dead
// one does not wedge the queue for long.
const ROADMAP_SELECTOR_STALE_MS = ROADMAP_SCAN_TIMEOUT_MS * 3;

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
// remote-tracking refs. Never touches a checkout. Bounded by whatever is left of the scan's
// single deadline (correction #51, point 3), capped at FETCH_TIMEOUT_MS.
function defaultFetchOriginMain(root, timeoutMs) {
  if (timeoutMs <= 0) return false;
  try {
    execSync('git fetch --quiet origin main', { cwd: root, timeout: Math.min(FETCH_TIMEOUT_MS, timeoutMs), stdio: ['ignore', 'ignore', 'ignore'] });
    return true;
  } catch {
    return false;
  }
}

function defaultSpecOnOriginMain(root, prefix, timeoutMs) {
  if (timeoutMs <= 0) return false;
  try {
    const files = execSync('git ls-tree -r --name-only origin/main -- docs/specs', { cwd: root, timeout: timeoutMs, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    return files.split('\n').some((f) => path.basename(f).startsWith(`${prefix}-`));
  } catch {
    return false;
  }
}

// Every `gh issue list` / `gh pr list` call caps its page at 500 (chantier 17 correction #51,
// point 7): the default of 30 would silently under-count a busy backlog and make condition 4
// (open-PR overlap) and the global `blocked` brakes look emptier than they are.
const GH_LIST_LIMIT = '500';

// Throws when the scan's shared deadline (ROADMAP_SCAN_TIMEOUT_MS) is already spent: caught by
// `scan()`'s outer try/catch, which reports a degraded (empty) result rather than a partial
// one (correction #51, points 3 and 13).
function ghJson(args, timeoutMs) {
  if (timeoutMs <= 0) throw new Error('ROADMAP_SCAN_TIMEOUT_MS dépassé');
  const out = execFileSync('gh', args, { encoding: 'utf8', timeout: timeoutMs, stdio: ['ignore', 'pipe', 'ignore'] });
  return JSON.parse(out || '[]');
}

function defaultToImplementIssues(timeoutMs) {
  return ghJson(['issue', 'list', '--label', 'to-implement', '--state', 'open', '--limit', GH_LIST_LIMIT, '--json', 'number,title,body,labels'], timeoutMs);
}

function defaultOpenPRs(timeoutMs) {
  return ghJson(['pr', 'list', '--state', 'open', '--limit', GH_LIST_LIMIT, '--json', 'number,headRefName,body,labels'], timeoutMs);
}

function defaultBlockedPROpen(timeoutMs) {
  return ghJson(['pr', 'list', '--state', 'open', '--label', 'blocked', '--limit', GH_LIST_LIMIT, '--json', 'number'], timeoutMs).length > 0;
}

function defaultBlockedIssueOpen(timeoutMs) {
  return ghJson(['issue', 'list', '--state', 'open', '--label', 'blocked', '--limit', GH_LIST_LIMIT, '--json', 'number'], timeoutMs).length > 0;
}

// Verified by Jonathan on issue #40 (correction #51, point 0): `closedByPullRequestsReferences`
// entries carry no `state` field (`{id, number, repository, url}`); GitHub only lists a
// reference there once the PR has actually merged and closed the issue. A per-dependency `gh`
// failure is tolerated here (returns null) — spec 08 § 11.3 condition 2 treats a read failure
// as "not ready, no gap" (point 8), never as a global scan failure. Running out of the scan's
// shared deadline is different: it degrades the whole scan (point 3), so it is re-thrown.
function defaultDependencyIssueView(issueNumber, timeoutMs) {
  if (timeoutMs <= 0) throw new Error('ROADMAP_SCAN_TIMEOUT_MS dépassé');
  try {
    return ghJson(['issue', 'view', String(issueNumber), '--json', 'state,closedByPullRequestsReferences'], timeoutMs);
  } catch (err) {
    if (/dépassé/.test(err.message || '')) throw err;
    return null;
  }
}

// Optional confirmation (spec 08 § 11.3 condition 2, "optionnellement confirmer") that the
// first referencing PR is really `MERGED`. Returns `null` (unknown, does not override a
// positive signal) on any `gh` failure or deadline exhaustion — the reference itself already
// is strong evidence per point 0.
function defaultConfirmPRMerged(prNumber, timeoutMs) {
  if (timeoutMs <= 0) return null;
  try {
    const view = ghJson(['pr', 'view', String(prNumber), '--json', 'state'], timeoutMs);
    return !!(view && view.state === 'MERGED');
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

// Returns `{ ok: false }` on any fragment it does not recognise ("1 / 2", free text…):
// correction #51 point 12 — an unparseable "Dépend de" cell must fail the candidate closed,
// never be silently read as "no dependencies".
function parseDependsOn(cell) {
  if (!cell || cell === '—') return { ok: true, deps: [] };
  const out = [];
  for (const part of cell.split(',')) {
    const trimmed = part.trim();
    const range = /^(\d+)\s*[-–]\s*(\d+)$/.exec(trimmed);
    if (range) {
      for (let n = Number(range[1]); n <= Number(range[2]); n++) out.push(n);
    } else if (/^\d+$/.test(trimmed)) {
      out.push(Number(trimmed));
    } else {
      return { ok: false, deps: [] };
    }
  }
  return { ok: true, deps: out };
}

function parseIssueNumber(cell) {
  const m = /#(\d+)/.exec(cell || '');
  return m ? Number(m[1]) : null;
}

// Only the spec identifier(s) at the head of each comma-separated segment of the "Spec" cell
// (correction #51, point 1): "00 § 4.1" → ["00"]; "03 § Médiane…, 02 § Ratio…" → ["03", "02"];
// "08 § 11 (2026-08-30)" → ["08"] (the year is not a spec number); a range such as
// "02–05 « non prouvé »" → ["02"] only — the second bound is not "en tête de cellule".
function parseSpecPrefixes(cell) {
  if (!cell || cell === '—') return [];
  const out = [];
  for (const part of cell.split(',')) {
    const m = /^\s*(\d{2})\b/.exec(part);
    if (m) out.push(m[1]);
  }
  return [...new Set(out)];
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

// Every numeric token of a branch name, delimited by `-` or `/` (correction #51, point 10):
// `feat/40-slug` → [40]; `feat/40-12-fix` → [40, 12] — a branch can carry more than one
// number and each must be compared, the same way guard-git.js tests each lock id against the
// whole branch rather than assuming a single token.
function branchIssueNumbers(headRefName) {
  if (!headRefName) return [];
  return headRefName
    .split(/[-/]/)
    .filter((t) => /^\d+$/.test(t))
    .map(Number);
}

function closesNumbers(body) {
  return [...((body || '').matchAll(/closes\s+#(\d+)/gi))].map((m) => Number(m[1]));
}

// -- Roadmap-selector lock (spec 08 § 11.5) -------------------------------------------------
//
// One shared file, `<git common dir>/feature-locks/roadmap-selector`, created with the `wx`
// flag (atomic exclusive create — the OS refuses if it already exists, unlike the read-then-
// write race of a directory listing). Correction #51, point 2: this is the actual mutual-
// exclusion primitive between two concurrent `/roadmap` invocations; `feature-lock.js lock`
// stays idempotent (by design, for /feature runs) and is not reused here. The file's content
// (a millisecond timestamp and this process's pid) is what `guard-git.js` already treats as
// "a lock starting with roadmap-" (authorizes no branch), and what the staleness sweep below
// reads.
function roadmapSelectorLockFile(root) {
  return path.join(locksDir(root), 'roadmap-selector');
}

function readRoadmapSelectorLock(root) {
  try {
    const content = fs.readFileSync(roadmapSelectorLockFile(root), 'utf8');
    const [id, iso, pidLine] = content.trim().split('\n');
    return { id, ts: Date.parse(iso), pid: Number(pidLine) };
  } catch {
    return null;
  }
}

function isProcessAlive(pid) {
  if (!Number.isInteger(pid) || pid <= 0) return false;
  try {
    process.kill(pid, 0);
    return true;
  } catch {
    return false;
  }
}

// Removes an abandoned `roadmap-selector` lock (invocation dead in route) before anything
// else can act on it — spec 08 § 11.5: "signalé au journal et retiré". Returns the id removed,
// or null if nothing was stale. Called first thing by `scan()` and by `lock` (point 5).
function sweepStaleRoadmapLock(root, now = Date.now()) {
  const lock = readRoadmapSelectorLock(root);
  if (!lock) return null;
  const aged = Number.isFinite(lock.ts) && now - lock.ts > ROADMAP_SELECTOR_STALE_MS;
  const orphaned = !isProcessAlive(lock.pid);
  if (aged || orphaned) {
    fs.rmSync(roadmapSelectorLockFile(root), { force: true });
    return lock.id || 'roadmap-selector';
  }
  return null;
}

// Unique id (millisecond timestamp + pid, correction #51 point 2) recorded inside the lock —
// the mutual exclusion itself is the file's existence, not the id. The pid recorded is the
// *parent* process (`process.ppid`): `roadmap-ready.js lock` is itself a short-lived `node`
// invocation that exits the instant it has written the file, so its own pid would already be
// dead by the time a later invocation checks liveness (correction #51, point 5 versus point 2
// interacting) — the session that spawned it is the real, long-lived owner of the selection
// window.
function lockRoadmapSelector(root, now = Date.now()) {
  sweepStaleRoadmapLock(root, now);
  fs.mkdirSync(locksDir(root), { recursive: true });
  const ownerPid = process.ppid || process.pid;
  const id = `roadmap-${now}-${ownerPid}`;
  try {
    fs.writeFileSync(roadmapSelectorLockFile(root), `${id}\n${new Date(now).toISOString()}\n${ownerPid}\n`, { flag: 'wx' });
    return { ok: true, id };
  } catch (err) {
    if (err.code === 'EEXIST') return { ok: false, id: null };
    throw err;
  }
}

function unlockRoadmapSelector(root) {
  fs.rmSync(roadmapSelectorLockFile(root), { force: true });
}

// -- Core, pure decision (spec 08 § 11.3) ---------------------------------------------------
//
// All GitHub-backed facts are injected so this function is testable without the network
// (spec 08 § 11.9): `toImplementIssues`, `openPRs`, `blockedPROpen`, `blockedIssueOpen`,
// `dependencyIssueView` and `confirmPRMerged` come from `gh` in production, from fixtures in
// tests. `fetchOriginMain`, `specOnOriginMain`, `isPaused` and `lockedIds` touch real disk/git
// and default to the real thing; tests run them against a disposable repository (like
// guard-git.test.js) rather than mocking git itself.
//
// A single deadline (`ROADMAP_SCAN_TIMEOUT_MS` from `now`) is set once per scan; every `gh`
// call below receives what remains of it. Anything that fails once the global brakes (pause,
// blocked, remote unknown) are cleared — ROADMAP.md unreadable, a `gh` listing failing, the
// deadline running out mid-scan — degrades the whole scan to an explicitly empty, explicitly
// labelled result (correction #51, points 3 and 13): never a partial list, never confused with
// "aucun front prêt" (a real, completed scan that genuinely found nothing).
function scan(opts) {
  const root = opts.root || repoRoot();
  const now = opts.now || Date.now();
  const deadline = now + ROADMAP_SCAN_TIMEOUT_MS;
  const remaining = () => Math.max(0, deadline - Date.now());

  const fetchOriginMain = opts.fetchOriginMain || (() => defaultFetchOriginMain(root, remaining()));
  const specOnOriginMain = opts.specOnOriginMain || ((prefix) => defaultSpecOnOriginMain(root, prefix, remaining()));
  const isPaused = opts.isPaused || (() => defaultIsPaused(root));
  const lockedIds = opts.lockedIds || (() => defaultLockedIds(root));
  const toImplementIssues = opts.toImplementIssues || (() => defaultToImplementIssues(remaining()));
  const openPRs = opts.openPRs || (() => defaultOpenPRs(remaining()));
  const blockedPROpen = opts.blockedPROpen || (() => defaultBlockedPROpen(remaining()));
  const blockedIssueOpen = opts.blockedIssueOpen || (() => defaultBlockedIssueOpen(remaining()));
  const dependencyIssueView = opts.dependencyIssueView || ((n) => defaultDependencyIssueView(n, remaining()));
  const confirmPRMerged = opts.confirmPRMerged || ((n) => defaultConfirmPRMerged(n, remaining()));

  // Point 5: an abandoned roadmap-selector lock is swept before anything else, scan included —
  // a session that only ever calls `scan` (SessionStart) must not let a dead lock linger.
  sweepStaleRoadmapLock(root, now);

  const empty = () => ({ paused: false, remoteUnknown: false, blockedReason: null, degraded: false, degradedReason: null, ready: [], toSpecify: [], gaps: [], notReady: [] });

  const result = empty();
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

  try {
    return computeReadiness();
  } catch (err) {
    // Nothing partial survives (point 13): a fresh, explicitly degraded empty result.
    const degraded = empty();
    degraded.degraded = true;
    degraded.degradedReason = err.message || 'scan interrompu';
    return degraded;
  }

  function computeReadiness() {
    const out = empty();

    let roadmapText;
    try {
      roadmapText = fs.readFileSync(path.join(root, 'ROADMAP.md'), 'utf8');
    } catch {
      throw new Error('ROADMAP.md illisible ou absent');
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
    } catch (err) {
      throw new Error(`lecture gh en échec ou délai dépassé (${err.message})`);
    }

    const lockedSet = new Set(lockedIds());
    const openPRIssueNumbers = new Set();
    const busyChantierNums = new Set();
    for (const pr of prs) {
      const nums = new Set([...branchIssueNumbers(pr.headRefName), ...closesNumbers(pr.body)]);
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
      if (remaining() <= 0) throw new Error('ROADMAP_SCAN_TIMEOUT_MS dépassé pendant le scan');
      if ((issue.labels || []).some((l) => l.name === 'blocked')) continue; // condition 1
      const chantierNum = issueToChantier.get(issue.number);
      if (chantierNum === undefined) continue; // no ROADMAP row for this issue: silent, skipped
      const chantier = chantiers.get(chantierNum);

      // Condition 6: spec present on origin/main.
      const prefixes = parseSpecPrefixes(chantier.spec);
      if (prefixes.length === 0 || !prefixes.every((p) => specOnOriginMain(p))) {
        out.toSpecify.push({ issue: issue.number, chantier: chantierNum });
        continue;
      }

      // Point 12: an unparseable "Dépend de" cell fails the candidate closed.
      const parsedDeps = parseDependsOn(chantier.dependsOn);
      if (!parsedDeps.ok) {
        out.notReady.push({ issue: issue.number, chantier: chantierNum, reason: `« Dépend de » illisible : "${chantier.dependsOn}"` });
        continue;
      }

      // Condition 2: each cited dependency is merged, GitHub state first, ROADMAP repli
      // second. Point 8: an OPEN dependency is an ordinary wait (no gap); a read failure is
      // "not ready, no gap" either; only a CLOSED issue without a merged PR is a gap.
      let blockedByDependency = null;
      for (const depNum of parsedDeps.deps) {
        const dep = chantiers.get(depNum);
        if (!dep) {
          blockedByDependency = `dépendance ${depNum} absente de ROADMAP.md`;
          break;
        }
        const depIssueNum = parseIssueNumber(dep.issue);
        if (depIssueNum === null) {
          if (!/mergé/i.test(dep.state || '')) {
            blockedByDependency = `dépendance ${depNum} (repli ROADMAP.md) non mergée`;
            break;
          }
          continue;
        }
        const view = dependencyIssueView(depIssueNum);
        if (!view) {
          blockedByDependency = `dépendance ${depNum} (issue #${depIssueNum}) : lecture gh impossible`;
          break; // point 8: read failure, not a gap
        }
        if (view.state !== 'CLOSED') {
          blockedByDependency = `dépendance ${depNum} (issue #${depIssueNum}) encore ouverte`;
          break; // point 8: ordinary wait, not a gap
        }
        // Point 0: a reference only ever appears once the PR that carries it has merged and
        // closed the issue — no per-reference `state` field to read.
        const references = Array.isArray(view.closedByPullRequestsReferences) ? view.closedByPullRequestsReferences : [];
        let merged = references.length > 0;
        if (merged && references[0] && references[0].number != null) {
          const confirmed = confirmPRMerged(references[0].number);
          if (confirmed === false) merged = false;
        }
        if (!merged) {
          blockedByDependency = `dépendance ${depNum} (issue #${depIssueNum}) fermée sans PR mergée`;
          out.gaps.push(blockedByDependency);
          break;
        }
      }
      if (blockedByDependency) {
        out.notReady.push({ issue: issue.number, chantier: chantierNum, reason: blockedByDependency });
        continue;
      }

      // Condition 3: no lock on this issue.
      if (lockedSet.has(String(issue.number))) {
        out.notReady.push({ issue: issue.number, chantier: chantierNum, reason: `verrou feature-locks/${issue.number} présent` });
        continue;
      }

      // Condition 4: no open PR for this issue.
      if (openPRIssueNumbers.has(issue.number)) {
        out.notReady.push({ issue: issue.number, chantier: chantierNum, reason: `PR ouverte pour #${issue.number}` });
        continue;
      }

      // Point 11: outputs absent or "—" make the overlap check undecidable — never silently
      // "no overlap because there is nothing to compare".
      const outputs = parseOutputs(chantier.outputs);
      if (outputs.length === 0) {
        out.notReady.push({ issue: issue.number, chantier: chantierNum, reason: 'sorties absentes de ROADMAP.md : chevauchement indécidable' });
        continue;
      }

      candidates.push({ issue: issue.number, chantier: chantierNum, outputs });
    }

    // Point 4: deterministic order of service is by chantier number (the graph's own order),
    // not by issue number — the two need not coincide.
    candidates.sort((a, b) => a.chantier - b.chantier);

    const availableSlots = Math.max(0, MAX_CONCURRENT_FRONTS - busyChantierNums.size);
    const retainedOutputs = new Set(busyOutputs);
    for (const candidate of candidates) {
      if (out.ready.length >= availableSlots) {
        out.notReady.push({ issue: candidate.issue, chantier: candidate.chantier, reason: `plafond MAX_CONCURRENT_FRONTS=${MAX_CONCURRENT_FRONTS} atteint` });
        continue;
      }
      if (anyOverlap(candidate.outputs, retainedOutputs)) {
        out.notReady.push({ issue: candidate.issue, chantier: candidate.chantier, reason: 'sorties partagées avec un front déjà ouvert ou retenu' });
        continue;
      }
      candidate.outputs.forEach((o) => retainedOutputs.add(o));
      out.ready.push(candidate);
    }

    return out;
  }
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
  // Point 13: a degraded scan (ROADMAP.md unreadable, gh listing failed, deadline exceeded)
  // prints as such — never as "aucun front prêt", which claims a completed scan found nothing.
  if (result.degraded) {
    lines.push(`scan dégradé : ${result.degradedReason}. Sortie vide, rien n'est ouvert.`);
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
  if (sub === 'lock') {
    // Point 2: atomic acquire-or-fail — the loser exits non-zero, exactly what `/roadmap`
    // step 0 acts on ("le perdant sort").
    try {
      const swept = sweepStaleRoadmapLock(root);
      if (swept) process.stdout.write(`roadmap-selector: verrou abandonné retiré (${swept}).\n`);
      const r = lockRoadmapSelector(root);
      if (r.ok) {
        process.stdout.write(`locked ${r.id}\n`);
        process.exit(0);
      }
      process.stdout.write('roadmap-selector: déjà verrouillé par une autre invocation.\n');
      process.exit(1);
    } catch (err) {
      process.stderr.write(`roadmap-ready: erreur (${err.message}).\n`);
      process.exit(1);
    }
  } else if (sub === 'unlock') {
    unlockRoadmapSelector(root);
    process.stdout.write('roadmap-selector unlocked\n');
    process.exit(0);
  } else if (sub === 'pause') {
    fs.mkdirSync(path.dirname(pausedMarker(root)), { recursive: true });
    fs.writeFileSync(pausedMarker(root), `${new Date().toISOString()} ${arg || 'pause'}\n`);
    process.stdout.write(`roadmap en pause (${arg || 'pause'}).\n`);
    process.exit(0);
  } else if (sub === 'resume') {
    fs.rmSync(pausedMarker(root), { force: true });
    process.stdout.write('roadmap reprise.\n');
    process.exit(0);
  } else {
    try {
      process.stdout.write(`${render(scan({ root }))}\n`);
    } catch (err) {
      // A scan never blocks (spec 08 § 11.3): print what happened, exit 0 regardless.
      process.stdout.write(`roadmap-ready: erreur non bloquante (${err.message}).\n`);
    }
    process.exit(0);
  }
}

if (require.main === module) main();

module.exports = {
  MAX_CONCURRENT_FRONTS,
  FETCH_TIMEOUT_MS,
  ROADMAP_SCAN_TIMEOUT_MS,
  REBASE_MECHANICAL_MAX,
  ROADMAP_SELECTOR_STALE_MS,
  parseRoadmap,
  latestByChantier,
  parseDependsOn,
  parseIssueNumber,
  parseSpecPrefixes,
  parseOutputs,
  outputsOverlap,
  branchIssueNumbers,
  roadmapSelectorLockFile,
  lockRoadmapSelector,
  unlockRoadmapSelector,
  sweepStaleRoadmapLock,
  scan,
  render,
};
