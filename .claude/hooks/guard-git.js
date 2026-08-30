// PreToolUse (Bash): a `git commit` must not stage src/Domain and src/Infrastructure
// together (AGENTS.md rule 7), and must never include a private path; a `git push` must
// never use a bare --force (--force-with-lease is the only accepted form, for rebases).
// Git global options (`-C`, `-c`, `--exec-path`, …) are parsed, not pattern-matched.
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { execSync } = require('node:child_process');
const { readInput, projectRoot, block, parseGitAll, parseGhPrAll, locksDir } = require('./lib');

const input = readInput();
const command = (input.tool_input && input.tool_input.command) || '';

// Every git invocation of a chained command is checked: `git add -A && git commit`,
// `git fetch origin && git push --force`.
// `gh pr create` only inside a /feature run: the current branch must carry the id of a lock
// this run owns (`<git common dir>/feature-locks/<id>`, see feature-lock.js); `gh pr merge`
// only with --auto or --disable-auto — a synchronous merge is never part of the flow
// (spec 08 § Le cycle est imposé, chantier 16).
checkGh(command, projectRoot(input));

// Checkout guard (spec 08 § 11.5): once more than one /feature-run lock exists, the main
// checkout is read-only for git — an agent `front` must never touch it while another front
// (or the session's own roadmap fetch) is live there; the branch operations of a front stay
// in the worktree of its `dev` agent.
const GUARDED_SUBCOMMANDS = new Set(['checkout', 'switch', 'rebase', 'merge', 'commit', 'push']);

const invocations = parseGitAll(command);
for (const [index, git] of invocations.entries()) {
  const root = git.cPath ? path.resolve(projectRoot(input), git.cPath) : projectRoot(input);

  if (GUARDED_SUBCOMMANDS.has(git.subcommand) && locksCount(root) > 1 && isMainCheckout(root)) {
    block(`guard-git: git ${git.subcommand} on the main checkout is refused while ${locksCount(root)} /feature locks are held (spec 08 § 11.5); operate from the owning worktree instead.`);
  }

  // Bare --force, -f (alone or bundled), force-prefixed refspecs (`+HEAD:branch`), --mirror.
  if (git.subcommand === 'push') {
    const args = ` ${git.args} `;
    // Refspecs may be shell-quoted: '+HEAD:branch' — the shell strips the quotes before git.
    if (/\s--force(\s|$)|\s-f(\s|$)|\s-[a-zA-Z]*f[a-zA-Z]*(\s|$)|\s["']?\+\S+|\s--mirror(\s|$)/.test(args)) {
      block('guard-git: bare --force / -f / +refspec / --mirror is refused; rebase then push with --force-with-lease (CLAUDE.md).');
    }
    continue;
  }

  if (git.subcommand !== 'commit') continue;
  // `git add … && git commit`: the index is still empty when the hook runs, so the files
  // the preceding add would stage are simulated with `git add --dry-run`.
  const priorAdds = invocations.slice(0, index).filter((g) => g.subcommand === 'add');
  checkCommit(git, root, priorAdds);
}
process.exit(0);

function checkCommit(git, root, priorAdds) {
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
  for (const add of priorAdds) {
    gitLines(`add --dry-run ${add.args}`).forEach((line) => {
      const m = /^add '(.+)'$/.exec(line);
      if (m) files.add(m[1]);
    });
  }

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
}


function checkGh(command, root) {
  for (const { subcommand, args } of parseGhPrAll(command)) {
    if (subcommand === 'create' && !branchOwnsALock(root)) {
      block('guard-git: gh pr create outside a /feature run is refused; open the issue and run /feature <n°> (CLAUDE.md § Flow).');
    }
    if (subcommand === 'merge' && !/\s--(auto|disable-auto)(\s|$)/.test(` ${args} `)) {
      block('guard-git: synchronous gh pr merge is refused; arm with --auto (spec 08).');
    }
  }
}

// A lock `<n°>` authorizes branches carrying that number as a token (`feat/40-slug`,
// `docs/spec-40`); a lock `trivial-*` authorizes `trivial/*` branches. A lock `roadmap-*`
// authorizes no branch at all — it only exists for the few seconds of the /roadmap selection
// window (spec 08 § 11.5) and never owns a PR. Any other lock present belongs to another run
// and authorizes nothing here.
function branchOwnsALock(root) {
  let branch = '';
  try {
    branch = execSync('git rev-parse --abbrev-ref HEAD', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch {
    return false;
  }
  let ids = [];
  try {
    ids = fs.readdirSync(locksDir(root));
  } catch {
    return false;
  }
  return ids.some((id) => {
    if (id.startsWith('roadmap-')) return false;
    if (id.startsWith('trivial-')) return branch.startsWith('trivial/');
    return new RegExp(`(^|[-/])${id}([-/]|$)`).test(branch);
  });
}

function locksCount(root) {
  try {
    return fs.readdirSync(locksDir(root)).length;
  } catch {
    return 0;
  }
}

// The main checkout is where `--git-dir` and `--git-common-dir` coincide; any other worktree
// (an agent's `dev` worktree, a throwaway test repo) has its own `--git-dir` under
// `<common>/worktrees/<name>`.
function isMainCheckout(root) {
  try {
    const gitDir = path.resolve(root, execSync('git rev-parse --git-dir', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim());
    const commonDir = path.resolve(root, execSync('git rev-parse --git-common-dir', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim());
    return gitDir === commonDir;
  } catch {
    return false;
  }
}
