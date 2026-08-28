// PreToolUse (Bash): a `git commit` must not stage src/Domain and src/Infrastructure
// together (AGENTS.md rule 7), and must never include a private path; a `git push` must
// never use a bare --force (--force-with-lease is the only accepted form, for rebases).
// Git global options (`-C`, `-c`, `--exec-path`, …) are parsed, not pattern-matched.
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { execSync } = require('node:child_process');
const { readInput, projectRoot, block, parseGit } = require('./lib');

const input = readInput();
const command = (input.tool_input && input.tool_input.command) || '';
const git = parseGit(command);
if (!git) process.exit(0);

const root = git.cPath ? path.resolve(projectRoot(input), git.cPath) : projectRoot(input);

// Bare --force, -f (alone or bundled), and force-prefixed refspecs (`+HEAD:branch`).
if (git.subcommand === 'push') {
  const args = ` ${git.args} `;
  if (/\s--force(\s|$)|\s-f(\s|$)|\s-[a-zA-Z]*f[a-zA-Z]*(\s|$)|\s\+\S+/.test(args)) {
    block('guard-git: bare --force / -f / +refspec is refused; rebase then push with --force-with-lease (CLAUDE.md).');
  }
  process.exit(0);
}

if (git.subcommand !== 'commit') process.exit(0);

function gitLines(args) {
  try {
    return execSync(`git ${args}`, { cwd: root, encoding: 'utf8' }).split('\n').filter(Boolean);
  } catch {
    return [];
  }
}

// `git commit -a` / `-am` / `--all` also commits modified tracked files not yet staged.
const commitsAll = /(^|\s)(-a|--all|-[a-zA-Z]*a[a-zA-Z]*)(\s|$)/.test(git.args);
const files = new Set(gitLines('diff --cached --name-only'));
if (commitsAll) gitLines('diff --name-only').forEach((f) => files.add(f));

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
