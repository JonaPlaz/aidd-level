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

function parseGit(command) {
  const match = /(?:^|[;&|(]\s*)git\s+(.*)$/s.exec(command);
  if (!match) return null;
  const tokens = match[1].split(/\s+/).filter(Boolean);
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
  const subcommand = tokens[i] || '';
  const args = tokens.slice(i + 1).join(' ');
  return { subcommand, args, cPath: cPath ? cPath.replace(/^["']|["']$/g, '') : null };
}

module.exports = { readInput, projectRoot, relativePath, block, parseGit };
