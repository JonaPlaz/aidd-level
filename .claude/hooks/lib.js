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

module.exports = { readInput, projectRoot, relativePath, block };
