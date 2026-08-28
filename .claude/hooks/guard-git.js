// PreToolUse (Bash): a `git commit` must not stage src/Domain and src/Infrastructure
// together (AGENTS.md rule 7), and must never include a private path; a `git push` must
// never use a bare --force (--force-with-lease is the only accepted form, for rebases).
// Private paths are read from .worktreeinclude, which lists exactly the gitignored
// files carried into worktrees; nothing private is spelled out in this file.
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { execSync } = require('node:child_process');
const { readInput, projectRoot, block } = require('./lib');

const input = readInput();
const command = (input.tool_input && input.tool_input.command) || '';

// Bare --force, -f (alone or bundled), and force-prefixed refspecs (`+HEAD:branch`).
if (/\bgit\s+push\b/.test(command) && /(\s--force(\s|$)|\s-f(\s|$)|\s-[a-zA-Z]*f[a-zA-Z]*(\s|$)|\s\+\S+)/.test(command)) {
  block('guard-git: bare --force / -f / +refspec is refused; rebase then push with --force-with-lease (CLAUDE.md).');
}

if (!/\bgit\s+commit\b/.test(command)) process.exit(0);

const root = projectRoot(input);

function git(args) {
  try {
    return execSync(`git ${args}`, { cwd: root, encoding: 'utf8' }).split('\n').filter(Boolean);
  } catch {
    return [];
  }
}

// `git commit -a` / `-am` / `--all` also commits modified tracked files not yet staged.
const commitsAll = /\s(-a|--all|-a[a-zA-Z]+|-[a-zA-Z]*a[a-zA-Z]*)(\s|$)/.test(command);
const files = new Set(git('diff --cached --name-only'));
if (commitsAll) git('diff --name-only').forEach((f) => files.add(f));

// Private prefixes come from both the working-tree declaration and the committed
// baseline (HEAD), so a commit that edits or deletes .worktreeinclude cannot disable
// the guard for itself.
function parsePrefixes(text) {
  return text
    .split('\n')
    .map((l) => l.trim())
    .filter((l) => l && !l.startsWith('#'));
}
const privatePrefixes = new Set();
try {
  parsePrefixes(fs.readFileSync(path.join(root, '.worktreeinclude'), 'utf8')).forEach((p) => privatePrefixes.add(p));
} catch {
  // Working-tree declaration missing: the committed one below still applies.
}
try {
  parsePrefixes(execSync('git show HEAD:.worktreeinclude', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] })).forEach((p) => privatePrefixes.add(p));
} catch {
  // No committed declaration yet (first commit).
}
for (const f of files) {
  if ([...privatePrefixes].some((p) => f === p || f.startsWith(p.replace(/\/?$/, '/')))) {
    block(`guard-git: ${f} is declared private in .worktreeinclude and must never be committed.`);
  }
}

const touchesDomain = [...files].some((f) => f.startsWith('src/Domain/'));
const touchesInfra = [...files].some((f) => f.startsWith('src/Infrastructure/'));
if (touchesDomain && touchesInfra) {
  block('guard-git: a commit must not touch src/Domain and src/Infrastructure together (AGENTS.md rule 7). Split the commit.');
}
process.exit(0);
