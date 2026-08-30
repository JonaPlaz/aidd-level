// Violation tests of guard-git.js (spec 08 § 4): each rule is exercised by the command it
// must refuse and by the neighbouring command it must let through. Runs in a throwaway git
// repository so locks and branches never touch the real one. `node .claude/hooks/tests/guard-git.test.js`.
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execSync, spawnSync } = require('node:child_process');

const hooks = path.resolve(__dirname, '..');
const repo = fs.mkdtempSync(path.join(os.tmpdir(), 'guard-git-'));
execSync('git init -q -b main && git -c user.email=t@t -c user.name=t commit -q --allow-empty -m init', { cwd: repo });

function run(command, cwd = repo) {
  const r = spawnSync('node', [path.join(hooks, 'guard-git.js')], { input: JSON.stringify({ tool_input: { command }, cwd }), encoding: 'utf8' });
  return r.status === 0 ? 'pass' : `block: ${r.stderr.trim()}`;
}
function lock(action, id) {
  execSync(`node ${path.join(hooks, 'feature-lock.js')} ${action} ${id}`, { cwd: repo });
}

// gh pr create: refused without a lock owned by the current branch, flags before the subcommand included.
execSync('git checkout -q -b feat/40-frontier-note', { cwd: repo });
assert.match(run('gh pr create --title x'), /^block: guard-git: gh pr create outside/);
assert.match(run('gh pr -R JonaPlaz/aidd-level create --title x'), /^block:/);
assert.match(run('gh pr --repo=JonaPlaz/aidd-level create'), /^block:/);
lock('lock', '41');
assert.match(run('gh pr create --title x'), /^block:/, 'a lock of another run authorizes nothing');
lock('lock', '40');
assert.equal(run('gh pr create --title x'), 'pass');
assert.equal(run('git push -u origin feat/40-x && gh pr create --label to-review'), 'pass');
lock('unlock', '40');
assert.match(run('gh pr create --title x'), /^block:/, 'unlock removes only this run\'s lock');
lock('unlock', '41');
execSync('git checkout -q -b trivial/readme-line', { cwd: repo });
lock('lock', 'trivial-1');
assert.equal(run('gh pr create'), 'pass');
lock('unlock', 'trivial-1');

// gh pr merge: --auto / --disable-auto only.
assert.match(run('gh pr merge 1 --squash'), /^block: guard-git: synchronous gh pr merge/);
assert.match(run('gh pr -R o/r merge 1 --squash --delete-branch'), /^block:/);
assert.equal(run('gh pr merge 1 --auto --squash --delete-branch'), 'pass');
assert.equal(run('gh pr merge 1 --disable-auto'), 'pass');
assert.equal(run('gh pr view 1 --json state'), 'pass');

// git push: bare --force refused, --force-with-lease admitted.
assert.match(run('git push --force'), /^block: guard-git: bare --force/);
assert.match(run('git fetch origin && git push -f'), /^block:/);
assert.equal(run('git push --force-with-lease'), 'pass');

// git commit: Domain and Infrastructure never together.
fs.mkdirSync(path.join(repo, 'src/Domain'), { recursive: true });
fs.mkdirSync(path.join(repo, 'src/Infrastructure'), { recursive: true });
fs.writeFileSync(path.join(repo, 'src/Domain/A.php'), '<?php\n');
fs.writeFileSync(path.join(repo, 'src/Infrastructure/B.php'), '<?php\n');
execSync('git add src', { cwd: repo });
assert.match(run('git commit -m x'), /^block: guard-git: a commit must not touch/);
execSync('git reset -q src/Infrastructure', { cwd: repo });
assert.equal(run('git commit -m x'), 'pass');

fs.rmSync(repo, { recursive: true, force: true });
console.log('guard-git: all violation tests pass');
