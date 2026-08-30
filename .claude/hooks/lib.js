// Shared helpers for the project hooks. Hooks read one JSON document on stdin
// (see https://code.claude.com/docs/en/hooks) and block with exit code 2.
'use strict';

const fs = require('node:fs');
const path = require('node:path');

function readInput() {
  try {
    return JSON.parse(fs.readFileSync(0, 'utf8'));
  } catch {
    return {};
  }
}

// The worktree root: `cwd` follows Claude into worktrees, CLAUDE_PROJECT_DIR does not.
function projectRoot(input) {
  return input.cwd || process.env.CLAUDE_PROJECT_DIR || process.cwd();
}

function relativePath(input, filePath) {
  if (!filePath) return '';
  const abs = path.isAbsolute(filePath) ? filePath : path.join(projectRoot(input), filePath);
  return path.relative(projectRoot(input), abs).split(path.sep).join('/');
}

function block(message) {
  process.stderr.write(`${message}\n`);
  process.exit(2);
}

// Parses the first `git …` invocation of a shell command: skips every global option
// (with or without a separate value) and returns the subcommand, its arguments and the
// `-C <path>` if any. Global options taking a value, per `git --help`.
const GIT_VALUE_OPTIONS = new Set(['-C', '-c', '--git-dir', '--work-tree', '--exec-path', '--namespace', '--super-prefix', '--config-env', '--list-cmds', '--attr-source']);

function parseGitInvocation(rest) {
  const tokens = rest.split(/\s+/).filter(Boolean);
  let cPath = null;
  let i = 0;
  while (i < tokens.length && tokens[i].startsWith('-')) {
    const [name, inlineValue] = tokens[i].split(/=(.*)/s);
    if (GIT_VALUE_OPTIONS.has(name) && inlineValue === undefined) {
      if (name === '-C') cPath = tokens[i + 1];
      i += 2;
    } else {
      if (name === '-C') cPath = inlineValue;
      i += 1;
    }
  }
  return { subcommand: tokens[i] || '', args: tokens.slice(i + 1).join(' '), cPath: cPath ? cPath.replace(/^["']|["']$/g, '') : null };
}

// Every `git …` invocation of a shell command, in order: `git fetch origin && git push --force`
// yields two entries. Segments are split on `&&`, `||`, `;`, `|` and newlines.
function parseGitAll(command) {
  const segments = command.split(/&&|\|\||;|\||\n/);
  const found = [];
  for (const segment of segments) {
    const match = /^\s*(?:\(\s*)?(?:[A-Za-z_][A-Za-z0-9_]*=\S*\s+)*git\s+(.*)$/s.exec(segment);
    if (match) found.push(parseGitInvocation(match[1]));
  }
  return found;
}

// First invocation, kept for callers that only need one.
function parseGit(command) {
  return parseGitAll(command)[0] || null;
}

// `<git common dir>/feature-locks/`: shared by every worktree of the repository, inside
// .git so it is never committed. Falls back to `<root>/.git/feature-locks` outside git.
function locksDir(root) {
  const { execSync } = require('node:child_process');
  try {
    const common = execSync('git rev-parse --path-format=absolute --git-common-dir', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
    return path.join(common, 'feature-locks');
  } catch {
    return path.join(root, '.git', 'feature-locks');
  }
}

// Every `gh pr <subcommand> …` invocation of a shell command, with `gh pr` global flags
// (`-R/--repo <value>`, `--help`…) parsed before the subcommand rather than assumed absent.
function parseGhPrAll(command) {
  const segments = command.split(/&&|\|\||;|\||\n/);
  const found = [];
  for (const segment of segments) {
    const m = /^\s*(?:\(\s*)?(?:[A-Za-z_][A-Za-z0-9_]*=\S*\s+)*gh\s+pr\s+(.*)$/s.exec(segment);
    if (!m) continue;
    const tokens = m[1].split(/\s+/).filter(Boolean);
    let i = 0;
    while (i < tokens.length && tokens[i].startsWith('-')) {
      const [name, inlineValue] = tokens[i].split(/=(.*)/s);
      i += (name === '-R' || name === '--repo') && inlineValue === undefined ? 2 : 1;
    }
    found.push({ subcommand: tokens[i] || '', args: tokens.slice(i + 1).join(' ') });
  }
  return found;
}

module.exports = { readInput, projectRoot, relativePath, block, parseGit, parseGitAll, parseGhPrAll, locksDir };
