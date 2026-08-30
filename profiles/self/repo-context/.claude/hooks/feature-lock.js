// Run-owned lock of the feature skill (spec 08 § Le cycle est imposé): one file per run under
// `<git common dir>/feature-locks/`, named by the issue number (or `trivial-<stamp>`), visible
// from every worktree of the repository, never committed. `guard-git` lets `gh pr create`
// through only when the current branch carries the id of an existing lock.
//   node .claude/hooks/feature-lock.js lock <id>     create (idempotent)
//   node .claude/hooks/feature-lock.js unlock <id>   remove this run's lock only
//   node .claude/hooks/feature-lock.js list          print the ids present
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { locksDir } = require('./lib');

const [action, id] = process.argv.slice(2);
const dir = locksDir(process.cwd());

if (action === 'list') {
  fs.mkdirSync(dir, { recursive: true });
  process.stdout.write(fs.readdirSync(dir).join('\n') + '\n');
  process.exit(0);
}
if (!id || !/^[A-Za-z0-9_-]+$/.test(id)) {
  process.stderr.write('usage: feature-lock.js lock|unlock <id> | list\n');
  process.exit(1);
}
const file = path.join(dir, id);
if (action === 'lock') {
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(file, `${new Date().toISOString()}\n`);
  process.stdout.write(`locked ${id}\n`);
} else if (action === 'unlock') {
  fs.rmSync(file, { force: true });
  process.stdout.write(`unlocked ${id}\n`);
} else {
  process.stderr.write('usage: feature-lock.js lock|unlock <id> | list\n');
  process.exit(1);
}
