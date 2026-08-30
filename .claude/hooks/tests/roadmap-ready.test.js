// Unit tests of the front-maturity rule (spec 08 § 11.9) and of the correction pass on
// PR #51 (fourteen points, each referenced by number below). Each case is a `ROADMAP.md`
// fixture plus a `gh` state injected by plain objects/functions — never the network. Disk
// and git facts genuinely local (locks, pause marker, fetch, spec-on-origin) run against a
// disposable git repository, like guard-git.test.js.
// `node .claude/hooks/tests/roadmap-ready.test.js`.
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execSync, spawnSync } = require('node:child_process');

const hooksDir = path.resolve(__dirname, '..');
const roadmapReady = require(path.join(hooksDir, 'roadmap-ready.js'));
const {
  scan,
  render,
  MAX_CONCURRENT_FRONTS,
  parseSpecPrefixes,
  parseDependsOn,
  branchIssueNumbers,
  lockRoadmapSelector,
  unlockRoadmapSelector,
  sweepStaleRoadmapLock,
  roadmapSelectorLockFile,
} = roadmapReady;
const { locksDir, pausedMarker } = require(path.join(hooksDir, 'lib.js'));

function header() {
  return '| # | Chantier | Spec | Dépend de | Sorties | Issue | État |\n|---|---|---|---|---|---|---|\n';
}

function repo() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'roadmap-ready-'));
  execSync('git init -q -b main && git -c user.email=t@t -c user.name=t commit -q --allow-empty -m init', { cwd: dir });
  return dir;
}

function writeRoadmap(dir, rows) {
  fs.writeFileSync(path.join(dir, 'ROADMAP.md'), header() + rows.join('\n') + '\n');
}

const NO_GH_CALL = () => {
  throw new Error('unexpected gh call in this test');
};

function baseOpts(dir, overrides) {
  return Object.assign(
    {
      root: dir,
      fetchOriginMain: () => true,
      specOnOriginMain: () => true,
      blockedPROpen: () => false,
      blockedIssueOpen: () => false,
      openPRs: () => [],
      dependencyIssueView: NO_GH_CALL,
      confirmPRMerged: () => null, // unknown by default: never silently confirms nor denies
    },
    overrides,
  );
}

// -- Point 0: closedByPullRequestsReferences carries no per-entry `state` -------------------

// Case 1: dependency issue open → discarded, no gap (point 8: OPEN is an ordinary wait).
(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | #1 | à faire |', '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |']);
  const r = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      dependencyIssueView: () => ({ state: 'OPEN', closedByPullRequestsReferences: [] }),
    }),
  );
  assert.equal(r.ready.length, 0);
  assert.equal(r.notReady.length, 1);
  assert.equal(r.gaps.length, 0, 'an open dependency is not a gap (point 8)');
  fs.rmSync(dir, { recursive: true, force: true });
})();

// Case 2: dependency issue CLOSED with a reference (the real gh shape has no `state` field on
// the reference itself, point 0), ROADMAP état line still missing → satisfied.
(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | #1 | à faire |', '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |']);
  const r = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      dependencyIssueView: () => ({ state: 'CLOSED', closedByPullRequestsReferences: [{ id: 'PR_1', number: 9, repository: 'aidd-level', url: 'https://x' }] }),
    }),
  );
  assert.equal(r.ready.length, 1);
  assert.equal(r.ready[0].issue, 2);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// Case 2bis: an optional confirmation contradicting the reference (pr view state !== MERGED)
// overrides it back to "not merged".
(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | #1 | à faire |', '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |']);
  const r = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      dependencyIssueView: () => ({ state: 'CLOSED', closedByPullRequestsReferences: [{ number: 9 }] }),
      confirmPRMerged: () => false,
    }),
  );
  assert.equal(r.ready.length, 0);
  assert.equal(r.gaps.length, 1);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// Case 3: dependency issue CLOSED with no reference at all → discarded, gap printed.
(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | #1 | à faire |', '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |']);
  const r = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      dependencyIssueView: () => ({ state: 'CLOSED', closedByPullRequestsReferences: [] }),
    }),
  );
  assert.equal(r.ready.length, 0);
  assert.equal(r.gaps.length, 1);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// Point 8: a `gh` read failure on a single dependency is "not ready, no gap" — never a global
// scan failure, never counted as an écart.
(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | #1 | à faire |', '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |']);
  const r = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      dependencyIssueView: () => null,
    }),
  );
  assert.equal(r.ready.length, 0);
  assert.equal(r.degraded, false, 'a single dependency read failure never degrades the whole scan');
  assert.equal(r.gaps.length, 0, 'a read failure is not a gap');
  fs.rmSync(dir, { recursive: true, force: true });
})();

// Case 4: dependency without issue (column —), two lines, the last says mergé → satisfied by repli.
(() => {
  const dir = repo();
  writeRoadmap(dir, [
    '| 1 | Noyau | 00 | — | `src/A/` | — | à faire |',
    '| 1 | Noyau | 00 | — | — | — | **mergé** : #9 `abc1234` |',
    '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |',
  ]);
  const r = scan(baseOpts(dir, { toImplementIssues: () => [{ number: 2, body: '', labels: [] }] }));
  assert.equal(r.ready.length, 1);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// -- Point 1: only the spec identifier(s) at the head of a cell, never every two-digit number --

(() => {
  assert.deepEqual(parseSpecPrefixes('00 § 4.1'), ['00']);
  assert.deepEqual(parseSpecPrefixes('03 § Médiane sur la borne, 02 § Ratio absent'), ['03', '02']);
  assert.deepEqual(parseSpecPrefixes('08 § 11 (2026-08-30)'), ['08'], 'the year is not a spec number');
  assert.deepEqual(parseSpecPrefixes('02–05 « non prouvé »'), ['02'], 'only the head of the range, never both bounds');
})();

// -- Point 12: an unrecognised "Dépend de" fragment fails the candidate closed --------------

(() => {
  assert.deepEqual(parseDependsOn('—'), { ok: true, deps: [] });
  assert.deepEqual(parseDependsOn('2, 3, 4'), { ok: true, deps: [2, 3, 4] });
  assert.deepEqual(parseDependsOn('2–4'), { ok: true, deps: [2, 3, 4] });
  assert.equal(parseDependsOn('1 / 2').ok, false, 'an unrecognised fragment must not parse as "no dependencies"');
})();

(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 2 | Front | 00 | 1 / 2 | `src/B/` | #2 | à faire |']);
  const r = scan(baseOpts(dir, { toImplementIssues: () => [{ number: 2, body: '', labels: [] }] }));
  assert.equal(r.ready.length, 0);
  assert.match(r.notReady[0].reason, /illisible/);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// -- Point 11: outputs absent or "—" make overlap undecidable — never silently "no overlap" -

(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | — | **mergé** : #9 `abc1234` |', '| 2 | Front | 00 | 1 | — | #2 | à faire |']);
  const r = scan(baseOpts(dir, { toImplementIssues: () => [{ number: 2, body: '', labels: [] }] }));
  assert.equal(r.ready.length, 0);
  assert.match(r.notReady[0].reason, /indécidable/);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// -- Condition 5 (unchanged): outputs shared with a front already open --------------------

(() => {
  const dir = repo();
  writeRoadmap(dir, [
    '| 1 | Noyau | 00 | — | `src/A/` | — | **mergé** : #9 `abc1234` |',
    '| 2 | Front | 00 | 1 | `CLAUDE.md` | #2 | à faire |',
    '| 3 | Autre front | 00 | 1 | `CLAUDE.md` | #3 | à faire |',
  ]);
  const r = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      openPRs: () => [{ number: 99, headRefName: 'feat/3-other', body: '', labels: [] }],
    }),
  );
  assert.equal(r.ready.length, 0);
  assert.match(r.notReady[0].reason, /sorties partagées/);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// -- Condition 3 (unchanged): lock present --------------------------------------------------

(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | — | **mergé** : #9 `abc1234` |', '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |']);
  fs.mkdirSync(locksDir(dir), { recursive: true });
  fs.writeFileSync(path.join(locksDir(dir), '2'), 'locked\n');
  const r = scan(baseOpts(dir, { toImplementIssues: () => [{ number: 2, body: '', labels: [] }] }));
  assert.equal(r.ready.length, 0);
  assert.match(r.notReady[0].reason, /verrou/);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// -- Point 10: every numeric token of a branch name is compared, not just the first ---------

(() => {
  assert.deepEqual(branchIssueNumbers('feat/40-slug'), [40]);
  assert.deepEqual(branchIssueNumbers('feat/40-12-fix'), [40, 12]);
  assert.deepEqual(branchIssueNumbers('docs/spec-40'), [40]);
  assert.deepEqual(branchIssueNumbers('chore/unrelated'), []);
})();

(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | — | **mergé** : #9 `abc1234` |', '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |']);
  const r1 = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      openPRs: () => [{ number: 50, headRefName: 'feat/2-front', body: '', labels: [] }],
    }),
  );
  assert.equal(r1.ready.length, 0, 'branch token exactly matching the candidate');
  const r2 = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      openPRs: () => [{ number: 51, headRefName: 'feat/40-2-fix', body: '', labels: [] }],
    }),
  );
  assert.equal(r2.ready.length, 0, 'a second numeric token in the branch name must also be compared');
  const r3 = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      openPRs: () => [{ number: 52, headRefName: 'chore/unrelated', body: 'Closes #2', labels: [] }],
    }),
  );
  assert.equal(r3.ready.length, 0, 'Closes #<n> in the body');
  fs.rmSync(dir, { recursive: true, force: true });
})();

// -- Condition 6 (unchanged): spec presence on origin/main -----------------------------------

(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 2 | Front | 99 | — | `src/B/` | #2 | à faire |']);
  const r = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      specOnOriginMain: () => false,
    }),
  );
  assert.equal(r.ready.length, 0);
  assert.equal(r.toSpecify.length, 1);
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  const origin = repo();
  const dir = repo();
  execSync(`git remote add origin ${origin}`, { cwd: dir });
  execSync('git fetch --quiet origin main', { cwd: dir });
  writeRoadmap(dir, ['| 2 | Front | 17 | — | `src/B/` | #2 | à faire |']);
  const r = scan(
    Object.assign(baseOpts(dir), {
      specOnOriginMain: undefined,
      fetchOriginMain: undefined,
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
    }),
  );
  assert.equal(r.ready.length, 0);
  assert.equal(r.toSpecify.length, 1);
  fs.rmSync(dir, { recursive: true, force: true });
  fs.rmSync(origin, { recursive: true, force: true });
})();

// -- Global brakes (unchanged) ---------------------------------------------------------------

(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 2 | Front | 00 | — | `src/B/` | #2 | à faire |']);
  const rPr = scan(baseOpts(dir, { blockedPROpen: () => true, toImplementIssues: NO_GH_CALL }));
  assert.equal(rPr.ready.length, 0);
  assert.match(rPr.blockedReason, /PR/);
  const rIssue = scan(baseOpts(dir, { blockedIssueOpen: () => true, toImplementIssues: NO_GH_CALL }));
  assert.equal(rIssue.ready.length, 0);
  assert.match(rIssue.blockedReason, /issue/);
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | — | **mergé** : #9 `abc1234` |', '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |']);
  fs.writeFileSync(pausedMarker(dir), `${new Date().toISOString()} pause\n`);
  const paused = scan(baseOpts(dir, { toImplementIssues: NO_GH_CALL }));
  assert.equal(paused.paused, true);
  assert.equal(paused.ready.length, 0);
  fs.rmSync(pausedMarker(dir), { force: true });
  const resumed = scan(baseOpts(dir, { toImplementIssues: () => [{ number: 2, body: '', labels: [] }] }));
  assert.equal(resumed.paused, false);
  assert.equal(resumed.ready.length, 1);
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  const dir = repo();
  execSync('git remote add origin /nonexistent/path/for/sure', { cwd: dir });
  writeRoadmap(dir, ['| 2 | Front | 00 | — | `src/B/` | #2 | à faire |']);
  const r = scan(Object.assign(baseOpts(dir), { fetchOriginMain: undefined, toImplementIssues: NO_GH_CALL }));
  assert.equal(r.remoteUnknown, true);
  assert.equal(r.ready.length, 0);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// -- Point 4: order of service is by chantier number, not by issue number -------------------

(() => {
  const dir = repo();
  const rows = [];
  for (let n = 10; n <= 14; n++) rows.push(`| ${n} | Front ${n} | 00 | — | \`src/F${n}/\` | #${n} | à faire |`);
  writeRoadmap(dir, rows);
  const r = scan(
    baseOpts(dir, {
      toImplementIssues: () => [10, 11, 12, 13, 14].map((number) => ({ number, body: '', labels: [] })),
    }),
  );
  assert.equal(r.ready.length, MAX_CONCURRENT_FRONTS);
  assert.deepEqual(
    r.ready.map((c) => c.issue),
    [10, 11, 12],
  );
  assert.equal(r.notReady.length, 2);
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  // Chantier numbers deliberately in the opposite order of their issue numbers.
  const dir = repo();
  writeRoadmap(dir, [
    '| 5 | Chantier cinq | 00 | — | `src/F5/` | #20 | à faire |',
    '| 3 | Chantier trois | 00 | — | `src/F3/` | #21 | à faire |',
  ]);
  const r = scan(
    baseOpts(dir, {
      toImplementIssues: () => [
        { number: 20, body: '', labels: [] },
        { number: 21, body: '', labels: [] },
      ],
    }),
  );
  assert.deepEqual(
    r.ready.map((c) => c.chantier),
    [3, 5],
    'ascending chantier order',
  );
  assert.deepEqual(
    r.ready.map((c) => c.issue),
    [21, 20],
    'issue #21 (chantier 3) is served before issue #20 (chantier 5)',
  );
  fs.rmSync(dir, { recursive: true, force: true });
})();

// -- Point 7: gh issue/pr list calls request --limit 500 -------------------------------------

(() => {
  const src = fs.readFileSync(path.join(hooksDir, 'roadmap-ready.js'), 'utf8');
  const ghListCalls = [...src.matchAll(/gh(?:Json)?\(\[('issue'|'pr'), 'list'[^\]]*\]/g)];
  assert.ok(ghListCalls.length >= 4, 'expected at least the four gh …list… call sites');
  for (const call of ghListCalls) {
    assert.match(call[0], /'--limit', GH_LIST_LIMIT/, `missing --limit on: ${call[0]}`);
  }
  assert.match(src, /GH_LIST_LIMIT = '500'/);
})();

// -- Point 13: a degraded scan (ROADMAP.md unreadable, gh failing, deadline exceeded) prints
// as such, never as "aucun front prêt" ------------------------------------------------------

(() => {
  const dir = repo(); // no ROADMAP.md written
  const r = scan(baseOpts(dir, { toImplementIssues: NO_GH_CALL }));
  assert.equal(r.ready.length, 0);
  assert.equal(r.degraded, true, 'ROADMAP.md missing must degrade the scan, not "no front ready"');
  assert.match(render(r), /scan dégradé/);
  assert.doesNotMatch(render(r), /aucun front prêt/);
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 2 | Front | 00 | — | `src/B/` | #2 | à faire |']);
  const r = scan(baseOpts(dir, { toImplementIssues: NO_GH_CALL })); // `gh` throws
  assert.equal(r.ready.length, 0);
  assert.equal(r.degraded, true);
  assert.match(render(r), /scan dégradé/);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// Point 3: the shared deadline already spent when the scan starts degrades it too.
(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 2 | Front | 00 | — | `src/B/` | #2 | à faire |']);
  const past = Date.now() - 10 * 60_000; // now() so far in the past that remaining() is 0 throughout
  const r = scan(
    baseOpts(dir, {
      now: past,
      toImplementIssues: () => {
        throw new Error('ROADMAP_SCAN_TIMEOUT_MS dépassé');
      },
    }),
  );
  assert.equal(r.ready.length, 0);
  assert.equal(r.degraded, true);
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  // Outside a git repository: the CLI must still print and exit 0.
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'roadmap-ready-nogit-'));
  const proc = spawnSync('node', [path.join(hooksDir, 'roadmap-ready.js')], { cwd: dir, env: Object.assign({}, process.env, { CLAUDE_PROJECT_DIR: dir }), encoding: 'utf8' });
  assert.equal(proc.status, 0);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// -- Points 2 and 5: the roadmap-selector exclusive lock -------------------------------------

(() => {
  const dir = repo();
  const a = lockRoadmapSelector(dir);
  assert.equal(a.ok, true);
  const b = lockRoadmapSelector(dir); // a second, concurrent acquisition attempt
  assert.equal(b.ok, false, 'a live roadmap-selector lock refuses a second acquisition');
  assert.ok(fs.existsSync(roadmapSelectorLockFile(dir)));
  unlockRoadmapSelector(dir);
  assert.ok(!fs.existsSync(roadmapSelectorLockFile(dir)));
  const c = lockRoadmapSelector(dir);
  assert.equal(c.ok, true, 'unlock frees the selector for the next run');
  assert.notEqual(a.id, c.id, 'ids are unique (timestamp + pid)');
  unlockRoadmapSelector(dir);
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  // A lock left by a process that no longer exists is swept before anything else acts on it.
  const dir = repo();
  fs.mkdirSync(locksDir(dir), { recursive: true });
  const deadPid = 999999; // exceedingly unlikely to be a live pid
  fs.writeFileSync(roadmapSelectorLockFile(dir), `roadmap-1-${deadPid}\n${new Date().toISOString()}\n${deadPid}\n`);
  const swept = sweepStaleRoadmapLock(dir);
  assert.ok(swept, 'an orphaned lock (dead pid) is reported as swept');
  assert.ok(!fs.existsSync(roadmapSelectorLockFile(dir)));
  const r = lockRoadmapSelector(dir);
  assert.equal(r.ok, true, 'the selector is free again after the sweep');
  unlockRoadmapSelector(dir);
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  // A lock older than ROADMAP_SELECTOR_STALE_MS, even with a live pid, is swept too.
  const dir = repo();
  fs.mkdirSync(locksDir(dir), { recursive: true });
  const oldTs = Date.now() - (roadmapReady.ROADMAP_SELECTOR_STALE_MS + 60_000);
  fs.writeFileSync(roadmapSelectorLockFile(dir), `roadmap-old-${process.pid}\n${new Date(oldTs).toISOString()}\n${process.pid}\n`);
  const swept = sweepStaleRoadmapLock(dir);
  assert.ok(swept, 'an aged lock is swept even if its pid is still alive');
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  // `scan()` itself sweeps a stale selector lock before doing anything else (point 5): a
  // SessionStart-only session must not let a dead lock linger.
  const dir = repo();
  writeRoadmap(dir, ['| 2 | Front | 00 | — | `src/B/` | #2 | à faire |']);
  fs.mkdirSync(locksDir(dir), { recursive: true });
  const deadPid = 999998;
  fs.writeFileSync(roadmapSelectorLockFile(dir), `roadmap-2-${deadPid}\n${new Date().toISOString()}\n${deadPid}\n`);
  scan(baseOpts(dir, { toImplementIssues: () => [{ number: 2, body: '', labels: [] }] }));
  assert.ok(!fs.existsSync(roadmapSelectorLockFile(dir)), 'scan() swept the stale selector lock');
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  // The CLI `lock` sub-command exits non-zero for the loser (point 2: "le perdant sort").
  const dir = repo();
  const win = spawnSync('node', [path.join(hooksDir, 'roadmap-ready.js'), 'lock'], { cwd: dir, env: Object.assign({}, process.env, { CLAUDE_PROJECT_DIR: dir }), encoding: 'utf8' });
  assert.equal(win.status, 0);
  const lose = spawnSync('node', [path.join(hooksDir, 'roadmap-ready.js'), 'lock'], { cwd: dir, env: Object.assign({}, process.env, { CLAUDE_PROJECT_DIR: dir }), encoding: 'utf8' });
  assert.notEqual(lose.status, 0, 'a second concurrent `lock` exits non-zero');
  const unlock = spawnSync('node', [path.join(hooksDir, 'roadmap-ready.js'), 'unlock'], { cwd: dir, env: Object.assign({}, process.env, { CLAUDE_PROJECT_DIR: dir }), encoding: 'utf8' });
  assert.equal(unlock.status, 0);
  fs.rmSync(dir, { recursive: true, force: true });
})();

console.log('roadmap-ready: all unit tests pass');
