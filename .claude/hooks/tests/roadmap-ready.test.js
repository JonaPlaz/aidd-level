// Unit tests of the front-maturity rule (spec 08 § 11.9). Each case is a `ROADMAP.md`
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
const { scan, MAX_CONCURRENT_FRONTS } = require(path.join(hooksDir, 'roadmap-ready.js'));
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

// Default fixture: only one to-implement candidate (#2, chantier 2), spec present.
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
    },
    overrides,
  );
}

// -- Condition 2: dependency maturity --------------------------------------------------

// Case 1: dependency issue open → discarded.
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
  fs.rmSync(dir, { recursive: true, force: true });
})();

// Case 2: dependency issue CLOSED with a merged PR, ROADMAP état line still missing → satisfied.
(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | #1 | à faire |', '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |']);
  const r = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      dependencyIssueView: () => ({ state: 'CLOSED', closedByPullRequestsReferences: [{ state: 'MERGED' }] }),
    }),
  );
  assert.equal(r.ready.length, 1);
  assert.equal(r.ready[0].issue, 2);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// Case 3: dependency issue CLOSED without a merged PR → discarded, gap printed.
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

// -- Condition 5: overlapping outputs ----------------------------------------------------

// Case 5: outputs shared with a front already open (open PR referencing another issue) → discarded.
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

// -- Condition 3: lock present -----------------------------------------------------------

// Case 6: a `feature-locks/<n°>` file present → discarded.
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

// -- Condition 4: open PR already referencing the issue ----------------------------------

// Case 7: an open PR carries the number as a branch token → discarded.
(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 1 | Noyau | 00 | — | `src/A/` | — | **mergé** : #9 `abc1234` |', '| 2 | Front | 00 | 1 | `src/B/` | #2 | à faire |']);
  const r1 = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      openPRs: () => [{ number: 50, headRefName: 'feat/2-front', body: '', labels: [] }],
    }),
  );
  assert.equal(r1.ready.length, 0);
  // Case 7bis: or `Closes #<n°>` in the body, on an unrelated branch name.
  const r2 = scan(
    baseOpts(dir, {
      toImplementIssues: () => [{ number: 2, body: '', labels: [] }],
      openPRs: () => [{ number: 51, headRefName: 'chore/unrelated', body: 'Closes #2', labels: [] }],
    }),
  );
  assert.equal(r2.ready.length, 0);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// -- Condition 6: spec presence on origin/main -------------------------------------------

// Case 8: spec absent from origin/main → classed "à spécifier", never retained.
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

// Case 8bis: default implementation against a real disposable repo (spec 08 § 11.9: "dépôt
// jetable"), no injection — an origin remote genuinely lacking docs/specs/17-*.md.
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

// -- Global brakes --------------------------------------------------------------------------

// Case 9: an open PR labelled `blocked` → retained list empty, brake announced; same for an
// open issue labelled `blocked` (tested separately).
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

// Case 10: pause marker present → empty list, brake "pause"; `resume` (removing the marker)
// brings the list back.
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

// Case 11: `git fetch` failing (or exceeding FETCH_TIMEOUT) → empty list, brake "remote state
// unknown" — real failure against a disposable repo whose `origin` does not exist.
(() => {
  const dir = repo();
  execSync('git remote add origin /nonexistent/path/for/sure', { cwd: dir });
  writeRoadmap(dir, ['| 2 | Front | 00 | — | `src/B/` | #2 | à faire |']);
  const r = scan(Object.assign(baseOpts(dir), { fetchOriginMain: undefined, toImplementIssues: NO_GH_CALL }));
  assert.equal(r.remoteUnknown, true);
  assert.equal(r.ready.length, 0);
  fs.rmSync(dir, { recursive: true, force: true });
})();

// Case 12: five ready fronts → three retained, ascending order (MAX_CONCURRENT_FRONTS).
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

// Case 13: `gh` failing, `ROADMAP.md` unreadable, outside a git repository → empty output, exit 0.
(() => {
  const dir = repo();
  const r1 = scan(baseOpts(dir, { toImplementIssues: NO_GH_CALL }));
  // ROADMAP.md was never written in this fixture: unreadable → silent, empty.
  assert.equal(r1.ready.length, 0);
  assert.equal(r1.paused, false);
  assert.equal(r1.remoteUnknown, false);
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  const dir = repo();
  writeRoadmap(dir, ['| 2 | Front | 00 | — | `src/B/` | #2 | à faire |']);
  const r = scan(baseOpts(dir, { toImplementIssues: NO_GH_CALL })); // `gh` fails
  assert.equal(r.ready.length, 0);
  fs.rmSync(dir, { recursive: true, force: true });
})();

(() => {
  // Outside a git repository: the CLI must still print and exit 0.
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'roadmap-ready-nogit-'));
  const proc = spawnSync('node', [path.join(hooksDir, 'roadmap-ready.js')], { cwd: dir, env: Object.assign({}, process.env, { CLAUDE_PROJECT_DIR: dir }), encoding: 'utf8' });
  assert.equal(proc.status, 0);
  fs.rmSync(dir, { recursive: true, force: true });
})();

console.log('roadmap-ready: all unit tests pass');
